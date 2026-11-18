<?php
// ============================================
// LEAGUES - Finalize saved competition once
// ============================================

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/../helpers/tournament_awards.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$league = $_POST['league'] ?? '';
$token = $_POST['csrf_token'] ?? '';

$leagueMatches = [
    'England' => 38,
    'Spain' => 38,
    'Italy' => 38,
    'Germany' => 34,
    'France' => 34,
    'Portugal' => 34,
    'Netherlands' => 34,
    'Turkey' => 34,
    'rest' => 38,
];

if (!is_string($league) || !array_key_exists($league, $leagueMatches)) {
    http_response_code(400);
    exit('Invalid league.');
}

if (
    !is_string($token) || !isset($_SESSION['league_csrf']) ||
    !hash_equals($_SESSION['league_csrf'], $token)
) {
    http_response_code(403);
    exit('Invalid security token.');
}

$competition = 'league_' . strtolower($league);
$matchCount = $leagueMatches[$league];

try {
    $con->begin_transaction();

    // ============================================
    // LEAGUES - Determine season from teams archive
    // ============================================
    $seasonResult = $con->query(
        'SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season FROM teams_archive'
    );
    $season = (int) $seasonResult->fetch_assoc()['current_season'];
    $seasonResult->free();

    // ============================================
    // LEAGUES - Lock tournament to prevent double finalization
    // ============================================
    $statusResult = $con->execute_query(
        'SELECT id, is_played FROM tournaments '
            . 'WHERE season = ? AND competition = ? FOR UPDATE',
        [$season, $competition]
    );
    $tournament = $statusResult->fetch_assoc();
    $statusResult->free();

    if (!$tournament) {
        throw new RuntimeException('Tournament record is missing.');
    }
    if ((int) $tournament['is_played'] === 1) {
        throw new RuntimeException('This league has already been finalized.');
    }

    // ============================================
    // LEAGUES - Read and validate saved JSON
    // ============================================
    $progress = loadTournamentProgress($season, $competition);
    $magic = $progress['magic_number'] ?? null;
    $teamIds = $progress['team_ids'] ?? null;

    if (!is_int($magic) || $magic < 1 || $magic > 1000000) {
        throw new RuntimeException('Save a valid Magic Number first.');
    }
    if (
        !is_array($teamIds) || count($teamIds) === 0 ||
        count(array_filter($teamIds, 'is_int')) !== count($teamIds) ||
        count(array_unique($teamIds)) !== count($teamIds)
    ) {
        throw new RuntimeException('Saved team order is invalid.');
    }

    // ============================================
    // LEAGUES - Lock and validate actual league membership
    // ============================================
    if ($league === 'rest') {
        $nations = [
            'England',
            'Spain',
            'Italy',
            'Germany',
            'France',
            'Portugal',
            'Netherlands',
            'Turkey'
        ];
        $placeholders = implode(',', array_fill(0, count($nations), '?'));
        $teamsResult = $con->execute_query(
            "SELECT id FROM teams WHERE nation NOT IN ($placeholders) "
                . 'OR nation IS NULL ORDER BY id FOR UPDATE',
            $nations
        );
    } else {
        $teamsResult = $con->execute_query(
            'SELECT id FROM teams WHERE nation = ? ORDER BY id FOR UPDATE',
            [$league]
        );
    }

    $actualIds = array_map('intval', array_column(
        $teamsResult->fetch_all(MYSQLI_ASSOC),
        'id'
    ));
    $teamsResult->free();

    $savedSorted = $teamIds;
    sort($savedSorted, SORT_NUMERIC);
    sort($actualIds, SORT_NUMERIC);
    if ($savedSorted !== $actualIds) {
        throw new RuntimeException(
            'Saved teams no longer match the league. Save the league again.'
        );
    }

    // ============================================
    // LEAGUES - Validate individual player awards
    // ============================================

    $playerAwards = validateTournamentPlayerAwards(
        $con,
        $actualIds
    );

    // ============================================
    // LEAGUES - Apply points and matches only once
    // ============================================
    foreach ($teamIds as $index => $teamId) {
        $position = $index + 1;
        $points = max(0, (11 - $position) * $magic);
        $con->execute_query(
            'UPDATE teams SET points = points + ?, matches = matches + ? WHERE id = ?',
            [$points, $matchCount, $teamId]
        );
    }

    // ============================================
    // LEAGUES - Record winner and completion
    // ============================================
    $winnerId = $teamIds[0];

    $con->execute_query(
        "UPDATE tournaments
     SET winner_team_id = ?,
         best_player_id = ?,
         top_scorer_id = ?,
         top_assister_id = ?,
         is_played = 1
     WHERE id = ?
       AND is_played = 0",
        [
            $winnerId,
            $playerAwards['best_player_id'],
            $playerAwards['top_scorer_id'],
            $playerAwards['top_assister_id'],
            (int)$tournament['id']
        ]
    );

    if ($con->affected_rows !== 1) {
        throw new RuntimeException('Could not finalize tournament.');
    }

    $con->commit();
    header('Location: leagues.php?league=' . rawurlencode($league) . '&finalized=1');
    exit;
} catch (Throwable $error) {
    $con->rollback();
    http_response_code(400);
    // Development environment. Do not display raw exceptions on a public server.
    exit('Tournament finalization failed: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8'));
}

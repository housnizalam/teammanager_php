
<?php

// ============================================
// CHAMPIONS - Save pots endpoint
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';

try {
    // ============================================
    // CHAMPIONS - Validate request
    // ============================================

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !isset($_SESSION['champions_csrf']) ||
        !hash_equals($_SESSION['champions_csrf'], $token)
    ) {
        throw new RuntimeException('Invalid CSRF token.');
    }

    $rawPots = $_POST['pots'] ?? '';

    if (!is_string($rawPots) || strlen($rawPots) > 10000) {
        throw new RuntimeException('Invalid save data.');
    }

    // ============================================
// CHAMPIONS - Validate submitted JSON
// ============================================

if (trim($rawPots) === '') {
    throw new RuntimeException(
        'No pots data received. Check JavaScript save handler.'
    );
}

    $pots = json_decode(
        $rawPots,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // ============================================
    // CHAMPIONS - Validate pots and team IDs
    // ============================================

    if (!is_array($pots) || count($pots) !== 4) {
        throw new RuntimeException('Four pots are required.');
    }

    $allTeamIds = [];

    for ($pot = 1; $pot <= 4; $pot++) {
        $key = (string) $pot;

        if (
            !array_key_exists($key, $pots) ||
            !is_array($pots[$key]) ||
            !array_is_list($pots[$key]) ||
            count($pots[$key]) > 8
        ) {
            throw new RuntimeException(
                "Invalid teams in Pot $pot."
            );
        }

        foreach ($pots[$key] as $teamId) {
            if (!is_int($teamId) || $teamId < 1) {
                throw new RuntimeException('Invalid team ID.');
            }

            $allTeamIds[] = $teamId;
        }
    }

    if (count($allTeamIds) !== count(array_unique($allTeamIds))) {
        throw new RuntimeException(
            'A team cannot appear in multiple pots.'
        );
    }

    // Verify that every selected team exists.
    if ($allTeamIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(0, count($allTeamIds), '?')
        );

        $result = $con->execute_query(
            "SELECT COUNT(*) AS total
             FROM teams
             WHERE id IN ($placeholders)",
            $allTeamIds
        );

        $total = (int) $result->fetch_assoc()['total'];
        $result->free();

        if ($total !== count($allTeamIds)) {
            throw new RuntimeException(
                'One or more selected teams do not exist.'
            );
        }
    }

    // ============================================
    // CHAMPIONS - Current season
    // ============================================

    $seasonResult = $con->query(
        "SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season
         FROM teams_archive"
    );

    $currentSeason = (int) $seasonResult
        ->fetch_assoc()['current_season'];

    $seasonResult->free();

    // ============================================
    // CHAMPIONS - Check tournament status
    // ============================================

    $result = $con->execute_query(
        "SELECT is_played
         FROM tournaments
         WHERE season = ? AND competition = 'champions'",
        [$currentSeason]
    );

    $tournament = $result->fetch_assoc();
    $result->free();

    if ($tournament === null) {
        throw new RuntimeException(
            'Champions League tournament record not found.'
        );
    }

    if ((int) $tournament['is_played'] === 1) {
        throw new RuntimeException(
            'This tournament has already been finalized.'
        );
    }

    $existingProgress = loadTournamentProgress(
        $currentSeason,
        'champions'
    );

    if (
        ($existingProgress['stage'] ?? 'pot_selection')
        !== 'pot_selection'
    ) {
        throw new RuntimeException(
            'Pot selection has already been completed.'
        );
    }

    // ============================================
    // CHAMPIONS - Save partial progress
    // ============================================

    saveTournamentProgress(
        $currentSeason,
        'champions',
        [
            'stage' => 'pot_selection',
            'pots' => $pots
        ]
    );

    header('Location: champions.php?saved=1');
    exit;

} catch (Throwable $e) {
    http_response_code(400);

    echo '<h2>Cannot save Champions League pots</h2>';
    echo '<p>' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
    echo '<a href="champions.php">Back to Champions League</a>';
}

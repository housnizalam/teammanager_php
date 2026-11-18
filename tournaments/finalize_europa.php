<?php
// ============================================
// EUROPA - Finalize tournament exactly once
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/partials/europa_validation.php';
require_once __DIR__ . '/../helpers/tournament_awards.php';

$transactionStarted = false;
try {
    // ============================================
    // EUROPA - Request and CSRF validation
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }
    $token = $_POST['csrf_token'] ?? null;
    if (
        !is_string($token) ||
        !isset($_SESSION['europa_csrf']) ||
        !hash_equals($_SESSION['europa_csrf'], $token)
    ) {
        throw new RuntimeException('Invalid CSRF token.');
    }

    // ============================================
    // EUROPA - Lock tournament record
    // ============================================
    $result = $con->query(
        'SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season FROM teams_archive'
    );
    $season = (int) $result->fetch_assoc()['current_season'];
    $result->free();

    $con->begin_transaction();
    $transactionStarted = true;

    $result = $con->execute_query(
        "SELECT is_played FROM tournaments
         WHERE season = ? AND competition = 'europa' FOR UPDATE",
        [$season]
    );
    $tournament = $result->fetch_assoc();
    $result->free();
    if ($tournament === null) {
        throw new RuntimeException('Europa League tournament record not found.');
    }
    if ((int) $tournament['is_played'] === 1) {
        throw new RuntimeException('Europa League already finalized.');
    }

    // ============================================
    // EUROPA - Validate every saved result
    // ============================================
    $progress = loadTournamentProgress($season, 'europa');
    $resultData = europaValidateProgress($progress, $con, true);
    $r16 = $resultData['round_of_16'];
    $qf = $resultData['quarter_finals'];
    $sf = $resultData['semi_finals'];
    $finalists = $resultData['finalists'];
    $champion = $resultData['winner'];

    // ============================================
    // EUROPA - Validate individual player awards
    // ============================================

    $playerAwards = validateTournamentPlayerAwards(
        $con,
        array_values($r16)
    );

    // ============================================
    // EUROPA - Awards for each of the 16 teams
    // ============================================
    $awards = [];
    foreach ($r16 as $teamId) {
        $awards[$teamId] = ['points' => 0, 'matches' => 2];
    }
    foreach ($qf as $teamId) {
        $awards[$teamId] = ['points' => 15, 'matches' => 4];
    }
    foreach ($sf as $teamId) {
        $awards[$teamId] = ['points' => 20, 'matches' => 6];
    }
    foreach ($finalists as $teamId) {
        $awards[$teamId] = ['points' => 25, 'matches' => 7];
    }
    $awards[$champion] = ['points' => 30, 'matches' => 7];

    // ============================================
    // EUROPA - Apply awards to current season
    // ============================================
    foreach ($awards as $teamId => $award) {
        $con->execute_query(
            'UPDATE teams SET points = points + ?, matches = matches + ? WHERE id = ?',
            [$award['points'], $award['matches'], $teamId]
        );
        if ($con->affected_rows !== 1) {
            throw new RuntimeException("Failed to update team {$teamId}.");
        }
    }

    // ============================================
    // EUROPA - Mark tournament completed
    // ============================================

    $con->execute_query(
        "UPDATE tournaments
     SET is_played = 1,
         winner_team_id = ?,
         best_player_id = ?,
         top_scorer_id = ?,
         top_assister_id = ?
     WHERE season = ?
       AND competition = 'europa'
       AND is_played = 0",
        [
            $champion,
            $playerAwards['best_player_id'],
            $playerAwards['top_scorer_id'],
            $playerAwards['top_assister_id'],
            $season
        ]
    );

    if ($con->affected_rows !== 1) {
        throw new RuntimeException('Could not mark Europa League as completed.');
    }

    $con->commit();
    $transactionStarted = false;
    header('Location: europa.php?finished=1');
    exit;
} catch (Throwable $e) {
    if ($transactionStarted) {
        $con->rollback();
    }
    http_response_code(400);
    echo '<h2>Cannot finalize Europa League</h2><p>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</p><a href="europa.php">Back to Europa League</a>';
}

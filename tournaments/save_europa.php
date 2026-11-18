<?php
// ============================================
// EUROPA - Save partial knockout progress
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/partials/europa_validation.php';

$transactionStarted = false;
try {
    // ============================================
    // EUROPA - Request and CSRF validation
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) ||
        !isset($_SESSION['europa_csrf']) ||
        !hash_equals($_SESSION['europa_csrf'], $token)) {
        throw new RuntimeException('Invalid CSRF token.');
    }
    $raw = $_POST['progress'] ?? null;
    if (!is_string($raw) || trim($raw) === '' || strlen($raw) > 12000) {
        throw new RuntimeException('Invalid Europa League save payload.');
    }
    $submitted = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($submitted)) {
        throw new RuntimeException('Invalid progress JSON.');
    }
    $submitted['stage'] = 'knockout';

    // ============================================
    // EUROPA - Current season and row lock
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
    if ($tournament === null || (int) $tournament['is_played'] === 1) {
        throw new RuntimeException('Europa League is unavailable or already completed.');
    }

    // ============================================
    // EUROPA - Verify progress and previous-round locks
    // ============================================
    $validated = europaValidateProgress($submitted, $con);
    $previous = loadTournamentProgress($season, 'europa');
    if ($previous !== []) {
        if (($previous['stage'] ?? '') !== 'knockout') {
            throw new RuntimeException('Unsupported existing tournament stage.');
        }
        $locks = [
            ['quarter_finals', 'round_of_16'],
            ['semi_finals', 'quarter_finals'],
            ['finalists', 'semi_finals'],
            ['winner', 'finalists'],
        ];
        foreach ($locks as [$later, $earlier]) {
            $wasStarted = $later === 'winner'
                ? (($previous[$later] ?? null) !== null)
                : (($previous[$later] ?? []) !== []);
            if ($wasStarted && ($previous[$earlier] ?? []) != $validated[$earlier]) {
                throw new RuntimeException("{$earlier} is locked after {$later} has been saved.");
            }
        }
    }

    saveTournamentProgress($season, 'europa', $validated);
    $con->commit();
    $transactionStarted = false;
    header('Location: europa.php?saved=1');
    exit;
} catch (Throwable $e) {
    if ($transactionStarted) {
        $con->rollback();
    }
    http_response_code(400);
    echo '<h2>Cannot save Europa League</h2><p>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</p><a href="europa.php">Back to Europa League</a>';
}

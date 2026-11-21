
<?php

// ============================================
// RATING FIX - Initialization
// ============================================

session_start();

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/rating_fix.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// ============================================
// RATING FIX - CSRF protection
// ============================================

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['rating_fix_csrf'] ?? '';

if (
    !is_string($submittedToken)
    || $expectedToken === ''
    || !hash_equals($expectedToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token.');
}

// ============================================
// RATING FIX - Apply once per season
// ============================================

try {
    $con->begin_transaction();

    // Derive the current season from the archive.
    $seasonResult = $con->query("
        SELECT COALESCE(MAX(seasons), 0) + 1
            AS current_season
        FROM teams_archive
    ");

    $season = (int)$seasonResult
        ->fetch_assoc()['current_season'];

    $seasonResult->free();

    // Lock relevant tournament records.
    if (!ratingFixAllCompleted($con, $season, true)) {
        throw new RuntimeException(
            'All tournaments must be completed first.'
        );
    }

    // Create the rating_fix record if it does not exist.
    // The unique (season, competition) key prevents duplicates.
    $con->execute_query(
        "INSERT INTO tournaments
            (season, competition, is_played)
         VALUES (?, 'rating_fix', 0)
         ON DUPLICATE KEY UPDATE id = id",
        [$season]
    );

    // Lock the rating_fix record before checking it.
    if (ratingFixAlreadyApplied($con, $season, true)) {
        throw new RuntimeException(
            'Fix Rating was already applied this season.'
        );
    }


    // ============================================
    // RATING FIX - Team Bonus and Matches Penalty
    // ============================================

    $con->query("
    UPDATE players AS p
    JOIN teams AS t ON p.team_id = t.id
    SET p.rating = GREATEST(
        0,
        p.rating
        + (t.points / 120)
        - (GREATEST(0, 40 - p.matches) * 0.1)
    )
    WHERE p.rating > 0
");

    // Mark this season as processed.
    $con->execute_query(
        "UPDATE tournaments
         SET is_played = 1
         WHERE season = ?
           AND competition = 'rating_fix'",
        [$season]
    );

    $con->commit();

    $_SESSION['rating_fix_message'] =
        'Player ratings updated successfully.';
} catch (Throwable $exception) {
    $con->rollback();

    error_log(
        'Fix Rating failed: ' . $exception->getMessage()
    );

    $_SESSION['rating_fix_error'] =
        'Fix Rating could not be completed. '
        . $exception->getMessage();
}

$con->close();

// ============================================
// RATING FIX - Redirect to Players
// ============================================

header('Location: index.php');
exit;

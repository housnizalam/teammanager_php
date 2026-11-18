
<?php

// ============================================
// CHAMPIONS - Generate group stage
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

    // ============================================
    // CHAMPIONS - Resolve current season
    // ============================================

    $result = $con->query(
        "SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season
         FROM teams_archive"
    );

    $season = (int) $result->fetch_assoc()['current_season'];
    $result->free();

    // ============================================
    // CHAMPIONS - Check tournament status
    // ============================================

    $result = $con->execute_query(
        "SELECT is_played
         FROM tournaments
         WHERE season = ?
           AND competition = 'champions'",
        [$season]
    );

    $tournament = $result->fetch_assoc();
    $result->free();

    if (
        $tournament === null ||
        (int) $tournament['is_played'] === 1
    ) {
        throw new RuntimeException(
            'Tournament is unavailable or already completed.'
        );
    }

    // ============================================
    // CHAMPIONS - Validate saved pots
    // ============================================

    $progress = loadTournamentProgress($season, 'champions');

    if (($progress['stage'] ?? '') !== 'pot_selection') {
        throw new RuntimeException(
            'Group generation is not available at this stage.'
        );
    }

    $pots = $progress['pots'] ?? null;

    if (!is_array($pots) || count($pots) !== 4) {
        throw new RuntimeException('Invalid saved pots.');
    }

    $allIds = [];

    for ($pot = 1; $pot <= 4; $pot++) {
        $key = (string) $pot;
        $ids = $pots[$key] ?? null;

        if (
            !is_array($ids) ||
            !array_is_list($ids) ||
            count($ids) !== 8
        ) {
            throw new RuntimeException(
                "Pot {$pot} must contain exactly 8 teams."
            );
        }

        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new RuntimeException('Invalid team ID.');
            }

            $allIds[] = $id;
        }
    }

    if (count(array_unique($allIds)) !== 32) {
        throw new RuntimeException(
            'The 32 selected teams must be unique.'
        );
    }

    // Verify that selected teams still exist.
    $placeholders = implode(',', array_fill(0, 32, '?'));

    $result = $con->execute_query(
        "SELECT COUNT(*) AS total
         FROM teams
         WHERE id IN ($placeholders)",
        $allIds
    );

    $existingCount = (int) $result->fetch_assoc()['total'];
    $result->free();

    if ($existingCount !== 32) {
        throw new RuntimeException(
            'One or more selected teams no longer exist.'
        );
    }

    // ============================================
    // CHAMPIONS - Shuffle each pot securely
    // ============================================

    function shuffleChampionsPot(array $ids): array
    {
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);

            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return $ids;
    }

    $shuffledPots = [];

    for ($pot = 1; $pot <= 4; $pot++) {
        $shuffledPots[$pot] = shuffleChampionsPot(
            $pots[(string) $pot]
        );
    }

    // ============================================
    // CHAMPIONS - Build 8 groups
    // ============================================

    $groups = [];
    $groupNames = range('A', 'H');

    foreach ($groupNames as $index => $groupName) {
        $groups[$groupName] = [
            $shuffledPots[1][$index],
            $shuffledPots[2][$index],
            $shuffledPots[3][$index],
            $shuffledPots[4][$index]
        ];
    }

    // ============================================
    // CHAMPIONS - Persist new stage
    // ============================================

    $progress['stage'] = 'group_stage';
    $progress['groups'] = $groups;

    // Preserve the original pots in the same JSON.
    saveTournamentProgress($season, 'champions', $progress);

    header('Location: champions.php?groups=generated');
    exit;

} catch (Throwable $e) {
    http_response_code(400);

    echo '<h2>Cannot generate groups</h2>';
    echo '<p>' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
    echo '<a href="champions.php">Back to Champions League</a>';
}

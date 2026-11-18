
<?php

// ============================================
// CHAMPIONS - Finalize Tournament
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/../helpers/tournament_awards.php';

// ============================================
// CHAMPIONS - Validate complete slot map
// ============================================

function championsValidateSlots(
    mixed $value,
    array $expectedKeys
): array {
    if (!is_array($value) || count($value) !== count($expectedKeys)) {
        throw new RuntimeException('Incomplete knockout stage.');
    }

    $actualKeys = array_keys($value);
    sort($actualKeys);
    sort($expectedKeys);

    if ($actualKeys !== $expectedKeys) {
        throw new RuntimeException('Invalid knockout slot structure.');
    }

    foreach ($value as $teamId) {
        if (!is_int($teamId) || $teamId <= 0) {
            throw new RuntimeException('Invalid team ID.');
        }
    }

    if (count(array_unique(array_values($value))) !== count($value)) {
        throw new RuntimeException('Duplicate team in knockout stage.');
    }

    return $value;
}

// ============================================
// CHAMPIONS - Verify winner against match
// ============================================

function championsValidateMatchups(
    array $source,
    array $destination,
    array $matchups
): void {
    foreach ($matchups as $target => [$first, $second]) {
        if (
            $destination[$target] !== $source[$first] &&
            $destination[$target] !== $source[$second]
        ) {
            throw new RuntimeException(
                "Invalid qualifier in {$target}."
            );
        }
    }
}

$transactionStarted = false;

try {
    // ============================================
    // CHAMPIONS - Request and CSRF validation
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
    // CHAMPIONS - Lock tournament record
    // ============================================

    $con->begin_transaction();
    $transactionStarted = true;

    $result = $con->execute_query(
        "SELECT is_played
         FROM tournaments
         WHERE season = ?
           AND competition = 'champions'
         FOR UPDATE",
        [$season]
    );

    $tournament = $result->fetch_assoc();
    $result->free();

    if ($tournament === null) {
        throw new RuntimeException('Tournament record not found.');
    }

    if ((int) $tournament['is_played'] === 1) {
        throw new RuntimeException(
            'Champions League has already been finalized.'
        );
    }

    // ============================================
    // CHAMPIONS - Load saved tournament
    // ============================================

    $progress = loadTournamentProgress($season, 'champions');

    if (($progress['stage'] ?? '') !== 'group_stage') {
        throw new RuntimeException('Invalid tournament stage.');
    }

    // ============================================
    // CHAMPIONS - Validate 4 original pots
    // ============================================

    $pots = $progress['pots'] ?? null;

    if (!is_array($pots) || count($pots) !== 4) {
        throw new RuntimeException('Invalid original pots.');
    }

    $potOfTeam = [];

    for ($pot = 1; $pot <= 4; $pot++) {
        $ids = $pots[(string) $pot] ?? null;

        if (
            !is_array($ids) ||
            !array_is_list($ids) ||
            count($ids) !== 8
        ) {
            throw new RuntimeException("Invalid Pot {$pot}.");
        }

        foreach ($ids as $id) {
            if (
                !is_int($id) ||
                $id <= 0 ||
                isset($potOfTeam[$id])
            ) {
                throw new RuntimeException(
                    'Invalid or duplicated pot team.'
                );
            }

            $potOfTeam[$id] = $pot;
        }
    }

    // ============================================
    // CHAMPIONS - Validate 8 groups
    // ============================================

    $groups = $progress['groups'] ?? null;

    if (!is_array($groups) || count($groups) !== 8) {
        throw new RuntimeException('Invalid groups.');
    }

    $groupOfTeam = [];

    foreach (range('A', 'H') as $groupName) {
        $ids = $groups[$groupName] ?? null;

        if (
            !is_array($ids) ||
            !array_is_list($ids) ||
            count($ids) !== 4
        ) {
            throw new RuntimeException(
                "Invalid Group {$groupName}."
            );
        }

        $usedPots = [];

        foreach ($ids as $id) {
            if (
                !is_int($id) ||
                !isset($potOfTeam[$id]) ||
                isset($groupOfTeam[$id])
            ) {
                throw new RuntimeException(
                    "Invalid team in Group {$groupName}."
                );
            }

            $pot = $potOfTeam[$id];

            if (isset($usedPots[$pot])) {
                throw new RuntimeException(
                    "Duplicate pot in Group {$groupName}."
                );
            }

            $usedPots[$pot] = true;
            $groupOfTeam[$id] = $groupName;
        }
    }

    if (count($groupOfTeam) !== 32) {
        throw new RuntimeException('Expected exactly 32 teams.');
    }

    // ============================================
    // CHAMPIONS - Validate knockout participants
    // ============================================

    $r16Keys = array_merge(
        array_map(fn($n) => "L{$n}", range(1, 8)),
        array_map(fn($n) => "R{$n}", range(1, 8))
    );

    $qfKeys = array_map(fn($n) => "Q{$n}", range(1, 8));
    $sfKeys = array_map(fn($n) => "S{$n}", range(1, 4));

    $r16 = championsValidateSlots(
        $progress['round_of_16'] ?? null,
        $r16Keys
    );

    $qf = championsValidateSlots(
        $progress['quarter_finals'] ?? null,
        $qfKeys
    );

    $sf = championsValidateSlots(
        $progress['semi_finals'] ?? null,
        $sfKeys
    );

    $finalists = championsValidateSlots(
        $progress['finalists'] ?? null,
        ['F1', 'F2']
    );

    // Exactly two qualified teams per group.
    $groupCounts = array_fill_keys(range('A', 'H'), 0);

    foreach ($r16 as $teamId) {
        if (!isset($groupOfTeam[$teamId])) {
            throw new RuntimeException(
                'R16 contains a team outside the groups.'
            );
        }

        $groupCounts[$groupOfTeam[$teamId]]++;
    }

    foreach ($groupCounts as $count) {
        if ($count !== 2) {
            throw new RuntimeException(
                'Each group must supply exactly two qualifiers.'
            );
        }
    }

    // ============================================
    // CHAMPIONS - Validate progression paths
    // ============================================

    championsValidateMatchups($r16, $qf, [
        'Q1' => ['L1', 'L2'],
        'Q2' => ['L3', 'L4'],
        'Q3' => ['L5', 'L6'],
        'Q4' => ['L7', 'L8'],
        'Q5' => ['R1', 'R2'],
        'Q6' => ['R3', 'R4'],
        'Q7' => ['R5', 'R6'],
        'Q8' => ['R7', 'R8']
    ]);

    championsValidateMatchups($qf, $sf, [
        'S1' => ['Q1', 'Q2'],
        'S2' => ['Q3', 'Q4'],
        'S3' => ['Q5', 'Q6'],
        'S4' => ['Q7', 'Q8']
    ]);

    championsValidateMatchups($sf, $finalists, [
        'F1' => ['S1', 'S2'],
        'F2' => ['S3', 'S4']
    ]);

    // ============================================
    // CHAMPIONS - Validate actual champion
    // ============================================

    $championId = $progress['winner'] ?? null;

    if (
        !is_int($championId) ||
        !in_array($championId, array_values($finalists), true)
    ) {
        throw new RuntimeException(
            'A valid champion must be selected and saved.'
        );
    }

    // ============================================
    // CHAMPIONS - Validate individual player awards
    // ============================================

    $playerAwards = validateTournamentPlayerAwards(
        $con,
        array_keys($groupOfTeam)
    );

    // ============================================
    // CHAMPIONS - Build points and matches
    // ============================================

    $awards = [];

    // Eliminated in groups.
    foreach ($groupOfTeam as $teamId => $group) {
        $awards[$teamId] = ['points' => 0, 'matches' => 6];
    }

    // Reached Round of 16.
    foreach ($r16 as $teamId) {
        $awards[$teamId] = ['points' => 20, 'matches' => 8];
    }

    // Reached Quarter-finals.
    foreach ($qf as $teamId) {
        $awards[$teamId] = ['points' => 40, 'matches' => 10];
    }

    // Reached Semi-finals.
    foreach ($sf as $teamId) {
        $awards[$teamId] = ['points' => 60, 'matches' => 12];
    }

    // Reached Final.
    foreach ($finalists as $teamId) {
        $awards[$teamId] = ['points' => 80, 'matches' => 13];
    }

    // Champion.
    $awards[$championId] = ['points' => 100, 'matches' => 13];

    // ============================================
    // CHAMPIONS - Update all 32 teams
    // ============================================

    foreach ($awards as $teamId => $award) {
        $con->execute_query(
            "UPDATE teams
             SET points = points + ?,
                 matches = matches + ?
             WHERE id = ?",
            [
                $award['points'],
                $award['matches'],
                $teamId
            ]
        );

        if ($con->affected_rows !== 1) {
            throw new RuntimeException(
                "Could not update team {$teamId}."
            );
        }
    }

    // ============================================
    // CHAMPIONS - Mark tournament as played
    // ============================================


    $con->execute_query(
        "UPDATE tournaments
     SET is_played = 1,
         winner_team_id = ?,
         best_player_id = ?,
         top_scorer_id = ?,
         top_assister_id = ?
     WHERE season = ?
       AND competition = 'champions'
       AND is_played = 0",
        [
            $championId,
            $playerAwards['best_player_id'],
            $playerAwards['top_scorer_id'],
            $playerAwards['top_assister_id'],
            $season
        ]
    );


    if ($con->affected_rows !== 1) {
        throw new RuntimeException(
            'Tournament could not be finalized.'
        );
    }

    // ============================================
    // CHAMPIONS - Commit all changes together
    // ============================================

    $con->commit();
    $transactionStarted = false;

    header('Location: champions.php?finished=1');
    exit;
} catch (Throwable $e) {
    if ($transactionStarted) {
        $con->rollback();
    }

    http_response_code(400);

    echo '<h2>Cannot finalize Champions League</h2>';
    echo '<p>' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
    echo '<a href="champions.php">Back to Champions League</a>';
}

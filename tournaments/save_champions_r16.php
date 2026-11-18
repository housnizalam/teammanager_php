
<?php

// ============================================
// CHAMPIONS - Save R16 selections
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

    $rawSlots = $_POST['slots'] ?? '';

    if (
        !is_string($rawSlots) ||
        trim($rawSlots) === '' ||
        strlen($rawSlots) > 10000
    ) {
        throw new RuntimeException('Invalid selection data.');
    }

    $slots = json_decode(
        $rawSlots,
        true,
        512,
        JSON_THROW_ON_ERROR
    );


    // ============================================
    // CHAMPIONS - Read quarter-final qualifiers
    // ============================================

    $rawQuarterFinals = $_POST['quarter_finals'] ?? '';

    if (
        !is_string($rawQuarterFinals) ||
        trim($rawQuarterFinals) === '' ||
        strlen($rawQuarterFinals) > 10000
    ) {
        throw new RuntimeException('Invalid quarter-final data.');
    }

    $quarterFinals = json_decode(
        $rawQuarterFinals,
        true,
        512,
        JSON_THROW_ON_ERROR
    );


    // ============================================
    // CHAMPIONS - Read semi-final qualifiers
    // ============================================

    $rawSemiFinals = $_POST['semi_finals'] ?? '';

    if (
        !is_string($rawSemiFinals) ||
        trim($rawSemiFinals) === '' ||
        strlen($rawSemiFinals) > 10000
    ) {
        throw new RuntimeException('Invalid semi-final data.');
    }

    $semiFinals = json_decode(
        $rawSemiFinals,
        true,
        512,
        JSON_THROW_ON_ERROR
    );


    // ============================================
    // CHAMPIONS - Read finalists and winner
    // ============================================

    $rawFinalists = $_POST['finalists'] ?? '';
    $rawWinner = $_POST['winner'] ?? '';

    if (
        !is_string($rawFinalists) ||
        trim($rawFinalists) === '' ||
        strlen($rawFinalists) > 10000 ||
        !is_string($rawWinner) ||
        trim($rawWinner) === '' ||
        strlen($rawWinner) > 100
    ) {
        throw new RuntimeException('Invalid final stage data.');
    }

    $finalists = json_decode(
        $rawFinalists,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $winnerId = json_decode(
        $rawWinner,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (
        !is_array($finalists) ||
        (count($finalists) > 2) ||
        ($winnerId !== null &&
            (!is_int($winnerId) || $winnerId <= 0))
    ) {
        throw new RuntimeException('Invalid finalists or winner.');
    }


    if (!is_array($semiFinals)) {
        throw new RuntimeException('Invalid semi-final format.');
    }


    if (!is_array($quarterFinals)) {
        throw new RuntimeException('Invalid quarter-final format.');
    }


    if (!is_array($slots)) {
        throw new RuntimeException('Invalid slots format.');
    }

    // ============================================
    // CHAMPIONS - Validate slot identifiers
    // ============================================

    $validSlots = array_merge(
        array_map(fn($n) => 'L' . $n, range(1, 8)),
        array_map(fn($n) => 'R' . $n, range(1, 8))
    );

    $selectedIds = [];

    foreach ($slots as $slot => $teamId) {
        if (!in_array((string) $slot, $validSlots, true)) {
            throw new RuntimeException('Unknown bracket slot.');
        }

        if (!is_int($teamId) || $teamId <= 0) {
            throw new RuntimeException('Invalid team ID.');
        }

        $selectedIds[] = $teamId;
    }

    if (count($selectedIds) !== count(array_unique($selectedIds))) {
        throw new RuntimeException(
            'The same team cannot occupy two slots.'
        );
    }

    // ============================================
    // CHAMPIONS - Load tournament progress
    // ============================================

    $result = $con->query(
        "SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season
         FROM teams_archive"
    );

    $season = (int) $result->fetch_assoc()['current_season'];
    $result->free();

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
            'Tournament is unavailable or completed.'
        );
    }

    $progress = loadTournamentProgress($season, 'champions');

    if (($progress['stage'] ?? '') !== 'group_stage') {
        throw new RuntimeException(
            'Round of 16 selection is not available.'
        );
    }

    // ============================================
    // CHAMPIONS - Validate groups and quotas
    // ============================================

    $groups = $progress['groups'] ?? null;

    if (!is_array($groups) || count($groups) !== 8) {
        throw new RuntimeException('Invalid group stage data.');
    }

    $teamToGroup = [];

    foreach (range('A', 'H') as $groupName) {
        $groupIds = $groups[$groupName] ?? null;

        if (
            !is_array($groupIds) ||
            !array_is_list($groupIds) ||
            count($groupIds) !== 4
        ) {
            throw new RuntimeException(
                "Invalid teams in Group {$groupName}."
            );
        }

        foreach ($groupIds as $teamId) {
            if (
                !is_int($teamId) ||
                $teamId <= 0 ||
                isset($teamToGroup[$teamId])
            ) {
                throw new RuntimeException(
                    'Duplicate or invalid group team.'
                );
            }

            $teamToGroup[$teamId] = $groupName;
        }
    }

    $groupCounts = array_fill_keys(range('A', 'H'), 0);

    foreach ($selectedIds as $teamId) {
        if (!isset($teamToGroup[$teamId])) {
            throw new RuntimeException(
                'Selected team does not belong to the groups.'
            );
        }

        $group = $teamToGroup[$teamId];
        $groupCounts[$group]++;

        if ($groupCounts[$group] > 2) {
            throw new RuntimeException(
                "Group {$group} cannot supply more than two teams."
            );
        }
    }


    // ============================================
    // CHAMPIONS - Validate quarter-final matchups
    // ============================================

    $matchups = [
        'Q1' => ['L1', 'L2'],
        'Q2' => ['L3', 'L4'],
        'Q3' => ['L5', 'L6'],
        'Q4' => ['L7', 'L8'],
        'Q5' => ['R1', 'R2'],
        'Q6' => ['R3', 'R4'],
        'Q7' => ['R5', 'R6'],
        'Q8' => ['R7', 'R8']
    ];

    // No winners until all 16 participants are selected.
    if ($quarterFinals !== []) {
        if (count($slots) !== 16) {
            throw new RuntimeException(
                'All 16 teams must be selected before choosing winners.'
            );
        }

        foreach ($groupCounts as $groupName => $count) {
            if ($count !== 2) {
                throw new RuntimeException(
                    "Group {$groupName} must supply exactly two teams."
                );
            }
        }
    }


    foreach ($quarterFinals as $qfSlot => $qualifierId) {
        if (!array_key_exists($qfSlot, $matchups)) {
            throw new RuntimeException(
                'Unknown quarter-final slot.'
            );
        }

        if (!is_int($qualifierId) || $qualifierId <= 0) {
            throw new RuntimeException('Invalid qualifier ID.');
        }

        [$firstSlot, $secondSlot] = $matchups[$qfSlot];

        $firstTeam = $slots[$firstSlot] ?? null;
        $secondTeam = $slots[$secondSlot] ?? null;

        if (
            $qualifierId !== $firstTeam &&
            $qualifierId !== $secondTeam
        ) {
            throw new RuntimeException(
                "Qualifier for {$qfSlot} does not belong to its R16 match."
            );
        }
    }



    // ============================================
    // CHAMPIONS - Validate semi-final matchups
    // ============================================

    $semiFinalMatchups = [
        'S1' => ['Q1', 'Q2'],
        'S2' => ['Q3', 'Q4'],
        'S3' => ['Q5', 'Q6'],
        'S4' => ['Q7', 'Q8']
    ];

    // Quarter-finals must be complete before
    // any semi-final qualifier can be selected.
    if ($semiFinals !== [] && count($quarterFinals) !== 8) {
        throw new RuntimeException(
            'All eight quarter-final slots must be completed first.'
        );
    }


foreach ($semiFinals as $sfSlot => $qualifierId) {
    if (!array_key_exists($sfSlot, $semiFinalMatchups)) {
        throw new RuntimeException('Unknown semi-final slot.');
    }

    if (!is_int($qualifierId) || $qualifierId <= 0) {
        throw new RuntimeException('Invalid semi-final qualifier.');
    }

    [$firstSlot, $secondSlot] = $semiFinalMatchups[$sfSlot];

    if (
        $qualifierId !== ($quarterFinals[$firstSlot] ?? null) &&
        $qualifierId !== ($quarterFinals[$secondSlot] ?? null)
    ) {
        throw new RuntimeException(
            "Qualifier for {$sfSlot} does not belong to its match."
        );
    }
}


    // Lock the QF participants once any SF qualifier
    // has already been saved.
    $previousSemiFinals = $progress['semi_finals'] ?? [];

    if (
        $previousSemiFinals !== [] &&
        ($progress['quarter_finals'] ?? []) != $quarterFinals
    ) {
        throw new RuntimeException(
            'Quarter-final results are locked after saving semi-final qualifiers.'
        );
    }


    // Prevent changing the original R16 draw after
    // saved quarter-final results already exist.
    $previousWinners = $progress['quarter_finals'] ?? [];

    if (
        $previousWinners !== [] &&
        ($progress['round_of_16'] ?? []) != $slots
    ) {
        throw new RuntimeException(
            'The R16 draw is locked after saving match winners.'
        );
    }


    // ============================================
    // CHAMPIONS - Validate final stage progression
    // ============================================

    $finalMatchups = [
        'F1' => ['S1', 'S2'],
        'F2' => ['S3', 'S4']
    ];

    // All four SF participants must exist before
    // selecting either finalist.
    if ($finalists !== [] && count($semiFinals) !== 4) {
        throw new RuntimeException(
            'All four semi-final slots must be filled first.'
        );
    }

    foreach ($finalists as $finalSlot => $teamId) {
        if (!array_key_exists($finalSlot, $finalMatchups)) {
            throw new RuntimeException('Unknown final slot.');
        }

        if (!is_int($teamId) || $teamId <= 0) {
            throw new RuntimeException('Invalid finalist ID.');
        }

        [$firstSlot, $secondSlot] = $finalMatchups[$finalSlot];

        if (
            $teamId !== ($semiFinals[$firstSlot] ?? null) &&
            $teamId !== ($semiFinals[$secondSlot] ?? null)
        ) {
            throw new RuntimeException(
                "Finalist for {$finalSlot} does not belong to its match."
            );
        }
    }

    // The champion must be one of the two finalists.
    if ($winnerId !== null) {
        if (
            count($finalists) !== 2 ||
            !in_array($winnerId, array_values($finalists), true)
        ) {
            throw new RuntimeException(
                'The champion must be one of the two finalists.'
            );
        }
    }

    // Preserve completed previous-round decisions.
    $previousFinalists = $progress['finalists'] ?? [];

    if (
        $previousFinalists !== [] &&
        ($progress['semi_finals'] ?? []) != $semiFinals
    ) {
        throw new RuntimeException(
            'Semi-final results are locked after saving finalists.'
        );
    }

    $previousWinner = $progress['winner'] ?? null;

    if (
        $previousWinner !== null &&
        ($progress['finalists'] ?? []) != $finalists
    ) {
        throw new RuntimeException(
            'Finalists are locked after saving the champion.'
        );
    }




    // ============================================
    // CHAMPIONS - Persist complete knockout progress
    // ============================================

    $progress['round_of_16'] = $slots;
    $progress['quarter_finals'] = $quarterFinals;
    $progress['semi_finals'] = $semiFinals;
    $progress['finalists'] = $finalists;
    $progress['winner'] = $winnerId;

    saveTournamentProgress($season, 'champions', $progress);



    header('Location: champions.php?r16_saved=1');
    exit;
} catch (Throwable $e) {
    http_response_code(400);

    echo '<h2>Cannot save Round of 16</h2>';
    echo '<p>' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
    echo '<a href="champions.php">Back to Champions League</a>';
}

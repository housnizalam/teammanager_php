<?php


// ============================================
// CHAMPIONS - Session and storage
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../helpers/tournament_storage.php';

$_SESSION['champions_csrf'] ??= bin2hex(random_bytes(32));


// ============================================
// CHAMPIONS LEAGUE - Database connection
// ============================================

require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_awards_ui.php';
require_once __DIR__ . '/../helpers/champions_qualification.php';


// ============================================
// CHAMPIONS LEAGUE - Nation filter
// ============================================

$nationsResult = $con->query(
    "SELECT DISTINCT nation
     FROM teams
     ORDER BY nation ASC"
);

$nations = $nationsResult->fetch_all(MYSQLI_ASSOC);
$nationsResult->free();



// ============================================
// CHAMPIONS LEAGUE - Available teams
// ============================================


$result = $con->query(
    "SELECT
        t.id,
        t.name,
        t.nation
     FROM teams AS t
     LEFT JOIN teams_archive AS ta
        ON ta.team_id = t.id
     ORDER BY
        COALESCE(ta.points, 0) DESC,
        t.name ASC"
);


$teams = $result->fetch_all(MYSQLI_ASSOC);
$result->free();


// ============================================
// CHAMPIONS - Load saved pots
// ============================================

$seasonResult = $con->query(
    "SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season
     FROM teams_archive"
);

$currentSeason = (int) $seasonResult
    ->fetch_assoc()['current_season'];

$seasonResult->free();

$statusResult = $con->execute_query(
    "SELECT is_played
     FROM tournaments
     WHERE season = ? AND competition = 'champions'",
    [$currentSeason]
);

$statusRow = $statusResult->fetch_assoc();
$statusResult->free();

if ($statusRow === null) {
    throw new RuntimeException(
        'Champions League tournament record not found.'
    );
}

$isPlayed = (int) $statusRow['is_played'] === 1;

$savedProgress = loadTournamentProgress(
    $currentSeason,
    'champions'
);


// ============================================
// CHAMPIONS - Load Saved or Automatic Pots
// ============================================

$emptyPots = [
    '1' => [],
    '2' => [],
    '3' => [],
    '4' => []
];

$savedPots = $savedProgress['pots'] ?? $emptyPots;

$automaticQualificationError = null;
$automaticQualificationApplied = false;

// Automatic qualification only for a new tournament.
// Never overwrite saved progress or a completed tournament.

if (
    !$isPlayed &&
    empty($savedProgress) &&
    $currentSeason > 1
) {
    try {
        $qualification = buildChampionsQualifiedTeams(
            $con,
            $currentSeason
        );

        // ============================================
        // CHAMPIONS - Verify Current Team IDs
        // ============================================

        $availableIds = array_fill_keys(
            array_map(
                static fn(array $team): int => (int)$team['id'],
                $teams
            ),
            true
        );

        $automaticPots = $emptyPots;

        foreach ($qualification['pots'] as $pot => $teamIds) {
            foreach ($teamIds as $teamId) {
                if (!isset($availableIds[$teamId])) {
                    throw new RuntimeException(
                        "Qualified team {$teamId} no longer exists."
                    );
                }
            }

            $automaticPots[(string)$pot] = $teamIds;
        }

        $savedPots = $automaticPots;
        $automaticQualificationApplied = true;
    } catch (Throwable $e) {
        // Keep the manual selection available on failure.
        $automaticQualificationError = $e->getMessage();
    }
}



// ============================================
// CHAMPIONS - Current stage and group data
// ============================================

$currentStage = $savedProgress['stage'] ?? 'pot_selection';
$savedGroups = $savedProgress['groups'] ?? [];

// Team lookup for group names.
$teamsById = [];

foreach ($teams as $team) {
    $teamsById[(int) $team['id']] = $team;
}

// Groups can only be generated from four full pots.
$potsReady = true;
$selectedIds = [];

for ($pot = 1; $pot <= 4; $pot++) {
    $ids = $savedProgress['pots'][(string) $pot] ?? [];

    if (!is_array($ids) || count($ids) !== 8) {
        $potsReady = false;
        break;
    }

    $selectedIds = array_merge($selectedIds, $ids);
}

$potsReady = $potsReady
    && count(array_unique($selectedIds)) === 32;




?>


<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Champions League - Football Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=champions-finish-1">
    <link rel="stylesheet" href="../assets/css/tournament_awards.css?v=1">

</head>

<body class="page-inner">

    <div class="app-shell">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <!-- MAIN CONTENT -->
        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">
                <div>
                    <h1 class="page-title">Champions League</h1>
                    <p class="page-subtitle">
                        Manage groups and knockout rounds.
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>
            </div>


            <!-- ============================================
     CHAMPIONS LEAGUE - Team selection
     ============================================ -->


            <!-- ============================================
     CHAMPIONS LEAGUE - POT SELECTION
     ============================================ -->

            <?php if ($currentStage === 'pot_selection'): ?>

                <section class="content-card">


                    <!-- CHAMPIONS - Pots header and save button -->
                    <div class="champions-pots-toolbar">

                        <h2>Champions League - Team Pots</h2>

                        <?php if (
                            !$isPlayed &&
                            ($savedProgress['stage'] ?? 'pot_selection') === 'pot_selection'
                        ): ?>

                            <button
                                type="submit"
                                form="champions-save-form"
                                class="action-button">
                                Save
                            </button>

                        <?php endif; ?>

                    </div>

                    <!-- CHAMPIONS - Save form -->
                    <form
                        id="champions-save-form"
                        method="post"
                        action="save_champions_pots.php">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                        $_SESSION['champions_csrf'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">

                        <input
                            type="hidden"
                            name="pots"
                            id="champions-save-data">
                    </form>

                    <?php if (($_GET['saved'] ?? '') === '1'): ?>
                        <p role="status" class="champions-save-message">
                            Team pots saved successfully.
                        </p>
                    <?php endif; ?>


                    <p class="page-subtitle">
                        Select exactly 8 teams for each pot.
                        A team can only be selected once.
                    </p>


                    <!-- ============================================
                    CHAMPIONS - Automatic Qualification Status
                    ============================================ -->

                    <?php if ($automaticQualificationApplied): ?>
                        <p class="champions-save-message" role="status">
                            Teams automatically qualified from Season
                            <?= (int)($currentSeason - 1) ?>.
                            Review the four pots and click Save.
                        </p>
                    <?php elseif ($automaticQualificationError !== null): ?>
                        <p class="page-subtitle" role="alert">
                            Automatic qualification unavailable:
                            <?= htmlspecialchars(
                                $automaticQualificationError,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            You can select teams manually.
                        </p>
                    <?php endif; ?>


                    <!-- CHAMPIONS - Four pots -->
                    <div class="champions-pots">

                        <?php for ($pot = 1; $pot <= 4; $pot++): ?>

                            <div class="champions-pot" data-pot="<?= $pot ?>">

                                <div class="champions-pot-header">
                                    <h3>Pot <?= $pot ?></h3>
                                    <span class="champions-pot-count">
                                        0 / 8
                                    </span>
                                </div>

                                <ol class="champions-pot-list"></ol>

                            </div>

                        <?php endfor; ?>

                    </div>

                    <!-- CHAMPIONS - Selection status -->
                    <div class="champions-selection-status">
                        Selected Teams:
                        <strong id="champions-total-count">0 / 32</strong>
                    </div>

                </section>


                <!-- CHAMPIONS - Generate groups action -->
                <?php if ($potsReady && !$isPlayed): ?>

                    <section class="content-card champions-generate">

                        <form
                            method="post"
                            action="generate_champions_groups.php"
                            onsubmit="return confirm(
                'Generate the eight groups now? ' +
                'The saved pots will be locked after the draw.'
            );">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                            $_SESSION['champions_csrf'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>">

                            <button type="submit" class="action-button">
                                Generate Groups
                            </button>
                        </form>

                    </section>

                <?php endif; ?>

            <?php elseif ($currentStage === 'group_stage'): ?>

                <!-- ============================================
         CHAMPIONS - Group Stage
         ============================================ -->

                <section class="content-card">

                    <div class="page-toolbar">
                        <h2>Champions League - Group Stage</h2>
                        <span>8 Groups / 32 Teams</span>
                    </div>

                    <?php if (($_GET['groups'] ?? '') === 'generated'): ?>
                        <p role="status" class="champions-save-message">
                            Groups generated and saved successfully.
                        </p>
                    <?php endif; ?>

                    <div class="champions-groups">

                        <?php foreach (range('A', 'H') as $groupName): ?>

                            <div class="champions-group-card">

                                <div class="champions-group-header">
                                    <h3>Group <?= $groupName ?></h3>
                                    <span>4 Teams</span>
                                </div>

                                <ol class="champions-group-list">

                                    <?php foreach (
                                        ($savedGroups[$groupName] ?? [])
                                        as $index => $teamId
                                    ): ?>


                                        <li
                                            class="champions-group-team"
                                            data-team-id="<?= (int) $teamId ?>"
                                            data-group="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">

                                            <span class="champions-group-number">
                                                <?= $index + 1 ?>
                                            </span>

                                            <span class="champions-group-name">
                                                <?= htmlspecialchars(
                                                    $teamsById[(int) $teamId]['name']
                                                        ?? 'Unknown Team',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>
                                        </li>

                                    <?php endforeach; ?>

                                </ol>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

                <!-- ============================================
                CHAMPIONS - Knockout Bracket Preview
                ============================================ -->

                <?php require __DIR__ . '/partials/champions_bracket.php'; ?>

            <?php endif; ?>



            <!-- ============================================
            CHAMPIONS LEAGUE - AVAILABLE TEAMS
            ============================================ -->
            <?php if ($currentStage === 'pot_selection'): ?>

                <section class="content-card">

                    <div class="page-toolbar">
                        <h2>Available Teams</h2>
                        <span><?= count($teams) ?> Teams</span>
                    </div>

                    <!-- CHAMPIONS - Nation filter -->
                    <div class="filter-panel champions-filter">

                        <label for="champions-nation">Nation</label>

                        <select id="champions-nation">
                            <option value="">All Nations</option>

                            <?php foreach ($nations as $nation): ?>

                                <?php if ($nation['nation'] === null) continue; ?>

                                <option
                                    value="<?= htmlspecialchars(
                                                $nation['nation'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">
                                    <?= htmlspecialchars(
                                        $nation['nation'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- CHAMPIONS - Teams table -->
                    <div class="table-container">

                        <table class="data-table">

                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th>Nation</th>
                                    <th>Pot</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="champions-team-list">

                                <?php foreach ($teams as $team): ?>

                                    <tr
                                        data-team-id="<?= (int) $team['id'] ?>"
                                        data-team-name="<?= htmlspecialchars(
                                                            $team['name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                        data-nation="<?= htmlspecialchars(
                                                            $team['nation'] ?? '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>">

                                        <td>
                                            <?= htmlspecialchars($team['name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $team['nation'] ?? ''
                                            ) ?>
                                        </td>

                                        <td>
                                            <select class="champions-pot-select">
                                                <option value="1">Pot 1</option>
                                                <option value="2">Pot 2</option>
                                                <option value="3">Pot 3</option>
                                                <option value="4">Pot 4</option>
                                            </select>
                                        </td>

                                        <td>
                                            <button
                                                type="button"
                                                class="action-button champions-add-team">
                                                Add
                                            </button>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </section>
            <?php endif; ?>


        </main>
    </div>

    <!-- CHAMPIONS - Round of 16 interaction -->
    <script src="../assets/js/champions_r16.js?v=5"></script>

    <!-- CHAMPIONS - Saved pots for JavaScript -->
    <script type="application/json" id="champions-saved-pots">
        <?= json_encode(
            $savedPots,
            JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_THROW_ON_ERROR
        ) ?>
    </script>

    <!-- CHAMPIONS - Pot selection logic -->
    <script src="../assets/js/champions_pots.js?v=7"></script>
    <script src="../assets/js/tournament_awards.js?v=1"></script>
</body>

</html>
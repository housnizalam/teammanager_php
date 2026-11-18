<?php
// ============================================
// LEAGUES - Session and JSON storage
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/../helpers/tournament_awards_ui.php';

$_SESSION['league_csrf'] ??= bin2hex(random_bytes(32));


// ============================================
// LEAGUES - Database connection
// ============================================

require_once __DIR__ . '/../helpers/db.php';


// ============================================
// LEAGUES - Available competitions
// ============================================

$leagues = [
    'England'     => 'Premier League',
    'Spain'       => 'La Liga',
    'Italy'       => 'Serie A',
    'Germany'     => 'Bundesliga',
    'France'      => 'Ligue 1',
    'Portugal'    => 'Primeira Liga',
    'Netherlands' => 'Eredivisie',
    'Turkey'      => 'Süper Lig',
    'rest'        => 'Rest of World'
];


// ============================================
// LEAGUES - Selected competition
// ============================================

$selectedLeague = $_GET['league'] ?? '';

if (
    !is_string($selectedLeague) ||
    !array_key_exists($selectedLeague, $leagues)
) {
    $selectedLeague = '';
}

$teams = [];


// ============================================
// LEAGUES - Load teams by nation
// ============================================

if ($selectedLeague !== '') {

    if ($selectedLeague === 'rest') {

        $mainNations = [
            'England',
            'Spain',
            'Italy',
            'Germany',
            'France',
            'Portugal',
            'Netherlands',
            'Turkey'
        ];

        $placeholders = implode(
            ', ',
            array_fill(0, count($mainNations), '?')
        );


        $sql = "
    SELECT
        t.id,
        t.name,
        t.nation
    FROM teams t
    LEFT JOIN teams_archive ta
        ON ta.team_id = t.id
    WHERE t.nation NOT IN ($placeholders)
       OR t.nation IS NULL
    ORDER BY
        COALESCE(ta.points, 0) DESC,
        t.name ASC
";


        $result = $con->execute_query(
            $sql,
            $mainNations
        );
    } else {


        $sql = "
    SELECT
        t.id,
        t.name,
        t.nation
    FROM teams t
    LEFT JOIN teams_archive ta
        ON ta.team_id = t.id
    WHERE t.nation = ?
    ORDER BY
        COALESCE(ta.points, 0) DESC,
        t.name ASC
";


        $result = $con->execute_query(
            $sql,
            [$selectedLeague]
        );
    }

    $teams = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}


// ============================================
// LEAGUES - Save and restore standings
// ============================================

// ============================================
// TOURNAMENTS - Current season from archive
// ============================================

$seasonResult = $con->query(
    "SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season
      FROM teams_archive"
);

$currentSeason = (int) $seasonResult
    ->fetch_assoc()['current_season'];

$seasonResult->free();


$saveError = '';
$savedMagicNumber = '';

// ============================================
// LEAGUES - Initial completion state
// ============================================

$isPlayed = false;

if ($selectedLeague !== '') {

    $competitionKey = 'league_' . strtolower($selectedLeague);


    // ============================================
    // LEAGUES - Completion status
    // ============================================

    $statusResult = $con->execute_query(
        "SELECT is_played
         FROM tournaments
         WHERE season = ? AND competition = ?",
        [$currentSeason, $competitionKey]
    );

    $statusRow = $statusResult->fetch_assoc();
    $statusResult->free();

    if ($statusRow === null) {
        throw new RuntimeException(
            'Tournament record not found.'
        );
    }

    $isPlayed = (int) $statusRow['is_played'] === 1;


    // --------------------------------------------
    // LEAGUES - Handle save request
    // --------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        try {
            $token = $_POST['csrf_token'] ?? '';
            // ممكن ينمحى

            if ($isPlayed) {
                throw new RuntimeException(
                    'This tournament has already been finalized.'
                );
            }
            // ممكن ينمحى

            if (
                !is_string($token) ||
                !hash_equals($_SESSION['league_csrf'], $token)
            ) {
                throw new RuntimeException('Invalid save request.');
            }

            $magicNumber = filter_var(
                $_POST['magic_number'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($magicNumber === false) {
                throw new RuntimeException(
                    'Magic Number must be a positive integer.'
                );
            }

            $submittedIds = json_decode(
                $_POST['team_order'] ?? '',
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $databaseIds = array_map(
                'intval',
                array_column($teams, 'id')
            );

            // Every team must appear exactly once.
            if (
                !is_array($submittedIds) ||
                count($submittedIds) !== count($databaseIds) ||
                count($submittedIds) !== count(array_unique($submittedIds)) ||
                count(array_filter($submittedIds, 'is_int')) !== count($submittedIds)
            ) {
                throw new RuntimeException('Invalid team order.');
            }

            $sortedSubmitted = $submittedIds;
            $sortedDatabase = $databaseIds;

            sort($sortedSubmitted);
            sort($sortedDatabase);

            if ($sortedSubmitted !== $sortedDatabase) {
                throw new RuntimeException(
                    'The team list does not match the selected league.'
                );
            }

            saveTournamentProgress(
                $currentSeason,
                $competitionKey,
                [
                    'magic_number' => $magicNumber,
                    'team_ids' => $submittedIds
                ]
            );

            header(
                'Location: leagues.php?league=' .
                    rawurlencode($selectedLeague) .
                    '&saved=1'
            );
            exit;
        } catch (Throwable $e) {
            $saveError = $e->getMessage();
        }
    }

    // --------------------------------------------
    // LEAGUES - Restore saved standings
    // --------------------------------------------

    $savedProgress = loadTournamentProgress(
        $currentSeason,
        $competitionKey
    );

    $savedMagicNumber = $savedProgress['magic_number'] ?? '';

    $savedIds = $savedProgress['team_ids'] ?? [];

    if (is_array($savedIds) && count($savedIds) > 0) {

        $teamsById = [];

        foreach ($teams as $team) {
            $teamsById[(int) $team['id']] = $team;
        }

        // Restore only if the saved list still
        // contains exactly the current league teams.
        $currentIds = array_keys($teamsById);

        $sortedSaved = $savedIds;
        sort($sortedSaved);
        sort($currentIds);

        if ($sortedSaved === $currentIds) {

            $orderedTeams = [];

            foreach ($savedIds as $teamId) {
                $orderedTeams[] = $teamsById[$teamId];
            }

            $teams = $orderedTeams;
        }
    }
}


?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Leagues - Football Manager</title>

    <!-- LEAGUES - Stylesheet cache version -->
    <link rel="stylesheet"
        href="../assets/css/style.css?v=20261117-2">
    <link rel="stylesheet" href="../assets/css/tournament_awards.css?v=1">
</head>

<body class="page-inner">

    <div class="app-shell">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . '/../helpers/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">Leagues</h1>
                    <p class="page-subtitle">
                        Select a league to manage its standings.
                    </p>
                </div>

                <?php
                require_once __DIR__ . '/../helpers/back_button.php';
                ?>

            </div>

            <!-- LEAGUE SELECTION -->
            <section class="content-card">

                <h2>Select League</h2>

                <form method="get" class="league-selection-form">

                    <label for="league">
                        Competition
                    </label>

                    <select
                        id="league"
                        name="league"
                        required
                        onchange="this.form.submit()">

                        <option value="">
                            -- Select League --
                        </option>

                        <?php foreach ($leagues as $key => $label): ?>

                            <option
                                value="<?= htmlspecialchars($key) ?>"
                                <?= $selectedLeague === $key
                                    ? 'selected'
                                    : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            </section>

            <!-- LEAGUE TEAMS -->
            <?php if ($selectedLeague !== ''): ?>

                <section class="content-card">

                    <div class="page-toolbar">

                        <h2>
                            <?= htmlspecialchars(
                                $leagues[$selectedLeague]
                            ) ?>
                        </h2>

                        <span>
                            <?= count($teams) ?> Teams
                        </span>

                    </div>

                    <?php if (count($teams) === 0): ?>

                        <p>
                            No teams found for this league.
                        </p>

                    <?php else: ?>




                        <!-- LEAGUES - Top controls -->
                        <div class="league-controls">

                            <!-- LEAGUES - Magic Number -->
                            <div class="league-magic-control">
                                <label for="magic-number">Magic Number</label>

                                <input
                                    type="number"
                                    id="magic-number"
                                    min="1"
                                    step="1"
                                    placeholder="Magic Number"
                                    value="<?= htmlspecialchars((string) $savedMagicNumber) ?>"
                                    <?= $isPlayed ? 'disabled' : '' ?>>
                            </div>

                            <!-- LEAGUES - Save button -->
                            <?php if (!$isPlayed): ?>
                                <button
                                    type="submit"
                                    form="league-save-form"
                                    class="league-action-button">
                                    Save
                                </button>
                            <?php endif; ?>

                        </div>



                        <div class="table-container">

                            <table class="data-table">

                                <thead>
                                    <tr>
                                        <th>Position</th>
                                        <th>Team</th>
                                        <th>Nation</th>
                                        <th>Points</th>
                                    </tr>
                                </thead>

                                <!-- LEAGUES - Draggable team list -->
                                <tbody id="league-teams">

                                    <?php foreach ($teams as $index => $team): ?>

                                        <tr
                                            draggable="<?= $isPlayed ? 'false' : 'true' ?>"
                                            data-team-id="<?= (int) $team['id'] ?>">
                                            <td class="league-position">
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $team['name']
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    $team['nation'] ?? ''
                                                ) ?>
                                            </td>

                                            <!-- LEAGUES - Calculated points -->
                                            <td class="league-points">—</td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>


                    <!-- LEAGUES - Save feedback -->
                    <?php if ($saveError !== ''): ?>
                        <p role="alert">
                            <?= htmlspecialchars($saveError) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                        <p role="status">
                            League standings saved successfully.
                        </p>
                    <?php endif; ?>

                    <!-- LEAGUES - Save progress -->
                    <form
                        id="league-save-form"
                        method="post"
                        action="?league=<?= urlencode($selectedLeague) ?>">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['league_csrf']) ?>">

                        <input
                            type="hidden"
                            name="magic_number"
                            id="save-magic-number">

                        <input
                            type="hidden"
                            name="team_order"
                            id="save-team-order">
                    </form>


                </section>


                <!-- LEAGUES - Finalize tournament -->
                <div class="league-finalize">

                    <?php if ($isPlayed): ?>

                        <p class="league-completed">
                            Tournament completed and results finalized.
                        </p>

                    <?php elseif (
                        isset($savedProgress['magic_number']) &&
                        !empty($savedProgress['team_ids'])
                    ): ?>

                        <form
                            method="post"
                            action="finalize_league.php"
                            onsubmit="return confirm(
                'Are you sure you want to finish this tournament? ' +
                'Points and matches will be permanently added ' +
                'to the database. This action cannot be undone.'
            );">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['league_csrf']) ?>">

                            <input
                                type="hidden"
                                name="league"
                                value="<?= htmlspecialchars($selectedLeague) ?>">

                            <!-- ============================================
                                 LEAGUES - Individual Awards
                                 ============================================ -->
                            <?php renderTournamentAwardFields(
                                $con,
                                array_column($teams, 'id')
                            ); ?>

                            <button
                                type="submit"
                                class="league-action-button">
                                Finish Tournament
                            </button>
                        </form>

                    <?php else: ?>

                        <p>
                            Save the league standings before finishing
                            the tournament.
                        </p>

                    <?php endif; ?>

                </div>


            <?php endif; ?>

        </main>

    </div>


    <script>
        // ============================================
        // LEAGUES - Drag & Drop team ordering
        // ============================================

        const leagueTeams = document.getElementById('league-teams');

        const magicNumberInput = document.getElementById('magic-number');

        if (leagueTeams) {
            let draggedRow = null;


            // ============================================
            // LEAGUES - Update positions and points
            // ============================================

            function updateLeaguePositions() {
                const rows = leagueTeams.querySelectorAll('tr');

                const magicNumber = Number(magicNumberInput.value);
                const isValidMagicNumber =
                    magicNumberInput.value.trim() !== '' &&
                    Number.isInteger(magicNumber) &&
                    magicNumber > 0;

                rows.forEach((row, index) => {
                    // Position starts at 1.
                    const position = index + 1;

                    row.querySelector('.league-position').textContent =
                        position;

                    const pointsCell = row.querySelector('.league-points');

                    if (!isValidMagicNumber) {
                        pointsCell.textContent = '—';
                        return;
                    }

                    // First place = Magic Number * 10.
                    // Each following place loses one Magic Number.
                    // Points cannot become negative.
                    const points = Math.max(
                        0,
                        (11 - position) * magicNumber
                    );

                    pointsCell.textContent = points;
                });
            }

            // ============================================
            // LEAGUES - Recalculate on Magic Number change
            // ============================================

            magicNumberInput.addEventListener(
                'input',
                updateLeaguePositions
            );


            // ============================================
            // LEAGUES - Drag start and end
            // ============================================

            leagueTeams.addEventListener('dragstart', (event) => {
                const row = event.target.closest('tr');

                if (!row) return;

                draggedRow = row;
                event.dataTransfer.effectAllowed = 'move';

                row.classList.add('league-dragging');
            });

            leagueTeams.addEventListener('dragend', () => {
                if (draggedRow) {
                    draggedRow.classList.remove('league-dragging');
                }

                draggedRow = null;
                updateLeaguePositions();
            });

            // ============================================
            // LEAGUES - Reorder rows
            // ============================================

            leagueTeams.addEventListener('dragover', (event) => {
                event.preventDefault();

                const targetRow = event.target.closest('tr');

                if (!draggedRow ||
                    !targetRow ||
                    targetRow === draggedRow) {
                    return;
                }

                const rectangle = targetRow.getBoundingClientRect();

                const insertAfter =
                    event.clientY > rectangle.top + rectangle.height / 2;

                if (insertAfter) {
                    targetRow.after(draggedRow);
                } else {
                    targetRow.before(draggedRow);
                }

                updateLeaguePositions();
            });

            leagueTeams.addEventListener('drop', (event) => {
                event.preventDefault();
                updateLeaguePositions();
            });
        }


        // ============================================
        // LEAGUES - Submit current standings
        // ============================================

        const leagueSaveForm =
            document.getElementById('league-save-form');

        if (leagueSaveForm && leagueTeams && magicNumberInput) {

            // Restore calculated points on page load.
            updateLeaguePositions();

            leagueSaveForm.addEventListener('submit', (event) => {

                const magicNumber = Number(magicNumberInput.value);

                if (
                    magicNumberInput.value.trim() === '' ||
                    !Number.isSafeInteger(magicNumber) ||
                    magicNumber < 1
                ) {
                    event.preventDefault();
                    alert('Please enter a valid Magic Number.');
                    return;
                }

                const teamIds = Array.from(
                    leagueTeams.querySelectorAll('tr')
                ).map(row => Number(row.dataset.teamId));

                document.getElementById('save-magic-number').value =
                    magicNumber;

                document.getElementById('save-team-order').value =
                    JSON.stringify(teamIds);
            });
        }
    </script>
    <script src="../assets/js/tournament_awards.js?v=1"></script>

</body>

</html>
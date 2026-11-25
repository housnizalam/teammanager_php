<?php
// ============================================
// EUROPA - Session, storage and database
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/tournament_storage.php';
require_once __DIR__ . '/../helpers/tournament_awards_ui.php';
require_once __DIR__ . '/../helpers/europa_qualification.php';

$_SESSION['europa_csrf'] ??= bin2hex(random_bytes(32));

// ============================================
// EUROPA - Current season and tournament status
// ============================================
$seasonResult = $con->query(
    'SELECT COALESCE(MAX(seasons), 0) + 1 AS current_season FROM teams_archive'
);
$currentSeason = (int) $seasonResult->fetch_assoc()['current_season'];
$seasonResult->free();

$statusResult = $con->execute_query(
    "SELECT is_played, winner_team_id FROM tournaments
     WHERE season = ? AND competition = 'europa'",
    [$currentSeason]
);
$tournament = $statusResult->fetch_assoc();
$statusResult->free();
if ($tournament === null) {
    throw new RuntimeException('Europa League tournament record not found.');
}
$isPlayed = (int) $tournament['is_played'] === 1;

// ============================================
// EUROPA - Teams sorted by archived points
// ============================================
$teamResult = $con->query(
    "SELECT t.id, t.name, t.nation
     FROM teams AS t
     LEFT JOIN teams_archive AS ta ON ta.team_id = t.id
     ORDER BY COALESCE(ta.points, 0) DESC, t.name ASC"
);
$teams = $teamResult->fetch_all(MYSQLI_ASSOC);
$teamResult->free();

$teamsById = [];
$nations = [];
foreach ($teams as $team) {
    $teamsById[(int) $team['id']] = $team;
    if ($team['nation'] !== null && $team['nation'] !== '') {
        $nations[$team['nation']] = true;
    }
}
$nations = array_keys($nations);
sort($nations, SORT_NATURAL | SORT_FLAG_CASE);



// ============================================
// EUROPA - Highlight Domestic Qualification Candidates
// ============================================

$europaCandidateIds = [];
$europaCandidateError = null;

try {
    $candidates = getEuropaDomesticCandidates(
        $currentSeason
    );

    // Map team ID to its domestic league.
    foreach ($candidates as $league => $teamId) {
        if (!isset($teamsById[$teamId])) {
            throw new RuntimeException(
                "Qualified team {$teamId} was not found."
            );
        }

        $europaCandidateIds[$teamId] = $league;
    }

    // ============================================
    // EUROPA - Move Candidates to End of Team List
    // ============================================

    $regularTeams = [];
    $qualifiedTeams = [];

    foreach ($teams as $team) {
        $teamId = (int)$team['id'];

        if (isset($europaCandidateIds[$teamId])) {
            $qualifiedTeams[] = $team;
        } else {
            $regularTeams[] = $team;
        }
    }

    $teams = array_merge(
        $regularTeams,
        $qualifiedTeams
    );
} catch (Throwable $e) {
    // Keep the existing manual team selection available.
    $europaCandidateIds = [];
    $europaCandidateError = $e->getMessage();
}


// ============================================
// EUROPA - Load saved knockout progress
// ============================================
$savedProgress = loadTournamentProgress($currentSeason, 'europa');
if ($savedProgress !== [] && ($savedProgress['stage'] ?? '') !== 'knockout') {
    throw new RuntimeException('Unsupported Europa League progress stage.');
}

$encodeJson = static fn(mixed $value): string => json_encode(
    $value,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Europa League - Football Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=europa-1">
    <link rel="stylesheet" href="../assets/css/europa.css?v=4">
    <link rel="stylesheet" href="../assets/css/tournament_awards.css?v=1">
</head>

<body class="page-inner">
    <div class="app-shell">
        <!-- EUROPA - Shared sidebar -->
        <?php require_once __DIR__ . '/../helpers/sidebar.php'; ?>

        <main class="app-main">
            <!-- EUROPA - Page header -->
            <div class="page-header-bar">
                <div>
                    <h1 class="page-title">Europa League</h1>
                    <p class="page-subtitle">Select 16 teams and manage the knockout bracket.</p>
                </div>
                <?php require_once __DIR__ . '/../helpers/back_button.php'; ?>
            </div>

            <!-- EUROPA - Selection source: display until R16 is full -->
            <section class="content-card" id="europa-available-section">
                <div class="page-toolbar">
                    <h2>Available Teams</h2>
                    <span><?= count($teams) ?> Teams</span>
                </div>
                <p class="page-subtitle">
                    Select a Round of 16 slot below, then add a team here.
                    Each team can be selected only once.
                </p>


                <!-- ============================================
     EUROPA - Domestic Qualification Information
     ============================================ -->

                <?php if (count($europaCandidateIds) === 9): ?>
                    <p class="page-subtitle">
                        The nine green-highlighted teams at the bottom
                        are the best non-Champions-League teams
                        from the previous domestic league season.
                    </p>
                <?php elseif ($europaCandidateError !== null): ?>
                    <p class="page-subtitle" role="status">
                        Domestic qualification suggestions unavailable:
                        <?= htmlspecialchars(
                            $europaCandidateError,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>
                <?php endif; ?>


                <div class="filter-panel champions-filter">
                    <label for="europa-nation">Nation</label>
                    <select id="europa-nation">
                        <option value="">All Nations</option>
                        <?php foreach ($nations as $nation): ?>
                            <option value="<?= htmlspecialchars($nation, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($nation, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Nation</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="europa-team-list">

                            <?php foreach ($teams as $team): ?>
                                <?php
                                $teamId = (int)$team['id'];
                                $isDomesticCandidate = isset($europaCandidateIds[$teamId]);
                                ?>

                                <tr
                                    class="<?= $isDomesticCandidate ? 'europa-qualified-row' : '' ?>"
                                    data-team-id="<?= $teamId ?>"

                                    data-team-name="<?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-nation="<?= htmlspecialchars($team['nation'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <td><?= htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($team['nation'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><button type="button" class="action-button europa-add-team">Add</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>


            <!-- ============================================
                 EUROPA - Team Search
                 ============================================ -->

            <div class="europa-search-bar">
                <label for="europa-team-search">Search Team</label>

                <input
                    type="search"
                    id="europa-team-search"
                    placeholder="Search by team name..."
                    autocomplete="off">
            </div>

            <!-- EUROPA - Mirrored bracket, progress and actions -->
            <?php require __DIR__ . '/partials/europa_bracket.php'; ?>

        </main>
    </div>

    <!-- EUROPA - All saved data is read as inert JSON; not executable JS -->
    <script type="application/json" id="europa-saved-progress">
        <?= $encodeJson([
            'round_of_16' => $savedProgress['round_of_16'] ?? new stdClass(),
            'quarter_finals' => $savedProgress['quarter_finals'] ?? new stdClass(),
            'semi_finals' => $savedProgress['semi_finals'] ?? new stdClass(),
            'finalists' => $savedProgress['finalists'] ?? new stdClass(),
            'winner' => $savedProgress['winner'] ?? null,
        ]) ?>
    </script>
    <script src="../assets/js/europa_knockout.js?v=2"></script>
    <script src="../assets/js/tournament_awards.js?v=1"></script>
</body>

</html>
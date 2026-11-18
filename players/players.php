<?php

// ============================================
// RATING FIX - Session
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/formation.php";
require_once __DIR__ . "/../helpers/table.php";


// ============================================
// RATING FIX - Current season and button state
// ============================================

require_once __DIR__ . "/../helpers/rating_fix.php";

$_SESSION['rating_fix_csrf'] ??= bin2hex(random_bytes(32));

$seasonResult = $con->query("
    SELECT COALESCE(MAX(seasons), 0) + 1
        AS current_season
    FROM teams_archive
");

$currentSeason = (int)$seasonResult
    ->fetch_assoc()['current_season'];

$seasonResult->free();

$allTournamentsCompleted = ratingFixAllCompleted(
    $con,
    $currentSeason
);

$ratingFixApplied = ratingFixAlreadyApplied(
    $con,
    $currentSeason
);

$ratingFixEnabled =
    $allTournamentsCompleted && !$ratingFixApplied;

$ratingFixMessage = $_SESSION['rating_fix_message'] ?? null;
$ratingFixError = $_SESSION['rating_fix_error'] ?? null;

unset(
    $_SESSION['rating_fix_message'],
    $_SESSION['rating_fix_error']
);


$positionOptions = ["" => "-- All Positions --"];
foreach (playerPositions() as $position) {
    $positionOptions[$position] = $position;
}

$teamOptions = ["" => "-- All Teams --"];
$teams = $con->query("SELECT id, name FROM teams ORDER BY name");
while ($team = $teams->fetch_assoc()) {
    $teamOptions[$team["id"]] = $team["name"];
}
$teams->close();

$filterOptions = [
    "name" => [],
    "position" => $positionOptions,
    "team" => $teamOptions
];

$filters = [
    "name" => "players.name",
    "position" => "players.position",
    "team" => "players.team_id"
];

$sorts = [
    "name" => "players.name",
    "age" => "players.age",
    "position" => positionSortSql("players.position"),
    "matches" => "players.matches",
    "goals" => "players.goals",
    "assists" => "players.assists",
    "rating" => "players.rating"
];

$sortOptions = [
    "name" => "Name",
    "age" => "Age",
    "position" => "Position",
    "matches" => "Matches",
    "goals" => "Goals",
    "assists" => "Assists",
    "rating" => "Rating"
];

$baseSql = "SELECT players.*, teams.name AS team_name
            FROM players
            JOIN teams ON players.team_id = teams.id
            WHERE 1=1";

[$sql, $params] = buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $_GET,
    [],
    "position",
    "ASC"
);

$res = $con->execute_query($sql, $params);

$formationRes = $con->query("SELECT * FROM players");

$formationPlayers = $formationRes->fetch_all(MYSQLI_ASSOC);
$formationRes->close();

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Players</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>

<body class="page-inner page-team-details">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">Players</h1>

                    <p class="page-subtitle">
                        Overview of all players in the current season and the Best XI.
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <!-- FILTERS -->
            <div class="content-card filter-panel">

                <?php
                showListFilters(
                    $filterOptions,
                    $sortOptions,
                    $_GET,
                    "position",
                    "DESC"
                );
                ?>

            </div>

            <!-- PLAYERS SECTION -->
            <section class="team-players-section">


                <!-- ============================================
     RATING FIX - Players Toolbar
     ============================================ -->

                <div class="page-toolbar players-rating-toolbar">

                    <h2>All Players</h2>

                    <form
                        class="rating-fix-form"
                        action="fix_rating.php"
                        method="POST">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                        $_SESSION['rating_fix_csrf'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">

                        <button
                            type="submit"
                            class="action-button rating-fix-button"
                            <?= $ratingFixEnabled ? '' : 'disabled' ?>>
                            <?= $ratingFixApplied ? 'Applied' : 'Fix Rating' ?>
                        </button>
                    </form>

                </div>

                <?php if ($ratingFixMessage): ?>
                    <p class="rating-fix-success">
                        <?= htmlspecialchars($ratingFixMessage) ?>
                    </p>
                <?php endif; ?>

                <?php if ($ratingFixError): ?>
                    <p class="rating-fix-error">
                        <?= htmlspecialchars($ratingFixError) ?>
                    </p>
                <?php endif; ?>


                <div class="formation-layout">

                    <!-- PLAYERS TABLE -->
                    <div class="content-card team-players-table">

                        <?php
                        $columns = [


                            "name" => [
                                "label" => "Name",
                                "render" => function ($player) {
                                    return '<a href="player_details.php?player_id='
                                        . (int)$player["id"] . '">'
                                        . htmlspecialchars($player["name"])
                                        . '</a>';
                                }
                            ],

                            "position" => [
                                "label" => "Position"
                            ],

                            "age" => [
                                "label" => "Age"
                            ],

                            "team_name" => [
                                "label" => "Team",
                                "sort" => ""
                            ],

                            "rating" => [
                                "label" => "Rating"
                            ],

                            "goals" => [
                                "label" => "Goals"
                            ],

                            "assists" => [
                                "label" => "Assists"
                            ],

                            "matches" => [
                                "label" => "Matches"
                            ]
                        ];

                        // Scroll independently from Best XI
                        echo '<div class="players-scroll">';

                        showTable(
                            $res,
                            $columns,
                            "position",
                            "No players found."
                        );

                        echo '</div>';

                        $res->close();
                        $con->close();
                        ?>

                    </div>

                    <!-- BEST XI -->

                    <?php
                    // ============================================
                    // PLAYERS - Formation Details Links
                    // ============================================

                    showFormation(
                        $formationPlayers,
                        static fn(array $player): string =>
                        'player_details.php?player_id=' . (int)$player['id']
                    );
                    ?>


                </div>

            </section>

        </main>

    </div>

</body>

</html>
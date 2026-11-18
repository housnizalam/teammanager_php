<?php
require_once __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/formation.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/table.php";

$positionOptions = ["" => "-- All Positions --"];
foreach (playerPositions() as $position) {
    $positionOptions[$position] = $position;
}

$teamOptions = ["" => "-- All Teams --"];

$teams = $con->query(
    "SELECT DISTINCT team_name
     FROM players_archive
     ORDER BY team_name"
);

while ($team = $teams->fetch_assoc()) {
    $teamOptions[$team["team_name"]] = $team["team_name"];
}
$teams->close();

$filterOptions = [
    "name" => [],
    "position" => $positionOptions,
    "team_name" => $teamOptions
];

$filters = [
    "name" => "name",
    "position" => "position",
    "team_name" => "team_name"
];

$sorts = [
    "name" => "name",
    "age" => "age",
    "position" => positionSortSql(),
    "seasons" => "seasons",
    "matches" => "matches",
    "goals" => "goals",
    "assists" => "assists",
    "rating" => "rating"
];

$sortOptions = [
    "name" => "Name",
    "age" => "Age",
    "position" => "Position",
    "seasons" => "Seasons",
    "matches" => "Matches",
    "goals" => "Goals",
    "assists" => "Assists",
    "rating" => "Rating"
];

$baseSql = "SELECT * FROM players_archive WHERE 1=1";

[$sql, $params] = buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $_GET,
    [],
    "rating",
    "DESC"
);

$res = $con->execute_query($sql, $params);
$formationRes = $con->query("SELECT * FROM players_archive");

$formationPlayers = $formationRes->fetch_all(MYSQLI_ASSOC);
$formationRes->close();

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Players</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
</head>

<body class="page-inner page-team-details">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">Archive Players</h1>

                    <p class="page-subtitle">
                        Historical player statistics and the Best XI of all archived players.
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
                    "rating",
                    "DESC"
                );
                ?>

            </div>

            <!-- PLAYERS AND FORMATION -->
            <section class="team-players-section">

                <div class="page-toolbar">
                    <h2>Archived Players</h2>
                </div>

                <div class="formation-layout">

                    <!-- PLAYERS TABLE -->
                    <div class="content-card team-players-table">

                        <?php
                        $columns = [

                            "name" => [
                                "label" => "Name",
                                "render" => function ($player) {
                                    return '<a href="player_archive_details.php?player_id='
                                        . (int)$player["player_id"] . '">'
                                        . htmlspecialchars($player["name"])
                                        . '</a>';
                                }
                            ],

                            "position" => [
                                "label" => "Position"
                            ],


                            "team_name" => [
                                "label" => "Last Team",
                                "sort" => ""
                            ],

                            "age" => [
                                "label" => "Age"
                            ],

                            "seasons" => [
                                "label" => "Seasons"
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

                        // Independent scroll for the players table
                        echo '<div class="players-scroll">';

                        showTable(
                            $res,
                            $columns,
                            "rating",
                            "No archived players yet."
                        );

                        echo '</div>';

                        $res->close();
                        $con->close();
                        ?>


                    </div>

                    <!-- BEST XI -->

                    <?php
                    // ============================================
                    // ARCHIVE - Formation Player Details Links
                    // ============================================

                    showFormation(
                        $formationPlayers,
                        static fn(array $player): string =>
                        'player_archive_details.php?player_id='
                            . (int)$player['player_id']
                    );
                    ?>


                </div>

            </section>

        </main>

    </div>

</body>

</html>
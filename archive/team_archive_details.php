<?php
include __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/formation.php";
require_once __DIR__ . "/../helpers/table.php";

$team_id = $_GET["team_id"] ?? 0;

if (!ctype_digit((string)$team_id) || (int)$team_id < 1) {
    exit("Invalid team ID");
}

// Team aus dem Archiv laden
$sql = "SELECT * FROM teams_archive WHERE team_id = ?";
$res = $con->execute_query($sql, [$team_id]);

$team = $res->fetch_assoc();
$res->close();

if (!$team) {
    exit("Team not found in archive");
}

// Archivierte Spieler dieses Teams laden

$positionOptions = ["" => "-- All Positions --"];

foreach (playerPositions() as $position) {
    $positionOptions[$position] = $position;
}

$filterOptions = [
    "name" => [],
    "position" => $positionOptions
];

$filters = [
    "name" => "name",
    "position" => "position"
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

$baseSql = "SELECT * FROM players_archive
            WHERE team_name = ? AND 1=1";

[$sql, $params] = buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $_GET,
    [$team["name"]],
    "position",
    "ASC"
);

$res = $con->execute_query($sql, $params);

$formationRes = $con->execute_query(
    "SELECT * FROM players_archive WHERE team_name = ?",
    [$team["name"]]
);

$formationPlayers = $formationRes->fetch_all(MYSQLI_ASSOC);
$formationRes->close();

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Archive Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner page-team-details">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">
                        <?= htmlspecialchars($team["name"]) ?>
                    </h1>

                    <p class="page-subtitle">
                        Historical team statistics and player overview
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <!-- ARCHIVE TEAM INFORMATION -->
            <section class="content-card team-info-card">

                <div class="team-info-header">
                    <h2>Team Archive Information</h2>
                </div>

                <div class="team-info-grid">

                    <div class="team-info-item">
                        <label>Name</label>
                        <div class="archive-info-value">
                            <?= htmlspecialchars($team["name"]) ?>
                        </div>
                    </div>

                    <div class="team-info-item">
                        <label>Nation</label>
                        <div class="archive-info-value">
                            <?= htmlspecialchars($team["nation"]) ?>
                        </div>
                    </div>

                    <div class="team-info-item">
                        <label>Seasons</label>
                        <div class="archive-info-value">
                            <?= $team["seasons"] ?>
                        </div>
                    </div>

                    <div class="team-info-item">
                        <label>Matches</label>
                        <div class="archive-info-value">
                            <?= $team["matches"] ?>
                        </div>
                    </div>

                    <div class="team-info-item">
                        <label>Points</label>
                        <div class="archive-info-value">
                            <?= $team["points"] ?>
                        </div>
                    </div>

                </div>

            </section>

            <!-- ARCHIVED PLAYERS -->
            <section class="team-players-section">

                <div class="page-toolbar">
                    <h2>Archived Players</h2>
                </div>

                <div class="content-card filter-panel">

                    <?php
                    showListFilters(
                        $filterOptions,
                        $sortOptions,
                        $_GET,
                        "position",
                        "DESC",
                        ["team_id" => $team_id]
                    );
                    ?>

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

                        // Independent scrolling for the players table
                        echo '<div class="players-scroll">';

                        showTable(
                            $res,
                            $columns,
                            "position",
                            "No archived players found."
                        );

                        echo '</div>';

                        $res->close();
                        $con->close();
                        ?>

                    </div>

                    <!-- BEST XI -->
                    <?php showFormation($formationPlayers); ?>

                </div>

            </section>

        </main>

    </div>

</body>

</html>
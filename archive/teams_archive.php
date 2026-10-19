<?php

require_once __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/table.php";

$nationOptions = ["" => "-- All Nations --"];

$nations = $con->query(
    "SELECT DISTINCT nation FROM teams_archive ORDER BY nation"
);

while ($nation = $nations->fetch_assoc()) {
    $nationOptions[$nation["nation"]] = $nation["nation"];
}
$nations->close();

$filterOptions = [
    "name" => [],
    "nation" => $nationOptions
];

$filters = [
    "name" => "name",
    "nation" => "nation"
];

$sorts = [
    "name" => "name",
    "seasons" => "seasons",
    "matches" => "matches",
    "points" => "points"
];

$sortOptions = [
    "name" => "Name",
    "seasons" => "Seasons",
    "matches" => "Matches",
    "points" => "Points"
];

$baseSql = "SELECT * FROM teams_archive WHERE 1=1";

[$sql, $params] = buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $_GET,
    [],
    "points",
    "DESC"
);

$res = $con->execute_query($sql, $params);

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Archive</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>


        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">Teams Archive</h1>

                    <p class="page-subtitle">
                        Overview of all teams and their historical statistics.
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
                    "points",
                    "DESC"
                );
                ?>

            </div>

            <!-- ARCHIVE TABLE -->
            <div class="content-card">

                <div class="page-toolbar">
                    <h2>Archivierte Teams</h2>
                </div>


                <?php
                $columns = [
                    "name" => [
                        "label" => "Team",
                        "render" => function ($team) {
                            return '<a href="team_archive_details.php?team_id='
                                . (int)$team["team_id"] . '">'
                                . htmlspecialchars($team["name"])
                                . '</a>';
                        }
                    ],

                    "nation" => [
                        "label" => "Nation"
                    ],

                    "seasons" => [
                        "label" => "Seasons"
                    ],

                    "matches" => [
                        "label" => "Matches"
                    ],

                    "points" => [
                        "label" => "Points"
                    ]
                ];

                showTable(
                    $res,
                    $columns,
                    "points",
                    "No archived data yet."
                );

                $res->close();
                $con->close();
                ?>


            </div>

        </main>

    </div>

</body>

</html>
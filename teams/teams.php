<?php
require_once __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/table.php";

$nationOptions = ["" => "-- All Nations --"];

$nations = $con->query(
    "SELECT DISTINCT nation FROM teams ORDER BY nation"
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
    "name" => "teams.name",
    "nation" => "nation"
];

$sorts = [
    "id" => "id",
    "name" => "name",
    "matches" => "matches",
    "points" => "points"
];

$sortOptions = [
    "id" => "ID",
    "name" => "Name",
    "matches" => "Matches",
    "points" => "Points"
];

$baseSql = "SELECT * FROM teams WHERE 1=1";

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
    <title>View Teams</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <div class="page-header-bar">
                <div>
                    <h1 class="page-title">All Teams</h1>
                    <p class="page-subtitle">
                        Overview of all teams in the current season.
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <div class="content-card filter-panel">
                <?php
                showListFilters(
                    $filterOptions,
                    $sortOptions,
                    $_GET,
                    "points",
                    "DSC"
                );
                ?>
            </div>

            <div class="content-card">

                <div class="page-toolbar">
                    <h2>Teams</h2>

                    <a class="action-button" href="team_neu.php">
                        + Add New Team
                    </a>
                </div>


                <?php
                $columns = [
                    "name" => [
                        "label" => "Team",
                        "render" => function ($team) {
                            return '<a href="team_details.php?team_id='
                                . (int)$team["id"] . '">'
                                . htmlspecialchars($team["name"])
                                . '</a>';
                        }
                    ],

                    "nation" => [
                        "label" => "Nation",
                        "sort" => ""
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
                    "No Teams found."
                );

                $res->close();
                $con->close();
                ?>


            </div>

        </main>

    </div>

</body>

</html>
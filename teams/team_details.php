<?php
include __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/query_helpers.php";
require_once __DIR__ . "/../helpers/formation.php";
require_once __DIR__ . "/../helpers/table.php";

$team_id = intval($_GET["team_id"]);

if (!$team_id || $team_id < 1) {
    exit("Invalid Team-ID");
}

// Team laden
$sql = "SELECT * FROM teams WHERE id = ?";
$res = $con->execute_query($sql, [$team_id]);
$team = $res->fetch_assoc();
$res->close();

if (!$team) {
    exit("Team not found");
}

// Spieler laden
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
    "matches" => "matches",
    "goals" => "goals",
    "assists" => "assists",
    "rating" => "rating"
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

$baseSql = "SELECT * FROM players
            WHERE team_id = ? AND 1=1";

[$sql, $params] = buildListQuery(
    $baseSql,
    $filters,
    $sorts,
    $_GET,
    [$team_id],
    "position",
    "ASC"
);

$res = $con->execute_query($sql, $params);

$formationRes = $con->execute_query(
    "SELECT * FROM players WHERE team_id = ?",
    [$team_id]
);

$formationPlayers = $formationRes->fetch_all(MYSQLI_ASSOC);
$formationRes->close();
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Details</title>
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
                        Team overview and current season statistics
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <!-- TEAM INFORMATION -->
            <section class="content-card team-info-card">

                <div class="team-info-header">

                    <h2>Team Information</h2>

                    <div class="team-actions">

                        <button type="button"
                            id="editButton"
                            class="team-edit-button"
                            onclick="editTeam()">
                            Edit
                        </button>
                        <button type="button"
                            id="cancelButton"
                            class="team-cancel-button"
                            onclick="cancelEditTeam()"
                            hidden>
                            Cancel
                        </button>

                        <form action="team_delete.php" method="post"
                            onsubmit="return confirm('Do you really want to delete this team?');">

                            <input type="hidden"
                                name="id"
                                value="<?= $team["id"] ?>">

                            <button type="submit"
                                class="team-delete-button">
                                Delete
                            </button>

                        </form>

                    </div>

                </div>

                <form action="team_update.php" method="post">

                    <input type="hidden"
                        name="id"
                        value="<?= $team["id"] ?>">

                    <div class="team-info-grid" id="teamInfoGrid">

                        <div class="team-info-item">
                            <label for="teamName">Name</label>
                            <input type="text"
                                id="teamName"
                                name="name"
                                value="<?= htmlspecialchars($team["name"]) ?>"
                                maxlength="100"
                                readonly required>
                        </div>

                        <div class="team-info-item">
                            <label for="teamNation">Nation</label>
                            <input type="text"
                                id="teamNation"
                                name="nation"
                                value="<?= htmlspecialchars($team["nation"]) ?>"
                                maxlength="100"
                                readonly required>
                        </div>

                        <div class="team-info-item">
                            <label for="teamMatches">Matches</label>
                            <input type="number"
                                id="teamMatches"
                                name="matches"
                                value="<?= $team["matches"] ?>"
                                min="0"
                                readonly required>
                        </div>

                        <div class="team-info-item">
                            <label for="teamPoints">Points</label>
                            <input type="number"
                                id="teamPoints"
                                name="points"
                                value="<?= $team["points"] ?>"
                                min="0"
                                readonly required>
                        </div>

                    </div>

                    <button type="submit"
                        id="saveButton"
                        class="team-save-button"
                        hidden>
                        Save Changes
                    </button>

                </form>

            </section>

            <!-- PLAYERS -->
            <section class="team-players-section">

                <div class="page-toolbar">
                    <h2>Players</h2>

                    <a href="../players/player_new.php?team_id=<?= $team_id ?>"
                        class="action-button">
                        + Add Player
                    </a>
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

                    <div class="content-card team-players-table">


                        <?php
                        $columns = [


                            "name" => [
                                "label" => "Name",
                                "render" => function ($player) {
                                    return '<a href="../players/player_details.php?player_id='
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

                        echo '<div class="players-scroll">';

                        showTable(
                            $res,
                            $columns,
                            "position",
                            "No Player found."
                        );

                        echo '</div>';

                        $res->close();
                        $con->close();
                        ?>


                    </div>

                    <?php showFormation($formationPlayers); ?>

                </div>

            </section>

        </main>

    </div>

    <script>
        function editTeam() {
            const inputs = document.querySelectorAll(
                '#teamInfoGrid input[readonly]'
            );

            inputs.forEach(input => {
                input.readOnly = false;
            });

            document.getElementById("teamInfoGrid")
                .classList.add("is-editing");

            document.getElementById("saveButton").hidden = false;
            document.getElementById("editButton").hidden = true;
            document.getElementById("cancelButton").hidden = false;
        }

        function cancelEditTeam() {
            const form = document.getElementById("teamInfoGrid")
                .closest("form");

            // Restore original values
            form.reset();

            const inputs = document.querySelectorAll(
                "#teamInfoGrid input"
            );

            inputs.forEach(input => {
                input.readOnly = true;
            });

            document.getElementById("teamInfoGrid")
                .classList.remove("is-editing");

            document.getElementById("saveButton").hidden = true;
            document.getElementById("cancelButton").hidden = true;
            document.getElementById("editButton").hidden = false;
        }
    </script>

</body>

</html>
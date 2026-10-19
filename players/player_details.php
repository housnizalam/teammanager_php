<?php
include __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";

$player_id = $_GET["player_id"] ?? 0;

if (!is_numeric($player_id) || $player_id < 1 || $player_id != (int)$player_id) {
    exit("Invalid player ID");
}

// Spieler und Team laden
$sql = "SELECT players.*, teams.name AS team_name
        FROM players
        JOIN teams ON players.team_id = teams.id
        WHERE players.id = ?";

$res = $con->execute_query($sql, [$player_id]);

$player = $res->fetch_assoc();

if (!$player) {
    exit("Player not found");
}

$res->close();

// Alle Teams alphabetisch laden
$sql = "SELECT id, name FROM teams ORDER BY name ASC";
$res = $con->query($sql);

$teams = [];

while ($dsatz = $res->fetch_assoc()) {
    $teams[] = $dsatz;
}

$res->close();
$con->close();
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner page-player-details">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <!-- PAGE HEADER -->
            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">
                        <?= htmlspecialchars($player["name"]) ?>
                    </h1>

                    <p class="page-subtitle">
                        Player Profile and Current Season Statistics
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <!-- PLAYER INFORMATION -->
            <section class="content-card player-info-section">

                <div class="team-info-header">

                    <h2>Player Information</h2>

                    <div class="team-actions">

                        <button type="button"
                            id="editButton"
                            class="team-edit-button"
                            onclick="editPlayer()">
                            Edit
                        </button>

                        <button type="button"
                            id="cancelButton"
                            class="team-cancel-button"
                            onclick="cancelEditPlayer()"
                            hidden>
                            Cancel
                        </button>

                        <form action="player_delete.php"
                            method="post"
                            onsubmit="return confirm('Do you really want to delete this player?');">

                            <input type="hidden"
                                name="id"
                                value="<?= $player["id"] ?>">

                            <button type="submit"
                                class="team-delete-button">
                                Delete
                            </button>

                        </form>

                    </div>

                </div>

                <!-- UPDATE FORM -->
                <form id="playerUpdateForm"
                    action="player_update.php"
                    method="post">

                    <input type="hidden"
                        name="id"
                        value="<?= $player["id"] ?>">

                    <div class="player-info-grid"
                        id="playerInfoGrid">

                        <!-- NAME -->
                        <div class="player-info-item">
                            <label for="playerName">Name</label>

                            <input type="text"
                                id="playerName"
                                name="name"
                                value="<?= htmlspecialchars($player["name"]) ?>"
                                maxlength="100"
                                readonly required>
                        </div>

                        <!-- AGE -->
                        <div class="player-info-item">
                            <label for="playerAge">Age</label>

                            <input type="number"
                                id="playerAge"
                                name="age"
                                value="<?= $player["age"] ?>"
                                min="1"
                                readonly required>
                        </div>

                        <!-- POSITION -->
                        <div class="player-info-item">
                            <label for="playerPosition">Position</label>

                            <select name="position"
                                id="playerPosition"
                                disabled required>

                                <?php
                                $positions = playerPositions();

                                if (!in_array($player["position"], $positions)) {
                                    echo "<option value='' selected>-- Select a new Position --</option>";
                                }

                                foreach ($positions as $position) {
                                    $selected = "";

                                    if ($player["position"] == $position) {
                                        $selected = "selected";
                                    }

                                    echo "<option value='$position' $selected>$position</option>";
                                }
                                ?>

                            </select>
                        </div>

                        <!-- TEAM -->
                        <div class="player-info-item">

                            <label for="teamSelect">Team</label>

                            <a id="teamLink"
                                class="player-team-link"
                                href="../teams/team_details.php?team_id=<?= $player["team_id"] ?>">
                                <?= htmlspecialchars($player["team_name"]) ?>
                            </a>

                            <select name="team_id"
                                id="teamSelect"
                                hidden disabled>

                                <?php
                                foreach ($teams as $team) {
                                    $selected = "";

                                    if ($team["id"] == $player["team_id"]) {
                                        $selected = "selected";
                                    }

                                    echo "<option value='" . $team["id"] . "' $selected>"
                                        . htmlspecialchars($team["name"])
                                        . "</option>";
                                }
                                ?>

                            </select>

                        </div>

                    </div>

                    <!-- STATISTICS -->
                    <div class="player-section-divider"></div>

                    <h2>Player Statistics</h2>

                    <div class="player-info-grid"
                        id="playerStatsGrid">

                        <!-- MATCHES -->
                        <div class="player-info-item">
                            <label for="playerMatches">Player</label>

                            <input type="number"
                                id="playerMatches"
                                name="matches"
                                value="<?= $player["matches"] ?>"
                                min="0"
                                readonly required>
                        </div>

                        <!-- GOALS -->
                        <div class="player-info-item">
                            <label for="playerGoals">Goals</label>

                            <input type="number"
                                id="playerGoals"
                                name="goals"
                                value="<?= $player["goals"] ?>"
                                min="0"
                                readonly required>
                        </div>

                        <!-- ASSISTS -->
                        <div class="player-info-item">
                            <label for="playerAssists">Assists</label>

                            <input type="number"
                                id="playerAssists"
                                name="assists"
                                value="<?= $player["assists"] ?>"
                                min="0"
                                readonly required>
                        </div>

                        <!-- RATING -->
                        <div class="player-info-item">
                            <label for="playerRating">Rating</label>

                            <input type="number"
                                id="playerRating"
                                name="rating"
                                value="<?= $player["rating"] ?>"
                                min="0"
                                max="10"
                                step="0.1"
                                readonly required>
                        </div>

                    </div>

                    <div class="player-edit-actions">

                        <button type="submit"
                            id="saveButton"
                            class="team-save-button"
                            hidden>
                            Save Changes
                        </button>

                    </div>

                </form>

            </section>

        </main>

    </div>

    <script>
        function editPlayer() {

            const form = document.getElementById("playerUpdateForm");

            const inputs = form.querySelectorAll("input[readonly]");

            inputs.forEach(input => {
                input.readOnly = false;
            });

            document.getElementById("playerPosition").disabled = false;

            document.getElementById("teamLink").hidden = true;

            document.getElementById("teamSelect").hidden = false;
            document.getElementById("teamSelect").disabled = false;

            document.getElementById("playerInfoGrid")
                .classList.add("is-editing");

            document.getElementById("playerStatsGrid")
                .classList.add("is-editing");

            document.getElementById("editButton").hidden = true;
            document.getElementById("cancelButton").hidden = false;
            document.getElementById("saveButton").hidden = false;
        }

        function cancelEditPlayer() {

            const form = document.getElementById("playerUpdateForm");

            // Restore original values
            form.reset();

            const inputs = form.querySelectorAll(
                "input:not([type='hidden'])"
            );

            inputs.forEach(input => {
                input.readOnly = true;
            });

            document.getElementById("playerPosition").disabled = true;

            document.getElementById("teamSelect").disabled = true;
            document.getElementById("teamSelect").hidden = true;

            document.getElementById("teamLink").hidden = false;

            document.getElementById("playerInfoGrid")
                .classList.remove("is-editing");

            document.getElementById("playerStatsGrid")
                .classList.remove("is-editing");

            document.getElementById("saveButton").hidden = true;
            document.getElementById("cancelButton").hidden = true;
            document.getElementById("editButton").hidden = false;
        }
    </script>

</body>

</html>
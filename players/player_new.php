
<?php
include __DIR__ . "/../helpers/db.php";

$team_id = $_GET["team_id"] ?? 0;

if (!ctype_digit((string)$team_id) || (int)$team_id < 1) {
    exit("Invalid team ID");
}

// Team suchen
$sql = "SELECT name FROM teams WHERE id = ?";
$res = $con->execute_query($sql, [$team_id]);
$team = $res->fetch_assoc();

if (!$team) {
    exit("Team not found");
}

$res->close();
$con->close();
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Player</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">Add New Player</h1>

                    <p class="page-subtitle">
                        Add Plaer to
                        <?= htmlspecialchars($team["name"]) ?>
                        .
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <div class="form-page-container">

                <div class="form-card">

                    <h2>
                        Team:
                        <?= htmlspecialchars($team["name"]) ?>
                    </h2>

                    <p class="form-description">
                        Enter the new player's details.
                    </p>

                    <form action="player_insert.php" method="post">

                        <input
                            type="hidden"
                            name="team_id"
                            value="<?= $team_id ?>"
                        >

                        <div class="form-group">
                            <label for="name">Name:</label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="age">Age:</label>

                            <input
                                type="number"
                                id="age"
                                name="age"
                                min="1"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="position">Position:</label>

                            <select
                                id="position"
                                name="position"
                                required
                            >
                                <option value="">
                                    -- Position wählen --
                                </option>
                                <option value="GK">GK</option>
                                <option value="CB">CB</option>
                                <option value="RB">RB</option>
                                <option value="LB">LB</option>
                                <option value="CDM">CDM</option>
                                <option value="CM">CM</option>
                                <option value="CAM">CAM</option>
                                <option value="RW">RW</option>
                                <option value="LW">LW</option>
                                <option value="ST">ST</option>
                            </select>
                        </div>

                        <div class="form-actions">

                            <input
                                type="submit"
                                value="Add Player"
                                class="form-submit"
                            >

                            <input
                                type="reset"
                                value="Reset"
                                class="form-reset"
                            >

                        </div>

                    </form>

                </div>

            </div>

        </main>

    </div>

</body>

</html>

<?php
include __DIR__ . "/../helpers/db.php";

$player_id = $_GET["player_id"] ?? 0;

if (!ctype_digit((string)$player_id) || (int)$player_id < 1) {
    exit("Invalid player ID");
}

// Spieler aus dem Archiv laden
$sql = "SELECT * FROM players_archive WHERE player_id = ?";
$res = $con->execute_query($sql, [$player_id]);

$player = $res->fetch_assoc();

if (!$player) {
    exit("Player not found in archive");
}

$res->close();

// ============================================
// PLAYER ARCHIVE - Individual Honours
// ============================================

// Count awards from completed tournaments only.
// The player_id remains the same across seasons.

$awardsSql = "
    SELECT
        COUNT(CASE
            WHEN best_player_id = ? THEN 1
        END) AS best_player_count,

        COUNT(CASE
            WHEN top_scorer_id = ? THEN 1
        END) AS top_scorer_count,

        COUNT(CASE
            WHEN top_assister_id = ? THEN 1
        END) AS top_assister_count

    FROM tournaments
    WHERE is_played = 1
";

$awardsResult = $con->execute_query(
    $awardsSql,
    [
        (int)$player_id,
        (int)$player_id,
        (int)$player_id
    ]
);

$playerAwards = $awardsResult->fetch_assoc();
$awardsResult->close();

$con->close();
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Archive Details</title>
    <link rel="stylesheet"
        href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
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
                        Historical player profile and career statistics
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <!-- ARCHIVED PLAYER INFORMATION -->
            <section class="content-card player-info-section">

                <h2>Player Archive Information</h2>

                <div class="player-info-grid">

                    <!-- NAME -->
                    <div class="player-info-item">
                        <label>Name</label>
                        <div class="archive-info-value">
                            <?= htmlspecialchars($player["name"]) ?>
                        </div>
                    </div>

                    <!-- AGE -->
                    <div class="player-info-item">
                        <label>Age</label>
                        <div class="archive-info-value">
                            <?= $player["age"] ?>
                        </div>
                    </div>

                    <!-- POSITION -->
                    <div class="player-info-item">
                        <label>Position</label>
                        <div class="archive-info-value">
                            <?= htmlspecialchars($player["position"]) ?>
                        </div>
                    </div>

                    <!-- LAST TEAM -->
                    <div class="player-info-item">
                        <label>Last Team</label>
                        <div class="archive-info-value">
                            <?= htmlspecialchars($player["team_name"]) ?>
                        </div>
                    </div>

                    <!-- SEASONS -->
                    <div class="player-info-item">
                        <label>Seasons</label>
                        <div class="archive-info-value">
                            <?= $player["seasons"] ?>
                        </div>
                    </div>

                </div>

                <!-- VISUAL SEPARATOR -->
                <div class="player-section-divider"></div>

                <!-- CAREER STATISTICS -->
                <h2>Career Statistics</h2>

                <div class="player-info-grid">

                    <div class="player-info-item">
                        <label>Seasons</label>
                        <div class="archive-info-value">
                            <?= $player["seasons"] ?>
                        </div>
                    </div>

                    <div class="player-info-item">
                        <label>Player</label>
                        <div class="archive-info-value">
                            <?= $player["matches"] ?>
                        </div>
                    </div>

                    <div class="player-info-item">
                        <label>Gaols</label>
                        <div class="archive-info-value">
                            <?= $player["goals"] ?>
                        </div>
                    </div>

                    <div class="player-info-item">
                        <label>Assists</label>
                        <div class="archive-info-value">
                            <?= $player["assists"] ?>
                        </div>
                    </div>

                    <div class="player-info-item">
                        <label>Rating</label>
                        <div class="archive-info-value">
                            <?= $player["rating"] ?>
                        </div>
                    </div>

                </div>

                <!-- VISUAL SEPARATOR -->
                <div class="player-section-divider"></div>

                <!-- AVERAGE PER SEASON -->
                <h2>Average per Season</h2>

                <?php
                if ($player["seasons"] > 0) {
                    $avg_matches = round(
                        $player["matches"] / $player["seasons"],
                        1
                    );

                    $avg_goals = round(
                        $player["goals"] / $player["seasons"],
                        1
                    );

                    $avg_assists = round(
                        $player["assists"] / $player["seasons"],
                        1
                    );
                ?>

                    <div class="player-info-grid">

                        <div class="player-info-item">
                            <label>Matches per Season</label>
                            <div class="archive-info-value">
                                <?= $avg_matches ?>
                            </div>
                        </div>

                        <div class="player-info-item">
                            <label>Goal per Season</label>
                            <div class="archive-info-value">
                                <?= $avg_goals ?>
                            </div>
                        </div>

                        <div class="player-info-item">
                            <label>Assists per Season</label>
                            <div class="archive-info-value">
                                <?= $avg_assists ?>
                            </div>
                        </div>

                    </div>

                <?php
                }
                ?>

                <!-- ============================================
                PLAYER ARCHIVE - Individual Honours
                ============================================ -->

                <div class="player-section-divider"></div>

                <h2>Individual Honours</h2>

                <div class="player-info-grid player-honours-grid">

                    <!-- BEST PLAYER -->
                    <div class="player-info-item player-honour-card">
                        <label>Best Player</label>

                        <div class="archive-info-value player-honour-count">
                            <?= (int)$playerAwards['best_player_count'] ?>
                        </div>
                    </div>

                    <!-- TOP SCORER -->
                    <div class="player-info-item player-honour-card">
                        <label>Top Scorer</label>

                        <div class="archive-info-value player-honour-count">
                            <?= (int)$playerAwards['top_scorer_count'] ?>
                        </div>
                    </div>

                    <!-- TOP ASSISTER -->
                    <div class="player-info-item player-honour-card">
                        <label>Top Assister</label>

                        <div class="archive-info-value player-honour-count">
                            <?= (int)$playerAwards['top_assister_count'] ?>
                        </div>
                    </div>

                </div>

            </section>

        </main>

    </div>

</body>

</html>
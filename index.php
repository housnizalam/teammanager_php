<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Team Manager</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="page-home">

    <div class="app-shell">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . "/helpers/sidebar.php"; ?>

        <!-- MAIN CONTENT -->
        <main class="app-main">

            <h1>Football Team Manager</h1>

            <h2>Main Menu</h2>

            <div class="app-panel">
                <p>
                    Manage your teams, track player statistics,
                    and build football history.
                </p>

                <a class="app-button" href="teams/teams.php">
                    View Teams
                </a>

                <a class="app-button" href="players/players.php">
                    View Players
                </a>
            </div>

            <!-- TOURNAMENTS SECTION -->
            <h2>Tournaments</h2>

            <div class="app-panel">

                <h3>Available Tournaments</h3>

                <p>
                    Manage league standings, European competitions,
                    and tournament results.
                </p>

                <!-- TOURNAMENTS NAVIGATION -->
                <a href="tournaments/leagues.php" class="app-button">
                    Leagues
                </a>

                <a href="tournaments/champions.php" class="app-button">
                    Champions League
                </a>

                <a href="tournaments/europa.php" class="app-button">
                    Europa League
                </a>

            </div>

            <h2>Season Management</h2>

            <form action="season/end_season.php" method="post"
                onsubmit="return confirm('Are you sure you want to end the season? All current statistics will be archived and reset.');">

                <button type="submit" name="end_season" value="yes">
                    End Season
                </button>

            </form>

            <div class="app-panel">

                <h2>Archive</h2>

                <p>
                    <a href="archive/teams_archive.php">
                        View Teams Archive
                    </a>
                </p>

                <p>
                    <a href="archive/players_archive.php">
                        View Players Archive
                    </a>
                </p>

            </div>

        </main>

    </div>

</body>

</html>
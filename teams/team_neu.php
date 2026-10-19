
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Team</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="page-inner">

    <div class="app-shell">

        <?php require_once __DIR__ . "/../helpers/sidebar.php"; ?>

        <main class="app-main">

            <div class="page-header-bar">

                <div>
                    <h1 class="page-title">New Team</h1>
                    <p class="page-subtitle">
                        Add a new team to the current season.
                    </p>
                </div>

                <?php require_once __DIR__ . "/../helpers/back_button.php"; ?>

            </div>

            <div class="form-page-container">

                <div class="form-card">

                    <h2>Add team</h2>

                    <p class="form-description">
                        Enter the name and country of the new team.
                    </p>

                    <form action="team_neu_insert.php" method="post">

                        <div class="form-group">
                            <label for="name">Team name:</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="nation">Country:</label>
                            <input
                                type="text"
                                id="nation"
                                name="nation"
                                required
                            >
                        </div>

                        <div class="form-actions">

                            <input
                                type="submit"
                                value="Save Team"
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


<?php
include __DIR__ . "/../helpers/db.php";

$competition = $_POST["competition"] ?? "";

if ($competition != "league" && $competition != "champions") {
    exit("Ungültiger Wettbewerb");
}

try {
    $con->begin_transaction();

    // Alle Teams laden
    $sql = "SELECT * FROM teams";
    $teams = $con->query($sql);

    while ($team = $teams->fetch_assoc()) {

        $team_id = $team["id"];

        // Spiele und Punkte erzeugen
        if ($competition == "league") {
            $team_matches = 38;
            $team_points = rand(45, 95);
        } else {
            $team_matches = rand(8, 16);
            $team_points = rand(
                $team_matches,
                $team_matches * 2
            );
        }

        // Team-Statistiken addieren
        $sql = "UPDATE teams
                SET matches = matches + ?,
                    points = points + ?
                WHERE id = ?";

        $con->execute_query($sql, [
            $team_matches,
            $team_points,
            $team_id
        ]);

        // Spieler des Teams laden
        $sql = "SELECT * FROM players WHERE team_id = ?";
        $res = $con->execute_query($sql, [$team_id]);

        $players = [];

        while ($player = $res->fetch_assoc()) {
            $players[] = $player;
        }

        $res->close();

        // Spieler zufällig mischen
        shuffle($players);

        $nummer = 0;

        foreach ($players as $player) {

            $nummer++;

            // Nur 15 Spieler berücksichtigen
            if ($nummer > 15) {
                break;
            }

            // Erste 5 Spieler: Stammspieler
            if ($nummer <= 5) {
                $matches = rand(
                    (int)($team_matches * 0.8),
                    $team_matches
                );
            } else {
                // Andere Spieler: weniger Einsätze
                $matches = rand(
                    1,
                    (int)($team_matches * 0.7)
                );
            }

            // Tore und Assists je nach Position
            $goals = 0;
            $assists = 0;

            $position = $player["position"];

            if (
                $position == "ST" ||
                $position == "RW" ||
                $position == "LW"
            ) {
                $goals = rand(0, (int)($matches * 0.7));
                $assists = rand(0, (int)($matches * 0.35));
            } elseif ($position == "CAM") {
                $goals = rand(0, (int)($matches * 0.4));
                $assists = rand(0, (int)($matches * 0.5));
            } elseif (
                $position == "CM" ||
                $position == "CDM"
            ) {
                $goals = rand(0, (int)($matches * 0.2));
                $assists = rand(0, (int)($matches * 0.3));
            } elseif (
                $position == "CB" ||
                $position == "RB" ||
                $position == "LB"
            ) {
                $goals = rand(0, (int)($matches * 0.1));
                $assists = rand(0, (int)($matches * 0.15));
            }

            // GK: 0 Tore und 0 Assists


            // Bewertung zwischen 6.0 und 9.0
            $rating = rand(60, 90) / 10;

            // Alten Durchschnitt berücksichtigen
            $old_matches = $player["matches"];
            $old_rating = $player["rating"];

            $total_matches = $old_matches + $matches;

            $new_rating = round(
                (
                    $old_rating * $old_matches +
                    $rating * $matches
                ) / $total_matches,
                1
            );

            // Spieler-Statistiken addieren
            $sql = "UPDATE players
                    SET matches = matches + ?,
                        goals = goals + ?,
                        assists = assists + ?,
                        rating = ?
                    WHERE id = ?";

            $con->execute_query($sql, [
                $matches,
                $goals,
                $assists,
                $new_rating,
                $player["id"]
            ]);
        }
    }

    $teams->close();

    $con->commit();
    $con->close();

    header("Location: ../teams/teams.php");
    exit;
} catch (Throwable $e) {
    $con->rollback();
    exit("Error: " . $e->getMessage());
}

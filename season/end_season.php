
<?php
include __DIR__ . "/../helpers/db.php";

if (($_POST["end_season"] ?? "") != "yes") {
    exit("End Season was not confirmed.");
}

try {
    $con->begin_transaction();

    // ==================================
    // 1. Spieler archivieren
    // ==================================

    $sql = "SELECT players.*, teams.name AS team_name
            FROM players
            JOIN teams ON players.team_id = teams.id
            WHERE players.matches > 0";

    $players = $con->query($sql);

    while ($player = $players->fetch_assoc()) {

        $id = $player["id"];

        // Prüfen, ob Spieler im Archiv existiert
        $sql = "SELECT * FROM players_archive WHERE player_id = ?";
        $res = $con->execute_query($sql, [$id]);
        $archive = $res->fetch_assoc();
        $res->close();

        if ($archive) {

            // Alte und neue Statistiken addieren
            $seasons = $archive["seasons"] + 1;
            $matches = $archive["matches"] + $player["matches"];
            $goals = $archive["goals"] + $player["goals"];
            $assists = $archive["assists"] + $player["assists"];

            // Durchschnitt nach Saisonanzahl
            $rating = (
                $archive["rating"] * $archive["seasons"]
                + $player["rating"]
            ) / $seasons;

            $sql = "UPDATE players_archive
                    SET name = ?, age = ?, position = ?,
                        team_name = ?, seasons = ?,
                        matches = ?, goals = ?, assists = ?,
                        rating = ?
                    WHERE player_id = ?";

            $con->execute_query($sql, [
                $player["name"],
                $player["age"],
                $player["position"],
                $player["team_name"],
                $seasons,
                $matches,
                $goals,
                $assists,
                $rating,
                $id
            ]);
        } else {

            // Spieler zum ersten Mal archivieren
            $sql = "INSERT INTO players_archive
                    (player_id, name, age, position, team_name,
                     seasons, matches, goals, assists, rating)
                    VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?)";

            $con->execute_query($sql, [
                $id,
                $player["name"],
                $player["age"],
                $player["position"],
                $player["team_name"],
                $player["matches"],
                $player["goals"],
                $player["assists"],
                $player["rating"]
            ]);
        }
    }

    $players->close();

    // ==================================
    // 2. Teams archivieren
    // ==================================

    $sql = "SELECT * FROM teams WHERE matches > 0";
    $teams = $con->query($sql);

    while ($team = $teams->fetch_assoc()) {

        $id = $team["id"];

        $sql = "SELECT * FROM teams_archive WHERE team_id = ?";
        $res = $con->execute_query($sql, [$id]);
        $archive = $res->fetch_assoc();
        $res->close();

        if ($archive) {

            $sql = "UPDATE teams_archive
                    SET name = ?, nation = ?,
                        seasons = seasons + 1,
                        matches = matches + ?,
                        points = points + ?
                    WHERE team_id = ?";

            $con->execute_query($sql, [
                $team["name"],
                $team["nation"],
                $team["matches"],
                $team["points"],
                $id
            ]);
        } else {

            $sql = "INSERT INTO teams_archive
                    (team_id, name, nation, seasons, matches, points)
                    VALUES (?, ?, ?, 1, ?, ?)";

            $con->execute_query($sql, [
                $id,
                $team["name"],
                $team["nation"],
                $team["matches"],
                $team["points"]
            ]);
        }
    }

    $teams->close();

    // ==================================
    // 3. Aktuelle Statistiken zurücksetzen
    // ==================================

    $con->query(
        "UPDATE players
         SET matches = 0, goals = 0,
             assists = 0, rating = 0"
    );

    $con->query(
        "UPDATE teams
         SET matches = 0, points = 0"
    );

    // Alle Änderungen speichern
    $con->commit();
    $con->close();

    header("Location: ../index.php");
    exit;
} catch (Throwable $e) {

    // Bei Fehler alles rückgängig machen
    $con->rollback();

    exit("Error while ending the season: " . $e->getMessage());
}

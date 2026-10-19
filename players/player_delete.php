
<?php
include __DIR__ . "/../helpers/db.php";

$id = $_POST["id"] ?? 0;

// ID prüfen
if (!ctype_digit((string)$id) || (int)$id < 1) {
    exit("Invalid player ID");
}

$sql = "SELECT team_id FROM players WHERE id = ?";
$res = $con->execute_query($sql, [$id]);

$player = $res->fetch_assoc();

if (!$player) {
    exit("Player not found");
}

$team_id = $player["team_id"];
$res->close();

// Spieler löschen
$sql = "DELETE FROM players WHERE id = ?";
$con->execute_query($sql, [$id]);

$con->close();

// Zurück zum Team
header("Location: ../teams/team_details.php?team_id=" . $team_id);
exit;

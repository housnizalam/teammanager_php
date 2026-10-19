
<?php
include __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";

$team_id = $_POST["team_id"] ?? 0;
$name = trim($_POST["name"] ?? "");
$age = $_POST["age"] ?? 0;
$position = $_POST["position"] ?? "";

// Zahlen prüfen
if (
    !ctype_digit((string)$team_id) || (int)$team_id < 1 ||
    !ctype_digit((string)$age) || (int)$age < 1
) {
    exit("Invalid Numbers");
}

// Position prüfen
$positions = playerPositions();

if (!in_array($position, $positions)) {
    exit("Invalid Position");
}

// Prüfen, ob das Team existiert
$sql = "SELECT id FROM teams WHERE id = ?";
$res = $con->execute_query($sql, [$team_id]);

if (!$res->fetch_assoc()) {
    exit("Team not found");
}

$res->close();

// Spieler hinzufügen
$sql = "INSERT INTO players
        (name, age, position, matches, goals, assists, rating, team_id)
        VALUES (?, ?, ?, 0, 0, 0, 0, ?)";

$con->execute_query($sql, [
    $name,
    $age,
    $position,
    $team_id
]);

$con->close();

// Zurück zur Teamseite
header("Location: ../teams/team_details.php?team_id=" . $team_id);
exit;

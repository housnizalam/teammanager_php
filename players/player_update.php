
<?php
include __DIR__ . "/../helpers/db.php";
require_once __DIR__ . "/../helpers/functions.php";

$id = $_POST["id"] ?? 0;
$name = trim($_POST["name"] ?? "");
$age = $_POST["age"] ?? 0;
$position = $_POST["position"] ?? "";
$matches = $_POST["matches"] ?? "";
$goals = $_POST["goals"] ?? "";
$assists = $_POST["assists"] ?? "";
$rating = $_POST["rating"] ?? "";
$team_id = $_POST["team_id"] ?? 0;

// Zahlen prüfen
if (
    !ctype_digit((string)$id) || (int)$id < 1 ||
    !ctype_digit((string)$age) || (int)$age < 1 ||
    !ctype_digit((string)$matches) ||
    !ctype_digit((string)$goals) ||
    !ctype_digit((string)$assists) ||
    !is_numeric($rating) || $rating < 0 || $rating > 10
) {
    exit("Invalid Zahlen");
}


$positions = playerPositions();

if (!in_array($position, $positions)) {
    exit("Invalid Position");
}

// Team-ID prüfen
if (!ctype_digit((string)$team_id) || (int)$team_id < 1) {
    exit("Invalid team ID");
}

// Prüfen, ob das Team existiert
$sql = "SELECT id FROM teams WHERE id = ?";
$res = $con->execute_query($sql, [$team_id]);

if (!$res->fetch_assoc()) {
    exit("Team not found");
}

$res->close();

// Spieler aktualisieren
$sql = "UPDATE players
        SET name = ?, age = ?, position = ?,
            matches = ?, goals = ?, assists = ?, rating = ?,
            team_id = ?
        WHERE id = ?";

$con->execute_query($sql, [
    $name,
    $age,
    $position,
    $matches,
    $goals,
    $assists,
    $rating,
    $team_id,
    $id
]);

$con->close();

header("Location: player_details.php?player_id=" . $id);
exit;

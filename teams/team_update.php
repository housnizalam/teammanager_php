
<?php

include __DIR__ . "/../helpers/db.php";

$id = $_POST["id"] ?? 0;
$name = $_POST["name"] ?? "";
$nation = $_POST["nation"] ?? "";
$matches = $_POST["matches"] ?? "";
$points = $_POST["points"] ?? "";

if (
    !is_numeric($id) || $id < 1 ||
    !is_numeric($matches) || $matches < 0 ||
    !is_numeric($points) || $points < 0
) {
    exit("Invalid numbers");
}


if (
    $matches === false || $matches == null || $matches < 0 ||
    $points === false || $points == null || $points < 0
) {
    exit("Invalid matches or points.");
}

// Team aktualisieren
$sql = "UPDATE teams
        SET name = ?, nation = ?, matches = ?, points = ?
        WHERE id = ?";

$con->execute_query($sql, [
    $name,
    $nation,
    $matches,
    $points,
    $id
]);

$con->close();

// Zurück zur Team-Detailseite
header("Location: team_details.php?team_id=" . $id);
exit;

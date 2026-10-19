
<?php
include __DIR__ . "/../helpers/db.php";

$id = $_POST["id"] ?? 0;

if (!is_numeric($id) || $id < 1 || $id != (int)$id) {
    exit("Invalid Team-ID");
}

// Prüfen, ob das Team Spieler hat
$sql = "SELECT COUNT(*) AS anzahl FROM players WHERE team_id = ?";
$res = $con->execute_query($sql, [$id]);
$dsatz = $res->fetch_assoc();

if ($dsatz["anzahl"] > 0) {
    exit("This team cannot be deleted because it still has players.");
}

// Team löschen
$sql = "DELETE FROM teams WHERE id = ?";
$con->execute_query($sql, [$id]);

$con->close();

header("Location: teams.php");
exit;
?>
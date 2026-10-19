
<?php
include __DIR__ . "/../helpers/db.php";

$name = $_POST["name"] ?? "";
$nation = $_POST["nation"] ?? "";

if ($name == "" || $nation == "") {
    exit("Please fill in all fields.");
}

$sql = "INSERT INTO teams (name, nation) VALUES (?, ?)";

$con->execute_query($sql, [$name, $nation]);

$con->close();

header("Location: teams.php");
exit;

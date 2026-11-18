
<?php
$con = new mysqli("localhost", "root", "", "teammanager");

if ($con->connect_error) {
    exit("Connection error:: " . $con->connect_error);
}

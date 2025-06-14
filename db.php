<?php
$host = "localhost";
$username = "uxgukysg8xcbd";
$password = "6imcip8yfmic";
$dbname = "db1xzatfepfifr";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>

<?php
// db_connection.php

// Database connection parameters
$servername = "localhost";
$dbUsername = "examportalsql";  // Promijenite ako treba
$dbPassword = '1dBL$oV+e?RD';   // Promijenite ako treba
$dbname     = "zavrsni2024";          // Promijenite prema vašoj bazi

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbUsername, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Greška s bazom: " . $e->getMessage());
}
?>

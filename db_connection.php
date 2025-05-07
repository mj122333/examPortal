<?php
// db_connection.php

// Database connection parameters
$servername = "localhost";
$dbUsername = "";  // Promijenite ako treba
$dbPassword = '';   // Promijenite ako treba
$dbname     = "";          // Promijenite prema vašoj bazi

// Razine korisnika:
// 1 = Profesor/Admin
// 2 = Student
// 3 = Gost (ne spremlja se u bazi)

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbUsername, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Greška s bazom: " . $e->getMessage());
}
?>

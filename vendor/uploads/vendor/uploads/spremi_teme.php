<?php
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

// Check if user is logged in and topics are selected
if (!isset($_SESSION['user_id']) || empty($_POST['teme'])) {
    header('Location: odabir_teme.php');
    exit();
}

$userId = $_SESSION['user_id'];
$teme = $_POST['teme'];

// Save selected topics
$stmt = $conn->prepare("INSERT IGNORE INTO ep_korisnik_teme (korisnik_id, tema_id) VALUES (?, ?)");
foreach ($teme as $temaId) {
    $stmt->execute([$userId, $temaId]);
}

// After saving, redirect as needed (here back to login page)
header('Location: login.php');
exit();
?>

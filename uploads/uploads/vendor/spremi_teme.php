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

try {
    // Prvo provjeri postoji li korisnik
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ep_korisnik WHERE ID = ?");
    $stmt->execute([$userId]);
    $korisnikPostoji = $stmt->fetchColumn() > 0;

    if (!$korisnikPostoji) {
        error_log("Pokušaj dodavanja tema za nepostojećeg korisnika ID: {$userId}");
        header('Location: odabir_teme.php?error=invalid_user');
        exit();
    }

    // Započni transakciju
    $conn->beginTransaction();

    foreach ($teme as $temaId) {
        // Provjeri postoji li tema
        $checkTema = $conn->prepare("SELECT COUNT(*) FROM ep_teme WHERE ID = ?");
        $checkTema->execute([$temaId]);
        $temaPostoji = $checkTema->fetchColumn() > 0;

        if ($temaPostoji) {
            // Provjeri postoji li već veza
            $checkVeza = $conn->prepare("SELECT COUNT(*) FROM ep_korisnik_teme WHERE korisnik_id = ? AND tema_id = ?");
            $checkVeza->execute([$userId, $temaId]);
            $vezaPostoji = $checkVeza->fetchColumn() > 0;

            if (!$vezaPostoji) {
                // Dodaj novu vezu samo ako ne postoji
                $stmt = $conn->prepare("INSERT INTO ep_korisnik_teme (korisnik_id, tema_id) VALUES (?, ?)");
                if (!$stmt->execute([$userId, $temaId])) {
                    // Ako dođe do greške, rollback transakciju
                    $conn->rollBack();
                    error_log("Greška pri dodavanju veze korisnik-tema: " . implode(", ", $stmt->errorInfo()));
                    header('Location: odabir_teme.php?error=db_error');
                    exit();
                }
            }
        } else {
            // Ako tema ne postoji, rollback transakciju
            $conn->rollBack();
            error_log("Pokušaj dodavanja nepostojeće teme ID: {$temaId}");
            header('Location: odabir_teme.php?error=invalid_tema');
            exit();
        }
    }

    // Ako je sve prošlo uspješno, commit transakciju
    $conn->commit();
    
    // After saving, redirect as needed
    header('Location: login.php');
    exit();
} catch (PDOException $e) {
    // U slučaju bilo kakve greške, rollback transakciju
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("PDO greška pri spremanju tema: " . $e->getMessage());
    header('Location: odabir_teme.php?error=db_error');
    exit();
}
?>

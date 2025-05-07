<?php
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

// Ako je potrebno, ovdje možete zapisivati podatke o odjavi korisnika u bazu
// Na primjer, možete zapisivati logove o odjavama, ako je potrebno

try {
    // Ako želite pohraniti informaciju o odjavi u bazi (npr. zapisivanje loga)
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        // Na primjer, dodajemo zapis o odjavi korisnika u tablicu logova
        $stmt = $conn->prepare("INSERT INTO user_logout_log (user_id, logout_time) VALUES (:user_id, NOW())");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    }
} catch (PDOException $e) {
    // Ako dođe do greške pri unosu u bazu, prikazujemo poruku (nije kritično za odjavu)
    error_log("Greška pri pohranjivanju odjave u bazu: " . $e->getMessage());
}

// Uništavanje sesije i preusmjeravanje korisnika na login stranicu
session_destroy();
header("Location: login.php");
exit();
?>

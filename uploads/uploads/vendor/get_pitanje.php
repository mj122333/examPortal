<?php
session_start();

// Provjera je li korisnik prijavljen i ima li razinu profesora
if (!isset($_SESSION['user_id']) || $_SESSION['razina'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Neovlašteni pristup']);
    exit();
}

// Uključivanje baze podataka
require_once 'db_connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nedostaje ID pitanja']);
    exit();
}

$id = $_GET['id'];

try {
    // Dohvati podatke o pitanju
    $stmt = $conn->prepare("SELECT * FROM ep_pitanje WHERE ID = ?");
    $stmt->execute([$id]);
    $pitanje = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pitanje) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Pitanje nije pronađeno']);
        exit();
    }
    
    // Dohvati odgovore za pitanje
    $stmt = $conn->prepare("SELECT * FROM ep_odgovori WHERE pitanjeID = ? ORDER BY ID");
    $stmt->execute([$id]);
    $odgovori = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pitanje' => $pitanje,
        'odgovori' => $odgovori
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Greška: ' . $e->getMessage()]);
}
?> 
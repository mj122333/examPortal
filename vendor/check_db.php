<?php
// Postavljanje zaglavlja za čitljiviji ispis
header('Content-Type: text/html; charset=utf-8');

// Uključi display errors za razvoj
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Koristi db_connection.php za povezivanje
require_once 'db_connection.php';

echo "<pre style='background: #333; color: #fff; padding: 15px; font-family: monospace;'>";
echo "=== PROVJERA BAZE PODATAKA ===\n\n";

// Provjeri postojanje tablice ep_test_odgovori
try {
    $check = $conn->query("SHOW TABLES LIKE 'ep_test_odgovori'");
    $tableExists = $check->rowCount() > 0;
    
    echo "Tablica ep_test_odgovori postoji: " . ($tableExists ? 'DA' : 'NE') . "\n\n";
    
    if ($tableExists) {
        // Prikaži strukturu tablice
        echo "=== STRUKTURA TABLICE ===\n";
        $stmt = $conn->query("DESCRIBE ep_test_odgovori");
        echo "| STUPAC | TIP | NULL | KEY | DEFAULT | EXTRA |\n";
        echo "|--------|-----|------|-----|---------|-------|\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "| {$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']} |\n";
        }
        
        echo "\n=== PREGLED PODATAKA ===\n";
        
        // Provjeri broj redova u tablici
        $rowCount = $conn->query("SELECT COUNT(*) FROM ep_test_odgovori")->fetchColumn();
        echo "Broj zapisa u tablici: $rowCount\n\n";
        
        if ($rowCount > 0) {
            // Prikaži nekoliko primjera podataka
            echo "Primjeri podataka (zadnjih 5 unosa):\n";
            $data = $conn->query("SELECT * FROM ep_test_odgovori ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $row) {
                echo "---------------------------------------\n";
                echo "ID: {$row['id']}\n";
                echo "Test ID: {$row['test_id']}\n";
                echo "Pitanje: " . substr($row['question_text'], 0, 70) . (strlen($row['question_text']) > 70 ? "..." : "") . "\n";
                echo "Korisnikov odgovor: " . substr($row['user_answer_text'], 0, 70) . (strlen($row['user_answer_text']) > 70 ? "..." : "") . "\n";
                echo "Točan odgovor: " . substr($row['correct_answer_text'], 0, 70) . (strlen($row['correct_answer_text']) > 70 ? "..." : "") . "\n";
                echo "Točno: " . ($row['is_correct'] ? 'DA' : 'NE') . "\n";
            }
        }
    }
    
    // Provjeri poveznicu na tablicu ep_test
    echo "\n=== POVEZNICA S TABLICOM ep_test ===\n";
    $testTable = $conn->query("SHOW TABLES LIKE 'ep_test'")->rowCount() > 0;
    echo "Tablica ep_test postoji: " . ($testTable ? 'DA' : 'NE') . "\n";
    
    if ($testTable) {
        $testCount = $conn->query("SELECT COUNT(*) FROM ep_test")->fetchColumn();
        echo "Broj testova u tablici ep_test: $testCount\n";
        
        if ($testCount > 0 && $tableExists) {
            // Provjeri poveznicu između tablica
            $linkedRowsCount = $conn->query("
                SELECT COUNT(*) FROM ep_test_odgovori o 
                INNER JOIN ep_test t ON o.test_id = t.ID
            ")->fetchColumn();
            
            echo "Broj povezanih zapisa: $linkedRowsCount\n";
            
            // Provjeri ima li zapisa bez veze
            $orphanedRowsCount = $conn->query("
                SELECT COUNT(*) FROM ep_test_odgovori o 
                LEFT JOIN ep_test t ON o.test_id = t.ID
                WHERE t.ID IS NULL
            ")->fetchColumn();
            
            echo "Broj nepovezanih zapisa: $orphanedRowsCount\n";
        }
    }
    
    // Provjeri kolumne koje se koriste za unos u forms.php
    echo "\n=== SQL UPIT IZ forms.php ===\n";
    echo "INSERT INTO ep_test_odgovori (test_id, question_text, user_answer_text, correct_answer_text, is_correct, explanation)\n";
    
    // Provjeri zadnje podatke unesene u bazu
    echo "\n=== SESSION QUIZ ANSWERS ===\n";
    if (isset($_SESSION['quiz_answers'])) {
        echo "Broj odgovora u sesiji: " . count($_SESSION['quiz_answers']) . "\n";
        echo "Struktura:\n";
        print_r($_SESSION['quiz_answers']);
    } else {
        echo "Nema odgovora u sesiji.\n";
    }
    
} catch (PDOException $e) {
    echo "GREŠKA: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>

<p><a href="index.php" style="display: inline-block; padding: 10px 15px; background: #5B5B5B; color: #fff; text-decoration: none; margin-top: 20px;">Povratak na index</a></p> 
<?php
session_start();
require_once 'db_connection.php';

// Postavke zaglavlja za JSON odgovor
header('Content-Type: application/json');

// Provjera je li zahtjev poslan POST metodom
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Dozvoljene su samo POST metode.'
    ]);
    exit;
}

// Provjera jesu li svi potrebni parametri prisutni
if (!isset($_POST['test_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Nedostaju obavezni parametri.'
    ]);
    exit;
}

// Dohvaćanje i validacija parametra test_id
$testId = filter_var($_POST['test_id'], FILTER_VALIDATE_INT);

if (!$testId) {
    echo json_encode([
        'success' => false,
        'message' => 'Neispravan ID testa.'
    ]);
    exit;
}

// Dohvaćanje podataka o korisniku iz sesije
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Korisnik nije prijavljen u sesiju.'
    ]);
    exit;
}

$korisnikID = $_SESSION['user_id'];

// Dohvaćanje podataka o korisniku iz baze
try {
    $stmtUser = $conn->prepare("SELECT * FROM ep_korisnik WHERE ID = :id LIMIT 1");
    $stmtUser->execute([':id' => $korisnikID]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'E-mail korisnika nije pronađen.'
        ]);
        exit;
    }
    
    $email = $user['email'];
    $korisnickoIme = $user['ime'] ?? 'Učenik';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Greška pri dohvaćanju podataka o korisniku: ' . $e->getMessage()
    ]);
    exit;
}

// Dohvaćanje podataka o testu iz baze
try {
    // Dohvati osnovne podatke o ispitu iz ep_test
    $stmt = $conn->prepare("
        SELECT t.*, tm.naziv as tema_naziv 
        FROM ep_test t
        LEFT JOIN ep_teme tm ON t.kviz_id = tm.ID
        WHERE t.ID = :exam_id LIMIT 1
    ");
    $stmt->execute([':exam_id' => $testId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        echo json_encode([
            'success' => false,
            'message' => 'Test nije pronađen.'
        ]);
        exit;
    }
    
    // Dodatna provjera da test pripada prijavljenom korisniku
    if ($exam['korisnikID'] != $korisnikID) {
        echo json_encode([
            'success' => false,
            'message' => 'Nemate ovlasti za pristup ovom testu.'
        ]);
        exit;
    }
    
    // Dohvati sve odgovore iz ep_test_odgovori za ovaj ispit
    $stmt2 = $conn->prepare("SELECT * FROM ep_test_odgovori WHERE test_id = :exam_id");
    $stmt2->execute([':exam_id' => $testId]);
    $allAnswers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Razdvoji točne i netočne odgovore
    $correctAnswers = [];
    $wrongAnswers = [];
    
    foreach ($allAnswers as $row) {
        $entry = [
            "question" => $row["question_text"],
            "your_answer" => $row["user_answer_text"],
            "correct_answer" => $row["correct_answer_text"],
            "explanation" => $row["explanation"]
        ];
        
        if ($row["is_correct"] == 1) {
            $correctAnswers[] = $entry;
        } else {
            $wrongAnswers[] = $entry;
        }
    }
    
    // Kreiraj HTML sadržaj e-maila
    $emailHtml = createEmailHtml($exam, $correctAnswers, $wrongAnswers, $korisnickoIme);
    
    // Pošalji e-mail
    $mailSuccess = sendEmail($email, "Rezultati ispita - Tehnička škola Čakovec", $emailHtml);
    
    if ($mailSuccess) {
        echo json_encode([
            'success' => true,
            'message' => 'Rezultati su uspješno poslani na vašu e-mail adresu: ' . $email
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Došlo je do greške pri slanju e-maila.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Došlo je do greške: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Funkcija za kreiranje HTML sadržaja e-maila
 */
function createEmailHtml($exam, $correctAnswers, $wrongAnswers, $korisnickoIme) {
    $score = $exam['rezultat'];
    $totalQuestions = $exam['ukupno_pitanja'];
    $correctCount = $exam['tocno_odgovori'];
    $incorrectCount = $exam['netocno_odgovori'];
    $trajanje = $exam['trajanje'];
    $vrijeme_pocetka = $exam['vrijeme_pocetka'];
    $vrijeme_kraja = $exam['vrijeme_kraja'];
    $tema_naziv = $exam['tema_naziv'] ?? 'Nepoznata tema';
    
    // Izračunaj postotak - koristeći ispravne vrijednosti i osiguravajući da ne prelazi 100%
    $postotak = ($totalQuestions > 0) ? min(100, round(($correctCount / $totalQuestions) * 100)) : 0;
    
    // Početak HTML-a
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: "Roboto", Arial, sans-serif;
            }
            
            body {
                background: #2e2e2e;
                color: #fff;
                line-height: 1.6;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: #333333;
                border: 2px solid #A8D25B;
                box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #A6CEE3;
                padding-bottom: 15px;
            }
            
            h1 {
                color: #A8D25B;
                font-weight: bold;
                letter-spacing: 2px;
                text-transform: uppercase;
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .results {
                text-align: center;
                margin: 20px 0;
                font-size: 20px;
            }
            
            .time-info {
                background: #3a3a3a;
                padding: 15px;
                margin-bottom: 20px;
                border-left: 4px solid #A6CEE3;
            }
            
            .time-info p {
                margin: 5px 0;
            }
            
            .time-info strong {
                color: #A6CEE3;
            }
            
            .section-header {
                color: #A8D25B;
                margin: 30px 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 1px solid #A6CEE3;
                font-size: 18px;
                display: flex;
                align-items: center;
            }
            
            .section-icon {
                margin-right: 10px;
                font-size: 24px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #444;
            }
            
            th {
                background: #3a3a3a;
                color: #A6CEE3;
            }
            
            .correct-row {
                background: rgba(168, 210, 91, 0.1);
                border-left: 3px solid #A8D25B;
            }
            
            .incorrect-row {
                background: rgba(166, 206, 227, 0.05);
                border-left: 3px solid #A6CEE3;
            }
            
            .check-icon {
                color: #A8D25B;
            }
            
            .cross-icon {
                color: #e74c3c;
            }
            
            .footer {
                margin-top: 40px;
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid #444;
                color: #aaa;
                font-size: 14px;
            }
            
            .footer a {
                color: #A6CEE3;
                text-decoration: none;
            }
            
            .footer a:hover {
                text-decoration: underline;
            }
            
            .farewell {
                margin: 25px 0;
                padding: 15px;
                background: rgba(168, 210, 91, 0.1);
                border-left: 4px solid #A8D25B;
                line-height: 1.8;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Rezultati Tehničke Škole Čakovec ⚙️</h1>
            <p>Tema: ' . htmlspecialchars($tema_naziv) . '</p>
        </div>
        
        <div class="results">
            <h2>' . htmlspecialchars($correctCount) . ' / ' . htmlspecialchars($totalQuestions) . ' (' . $postotak . '%)</h2>
        </div>
        
        <div class="time-info">
            <p><strong>⏱️ Trajanje:</strong> ' . htmlspecialchars($trajanje) . '</p>
            <p><strong>🕒 Vrijeme početka:</strong> ' . htmlspecialchars($vrijeme_pocetka) . '</p>
            <p><strong>🕒 Vrijeme završetka:</strong> ' . htmlspecialchars($vrijeme_kraja) . '</p>
        </div>
        
        <h3 class="section-header"><span class="section-icon">✓</span> TOČNI ODGOVORI (' . count($correctAnswers) . ')</h3>';
        
    if (!empty($correctAnswers)) {
        $html .= '
        <table>
            <tr>
                <th>PITANJE</th>
                <th>VAŠ ODGOVOR</th>
                <th>TOČAN ODGOVOR</th>
                <th>OBJAŠNJENJE</th>
            </tr>';
            
        foreach ($correctAnswers as $item) {
            $html .= '
            <tr class="correct-row">
                <td>' . htmlspecialchars($item["question"]) . '</td>
                <td><span class="check-icon">✓</span> ' . htmlspecialchars($item["your_answer"]) . '</td>
                <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
                <td>' . htmlspecialchars($item["explanation"] ?: "Nema dodatnog objašnjenja.") . '</td>
            </tr>';
        }
        
        $html .= '</table>';
    } else {
        $html .= '<p style="text-align:center; padding: 15px; background: #3a3a3a;">
            Nema točnih odgovora.
        </p>';
    }
    
    $html .= '<h3 class="section-header"><span class="section-icon">✗</span> NETOČNI ODGOVORI (' . count($wrongAnswers) . ')</h3>';
    
    if (!empty($wrongAnswers)) {
        $html .= '
        <table>
            <tr>
                <th>PITANJE</th>
                <th>VAŠ ODGOVOR</th>
                <th>TOČAN ODGOVOR</th>
                <th>OBJAŠNJENJE</th>
            </tr>';
            
        foreach ($wrongAnswers as $item) {
            $html .= '
            <tr class="incorrect-row">
                <td>' . htmlspecialchars($item["question"]) . '</td>
                <td><span class="cross-icon">✗</span> ' . htmlspecialchars($item["your_answer"]) . '</td>
                <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
                <td>' . htmlspecialchars($item["explanation"] ?: "Nema dodatnog objašnjenja.") . '</td>
            </tr>';
        }
        
        $html .= '</table>';
    } else {
        $html .= '<p style="text-align:center; padding: 15px; background: #3a3a3a;">
            Svaka čast! Nema netočnih odgovora.
        </p>';
    }
    
    // Ljubazan pozdrav na kraju
    $html .= '
        <div class="farewell">
            <p>Poštovani/a ' . htmlspecialchars($korisnickoIme) . ',</p>
            <p>Hvala Vam na sudjelovanju u našem obrazovnom sustavu! Vaš trud i predanost u učenju vrlo su nam važni. Nadamo se da će Vam ovi rezultati biti korisni u daljnjem napretku.</p>
            <p>Ako imate bilo kakvih pitanja ili trebate dodatnu pomoć oko gradiva, nemojte oklijevati obratiti se svojim nastavnicima. Tu smo da Vam pomognemo u Vašem obrazovnom putovanju.</p>
            <p>Srdačan pozdrav od našeg ExamPortal tima!</p>
        </div>
        
        <div class="footer">
            <p>Ovo je automatski generirana poruka. Molimo ne odgovarajte na ovaj e-mail.</p>
            <p>Tehnička škola Čakovec | ExamPortal © ' . date('Y') . '</p>
            <p>Za više informacija posjetite našu stranicu: <a href="https://www.tsck.hr">www.tsck.hr</a></p>
        </div>
    </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Funkcija za slanje e-maila
 */
function sendEmail($to, $subject, $htmlMessage) {
    // Postavke zaglavlja
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ExamPortal Tehničke škole Čakovec <noreply@examportal.tsc.hr>" . "\r\n";
    
    // Pokušaj slanja e-maila
    return mail($to, $subject, $htmlMessage, $headers);
} 
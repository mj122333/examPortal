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

// Dohvaćanje e-maila korisnika iz baze
try {
    $stmtUser = $conn->prepare("SELECT email FROM korisnici WHERE id = :korisnik_id LIMIT 1");
    $stmtUser->execute([':korisnik_id' => $korisnikID]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'E-mail korisnika nije pronađen.'
        ]);
        exit;
    }
    
    $email = $user['email'];
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
    $stmt = $conn->prepare("SELECT * FROM ep_test WHERE ID = :exam_id LIMIT 1");
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
    $emailHtml = createEmailHtml($exam, $correctAnswers, $wrongAnswers);
    
    // Pošalji e-mail
    $mailSuccess = sendEmail($email, "Rezultati Mafija Kviza", $emailHtml);
    
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
function createEmailHtml($exam, $correctAnswers, $wrongAnswers) {
    $score = $exam['rezultat'];
    $totalQuestions = $exam['ukupno_pitanja'];
    $correctCount = $exam['tocno_odgovori'];
    $incorrectCount = $exam['netocno_odgovori'];
    $trajanje = $exam['trajanje'];
    $vrijeme_pocetka = $exam['vrijeme_pocetka'];
    $vrijeme_kraja = $exam['vrijeme_kraja'];
    
    // Početak HTML-a
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: Georgia, serif;
                color: #333;
                line-height: 1.6;
                background-color: #fafafa;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f9f9f9;
                border: 3px solid #daa520;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
            }
            h1, h2 {
                color: #111;
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #daa520;
                padding-bottom: 10px;
            }
            h1 {
                font-size: 28px;
                color: #daa520;
            }
            h2 {
                font-size: 22px;
                color: #333;
            }
            .mafija-accent {
                color: #daa520;
                font-weight: bold;
            }
            .score {
                text-align: center;
                font-size: 1.2rem;
                margin-bottom: 30px;
                padding: 15px;
                background-color: #f0f0f0;
                border-left: 4px solid #daa520;
            }
            .details {
                text-align: center;
                margin-bottom: 30px;
                padding: 15px;
                background-color: #f0f0f0;
                border-left: 4px solid #4682b4;
            }
            .details strong {
                color: #daa520;
                margin-right: 5px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background-color: #e6e6e6;
                border-bottom: 2px solid #daa520;
                color: #333;
                font-weight: bold;
            }
            .correct {
                background-color: rgba(218, 165, 32, 0.1);
                border-left: 3px solid #daa520;
            }
            .incorrect {
                background-color: rgba(100, 100, 100, 0.1);
                border-left: 3px solid #999;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                font-size: 0.9rem;
                color: #777;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            .icon {
                font-size: 1.2em;
                margin-right: 5px;
            }
            .percent {
                font-size: 1.3em;
                font-weight: bold;
                color: #daa520;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>🏆 Rezultati Mafija Kviza 🏆</h1>
        
        <div class="score">
            <strong>Ukupno bodova:</strong> <span class="mafija-accent">' . htmlspecialchars($score) . '</span> 
            od ' . htmlspecialchars($totalQuestions) . ' 
            (<span class="percent">' . round(($score / $totalQuestions) * 100) . '%</span>)
            ' . ($score == $totalQuestions ? '👑 <span class="mafija-accent">MAFIJA ŠEF!</span> 👑' : '') . '
        </div>
        
        <div class="details">
            <p><strong>⏱️ Trajanje:</strong> ' . htmlspecialchars($trajanje) . '</p>
            <p><strong>📅 Vrijeme početka:</strong> ' . htmlspecialchars($vrijeme_pocetka) . '</p>
            <p><strong>✅ Vrijeme završetka:</strong> ' . htmlspecialchars($vrijeme_kraja) . '</p>
        </div>

        <h2>✓ Točni Odgovori</h2>';
        
    if (!empty($correctAnswers)) {
        $html .= '
        <table>
            <tr>
                <th>Pitanje</th>
                <th>Vaš Odgovor</th>
                <th>Točan Odgovor</th>
                <th>Objašnjenje</th>
            </tr>';
            
        foreach ($correctAnswers as $item) {
            $html .= '
            <tr class="correct">
                <td>' . htmlspecialchars($item["question"]) . '</td>
                <td>✓ ' . htmlspecialchars($item["your_answer"]) . '</td>
                <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
                <td>' . htmlspecialchars($item["explanation"]) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
    } else {
        $html .= '<p style="text-align:center; padding: 15px; background-color: #f0f0f0; border-left: 3px solid #daa520;">
            Niste imali točnih odgovora.
        </p>';
    }

    $html .= '<h2>✗ Netočni Odgovori</h2>';
    
    if (!empty($wrongAnswers)) {
        $html .= '
        <table>
            <tr>
                <th>Pitanje</th>
                <th>Vaš Odgovor</th>
                <th>Točan Odgovor</th>
                <th>Objašnjenje</th>
            </tr>';
            
        foreach ($wrongAnswers as $item) {
            $html .= '
            <tr class="incorrect">
                <td>' . htmlspecialchars($item["question"]) . '</td>
                <td>✗ ' . htmlspecialchars($item["your_answer"]) . '</td>
                <td>✓ ' . htmlspecialchars($item["correct_answer"]) . '</td>
                <td>' . htmlspecialchars($item["explanation"]) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
    } else {
        $html .= '<p style="text-align:center; padding: 15px; background-color: #f0f0f0; border-left: 3px solid #daa520;">
            👑 Svi odgovori su točni! Pravi si Mafija Šef!
        </p>';
    }

    $html .= '
        <div class="footer">
            <p>Ovaj e-mail je automatski generiran iz Mafija Kviz sustava. Molimo ne odgovarajte na njega.</p>
            <p>© Mafija Kviz ' . date('Y') . ' | Organizirani ispiti s stilom!</p>
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
    $headers .= "From: Mafija Kviz <noreply@mafijakviz.com>" . "\r\n";
    
    // Pokušaj slanja e-maila
    return mail($to, $subject, $htmlMessage, $headers);
} 
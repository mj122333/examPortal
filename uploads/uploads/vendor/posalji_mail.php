<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // prilagodite putanju ako je potrebno

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

// 1. Provjera sesije i razine korisnika
if (!isset($_SESSION['user_id']) || $_SESSION['razina'] != 1) {
    die("Nemate pravo pristupa ovoj stranici.");
}
$profesorID = $_SESSION['user_id'];

// 2. Dohvati testId i provjeri
$testId = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;
$ucenikId = isset($_GET['ucenik_id']) ? intval($_GET['ucenik_id']) : 0;

if (!$testId || !$ucenikId) {
    die("Nevažeći test ID ili ID učenika.");
}

// 3. Dohvati podatke o testu
$stmtTest = $conn->prepare("
    SELECT t.*, tm.naziv as tema_naziv 
    FROM ep_test t
    LEFT JOIN ep_teme tm ON t.kviz_id = tm.ID
    WHERE t.ID = :testId LIMIT 1
");
$stmtTest->execute([':testId' => $testId]);
$test = $stmtTest->fetch();
if (!$test) {
    die("Test nije pronađen.");
}

// 4. Provjeri pripada li test učeniku
if ($test['korisnikID'] != $ucenikId) {
    die("Test ne pripada odabranom učeniku.");
}

// 5. Dohvati email i ime učenika iz ep_korisnik
$stmtUser = $conn->prepare("SELECT email, ime FROM ep_korisnik WHERE ID = :id LIMIT 1");
$stmtUser->execute([':id' => $ucenikId]);
$user = $stmtUser->fetch();
if (!$user) {
    die("Učenik nije pronađen.");
}
$emailKorisnika = $user['email'];
$userName = $user['ime'];

// 6. Dohvati sve odgovore za taj test
$stmtAns = $conn->prepare("
    SELECT 
        to.test_id,
        p.tekst_pitanja as question_text,
        o.tekst as user_answer_text,
        (SELECT tekst FROM ep_odgovori WHERE pitanje_id = p.ID AND tocan = 1 LIMIT 1) as correct_answer_text,
        p.objasnjenje as explanation,
        CASE WHEN o.tocan = 1 THEN 1 ELSE 0 END as is_correct
    FROM ep_test_odgovori to
    JOIN ep_pitanja p ON to.pitanje_id = p.ID
    LEFT JOIN ep_odgovori o ON to.odgovor_id = o.ID
    WHERE to.test_id = :testId
    ORDER BY to.ID ASC
");
$stmtAns->execute([':testId' => $testId]);
$allAnswers = $stmtAns->fetchAll();

// Podijeli odgovore na točne i netočne
$correctAnswers = [];
$wrongAnswers   = [];
foreach ($allAnswers as $row) {
    $entry = [
        "question"       => $row["question_text"],
        "your_answer"    => $row["user_answer_text"],
        "correct_answer" => $row["correct_answer_text"],
        "explanation"    => $row["explanation"]
    ];
    if ($row["is_correct"] == 1) {
        $correctAnswers[] = $entry;
    } else {
        $wrongAnswers[]   = $entry;
    }
}

// Pripremite varijable za HTML sadržaj
$score          = $test['rezultat'];
$totalQuestions = $test['ukupno_pitanja'];
$trajanje       = $test['trajanje'];
$vrijemeStart   = $test['vrijeme_pocetka'];
$vrijemeEnd     = $test['vrijeme_kraja'];
$tema_naziv     = $test['tema_naziv'] ?? 'Nepoznata tema';

// Izračunaj postotak
$postotak = ($totalQuestions > 0) ? round(($score / $totalQuestions) * 100) : 0;

// 7. Sastavite HTML sadržaj s formalnijim tekstom i dizajnom prema forms.php
$htmlContent = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Rezultati vašeg testa</title>
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
        <h2>' . htmlspecialchars($score) . ' / ' . htmlspecialchars($totalQuestions) . ' (' . $postotak . '%)</h2>
    </div>
    
    <div class="time-info">
        <p><strong>⏱️ Trajanje:</strong> ' . htmlspecialchars($trajanje) . '</p>
        <p><strong>🕒 Vrijeme početka:</strong> ' . htmlspecialchars($vrijemeStart) . '</p>
        <p><strong>🕒 Vrijeme završetka:</strong> ' . htmlspecialchars($vrijemeEnd) . '</p>
    </div>
    
    <h3 class="section-header"><span class="section-icon">✓</span> TOČNI ODGOVORI (' . count($correctAnswers) . ')</h3>';

// Točni odgovori
if (!empty($correctAnswers)) {
    $htmlContent .= '
    <table>
        <tr>
            <th>PITANJE</th>
            <th>VAŠ ODGOVOR</th>
            <th>TOČAN ODGOVOR</th>
            <th>OBJAŠNJENJE</th>
        </tr>';
        
    foreach ($correctAnswers as $item) {
        $htmlContent .= '
        <tr class="correct-row">
            <td>' . htmlspecialchars($item["question"]) . '</td>
            <td><span class="check-icon">✓</span> ' . htmlspecialchars($item["your_answer"]) . '</td>
            <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
            <td>' . htmlspecialchars($item["explanation"] ?: "Nema dodatnog objašnjenja.") . '</td>
        </tr>';
    }
    
    $htmlContent .= '</table>';
} else {
    $htmlContent .= '<p style="text-align:center; padding: 15px; background: #3a3a3a;">
        Nema točnih odgovora.
    </p>';
}

$htmlContent .= '<h3 class="section-header"><span class="section-icon">✗</span> NETOČNI ODGOVORI (' . count($wrongAnswers) . ')</h3>';

// Netočni odgovori
if (!empty($wrongAnswers)) {
    $htmlContent .= '
    <table>
        <tr>
            <th>PITANJE</th>
            <th>VAŠ ODGOVOR</th>
            <th>TOČAN ODGOVOR</th>
            <th>OBJAŠNJENJE</th>
        </tr>';
        
    foreach ($wrongAnswers as $item) {
        $htmlContent .= '
        <tr class="incorrect-row">
            <td>' . htmlspecialchars($item["question"]) . '</td>
            <td><span class="cross-icon">✗</span> ' . htmlspecialchars($item["your_answer"]) . '</td>
            <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
            <td>' . htmlspecialchars($item["explanation"] ?: "Nema dodatnog objašnjenja.") . '</td>
        </tr>';
    }
    
    $htmlContent .= '</table>';
} else {
    $htmlContent .= '<p style="text-align:center; padding: 15px; background: #3a3a3a;">
        Svaka čast! Nema netočnih odgovora.
    </p>';
}

// Ljubazan pozdrav na kraju
$htmlContent .= '
    <div class="farewell">
        <p>Poštovani/a ' . htmlspecialchars($userName) . ',</p>
        <p>Hvala Vam na sudjelovanju u našem obrazovnom sustavu! Vaš trud i predanost u učenju vrlo su nam važni. Nadamo se da će Vam ovi rezultati biti korisni u daljnjem napretku.</p>
        <p>Ako imate bilo kakvih pitanja ili trebate dodatnu pomoć oko gradiva, nemojte oklijevati obratiti se svojim nastavnicima. Tu smo da Vam pomognemo u Vašem obrazovnom putovanju.</p>
        <p>Srdačan pozdrav od našeg ExamPortal tima!</p>
    </div>
    
    <div class="footer">
        <p>Ovo je automatski generirana poruka. Molimo ne odgovarajte na ovaj e-mail.</p>
        <p>Tehnička škola Čakovec | ExamPortal © ' . date('Y') . '</p>
        <p>Za više informacija posjetite našu stranicu: <a href="https://tsck.eu/examPortal/login.php">https://tsck.eu/examPortal/login.php</a></p>
    </div>
</div>
</body>
</html>';

// 8. Pošalji HTML email putem PHPMailer-a
$mail = new PHPMailer(true);

try {
    // SMTP postavke (iz cPanela)
    $mail->isSMTP();
    $mail->Host       = 'mail.tsck.eu';           // Outgoing server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'examportal@tsck.eu';     // Puni email
    $mail->Password   = 'z0,K9YBt0hla';            // Lozinka
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    // Pošiljatelj i primatelj
    $mail->setFrom('examportal@tsck.eu', 'Exam Portal Tehničke škole Čakovec');
    $mail->addAddress($emailKorisnika);
    $capitalizedName = ucfirst(mb_strtolower($userName));

    // Sadržaj
    $mail->isHTML(true);           // Važno za HTML
    $mail->Subject = "Rezultati vašeg testa - Tehnička škola Čakovec";
    $mail->Body    = $htmlContent; // Ubacujemo HTML sadržaj

    $mail->send();
    // Nakon slanja emaila, preusmjeravanje natrag na forms.php (end-screen) s exam_id
    header("Location: forms.php?exam_id=$testId");
    exit();
} catch (Exception $e) {
    echo "Greška pri slanju emaila: " . $mail->ErrorInfo;
}
?>

<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // prilagodite putanju ako je potrebno

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

// 1. Provjera sesije
if (!isset($_SESSION['user_id'])) {
    die("Korisnik nije prijavljen.");
}
$korisnikID = $_SESSION['user_id'];

// 2. Dohvati email i ime korisnika iz ep_korisnik
$stmtUser = $conn->prepare("SELECT email, ime FROM ep_korisnik WHERE ID = :id LIMIT 1");
$stmtUser->execute([':id' => $korisnikID]);
$user = $stmtUser->fetch();
if (!$user) {
    die("Korisnik nije pronađen.");
}
$emailKorisnika = $user['email'];
$userName = $user['ime'];

// 3. Dohvati testId i provjeri
$testId = 0;
if (isset($_POST['test_id'])) {
    $testId = intval($_POST['test_id']);
} elseif (isset($_GET['test_id'])) {
    $testId = intval($_GET['test_id']);
}

if (!$testId) {
    die("Nevažeći test ID.");
}

// 4. Dohvati podatke o testu
$stmtTest = $conn->prepare("SELECT * FROM ep_test WHERE ID = :testId LIMIT 1");
$stmtTest->execute([':testId' => $testId]);
$test = $stmtTest->fetch();
if (!$test) {
    die("Test nije pronađen.");
}

// 5. Dohvati sve odgovore za taj test
$stmtAns = $conn->prepare("SELECT * FROM ep_test_odgovori WHERE test_id = :testId");
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

// 6. Sastavite HTML sadržaj s formalnijim tekstom
$htmlContent = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Rezultati vašeg testa</title>
  <style>
    /* Minimalni stil za email */
    body {
      background: #090909;
      color: #fff;
      font-family: Arial, sans-serif;
      margin: 0; 
      padding: 0;
    }
    .results-container {
      max-width: 600px;
      margin: 20px auto;
      background: #1b1b1b;
      border: 2px solid #ff00ff;
      border-radius: 12px;
      padding: 20px;
    }
    h1, h2 {
      text-align: center;
      color: #ffae00;
      margin-bottom: 20px;
    }
    .score {
      text-align: center;
      font-size: 1.3rem;
      margin-bottom: 20px;
      color: #40ffe5;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    th, td {
      padding: 8px;
      border: 1px solid #ff00ff;
    }
    th {
      background-color: rgba(255, 0, 255, 0.2);
    }
    .correct {
      background-color: rgba(200, 230, 201, 0.2);
    }
    .incorrect {
      background-color: rgba(255, 205, 210, 0.2);
    }
    .footer-note {
      text-align: center;
      font-size: 0.9rem;
      color: #ccc;
      margin-top: 20px;
    }
    .intro {
      margin-bottom: 20px;
      font-size: 1rem;
      line-height: 1.5;
    }
    a {
      color: #ffae00;
      text-decoration: none;
    }
  </style>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>
<div class="results-container">
  <p>Poštovani ' . htmlspecialchars($userName) . ',</p>
  <p class="intro">
    Ovdje su rezultati vašeg testa. Molimo pažljivo pregledajte navedene podatke ispod. Ukoliko imate bilo kakvih pitanja ili nejasnoća, slobodno nas kontaktirajte.
  </p>
  <h1>Rezultati Kviza</h1>
  <p class="score">
    <strong>Ukupno bodova:</strong> ' . htmlspecialchars($score) . ' od ' . htmlspecialchars($totalQuestions) . '
  </p>
  <p style="text-align:center;">
    <strong>Trajanje:</strong> ' . htmlspecialchars($trajanje) . '<br>
    <strong>Vrijeme početka:</strong> ' . htmlspecialchars($vrijemeStart) . '<br>
    <strong>Vrijeme završetka:</strong> ' . htmlspecialchars($vrijemeEnd) . '
  </p>';
  
// Točni odgovori
$htmlContent .= '<h2>Točni Odgovori</h2>';
if (!empty($correctAnswers)) {
    $htmlContent .= '<table>
      <tr>
        <th>Pitanje</th>
        <th>Vaš Odgovor</th>
        <th>Točan Odgovor</th>
        <th>Objašnjenje</th>
      </tr>';
    foreach ($correctAnswers as $item) {
        $htmlContent .= '<tr class="correct">
          <td>' . htmlspecialchars($item["question"]) . '</td>
          <td>' . htmlspecialchars($item["your_answer"]) . '</td>
          <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
          <td>' . htmlspecialchars($item["explanation"]) . '</td>
        </tr>';
    }
    $htmlContent .= '</table>';
} else {
    $htmlContent .= '<p style="text-align:center;">Niste imali točnih odgovora.</p>';
}

// Netočni odgovori
$htmlContent .= '<h2>Netočni Odgovori</h2>';
if (!empty($wrongAnswers)) {
    $htmlContent .= '<table>
      <tr>
        <th>Pitanje</th>
        <th>Vaš Odgovor</th>
        <th>Točan Odgovor</th>
        <th>Objašnjenje</th>
      </tr>';
    foreach ($wrongAnswers as $item) {
        $htmlContent .= '<tr class="incorrect">
          <td>' . htmlspecialchars($item["question"]) . '</td>
          <td>' . htmlspecialchars($item["your_answer"]) . '</td>
          <td>' . htmlspecialchars($item["correct_answer"]) . '</td>
          <td>' . htmlspecialchars($item["explanation"]) . '</td>
        </tr>';
    }
    $htmlContent .= '</table>';
} else {
    $htmlContent .= '<p style="text-align:center;">Svi odgovori su točni!</p>';
}

$htmlContent .= '
  <p class="footer-note">
    S poštovanjem,<br>
    Tim ExamPortal<br>
    <a href="http://tsck.eu/examPortal" target="_blank">tsck.eu/examPortal</a>
  </p>
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
    $mail->setFrom('examportal@tsck.eu', 'Exam Portal');
    $mail->addAddress($emailKorisnika);
    $capitalizedName = ucfirst(mb_strtolower($userName));

    // Sadržaj
    $mail->isHTML(true);           // Važno za HTML
    $mail->Subject = "Rezultati vašeg testa, " . htmlspecialchars($capitalizedName) . " - Exam Portal";
    $mail->Body    = $htmlContent; // Ubacujemo HTML sadržaj

    $mail->send();
    // Nakon slanja emaila, preusmjeravanje natrag na forms.php (end-screen) s exam_id
    header("Location: forms.php?exam_id=$testId");
    exit();
} catch (Exception $e) {
    echo "Greška pri slanju emaila: " . $mail->ErrorInfo;
}
?>

<?php
session_start();
require_once 'includes/db_connection.php';

// Provjeri postoji li ID rezultata u URL-u
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$resultId = $_GET['id'];

// Dohvati podatke o rezultatu
$stmt = $conn->prepare("
    SELECT r.*, t.naziv as tema_naziv, u.ime, u.prezime
    FROM ep_rezultati r
    JOIN ep_teme t ON r.temaID = t.ID
    JOIN ep_users u ON r.userID = u.ID
    WHERE r.ID = :id
");
$stmt->execute([':id' => $resultId]);
$result = $stmt->fetch();

if (!$result) {
    header("Location: index.php");
    exit();
}

// Dohvati detalje odgovora
$stmt = $conn->prepare("
    SELECT p.tekst_pitanja, o.tekst as odgovor, o.tocno
    FROM ep_rezultati_odgovori ro
    JOIN ep_pitanje p ON ro.pitanjeID = p.ID
    JOIN ep_odgovori o ON ro.odgovorID = o.ID
    WHERE ro.rezultatID = :id
    ORDER BY ro.ID
");
$stmt->execute([':id' => $resultId]);
$answers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Rezultati kviza | Tehnička škola Čakovec</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .results-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: rgba(33, 37, 41, 0.95);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
        }
        .result-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dc3545;
        }
        .result-header h1 {
            color: #fff;
            margin-bottom: 10px;
        }
        .result-header p {
            color: #adb5bd;
            margin: 5px 0;
        }
        .score-display {
            font-size: 2em;
            color: #dc3545;
            margin: 20px 0;
            text-align: center;
        }
        .answers-list {
            margin-top: 30px;
        }
        .answer-item {
            background: rgba(52, 58, 64, 0.9);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        .answer-item.correct {
            border-left-color: #28a745;
        }
        .answer-item.incorrect {
            border-left-color: #dc3545;
        }
        .question-text {
            color: #fff;
            margin-bottom: 10px;
        }
        .answer-text {
            color: #adb5bd;
        }
        .answer-status {
            margin-top: 10px;
            font-weight: bold;
        }
        .answer-status.correct {
            color: #28a745;
        }
        .answer-status.incorrect {
            color: #dc3545;
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #dc3545;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .back-button:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="results-container">
        <div class="result-header">
            <h1><i class="fas fa-trophy" style="color: #dc3545; margin-right: 10px;"></i>Rezultati kviza</h1>
            <p><strong>Korisnik:</strong> <?php echo htmlspecialchars($result['ime'] . ' ' . $result['prezime']); ?></p>
            <p><strong>Tema:</strong> <?php echo htmlspecialchars($result['tema_naziv']); ?></p>
            <p><strong>Datum:</strong> <?php echo date('d.m.Y. H:i', strtotime($result['vrijeme_pocetka'])); ?></p>
        </div>

        <div class="score-display">
            <i class="fas fa-star" style="color: #ffc107;"></i>
            Rezultat: <?php echo $result['broj_tocnih']; ?>/<?php echo count($answers); ?>
        </div>

        <div class="answers-list">
            <?php foreach ($answers as $answer): ?>
            <div class="answer-item <?php echo $answer['tocno'] ? 'correct' : 'incorrect'; ?>">
                <div class="question-text">
                    <?php echo htmlspecialchars($answer['tekst_pitanja']); ?>
                </div>
                <div class="answer-text">
                    <strong>Vaš odgovor:</strong> <?php echo htmlspecialchars($answer['odgovor']); ?>
                </div>
                <div class="answer-status <?php echo $answer['tocno'] ? 'correct' : 'incorrect'; ?>">
                    <i class="fas <?php echo $answer['tocno'] ? 'fa-check' : 'fa-times'; ?>"></i>
                    <?php echo $answer['tocno'] ? 'Točno' : 'Netočno'; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left" style="margin-right: 5px;"></i>
                Natrag na početnu
            </a>
        </div>
    </div>
</body>
</html> 
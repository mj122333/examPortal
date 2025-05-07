<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

// Preuzmi podatke o korisniku
$userId    = $_SESSION['user_id'];
$userLevel = $_SESSION['razina'] ?? 2; // 2 = student by default

$poruka = "";

// Dohvati povijest ispita za ulogiranog korisnika.
// Pretpostavimo da tablica ep_test ima primarni ključ ID (kao exam_id).
$historySql = "
    SELECT e.ID AS exam_id, e.vrijeme_kraja, e.trajanje, e.ukupno_pitanja, e.tocno_odgovori, e.netocno_odgovori, e.kviz_id,
           t.naziv AS theme_name
    FROM ep_test e
    LEFT JOIN ep_teme t ON e.kviz_id = t.ID
    WHERE e.korisnikID = :userId
    ORDER BY e.vrijeme_kraja DESC
";
$historyStmt = $conn->prepare($historySql);
$historyStmt->execute([':userId' => $userId]);
$examHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Dohvati teme za odabir (ovisno o razini korisnika)
if ($userLevel == 1) {
    // Profesor (razina 1) vidi sve teme
    $stmt = $conn->prepare("
        SELECT t.ID AS theme_id, 
               t.naziv AS theme_name, 
               COUNT(p.ID) AS broj_pitanja
        FROM ep_teme t
        LEFT JOIN ep_pitanje p ON p.temaID = t.ID
        GROUP BY t.ID, t.naziv
        ORDER BY t.naziv
    ");
    $stmt->execute();
    $korisnikTeme = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Student (razina 2) vidi samo one teme koje su mu dodijeljene
    $stmt = $conn->prepare(" 
        SELECT t.ID AS theme_id, 
               t.naziv AS theme_name, 
               COUNT(p.ID) AS broj_pitanja
        FROM ep_teme t
        INNER JOIN ep_korisnik_teme kt ON kt.tema_id = t.ID
        LEFT JOIN ep_pitanje p ON p.temaID = t.ID
        WHERE kt.korisnik_id = ?
        GROUP BY t.ID, t.naziv
        ORDER BY t.naziv
    ");
    $stmt->execute([$userId]);
    $korisnikTeme = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mafija Kviz | Odabir Teme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Georgia:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }
        body {
            background: #222222; /* Tamno siva pozadina */
            color: #fff;
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="serif" font-size="20" fill="rgba(255,215,0,0.03)">$</text><text x="60" y="70" font-family="serif" font-size="20" fill="rgba(30,144,255,0.03)">$</text></svg>');
            padding: 20px;
        }
        .page-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .tema-container {
            width: 100%;
            margin-bottom: 40px;
            background: linear-gradient(145deg, #333333, #1a1a1a); /* Sivi gradijent */
            border: 2px solid #ffd700; /* Zlatno žuta granica */
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.2), 0 0 60px rgba(255, 215, 0, 0.1);
            padding: 30px;
            border-radius: 0; /* Oštre ravne linije za mafija stil */
            position: relative;
            overflow: hidden;
        }
        .tema-container:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path d="M20,20 L180,20 L180,180 L20,180 Z" stroke="rgba(30,144,255,0.1)" fill="none" stroke-width="2" stroke-dasharray="10,5"/></svg>');
            pointer-events: none;
            opacity: 0.2;
        }
        h1, h2, h3 {
            color: #ffd700; /* Zlatna boja */
            text-shadow: 0 0 5px #ffd700;
            font-family: 'Georgia', serif;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            border-bottom: 2px solid #1e90ff; /* Plava linija ispod */
            padding-bottom: 10px;
        }
        h1 {
            font-size: 2.5rem;
            text-align: center;
        }
        h2 {
            font-size: 2rem;
            position: relative;
        }
        h2:after {
            content: "";
            position: absolute;
            width: 80px;
            height: 3px;
            bottom: -10px;
            left: 0;
            background-color: #1e90ff;
        }
        .tema-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tema-card {
            background-color: #2a2a2a;
            padding: 20px;
            border-left: 4px solid #ffd700; /* Zlatna lijeva granica */
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
        }
        .tema-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.2);
            border-left: 4px solid #1e90ff;
        }
        .tema-card h3 {
            color: #1e90ff; /* Plava boja */
            font-size: 1.5rem;
            margin-bottom: 10px;
            border-bottom: none;
            text-transform: none;
            letter-spacing: 1px;
        }
        .tema-info {
            font-size: 0.9rem;
            margin-bottom: 15px;
            color: #cccccc;
        }
        .tema-btn {
            display: inline-block;
            background-color: #1e90ff; /* Plava */
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s ease;
            box-shadow: 0 0 5px #1e90ff, 0 0 10px #1e90ff;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            text-decoration: none;
        }
        .tema-btn:hover {
            background-color: #ffd700; /* Žuta na hover */
            box-shadow: 0 0 10px #ffd700, 0 0 20px #ffd700;
            color: #222;
        }
        .tema-btn i {
            margin-right: 5px;
        }
        .history-box {
            width: 100%;
            margin-top: 30px;
            background-color: #2a2a2a;
            padding: 20px;
            border-left: 4px solid #1e90ff; /* Plava lijeva granica */
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .history-table th {
            background-color: rgba(30, 144, 255, 0.2);
            border-bottom: 2px solid #ffd700;
            text-align: left;
            padding: 10px;
            color: #ffd700;
            font-weight: bold;
        }
        .history-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
        }
        .history-table tr:hover {
            background-color: rgba(30, 144, 255, 0.05);
        }
        .score {
            color: #ffd700;
            font-weight: bold;
        }
        .timestamp {
            color: #999;
            font-style: italic;
            font-size: 0.9rem;
        }
        .preview-button {
            background-color: #1e90ff;
            padding: 5px 10px;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 0;
            transition: 0.3s ease;
        }
        .preview-button:hover {
            background-color: #ffd700;
            color: #222;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: transparent;
            color: #1e90ff;
            border: 2px solid #1e90ff;
            padding: 8px 15px;
            cursor: pointer;
            transition: 0.3s ease;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .logout-btn:hover {
            background-color: #1e90ff;
            color: #fff;
        }
        .teacher-link {
            display: inline-block;
            background-color: #ffd700;
            color: #222;
            padding: 12px 24px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            border-radius: 0;
            transition: 0.3s ease;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .teacher-link:hover {
            background-color: #1e90ff;
            color: #fff;
            box-shadow: 0 0 15px rgba(30, 144, 255, 0.5);
        }
        .mafija-icon {
            font-size: 40px;
            color: #ffd700;
            opacity: 0.1;
            position: absolute;
        }
        .mafija-icon.top-right {
            top: 20px;
            right: 20px;
        }
        .mafija-icon.bottom-left {
            bottom: 20px;
            left: 20px;
        }
        @media (max-width: 768px) {
            .tema-list {
                grid-template-columns: 1fr;
            }
            .history-table {
                font-size: 0.9rem;
            }
            .tema-container {
                padding: 20px;
            }
            h1 {
                font-size: 2rem;
            }
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1><i class="fas fa-crown" style="margin-right: 10px;"></i>Mafija Kviz</h1>
        
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Odjava</a>
        
        <div class="tema-container">
            <div class="mafija-icon top-right"><i class="fas fa-gem"></i></div>
            <div class="mafija-icon bottom-left"><i class="fas fa-dollar-sign"></i></div>
            
            <h2><i class="fas fa-list-alt" style="margin-right: 10px;"></i>Odaberi Temu</h2>
            
            <?php if ($poruka): ?>
                <div style="background-color: rgba(255,215,0,0.1); padding: 10px; margin-bottom: 20px; border-left: 3px solid #ffd700;">
                    <?= htmlspecialchars($poruka) ?>
                </div>
            <?php endif; ?>
            
            <div class="tema-list">
                <?php foreach ($korisnikTeme as $tema): ?>
                    <div class="tema-card">
                        <h3><?= htmlspecialchars($tema['theme_name']) ?></h3>
                        <div class="tema-info">
                            <p><i class="fas fa-question-circle" style="color: #1e90ff;"></i> Broj pitanja: <strong><?= $tema['broj_pitanja'] ?></strong></p>
                        </div>
                        <form action="index.php" method="POST">
                            <input type="hidden" name="tema_id" value="<?= htmlspecialchars($tema['theme_id']) ?>">
                            <button type="submit" class="tema-btn"><i class="fas fa-play"></i> Započni Kviz</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($userLevel == 1): // Samo profesori vide dodavanje pitanja ?>
                <div style="text-align: center; margin-top: 30px;">
                    <a href="dodaj_pitanje.php" class="teacher-link"><i class="fas fa-plus-circle" style="margin-right: 5px;"></i> Dodaj Novo Pitanje</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($examHistory) > 0): ?>
            <div class="tema-container">
                <h2><i class="fas fa-history" style="margin-right: 10px;"></i>Povijest Kvizova</h2>
                
                <div class="history-box">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Tema</th>
                                <th>Datum</th>
                                <th>Točni Odgovori</th>
                                <th>Ukupno</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examHistory as $exam): ?>
                                <tr>
                                    <td><?= htmlspecialchars($exam['theme_name'] ?? 'Nepoznata tema') ?></td>
                                    <td class="timestamp"><?= htmlspecialchars($exam['vrijeme_kraja']) ?></td>
                                    <td class="score"><?= htmlspecialchars($exam['tocno_odgovori']) ?> / <?= htmlspecialchars($exam['ukupno_pitanja']) ?></td>
                                    <td><?= round(($exam['tocno_odgovori'] / $exam['ukupno_pitanja']) * 100) ?>%</td>
                                    <td>
                                        <a href="forms.php?exam_id=<?= htmlspecialchars($exam['exam_id']) ?>" class="preview-button">
                                            <i class="fas fa-eye"></i> Pregled
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Jednostavna mafija animacija
        document.addEventListener('DOMContentLoaded', function() {
            // Dodajemo malo novčića po ekranu
            for (let i = 0; i < 5; i++) {
                createFallingMoney();
            }
        });

        function createFallingMoney() {
            const money = document.createElement('div');
            money.innerHTML = '<i class="fas fa-dollar-sign"></i>';
            money.style.position = 'fixed';
            money.style.color = '#ffd700';
            money.style.fontSize = Math.random() * 20 + 10 + 'px';
            money.style.left = Math.random() * 100 + 'vw';
            money.style.top = '-20px';
            money.style.opacity = Math.random() * 0.3 + 0.1;
            money.style.zIndex = '1000';
            money.style.pointerEvents = 'none';
            document.body.appendChild(money);
            
            const duration = Math.random() * 5 + 3;
            money.style.transition = `top ${duration}s linear, transform ${duration}s linear`;
            
            setTimeout(() => {
                money.style.top = '110vh';
                money.style.transform = `rotate(${Math.random() * 360}deg)`;
            }, 10);
            
            setTimeout(() => {
                document.body.removeChild(money);
                // Stvori novi nakon što nestane
                createFallingMoney();
            }, duration * 1000);
        }
    </script>
</body>
</html>

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
$userLevel = isset($_SESSION['razina']) ? (int)$_SESSION['razina'] : 2; // Konvertiraj u integer
$isProfessor = ($userLevel === 1); // Stroga provjera tipa
$isGuest = ($userLevel === 3); // Nova provjera za gost korisnike

// Dodatna provjera sesije i baze
if (!isset($_SESSION['razina']) || ($userLevel !== 1 && $userLevel !== 3)) {
    // Dohvati podatke iz baze
    $stmt = $conn->prepare("SELECT razinaID FROM ep_korisnik WHERE ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['razina'] = (int)$user['razinaID'];
        $userLevel = (int)$user['razinaID'];
        $isProfessor = ($userLevel === 1);
        $isGuest = ($userLevel === 3);
    }
}

// Debug ispis
// echo "<!-- Debug info: User Level = " . $userLevel . ", Is Professor = " . ($isProfessor ? "true" : "false") . ", Is Guest = " . ($isGuest ? "true" : "false") . ", Session = " . print_r($_SESSION, true) . " -->";
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
        WHERE t.aktivno = 1
        GROUP BY t.ID, t.naziv
        ORDER BY t.naziv
    ");
    $stmt->execute();
    $korisnikTeme = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Osiguraj da profesor uvijek ima pristup panelu bez obzira na teme
    $isProfessor = true;
} else if ($userLevel == 3) {
    // Gost (razina 3) vidi sve teme
    $stmt = $conn->prepare("
        SELECT t.ID AS theme_id, 
               t.naziv AS theme_name, 
               COUNT(p.ID) AS broj_pitanja
        FROM ep_teme t
        LEFT JOIN ep_pitanje p ON p.temaID = t.ID
        WHERE t.aktivno = 1
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
        WHERE kt.korisnik_id = ? AND t.aktivno = 1
        GROUP BY t.ID, t.naziv
        ORDER BY t.naziv
    ");
    $stmt->execute([$userId]);
    $korisnikTeme = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Provjeri i kreiraj istaknute teme ako ne postoje
$istaknuteTeme = ["Tehnički Izazov 2025", "Digitalni Labirint"];
$istaknuteTemeIds = [];

foreach ($istaknuteTeme as $nazivTeme) {
    // Provjeri postoji li tema
    $stmt = $conn->prepare("SELECT ID FROM ep_teme WHERE naziv = ?");
    $stmt->execute([$nazivTeme]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tema) {
        // Ako tema ne postoji, kreiraj je
        $stmt = $conn->prepare("INSERT INTO ep_teme (naziv) VALUES (?)");
        $stmt->execute([$nazivTeme]);
        $temaId = $conn->lastInsertId();
        
        // Dodaj temu svim korisnicima ako je korisnik profesor
        if ($userLevel == 1) {
            $stmt = $conn->prepare("SELECT ID FROM ep_korisnik WHERE razinaID = 2");
            $stmt->execute();
            $studenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($studenti as $student) {
                $stmt = $conn->prepare("INSERT INTO ep_korisnik_teme (korisnik_id, tema_id) VALUES (?, ?)");
                $stmt->execute([$student['ID'], $temaId]);
            }
        }
        
        $istaknuteTemeIds[$nazivTeme] = $temaId;
    } else {
        $istaknuteTemeIds[$nazivTeme] = $tema['ID'];
        
        // Provjeri postoji li veza između trenutnog korisnika i teme
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ep_korisnik_teme WHERE korisnik_id = ? AND tema_id = ?");
        $stmt->execute([$userId, $tema['ID']]);
        $postojiVeza = $stmt->fetchColumn() > 0;
        
        // Ako veza ne postoji, provjeri postojanje korisnika i teme
        if (!$postojiVeza) {
            try {
                // Započni transakciju
                $conn->beginTransaction();

                // Provjeri postoji li korisnik
                $stmt = $conn->prepare("SELECT COUNT(*) FROM ep_korisnik WHERE ID = ?");
                $stmt->execute([$userId]);
                $korisnikPostoji = $stmt->fetchColumn() > 0;
                
                // Provjeri postoji li tema
                $stmt = $conn->prepare("SELECT COUNT(*) FROM ep_teme WHERE ID = ?");
                $stmt->execute([$tema['ID']]);
                $temaPostoji = $stmt->fetchColumn() > 0;
                
                // Dodaj vezu samo ako oba postoje i veza ne postoji
                if ($korisnikPostoji && $temaPostoji) {
                    // Još jednom provjeri postoji li veza (za svaki slučaj)
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM ep_korisnik_teme WHERE korisnik_id = ? AND tema_id = ?");
                    $stmt->execute([$userId, $tema['ID']]);
                    $vezaPostoji = $stmt->fetchColumn() > 0;

                    if (!$vezaPostoji) {
                        $stmt = $conn->prepare("INSERT INTO ep_korisnik_teme (korisnik_id, tema_id) VALUES (?, ?)");
                        if (!$stmt->execute([$userId, $tema['ID']])) {
                            $conn->rollBack();
                            error_log("Greška pri dodavanju veze korisnik-tema: " . implode(", ", $stmt->errorInfo()));
                        }
                    }
                } else {
                    if (!$korisnikPostoji) {
                        error_log("Korisnik s ID {$userId} ne postoji.");
                    }
                    if (!$temaPostoji) {
                        error_log("Tema s ID {$tema['ID']} ne postoji.");
                    }
                    $conn->rollBack();
                }

                // Ako je sve prošlo u redu, commit transakciju
                $conn->commit();
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("PDO greška: " . $e->getMessage());
            }
        }
    }
}

// Dohvati podatke o istaknutim temama
$istaknuteTemePodaci = [];
foreach ($istaknuteTemeIds as $naziv => $id) {
    $stmt = $conn->prepare("
        SELECT t.ID AS theme_id, 
               t.naziv AS theme_name, 
               COUNT(p.ID) AS broj_pitanja
        FROM ep_teme t
        LEFT JOIN ep_pitanje p ON p.temaID = t.ID
        WHERE t.ID = ? AND t.aktivno = 1
        GROUP BY t.ID, t.naziv
    ");
    $stmt->execute([$id]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tema) {
        $istaknuteTemePodaci[] = $tema;
    }
}

// Dohvati sve teme iz baze
try {
    $stmt = $conn->query("SELECT ID, naziv FROM ep_teme WHERE aktivno = 1 ORDER BY naziv");
    $teme = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $teme = []; // Postavi prazan niz kao zadano
    $poruka = "Došlo je do greške pri dohvaćanju tema. Molimo pokušajte kasnije.";
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tehnička škola Čakovec | Odabir Teme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }
        body {
            background: #2e2e2e; /* Tamno siva pozadina inspirirana logom */
            color: #fff;
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(168,210,91,0.05)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(166,206,227,0.05)">TŠČ</text></svg>');
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
            background: linear-gradient(145deg, #3a3a3a, #222222); /* Tamno sivi gradijent */
            border: 2px solid #A8D25B; /* Zelena granica */
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            padding: 30px;
            border-radius: 0; /* Oštre ravne linije za tehnički stil */
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
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path d="M20,20 L180,20 L180,180 L20,180 Z" stroke="rgba(166,206,227,0.1)" fill="none" stroke-width="2" stroke-dasharray="10,5"/></svg>');
            pointer-events: none;
            opacity: 0.2;
        }
        h1, h2, h3 {
            color: #A8D25B; /* Zelena boja */
            text-shadow: 0 0 5px #A8D25B;
            font-family: 'Roboto', sans-serif;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            border-bottom: 2px solid #A6CEE3; /* Svijetloplava linija ispod */
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
            background-color: #A6CEE3;
        }
        .tema-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tema-card {
            background-color: #333333;
            padding: 20px;
            border-left: 4px solid #A8D25B; /* Zelena lijeva granica */
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
        }
        .tema-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(168, 210, 91, 0.2);
            border-left: 4px solid #A6CEE3;
        }
        .tema-card h3 {
            color: #A6CEE3; /* Svijetloplava boja */
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
            background-color: #A6CEE3; /* Svijetloplava */
            color: #2e2e2e;
            padding: 10px 20px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s ease;
            box-shadow: 0 0 5px #A6CEE3, 0 0 10px #A6CEE3;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            text-decoration: none;
        }
        .tema-btn:hover {
            background-color: #A8D25B; /* Zelena na hover */
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
            color: #2e2e2e;
        }
        .tema-btn i {
            margin-right: 5px;
        }
        .history-box {
            width: 100%;
            margin-top: 30px;
            background-color: #333333;
            padding: 20px;
            border-left: 4px solid #A6CEE3; /* Svijetloplava lijeva granica */
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .history-table th {
            background-color: rgba(166, 206, 227, 0.2);
            border-bottom: 2px solid #A8D25B;
            text-align: left;
            padding: 10px;
            color: #A8D25B;
            font-weight: bold;
        }
        .history-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(168, 210, 91, 0.1);
        }
        .history-table tr:hover {
            background-color: rgba(166, 206, 227, 0.05);
        }
        .score {
            color: #A8D25B;
            font-weight: bold;
        }
        .timestamp {
            color: #999;
            font-style: italic;
            font-size: 0.9rem;
        }
        .preview-button {
            background-color: #A6CEE3;
            padding: 5px 10px;
            color: #2e2e2e;
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 0;
            transition: 0.3s ease;
        }
        .preview-button:hover {
            background-color: #A8D25B;
            color: #2e2e2e;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #A6CEE3;
            color: #2e2e2e;
            text-decoration: none;
            border: none;
            transition: 0.3s ease;
            font-weight: bold;
        }
        .logout-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B;
        }
        .professor-btn {
            position: absolute;
            top: 20px;
            right: 160px; /* Pozicionirano lijevo od logout gumba */
            padding: 10px 20px;
            background-color: #A8D25B;
            color: #2e2e2e;
            text-decoration: none;
            border: none;
            transition: 0.3s ease;
            font-weight: bold;
        }
        .professor-btn:hover {
            background-color: #A6CEE3;
            box-shadow: 0 0 10px #A6CEE3;
        }
        .teacher-link {
            display: inline-block !important;
            background-color: #A8D25B;
            color: #2e2e2e;
            padding: 12px 24px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px !important;
            border-radius: 0;
            transition: 0.3s ease;
            box-shadow: 0 0 10px rgba(168, 210, 91, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 1000;
            position: relative; /* Osiguraj da element ostane vidljiv */
            visibility: visible !important; /* Forsiraj vidljivost */
            opacity: 1 !important; /* Osiguraj da je potpuno vidljiv */
        }
        .teacher-link:hover {
            background-color: #A6CEE3;
            color: #2e2e2e;
            box-shadow: 0 0 15px rgba(166, 206, 227, 0.5);
        }
        .mafija-icon {
            font-size: 40px;
            color: #A8D25B;
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
        <h1><i class="fas fa-cogs" style="margin-right: 10px;"></i>Tehnička škola Čakovec</h1>
        
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Odjava</a>
        <?php if ($userLevel == 1): ?>
            <a href="profesorski_panel.php" class="professor-btn"><i class="fas fa-chalkboard-teacher"></i> Profesorski Panel</a>
        <?php endif; ?>
        
        <!-- Novi gumbi za kvizove -->
        <div style="text-align: center; margin: 20px 0; position: relative; z-index: 9999;">
            <?php foreach ($istaknuteTemePodaci as $tema): ?>
                <a href="index.php?tema_id=<?= $tema['theme_id'] ?>" class="tema-btn" style="margin-right: 15px; background-color: <?= $tema['theme_name'] == 'Tehnički Izazov 2024' ? '#A8D25B' : '#A6CEE3' ?>; font-size: 1.2rem; padding: 15px 30px; box-shadow: 0 0 15px rgba(168, 210, 91, 0.5);">
                    <i class="fas <?= $tema['theme_name'] == 'Tehnički Izazov 2024' ? 'fa-microchip' : 'fa-laptop-code' ?>"></i> <?= htmlspecialchars($tema['theme_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="tema-container">
            <div class="mafija-icon" style="top: 10px; right: 10px;"><i class="fas fa-microchip"></i></div>
            <div class="mafija-icon" style="bottom: 10px; left: 10px;"><i class="fas fa-cog fa-spin"></i></div>
            
            <?php if ($isGuest): ?>
                <div style="background-color: rgba(166,206,227,0.1); padding: 10px; margin-bottom: 20px; border-left: 3px solid #A6CEE3;">
                    <i class="fas fa-info-circle"></i> Prijavljeni ste kao gost korisnik. Možete pregledavati sve teme, ali vaši rezultati neće biti spremljeni.
                </div>
            <?php endif; ?>
            
            <?php if ($poruka): ?>
                <div style="background-color: rgba(168,210,91,0.1); padding: 10px; margin-bottom: 20px; border-left: 3px solid #A8D25B;">
                    <?= htmlspecialchars($poruka) ?>
                </div>
            <?php endif; ?>
            
            <h2><i class="fas fa-clipboard-list" style="margin-right: 10px;"></i>Odaberi Temu za Testiranje</h2>
            
            <?php if (empty($korisnikTeme)): ?>
                <p>Nema dostupnih tema za testiranje.</p>
            <?php else: ?>
                <div class="tema-list">
                    <?php foreach ($korisnikTeme as $tema): ?>
                        <div class="tema-card">
                            <h3><?= htmlspecialchars($tema['theme_name']) ?></h3>
                            <div class="tema-info">
                                <p><i class="fas fa-tasks" style="color: #A6CEE3;"></i> Broj pitanja: <strong><?= $tema['broj_pitanja'] ?></strong></p>
                            </div>
                            <form action="index.php" method="POST">
                                <input type="hidden" name="tema_id" value="<?= $tema['theme_id'] ?>">
                                <button type="submit" class="tema-btn">
                                    <i class="fas fa-play"></i> Započni Test
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($examHistory) && !$isGuest): ?>
                <div class="history-box">
                    <h2><i class="fas fa-history" style="margin-right: 10px;"></i>Povijest testiranja</h2>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Vrijeme</th>
                                <th>Naziv teme</th>
                                <th>Rezultat</th>
                                <th>Trajanje</th>
                                <th>Akcije</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examHistory as $exam): ?>
                                <tr>
                                    <td class="timestamp"><?= htmlspecialchars($exam['vrijeme_kraja']) ?></td>
                                    <td><?= htmlspecialchars($exam['theme_name'] ?? 'Nepoznata tema') ?></td>
                                    <td class="score">
                                        <?= $exam['tocno_odgovori'] ?> / <?= $exam['ukupno_pitanja'] ?>
                                        (<?= round(($exam['tocno_odgovori'] / $exam['ukupno_pitanja']) * 100) ?>%)
                                    </td>
                                    <td><?= htmlspecialchars($exam['trajanje']) ?></td>
                                    <td>
                                        <a href="forms.php?exam_id=<?= $exam['exam_id'] ?>" class="preview-button">
                                            <i class="fas fa-search"></i> Detalji
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($isProfessor): ?>
                <div style="text-align: center; margin: 30px 0; position: relative; z-index: 9999; clear: both; display: block !important;">
                    <a href="dodaj_pitanje.php" class="teacher-link" style="margin-right: 15px;">
                        <i class="fas fa-plus-circle"></i> Dodaj pitanje
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Debug info - premješteno izvan tema-container -->
    <!-- <div class="tema-container" style="margin-top: 20px;">
        <h2><i class="fas fa-bug" style="margin-right: 10px;"></i>Debug Informacije</h2>
        <div style="background-color: rgba(0,0,0,0.1); padding: 10px; margin-top: 20px; border-left: 3px solid #A8D25B;">
            <p><strong>User Level:</strong> <?php echo $userLevel; ?></p>
            <p><strong>Is Professor:</strong> <?php echo $isProfessor ? 'Da' : 'Ne'; ?></p>
            <p><strong>Is Guest:</strong> <?php echo $isGuest ? 'Da' : 'Ne'; ?></p>
            <p><strong>User ID:</strong> <?php echo $userId; ?></p>
            <p><strong>Session Data:</strong></p>
            <pre style="background: #333; padding: 10px; overflow: auto;"><?php print_r($_SESSION); ?></pre>
        </div>
    </div> -->

    <script>
        // Padanje tehničkih ikona
        function createFallingIcons() {
            for (let i = 0; i < 30; i++) {
                setTimeout(() => {
                    const icon = document.createElement('div');
                    // Slučajni odabir tehničkih ikona
                    const icons = ['fas fa-cog', 'fas fa-microchip', 'fas fa-laptop-code', 'fas fa-tools', 'fas fa-memory'];
                    const randomIcon = icons[Math.floor(Math.random() * icons.length)];
                    
                    icon.innerHTML = `<i class="${randomIcon}"></i>`;
                    icon.style.position = 'fixed';
                    icon.style.color = '#A8D25B';
                    icon.style.fontSize = Math.random() * 20 + 10 + 'px';
                    icon.style.left = Math.random() * 100 + 'vw';
                    icon.style.top = '-20px';
                    icon.style.opacity = Math.random() * 0.7 + 0.3;
                    icon.style.zIndex = '1000';
                    icon.style.pointerEvents = 'none';
                    icon.style.transform = `rotate(${Math.random() * 360}deg)`;
                    icon.style.transition = 'top 5s linear, transform 5s linear';
                    
                    document.body.appendChild(icon);
                    
                    // Animacija padanja
                    setTimeout(() => {
                        icon.style.top = '105vh';
                        icon.style.transform = `rotate(${Math.random() * 720}deg)`;
                    }, 100);
                    
                    // Uklanjanje nakon animacije
                    setTimeout(() => {
                        document.body.removeChild(icon);
                    }, 5100);
                }, i * 500);
            }
        }
        
        // Pokreni padanje ikona kada se stranica učita
        document.addEventListener('DOMContentLoaded', function() {
            createFallingIcons();
        });
    </script>
</body>
</html>

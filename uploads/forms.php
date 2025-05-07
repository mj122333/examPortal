<?php
session_start();
// Isključujem prikaz svih upozorenja
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db_connection.php';

// Provjera strukture tablice
try {
    $desc = $conn->query("DESCRIBE ep_test_odgovori");
    $columns = [];
    
    if ($desc) {
        while ($row = $desc->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = $row['Type'];
        }
    }
} catch (Exception $e) {
    // Tihо ignoriramo, ne želimo da se pokaže greška
}

// Provjerite je li korisnik prijavljen
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Provjera je li gost korisnik
$isGuest = isset($_SESSION['razina']) && $_SESSION['razina'] == 3;

// Provjeri postoji li vrijeme početka u sesiji, ako ne postoji, postavi trenutno vrijeme
if (!isset($_SESSION['quiz_start_time'])) {
    $_SESSION['quiz_start_time'] = date("Y-m-d H:i:s");
}

// Provjeri postoje li odgovori korisnika
if (isset($_POST['answers'])) {
    $score = 0;
    $totalQuestions = intval($_POST['total']);
    $userAnswers = json_decode($_POST['answers'], true);
    
    // Debugging - prikaži što smo dobili od index.php
    echo "<div style='color: white; background: rgba(0,0,0,0.8); padding: 10px; margin: 10px; border: 1px solid #A8D25B;'>";
    echo "<h3>Debugging podataka primljenih od index.php</h3>";
    echo "<pre>";
    echo "Total questions: " . $totalQuestions . "\n";
    echo "User answers struktura:\n";
    print_r($userAnswers);
    echo "</pre>";
    echo "</div>";
    
    // Provjeri jesu li podaci ispravni
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Greška s JSON formatom
        $error = "Greška: Neispravni format odgovora.";
    } else {
        // Provjeri postoje li podaci o odgovorima u sesiji
        if (!isset($_SESSION['quiz_answers'])) {
            $error = "Greška: Podaci o kvizu nisu dostupni.";
            
            // Debugging - prikaži problem sa SESSION
            echo "<div style='color: white; background: rgba(255,0,0,0.2); padding: 10px; margin: 10px; border: 1px solid red;'>";
            echo "<h3>Greška: SESSION['quiz_answers'] nije postavljen</h3>";
            echo "<p>Sadržaj SESSION varijable:</p>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";
            echo "</div>";
        } else {
            // Debugging - prikaži SESSION quiz_answers
            echo "<div style='color: white; background: rgba(0,0,0,0.8); padding: 10px; margin: 10px; border: 1px solid #A6CEE3;'>";
            echo "<h3>SESSION['quiz_answers'] sadržaj</h3>";
            echo "<pre>";
            print_r($_SESSION['quiz_answers']);
            echo "</pre>";
            echo "</div>";
            
            $correctAnswers = [];
            $wrongAnswers = [];
            
            // Obradi svaki korisnikov odgovor
            foreach ($userAnswers as $answer) {
                $questionIndex = $answer['questionIndex'];
                if (!isset($questions[$questionIndex])) continue;
                
                $question = $questions[$questionIndex];
                $selectedAnswerIndex = $answer['selectedAnswer'];
                $answerOptions = explode("|", $question["answers"]);
                $correctIndex = $question["correctAnswer"];
                
                $userAnswerText = isset($answerOptions[$selectedAnswerIndex]) ? $answerOptions[$selectedAnswerIndex] : "Nije odabrano";
                $correctAnswerText = isset($answerOptions[$correctIndex]) ? $answerOptions[$correctIndex] : "Nije definirano";
                $explanationText = isset($question["hint"]) && !empty($question["hint"]) ? $question["hint"] : "Nema dodatnog objašnjenja.";
                
                if ($answer['isCorrect']) {
                    $correctAnswers[] = [
                        "question"       => $question["question"],
                        "your_answer"    => $userAnswerText,
                        "correct_answer" => $correctAnswerText,
                        "explanation"    => $explanationText
                    ];
                } else {
                    $wrongAnswers[] = [
                        "question"       => $question["question"],
                        "your_answer"    => $userAnswerText,
                        "correct_answer" => $correctAnswerText,
                        "explanation"    => $explanationText
                    ];
                }
            }
            
            // Postavi score za daljnju obradu
            $_POST['score'] = $score;
        }
    }
}

// Dohvati podatke o korisniku - ISPRAVLJEN KOD
try {
    if (!$isGuest) {
        $stmt = $conn->prepare("SELECT * FROM ep_korisnik WHERE ID = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $korisnik = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$korisnik) {
            throw new Exception("Korisnik nije pronađen");
        }
    } else {
        $korisnik = [
            'ime' => 'Gost korisnik',
            'razinaID' => 3
        ];
    }
} catch (PDOException $e) {
    die("Greška pri dohvaćanju podataka o korisniku: " . $e->getMessage());
} catch (Exception $e) {
    die("Greška: " . $e->getMessage());
}

// Provjeri postoji li exam_id u GET parametrima i izvrši odgovarajuću validaciju
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (isset($_GET['exam_id']) && $exam_id > 0) {
    // === REŽIM PREGLEDA RANIJEG ISPITA ===

    // Uključi datoteku za konekciju s bazom
    require_once 'db_connection.php';  // Uključivanje db_connection.php

    // Dohvati osnovne podatke o ispitu iz ep_test
    $stmt = $conn->prepare("
        SELECT t.*, te.naziv as tema_naziv 
        FROM ep_test t 
        LEFT JOIN ep_teme te ON t.kviz_id = te.ID 
        WHERE t.ID = :exam_id AND (t.korisnikID = :user_id OR :is_guest = 1)
        LIMIT 1
    ");
    $stmt->execute([
        ':exam_id' => $exam_id,
        ':user_id' => $_SESSION['user_id'] ?? 0,
        ':is_guest' => $isGuest ? 1 : 0
    ]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        die("Ispit nije pronađen ili nemate pristup ovom ispitu.");
    }

    // Dohvati sve odgovore iz ep_test_odgovori za ovaj ispit
    try {
        // Prvo dohvati samo odgovore iz ove tablice
        $stmt2 = $conn->prepare("
            SELECT * 
            FROM ep_test_odgovori 
            WHERE test_id = :exam_id
            ORDER BY id ASC
        ");
        $stmt2->execute([':exam_id' => $exam_id]);
        $allAnswers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        // Provjeri postoje li odgovori
        if (!$allAnswers || count($allAnswers) == 0) {
            echo "<div style='color: white; background: rgba(255,0,0,0.3); padding: 15px; margin: 15px; border: 2px solid red;'>
                <h3>Greška pri dohvaćanju odgovora</h3>
                <p>Nisu pronađeni odgovori za ispit ID: {$exam_id}</p>
                <p>Provjerite da li u tablici ep_test_odgovori postoje zapisi za ovaj ispit.</p>
            </div>";
        }

        // Razdvoji točne i netočne odgovore
        $correctAnswers = [];
        $wrongAnswers   = [];
        
        foreach ($allAnswers as $row) {
            // Pripremi podatke za prikaz - koristimo direktne nazive stupaca iz baze
            $entry = [
                "question"       => $row["question_text"] ?? "Nepoznato pitanje",
                "your_answer"    => $row["user_answer_text"] ?? "Nije odgovoreno",
                "correct_answer" => $row["correct_answer_text"] ?? "Nije dostupno",
                "explanation"    => $row["explanation"] ?? "Nema dodatnog objašnjenja."
            ];
            
            // Dodatna provjera - osiguraj da su sve vrijednosti stringovi
            foreach ($entry as $key => $value) {
                if ($value === null) {
                    $entry[$key] = "Nije dostupno";
                }
            }
            
            // Provjeri gdje dodati ovaj odgovor - osiguraj da je is_correct uvijek definirano
            $isCorrect = isset($row["is_correct"]) ? (bool)$row["is_correct"] : false;
            
            if ($isCorrect) {
                $correctAnswers[] = $entry;
            } else {
                $wrongAnswers[] = $entry;
            }
        }
    } catch (PDOException $e) {
        // Ispiši informativniju poruku o grešci
        die("Greška pri dohvaćanju podataka o ispitu: " . $e->getMessage());
    }

    // Varijable za prikaz
    $score           = $exam['rezultat'];
    $totalQuestions  = $exam['ukupno_pitanja'];
    $correctCount    = $exam['tocno_odgovori'];
    $incorrectCount  = $exam['netocno_odgovori'];
    $trajanje        = $exam['trajanje'];
    $vrijeme_pocetka = $exam['vrijeme_pocetka'];
    $vrijeme_kraja   = $exam['vrijeme_kraja'];
    $tema_naziv      = $exam['tema_naziv'] ?? 'Nepoznata tema';
    ?>
    <!-- Debug informacije za praćenje problema -->
    <div style="display: none; position: absolute; top: 10px; left: 10px; padding: 10px; background: rgba(0,0,0,0.8); color: lime; font-family: monospace; font-size: 12px; max-width: 500px; max-height: 300px; overflow: auto; z-index: 9999;">
        <h4>Debug Info:</h4>
        <p>Score: <?= $score ?>, Total: <?= $totalQuestions ?></p>
        <p>correctCount: <?= $correctCount ?>, incorrectCount: <?= $incorrectCount ?></p>
        <p>correctAnswers.length: <?= count($correctAnswers) ?></p>
        <p>wrongAnswers.length: <?= count($wrongAnswers) ?></p>
        <pre><?= print_r($correctAnswers, true) ?></pre>
        <pre><?= print_r($wrongAnswers, true) ?></pre>
    </div>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tehnička škola Čakovec | Rezultati</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
        <style>
            /* Slični stilovi kao kod završetka kviza */
            * {
                margin: 0; 
                padding: 0; 
                box-sizing: border-box;
                font-family: 'Roboto', sans-serif;
            }
            body {
                background: #2e2e2e; /* Tamno siva pozadina inspirirana logom */
                color: #fff;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(168,210,91,0.05)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(166,206,227,0.05)">TŠČ</text></svg>');
            }
            .results-container {
                width: 100%;
                max-width: 1200px;
                background: linear-gradient(145deg, #3a3a3a, #222222); /* Tamno sivi gradijent */
                border: 2px solid #A8D25B; /* Zelena granica */
                box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
                border-radius: 0; /* Oštre ravne linije za tehnički stil */
                padding: 30px;
                margin: 20px auto;
                position: relative;
                overflow: hidden;
            }
            .results-container:before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path d="M20,20 L180,20 L180,180 L20,180 Z" stroke="rgba(166,206,227,0.1)" fill="none" stroke-width="2" stroke-dasharray="10,5"/></svg>');
                pointer-events: none;
                opacity: 0.2;
                z-index: 0;
            }
            h1, h2 {
                text-align: center;
                color: #A8D25B; /* Zelena boja */
                text-shadow: 0 0 5px #A8D25B;
                margin-bottom: 20px;
                font-family: 'Roboto', sans-serif;
                font-weight: bold;
                letter-spacing: 2px;
                text-transform: uppercase;
                position: relative;
                z-index: 1;
            }
            .score {
                text-align: center;
                font-size: 1.6rem;
                margin-bottom: 30px;
                color: #A6CEE3; /* Svijetloplava boja */
                text-shadow: 0 0 5px rgba(166, 206, 227, 0.5);
                position: relative;
                z-index: 1;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.7);
                border-left: 4px solid #A8D25B;
            }
            .details {
                text-align: center;
                margin-bottom: 30px;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.5);
                border-left: 4px solid #A6CEE3;
                position: relative;
                z-index: 1;
            }
            .details strong {
                color: #A8D25B;
                margin-right: 5px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
                position: relative;
                z-index: 1;
                opacity: 1; /* promijenjeno s 0 na 1 da se tablica odmah vidi */
                transform: translateY(0); /* promijenjeno da nema translacije */
                transition: opacity 0.5s ease, transform 0.5s ease;
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid rgba(168, 210, 91, 0.3);
            }
            th {
                background-color: rgba(91, 91, 91, 0.2);
                border-bottom: 2px solid #A8D25B;
                color: #A8D25B;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .correct {
                background-color: rgba(168, 210, 91, 0.1);
                border-left: 3px solid #A8D25B;
            }
            .incorrect {
                background-color: rgba(100, 100, 100, 0.2);
                border-left: 3px solid #5B5B5B;
            }
            .results-btn {
                display: block;
                width: 250px;
                margin: 0 auto 20px auto;
                padding: 14px 28px;
                background-color: #5B5B5B; /* Siva */
                color: #fff;
                border: none;
                border-radius: 0;
                cursor: pointer;
                font-size: 1.1rem;
                text-align: center;
                box-shadow: 0 0 5px #5B5B5B, 0 0 10px #5B5B5B;
                transition: 0.3s ease;
                text-transform: uppercase;
                font-weight: bold;
                letter-spacing: 2px;
                position: relative;
                z-index: 1;
            }
            .results-btn:hover {
                background-color: #A8D25B; /* Zelena na hover */
                box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
                color: #2e2e2e;
            }
            .mafija-icon {
                position: absolute;
                font-size: 40px;
                color: #A8D25B;
                opacity: 0.1;
                z-index: 0;
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
                .results-container {
                    padding: 15px;
                }
                table {
                    font-size: 0.9rem;
                }
                th, td {
                    padding: 8px;
                }
            }
            .results-table {
                margin-bottom: 40px;
                background-color: rgba(30, 30, 40, 0.7);
                border: 1px solid rgba(168, 210, 91, 0.3);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }
            .empty-message {
                text-align: center;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.5);
                border-left: 3px solid #A8D25B;
                color: #A6CEE3;
            }
            .count-badge {
                display: inline-block;
                background-color: rgba(168, 210, 91, 0.2);
                color: #A8D25B;
                padding: 3px 10px;
                border-radius: 15px;
                margin-left: 10px;
                font-size: 0.9em;
                border: 1px solid #A8D25B;
                box-shadow: 0 0 5px rgba(168, 210, 91, 0.3);
            }
        </style>
    </head>
    <body>
    <div class="results-container">
        <div class="mafija-icon top-right"><i class="fas fa-microchip"></i></div>
        <div class="mafija-icon bottom-left"><i class="fas fa-cog"></i></div>
        
        <h1>
            <i class="fas fa-cogs" style="margin-right: 10px;"></i>Rezultati Tehničke škole<i class="fas fa-cogs" style="margin-left: 10px;"></i>
        </h1>
        
        <div class="score">
            <span style="font-size: 2.2rem; color: #A8D25B;"><?= $correctCount ?></span> / <?= $totalQuestions ?> 
            (<?= min(100, round(($correctCount / $totalQuestions) * 100)) ?>%)
            <?php if ($correctCount == $totalQuestions): ?>
                <span style="display: block; margin-top: 10px; font-size: 1.8rem; text-shadow: 0 0 10px #A8D25B;">⚡ TEHNIČKI GENIJE! ⚡</span>
            <?php endif; ?>
        </div>

        <?php if ($isGuest): ?>
            <div class="guest-notice" style="text-align: center; margin-bottom: 20px; padding: 15px; background-color: rgba(166, 206, 227, 0.2); border-left: 4px solid #A6CEE3; color: #A6CEE3;">
                <i class="fas fa-info-circle"></i> Kao gost korisnik, vaši rezultati neće biti spremljeni u bazi podataka.
            </div>
        <?php endif; ?>
        
        <div class="details">
            <strong>Vrijeme početka:</strong> <?= htmlspecialchars($vrijeme_pocetka) ?><br>
            <strong>Vrijeme završetka:</strong> <?= htmlspecialchars($vrijeme_kraja) ?><br>
            <strong>Trajanje:</strong> <?= htmlspecialchars($trajanje) ?><br>
            <strong>Tema:</strong> <?= htmlspecialchars($tema_naziv) ?>
        </div>

        <!-- DEBUGGING INFO - Prikazuje informacije o podacima -->
        <div style="background-color: #333; border: 1px solid #A8D25B; padding: 15px; margin-bottom: 20px; font-family: monospace; font-size: 14px;">
            <h3 style="color: #A8D25B; margin-bottom: 10px;">Dijagnostičke informacije</h3>
            <p>Test ID: <?= isset($testId) ? $testId : 'nije postavljen' ?></p>
            <p>Broj točnih odgovora u nizu: <?= count($correctAnswers) ?></p>
            <p>Broj netočnih odgovora u nizu: <?= count($wrongAnswers) ?></p>
            <p>Struktura podataka:</p>
            <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
Točni odgovori:
<?= print_r($correctAnswers, true) ?>

Netočni odgovori:
<?= print_r($wrongAnswers, true) ?>

Svi odgovori iz baze:
<?= isset($allAnswers) ? print_r($allAnswers, true) : 'Nije dostupno' ?>
            </pre>
            <p>Provjera tablice ep_test_odgovori:</p>
            <?php
            try {
                $debugStmt = $conn->prepare("SELECT COUNT(*) as broj FROM ep_test_odgovori WHERE test_id = :test_id");
                $debugStmt->execute([':test_id' => isset($testId) ? $testId : (isset($exam_id) ? $exam_id : 0)]);
                $brojZapisa = $debugStmt->fetchColumn();
                echo "<p>Broj zapisa u tablici: " . $brojZapisa . "</p>";
            } catch (Exception $e) {
                echo "<p>Greška pri dohvatu: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <?php if (!empty($correctAnswers)): ?>
            <!-- Točni odgovori -->
            <h2><i class="fas fa-check-circle"></i> Točni odgovori (<?php echo count($correctAnswers); ?>)</h2>
            <table class="results-table" id="correctTable">
                <thead>
                    <tr>
                        <th style="width: 40%;">Pitanje</th>
                        <th style="width: 20%;">Vaš odgovor</th>
                        <th style="width: 20%;">Točan odgovor</th>
                        <th style="width: 20%;">Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($correctAnswers) > 0): ?>
                        <?php foreach ($correctAnswers as $index => $answer): ?>
                            <tr class="correct">
                                <td><?php echo htmlspecialchars($answer['question']); ?></td>
                                <td><?php echo htmlspecialchars($answer['your_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['explanation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-message">Nema točnih odgovora</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Netočni odgovori -->
            <h2><i class="fas fa-times-circle"></i> Netočni odgovori (<?php echo count($wrongAnswers); ?>)</h2>
            <table class="results-table" id="incorrectTable">
                <thead>
                    <tr>
                        <th style="width: 40%;">Pitanje</th>
                        <th style="width: 20%;">Vaš odgovor</th>
                        <th style="width: 20%;">Točan odgovor</th>
                        <th style="width: 20%;">Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($wrongAnswers) > 0): ?>
                        <?php foreach ($wrongAnswers as $index => $answer): ?>
                            <tr class="incorrect">
                                <td><?php echo htmlspecialchars($answer['question']); ?></td>
                                <td><?php echo htmlspecialchars($answer['your_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['explanation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-message">Nema netočnih odgovora</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema točnih odgovora.</div>
            
            <!-- Dodatne dijagnostičke informacije kada nema odgovora -->
            <div style="background-color: #333; border: 1px solid #A8D25B; padding: 15px; margin: 20px 0; font-family: monospace; font-size: 14px;">
                <h3 style="color: #A8D25B; margin-bottom: 10px;">Dijagnostičke informacije</h3>
                <p>Test ID: <?= isset($testId) ? $testId : 'nije postavljen' ?></p>
                
                <p>Broj očekivanih točnih odgovora: <?= isset($score) ? $score : 'nije postavljeno' ?></p>
                <p>Ukupno pitanja: <?= isset($totalQuestions) ? $totalQuestions : 'nije postavljeno' ?></p>
                <p>Broj točnih odgovora u nizu: <?= count($correctAnswers) ?></p>
                <p>Broj netočnih odgovora u nizu: <?= count($wrongAnswers) ?></p>
                
                <p>Debug correctAnswers i wrongAnswers:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 200px;">
correctAnswers: <?= print_r($correctAnswers, true) ?>
wrongAnswers: <?= print_r($wrongAnswers, true) ?>
                </pre>
                
                <p>POST podaci:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 200px;">
<?= print_r($_POST, true) ?>
                </pre>
                
                <p>SESSION podaci:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 200px;">
<?= print_r($_SESSION, true) ?>
                </pre>
                
                <p>Dekodirana POST['answers']:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
<?= isset($_POST['answers']) ? print_r(json_decode($_POST['answers'], true), true) : 'Nije dostupno' ?>
                </pre>
                
                <p>Podaci o pitanjima:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
<?= print_r($questions, true) ?>
                </pre>
                
                <p>Razlog problema:</p>
                <ul style="background-color: #222; padding: 10px; color: #dc3545;">
                    <li>Informacije iz index.php se ne prenose ispravno u forms.php</li>
                    <li>Potrebno je dodati informaciju o točnosti odgovora (isCorrect) u userAnswers niz</li>
                    <li>Provjerite je li correctIndex ispravno postavljen u JavaScript kodu</li>
                    <li>Provjerite jesu li svi podaci ispravno povezani</li>
                </ul>
                
                <p>Rješenje:</p>
                <ol style="background-color: #222; padding: 10px; color: #A8D25B;">
                    <li>U index.php u funkciji selectAnswer dodajte provjeru točnosti i postavite isCorrect vrijednost</li>
                    <li>Osigurajte da se svi potrebni podaci šalju kroz POST (isCorrect, questionIndex, selectedAnswer)</li>
                    <li>Provjerite strukturu tablice ep_test_odgovori</li>
                </ol>
            </div>
        <?php endif; ?>

        <a href="odabir_teme.php" class="results-btn">Natrag na odabir teme</a>
        <a href="mailto:<?= $korisnik['email'] ?>?subject=Rezultati kviza&body=<?= urlencode("Rezultat kviza: $correctCount/$totalQuestions") ?>" class="results-btn">ŠALJI NA MAIL</a>
    </div>
    </body>
    </html>
<?php
} else {
    // === REŽIM RADA KVIZA ===

    // Dohvati sva pitanja i odgovore iz baze
    $stmt = $conn->prepare("
        SELECT p.ID as pitanjeID, p.tekst_pitanja, p.hint, o.ID as odgovorID, o.tekst, o.is_correct
        FROM ep_pitanje p
        JOIN ep_odgovori o ON p.ID = o.pitanjeID
        WHERE p.temaID = :temaID
        ORDER BY p.ID, o.ID
    ");
    $stmt->execute([':temaID' => $_SESSION['temaID']]);
    $pitanja = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Provjeri postoje li pitanja
    if (!$pitanja || count($pitanja) == 0) {
        die("Nema pitanja za ovu temu.");
    }
    
    // Pripremi podatke za prikaz
    $questions = [];
    $currentQuestionId = null;
    $currentQuestion = null;
    $currentAnswers = [];
    
    foreach ($pitanja as $row) {
        if ($row['pitanjeID'] != $currentQuestionId) {
            // Novo pitanje
            if ($currentQuestion !== null) {
                $questions[] = [
                    'question' => $currentQuestion,
                    'hint' => $currentHint,
                    'answers' => $currentAnswers,
                    'correctAnswer' => $correctAnswer
                ];
            }
            
            $currentQuestionId = $row['pitanjeID'];
            $currentQuestion = $row['tekst_pitanja'];
            $currentHint = $row['hint'];
            $currentAnswers = [];
            $correctAnswer = null;
        }
        
        // Dodaj odgovor
        $currentAnswers[] = [
            'id' => $row['odgovorID'],
            'text' => $row['tekst']
        ];
        
        // Provjeri je li točan odgovor
        if ($row['is_correct']) {
            $correctAnswer = $row['odgovorID'];
        }
    }
    
    // Dodaj zadnje pitanje
    if ($currentQuestion !== null) {
        $questions[] = [
            'question' => $currentQuestion,
            'hint' => $currentHint,
            'answers' => $currentAnswers,
            'correctAnswer' => $correctAnswer
        ];
    }
    
    // Spremi podatke u sesiju
    $_SESSION['quiz_answers'] = $questions;
    
    // Pripremi podatke za JSON
    $questionsJson = json_encode($questions);
    
    // Pripremi podatke za prikaz
    $totalQuestions = count($questions);
    $timeLimit = 10 * $totalQuestions; // 10 sekundi po pitanju
    $timeLimitFormatted = gmdate("H:i:s", $timeLimit);

    $correctCount   = 0;
    $incorrectCount = 0;

    // Izračun postotka uspješnosti uz provjeru dijeljenja s nulom
    $scorePercentage = $totalQuestions > 0 ? ($score / $totalQuestions) * 100 : 0;

    // Pripremi $answers za kasniji unos u bazu
    $answers = [];
    
    // Napunimo $answers iz $correctAnswers i $wrongAnswers
    foreach ($correctAnswers as $answer) {
        $answers[] = [
            'question' => $answer['question'] ?? 'Nepoznato pitanje',
            'user_answer' => $answer['your_answer'] ?? 'Nije odgovoreno',
            'correct_answer' => $answer['correct_answer'] ?? 'Nije dostupno',
            'is_correct' => 1,
            'explanation' => $answer['explanation'] ?? "Nema dodatnog objašnjenja."
        ];
    }
    
    foreach ($wrongAnswers as $answer) {
        $answers[] = [
            'question' => $answer['question'] ?? 'Nepoznato pitanje',
            'user_answer' => $answer['your_answer'] ?? 'Nije odgovoreno',
            'correct_answer' => $answer['correct_answer'] ?? 'Nije dostupno',
            'is_correct' => 0,
            'explanation' => $answer['explanation'] ?? "Nema dodatnog objašnjenja."
        ];
    }

    // Postaviti ispravne vrijednosti za $correctCount i $incorrectCount
    $correctCount = count($correctAnswers);
    $incorrectCount = count($wrongAnswers);
?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tehnička škola Čakovec | Kviz</title>
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
                background: #2e2e2e;
                color: #fff;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(168,210,91,0.05)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(166,206,227,0.05)">TŠČ</text></svg>');
            }
            .quiz-container {
                width: 100%;
                max-width: 800px;
                background: linear-gradient(145deg, #3a3a3a, #222222);
                border: 2px solid #A8D25B;
                box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
                border-radius: 0;
                padding: 30px;
                margin: 20px auto;
                position: relative;
                overflow: hidden;
            }
            .quiz-container:before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><path d="M20,20 L180,20 L180,180 L20,180 Z" stroke="rgba(166,206,227,0.1)" fill="none" stroke-width="2" stroke-dasharray="10,5"/></svg>');
                pointer-events: none;
                opacity: 0.2;
                z-index: 0;
            }
            h1 {
                text-align: center;
                color: #A8D25B;
                text-shadow: 0 0 5px #A8D25B;
                margin-bottom: 20px;
                font-family: 'Roboto', sans-serif;
                font-weight: bold;
                letter-spacing: 2px;
                text-transform: uppercase;
                position: relative;
                z-index: 1;
            }
            .question {
                font-size: 1.2rem;
                margin-bottom: 20px;
                color: #A6CEE3;
                text-shadow: 0 0 5px rgba(166, 206, 227, 0.5);
                position: relative;
                z-index: 1;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.7);
                border-left: 4px solid #A8D25B;
            }
            .answers {
                margin-bottom: 30px;
            }
            .answer {
                background-color: rgba(40, 40, 40, 0.5);
                color: #fff;
                padding: 15px;
                margin-bottom: 10px;
                cursor: pointer;
                transition: background-color 0.3s;
                border-left: 4px solid #A6CEE3;
                position: relative;
                z-index: 1;
            }
            .answer:hover {
                background-color: rgba(166, 206, 227, 0.2);
            }
            .answer.selected {
                background-color: #A8D25B;
                color: #2e2e2e;
                border-left-color: #A8D25B;
            }
            .submit-btn {
                display: block;
                width: 250px;
                margin: 0 auto 20px auto;
                padding: 14px 28px;
                background-color: #5B5B5B;
                color: #fff;
                border: none;
                border-radius: 0;
                cursor: pointer;
                font-size: 1.1rem;
                text-align: center;
                box-shadow: 0 0 5px #5B5B5B, 0 0 10px #5B5B5B;
                transition: 0.3s ease;
                text-transform: uppercase;
                font-weight: bold;
                letter-spacing: 2px;
                position: relative;
                z-index: 1;
            }
            .submit-btn:hover {
                background-color: #A8D25B;
                box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
                color: #2e2e2e;
            }
            .timer {
                text-align: center;
                font-size: 1.6rem;
                margin-bottom: 30px;
                color: #A6CEE3;
                text-shadow: 0 0 5px rgba(166, 206, 227, 0.5);
                position: relative;
                z-index: 1;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.7);
                border-left: 4px solid #A8D25B;
            }
            .mafija-icon {
                position: absolute;
                font-size: 40px;
                color: #A8D25B;
                opacity: 0.1;
                z-index: 0;
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
                .quiz-container {
                    padding: 15px;
                }
                .question {
                    font-size: 1rem;
                }
                .answer {
                    font-size: 0.9rem;
                }
            }
            .results-table {
                margin-bottom: 40px;
                background-color: rgba(30, 30, 40, 0.7);
                border: 1px solid rgba(168, 210, 91, 0.3);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }
            .empty-message {
                text-align: center;
                padding: 15px;
                background-color: rgba(40, 40, 40, 0.5);
                border-left: 3px solid #A8D25B;
                color: #A6CEE3;
            }
            .count-badge {
                display: inline-block;
                background-color: rgba(168, 210, 91, 0.2);
                color: #A8D25B;
                padding: 3px 10px;
                border-radius: 15px;
                margin-left: 10px;
                font-size: 0.9em;
                border: 1px solid #A8D25B;
                box-shadow: 0 0 5px rgba(168, 210, 91, 0.3);
            }
        </style>
    </head>
    <body>
    <div class="results-container">
        <div class="mafija-icon top-right"><i class="fas fa-microchip"></i></div>
        <div class="mafija-icon bottom-left"><i class="fas fa-cog"></i></div>
        
        <h1>
            <i class="fas fa-cogs" style="margin-right: 10px;"></i>Rezultati Tehničke škole<i class="fas fa-cogs" style="margin-left: 10px;"></i>
        </h1>
        
        <div class="score">
            <span style="font-size: 2.2rem; color: #A8D25B;"><?= $correctCount ?></span> / <?= $totalQuestions ?> 
            (<?= min(100, round(($correctCount / $totalQuestions) * 100)) ?>%)
            <?php if ($correctCount == $totalQuestions): ?>
                <span style="display: block; margin-top: 10px; font-size: 1.8rem; text-shadow: 0 0 10px #A8D25B;">⚡ TEHNIČKI GENIJE! ⚡</span>
            <?php endif; ?>
        </div>

        <?php if ($isGuest): ?>
            <div class="guest-notice" style="text-align: center; margin-bottom: 20px; padding: 15px; background-color: rgba(166, 206, 227, 0.2); border-left: 4px solid #A6CEE3; color: #A6CEE3;">
                <i class="fas fa-info-circle"></i> Kao gost korisnik, vaši rezultati neće biti spremljeni u bazi podataka.
            </div>
        <?php endif; ?>
        
        <div class="details">
            <strong>Vrijeme početka:</strong> <?= htmlspecialchars($vrijeme_pocetka) ?><br>
            <strong>Vrijeme završetka:</strong> <?= htmlspecialchars($vrijeme_kraja) ?><br>
            <strong>Trajanje:</strong> <?= htmlspecialchars($trajanje) ?><br>
            <strong>Tema:</strong> <?= htmlspecialchars($tema_naziv) ?>
        </div>

        <!-- DEBUGGING INFO - Prikazuje informacije o podacima -->
        <div style="background-color: #333; border: 1px solid #A8D25B; padding: 15px; margin-bottom: 20px; font-family: monospace; font-size: 14px;">
            <h3 style="color: #A8D25B; margin-bottom: 10px;">Dijagnostičke informacije</h3>
            <p>Test ID: <?= isset($testId) ? $testId : 'nije postavljen' ?></p>
            <p>Broj točnih odgovora u nizu: <?= count($correctAnswers) ?></p>
            <p>Broj netočnih odgovora u nizu: <?= count($wrongAnswers) ?></p>
            <p>Struktura podataka:</p>
            <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
Točni odgovori:
<?= print_r($correctAnswers, true) ?>

Netočni odgovori:
<?= print_r($wrongAnswers, true) ?>

Svi odgovori iz baze:
<?= isset($allAnswers) ? print_r($allAnswers, true) : 'Nije dostupno' ?>
            </pre>
            <p>Provjera tablice ep_test_odgovori:</p>
            <?php
            try {
                $debugStmt = $conn->prepare("SELECT COUNT(*) as broj FROM ep_test_odgovori WHERE test_id = :test_id");
                $debugStmt->execute([':test_id' => isset($testId) ? $testId : (isset($exam_id) ? $exam_id : 0)]);
                $brojZapisa = $debugStmt->fetchColumn();
                echo "<p>Broj zapisa u tablici: " . $brojZapisa . "</p>";
            } catch (Exception $e) {
                echo "<p>Greška pri dohvatu: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

        <?php if (!empty($correctAnswers)): ?>
            <!-- Točni odgovori -->
            <h2><i class="fas fa-check-circle"></i> Točni odgovori (<?php echo count($correctAnswers); ?>)</h2>
            <table class="results-table" id="correctTable">
                <thead>
                    <tr>
                        <th style="width: 40%;">Pitanje</th>
                        <th style="width: 20%;">Vaš odgovor</th>
                        <th style="width: 20%;">Točan odgovor</th>
                        <th style="width: 20%;">Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($correctAnswers) > 0): ?>
                        <?php foreach ($correctAnswers as $index => $answer): ?>
                            <tr class="correct">
                                <td><?php echo htmlspecialchars($answer['question']); ?></td>
                                <td><?php echo htmlspecialchars($answer['your_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['explanation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-message">Nema točnih odgovora</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Netočni odgovori -->
            <h2><i class="fas fa-times-circle"></i> Netočni odgovori (<?php echo count($wrongAnswers); ?>)</h2>
            <table class="results-table" id="incorrectTable">
                <thead>
                    <tr>
                        <th style="width: 40%;">Pitanje</th>
                        <th style="width: 20%;">Vaš odgovor</th>
                        <th style="width: 20%;">Točan odgovor</th>
                        <th style="width: 20%;">Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($wrongAnswers) > 0): ?>
                        <?php foreach ($wrongAnswers as $index => $answer): ?>
                            <tr class="incorrect">
                                <td><?php echo htmlspecialchars($answer['question']); ?></td>
                                <td><?php echo htmlspecialchars($answer['your_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                                <td><?php echo htmlspecialchars($answer['explanation']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-message">Nema netočnih odgovora</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema točnih odgovora.</div>
            
            <!-- Dodatne dijagnostičke informacije kada nema odgovora -->
            <div style="background-color: #333; border: 1px solid #A8D25B; padding: 15px; margin: 20px 0; font-family: monospace; font-size: 14px;">
                <h3 style="color: #A8D25B; margin-bottom: 10px;">Dijagnostičke informacije</h3>
                <p>Test ID: <?= isset($testId) ? $testId : 'nije postavljen' ?></p>
                <p>POST podaci:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
<?= print_r($_POST, true) ?>
                </pre>
                
                <p>SESSION podaci:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
<?= print_r($_SESSION, true) ?>
                </pre>
                
                <p>Dohvaćena pitanja:</p>
                <pre style="background-color: #222; padding: 10px; color: #A6CEE3; overflow: auto; max-height: 300px;">
<?= print_r($questions, true) ?>
                </pre>
                
                <p>Razlog problema:</p>
                <ul style="background-color: #222; padding: 10px; color: #dc3545;">
                    <li>Provjerite jesu li podaci iz kviza pravilno poslani - obično se šalju u POST kao 'answers' i sadrže JSON s odgovorima</li>
                    <li>Provjerite postoje li točni odgovori u bazi podataka za svako pitanje</li>
                    <li>Provjerite ispravnost funkcije u index.php koja priprema pitanja i odgovore</li>
                    <li>Provjerite jesu li u SESSION['quiz_answers'] ispravno postavljeni točni odgovori</li>
                </ul>
                
                <p>Rješenje:</p>
                <ol style="background-color: #222; padding: 10px; color: #A8D25B;">
                    <li>Dodajte u index.php kod koji će u niz sa svakim pitanjem uključiti i oznaku točnog odgovora (correctAnswer)</li>
                    <li>Osigurajte da prijenosom POST podataka šaljete informaciju o tome koji su odgovori točni</li>
                    <li>Provjerite strukturu tablice ep_test_odgovori - trebaju stupci: test_id, question_text, user_answer_text, correct_answer_text, is_correct</li>
                </ol>
            </div>
        <?php endif; ?>

        <a href="odabir_teme.php" class="results-btn">
            <i class="fas fa-arrow-left"></i> Natrag na odabir teme
        </a>

        <div style="text-align: center; margin-top: 20px; margin-bottom: 30px; position: relative; z-index: 1;">
            <button id="send-email-btn" class="results-btn" style="background-color: #A6CEE3; color: #2e2e2e; box-shadow: 0 0 10px #A6CEE3, 0 0 20px #A6CEE3;">
                <i class="fas fa-envelope" style="margin-right: 5px;"></i> ŠALJI NA MAIL
            </button>
            <div id="email-status" style="margin-top: 10px; display: none; padding: 15px; background: rgba(30, 30, 40, 0.9); border: 2px solid #A8D25B; position: relative; max-width: 600px; margin: 15px auto;"></div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animacija za pojavljivanje tablica s rezultatima
            function animateTables() {
                const tables = document.querySelectorAll('.results-table');
                tables.forEach((table, index) => {
                    setTimeout(() => {
                        table.style.opacity = '1';
                        table.style.transform = 'translateY(0)';
                    }, index * 300);
                });
            }

            // Funkcija za padanje zupčanika
            function createGearsAnimation(count = 30) {
                for (let i = 0; i < count; i++) {
                    const gear = document.createElement('div');
                    gear.innerHTML = Math.random() > 0.7 ? '<i class="fas fa-cog"></i>' : '<i class="fas fa-microchip"></i>';
                    gear.style.position = 'fixed';
                    gear.style.color = Math.random() > 0.7 ? '#A8D25B' : (Math.random() > 0.5 ? '#A6CEE3' : '#5B5B5B');
                    gear.style.fontSize = Math.random() * 20 + 10 + 'px';
                    gear.style.left = Math.random() * 100 + 'vw';
                    gear.style.top = '-20px';
                    gear.style.opacity = Math.random() * 0.7 + 0.3;
                    gear.style.zIndex = '1000';
                    gear.style.pointerEvents = 'none';
                    document.body.appendChild(gear);
                    
                    const duration = Math.random() * 3 + 2;
                    gear.style.transition = `top ${duration}s linear, transform ${duration}s linear`;
                    
                    setTimeout(() => {
                        gear.style.top = '110vh';
                        gear.style.transform = `rotate(${Math.random() * 360}deg)`;
                    }, 10);
                    
                    setTimeout(() => {
                        document.body.removeChild(gear);
                    }, duration * 1000);
                }
            }

            // Pozivanje animacije tablice nakon učitavanja stranice
            setTimeout(animateTables, 500);
            
            // Efekt kišenja zupčanika
            createGearsAnimation(50);
            
            // Dodaj efekt treperenja na naslov
            const title = document.querySelector('h1');
            setInterval(() => {
                title.style.textShadow = Math.random() > 0.7 ? 
                    '0 0 10px #A8D25B, 0 0 20px #A8D25B, 0 0 30px #A8D25B' : 
                    '0 0 5px #A8D25B';
            }, 500);
            
            // Dodaj funkcionalnost za slanje emaila
            const sendEmailBtn = document.getElementById('send-email-btn');
            const emailStatus = document.getElementById('email-status');
            
            // Efekt eksplozije zupčanika kad se klikne na gumb za mail
            sendEmailBtn.addEventListener('click', function() {
                createGearsAnimation(10); // Manji prasak zupčanika
                
                // Prikaži status
                emailStatus.style.display = 'block';
                emailStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Slanje rezultata na vašu e-mail adresu...';
                emailStatus.style.color = '#A6CEE3';
                
                // Dohvati podatke za slanje
                const testId = <?= isset($testId) && $testId > 0 ? $testId : 0 ?>;
                
                // Provjeri je li testId valjan
                if (!testId) {
                    emailStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Greška: ID testa nije dostupan (<?= isset($testId) ? $testId : 'nije definiran' ?>)';
                    emailStatus.style.color = '#A6CEE3';
                    return;
                }
                
                // XHR zahtjev
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'send_email.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                // Uspješno poslano
                                emailStatus.innerHTML = '<i class="fas fa-check-circle"></i> ' + response.message;
                                emailStatus.style.color = '#A8D25B';
                                createGearsAnimation(30); // Veliki prasak zupčanika za uspjeh
                                
                                // Sakrij nakon uspjeha
                                setTimeout(() => {
                                    emailStatus.style.display = 'none';
                                }, 5000);
                            } else {
                                // Greška
                                emailStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + response.message;
                                emailStatus.style.color = '#A6CEE3';
                            }
                        } catch (e) {
                            emailStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Greška pri slanju e-maila.';
                            emailStatus.style.color = '#A6CEE3';
                        }
                    } else {
                        emailStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Greška pri slanju e-maila.';
                        emailStatus.style.color = '#A6CEE3';
                    }
                };
                xhr.onerror = function() {
                    emailStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Greška pri slanju e-maila.';
                    emailStatus.style.color = '#A6CEE3';
                };
                xhr.send('test_id=' + encodeURIComponent(testId));
            });
        });
    </script>
    </body>
    </html>
    <?php
}
?>
                
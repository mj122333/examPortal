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

// Obradi odgovore ako postoje
if (isset($_POST['answers'])) {
    $answers = json_decode($_POST['answers'], true);
    // Provjeri je li JSON ispravno dekodiran
    if ($answers === null && json_last_error() !== JSON_ERROR_NONE) {
        die("Greška pri dekodiranju JSON odgovora: " . json_last_error_msg());
    }
    
    if ($answers) {
        // Standardiziraj ključeve u odgovorima
        foreach ($answers as &$answer) {
            // Provjeri i standardiziraj ključeve
            if (isset($answer['isCorrect'])) {
                $answer['is_correct'] = $answer['isCorrect'];
            }
            if (!isset($answer['question'])) {
                $answer['question'] = $answer['question_text'] ?? '';
            }
            if (!isset($answer['user_answer'])) {
                $answer['user_answer'] = $answer['user_answer_text'] ?? '';
            }
            if (!isset($answer['correct_answer'])) {
                $answer['correct_answer'] = $answer['correct_answer_text'] ?? '';
            }
        }
        unset($answer); // Oslobodi referencu
        
        // Izračunaj rezultat
        $correctCount = 0;
        $totalQuestions = count($answers);
        
        foreach ($answers as $answer) {
            if (isset($answer['is_correct']) && $answer['is_correct']) {
                $correctCount++;
            }
        }
        
        // Sprječavanje dijeljenja s nulom
        $score = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
        
        // Provjeri postojanje korisnika
        $korisnikID = $isGuest ? 999 : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
        if (!$isGuest && $korisnikID <= 0) {
            die("Greška: Nevažeći ID korisnika.");
        }
        
        if (!$isGuest) {
            $stmt = $conn->prepare("SELECT ID FROM ep_korisnik WHERE ID = :id");
            $stmt->execute([':id' => $korisnikID]);
            if (!$stmt->fetch()) {
                die("Greška: Korisnik ne postoji u bazi podataka.");
            }
        }
        
        // Provjeri postojanje teme
        $tema_id = isset($_POST['tema_id']) ? intval($_POST['tema_id']) : 0;
        if ($tema_id <= 0) {
            die("Greška: Nevažeći ID teme.");
        }
        
        $stmt = $conn->prepare("SELECT ID FROM ep_teme WHERE ID = :id");
        $stmt->execute([':id' => $tema_id]);
        if (!$stmt->fetch()) {
            die("Greška: Tema ne postoji u bazi podataka.");
        }
        
        // Spremi rezultate u bazu
        try {
            // Ako je gost korisnik, preskočimo spremanje u bazu
            if ($isGuest) {
                // Za gost korisnike ne spremamo rezultate u bazu
                // Postavi testId na 0 jer nećemo imati stvarni ID u bazi
                $testId = 0;
            } else {
                $conn->beginTransaction();
                
                $sql = "
                    INSERT INTO ep_test 
                    (
                      korisnikID,
                      vrijeme_pocetka,
                      vrijeme_kraja,
                      kviz_id,
                      rezultat,
                      ukupno_pitanja,
                      tocno_odgovori,
                      netocno_odgovori,
                      trajanje,
                      broj_pokusaja,
                      vremensko_ogranicenje
                    )
                    VALUES
                    (
                      :korisnikID,
                      :vrijeme_pocetka,
                      :vrijeme_kraja,
                      :kviz_id,
                      :rezultat,
                      :ukupno_pitanja,
                      :tocno_odgovori,
                      :netocno_odgovori,
                      :trajanje,
                      :broj_pokusaja,
                      :vremensko_ogranicenje
                    )
                ";
                
                $vrijeme_pocetka = $_SESSION['quiz_start_time'] ?? date("Y-m-d H:i:s");
                $vrijeme_kraja = date("Y-m-d H:i:s");
                $trajanje_sec = strtotime($vrijeme_kraja) - strtotime($vrijeme_pocetka);
                $trajanje_sec = max(0, $trajanje_sec); // Osiguravamo da nije negativno
                $trajanje = gmdate("H:i:s", $trajanje_sec);
                
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([ 
                    ':korisnikID'      => $korisnikID,
                    ':vrijeme_pocetka' => $vrijeme_pocetka,
                    ':vrijeme_kraja'   => $vrijeme_kraja,
                    ':kviz_id'         => $tema_id,
                    ':rezultat'        => $score,
                    ':ukupno_pitanja'  => $totalQuestions,
                    ':tocno_odgovori'  => $correctCount,
                    ':netocno_odgovori'=> $totalQuestions - $correctCount,
                    ':trajanje'        => $trajanje,
                    ':broj_pokusaja'   => 1,
                    ':vremensko_ogranicenje' => 0
                ]);

                if (!$result) {
                    throw new Exception("Greška pri unosu u ep_test");
                }

                // Dohvati ID novounesenog ispita
                $testId = $conn->lastInsertId();
                
                if (!$testId) {
                    throw new Exception("Greška pri dohvaćanju ID-a testa");
                }

                // Provjeri postoji li test s tim ID-em
                $checkSql = "SELECT ID FROM ep_test WHERE ID = :test_id";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([':test_id' => $testId]);
                
                if (!$checkStmt->fetch()) {
                    throw new Exception("Test s ID-em $testId ne postoji");
                }
                
                // SQL za unos odgovora
                $sql2 = "
                    INSERT INTO ep_test_odgovori 
                    (test_id, user_answer_text, correct_answer_text, question_text, is_correct, explanation)
                    VALUES 
                    (:test_id, :user_answer_text, :correct_answer_text, :question_text, :is_correct, :explanation)
                ";
                
                $stmt2 = $conn->prepare($sql2);
                
                // Spremi sve odgovore
                foreach ($answers as $answer) {
                    // Dohvati hint iz baze za ovo pitanje
                    $hint = NULL;
                    try {
                        $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                        $hintStmt->execute([':pitanje' => $answer['question']]);
                        $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                        if ($hintRow && !empty($hintRow['hint'])) {
                            $hint = $hintRow['hint'];
                        }
                    } catch (Exception $e) {
                        // Tiho ignoriraj grešku
                    }

                    $params = [
                        ':test_id' => $testId,
                        ':user_answer_text' => $answer['user_answer'] ?? 'Nije odgovoreno',
                        ':correct_answer_text' => $answer['correct_answer'] ?? 'Nije dostupno',
                        ':question_text' => $answer['question'] ?? 'Nepoznato pitanje',
                        ':is_correct' => $answer['is_correct'] ?? 0,
                        ':explanation' => $hint ?? $answer['explanation'] ?? NULL
                    ];
                    
                    $result2 = $stmt2->execute($params);
                    
                    if (!$result2) {
                        throw new Exception("Greška pri unosu odgovora");
                    }
                }
                
                $conn->commit();
            }
            
        } catch (Exception $e) {
            if (!$isGuest) {
                $conn->rollBack();
            }
            die("Greška pri spremanju rezultata: " . $e->getMessage());
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
    $stmt2 = $conn->prepare("
        SELECT t_odg.*, p.pitanje, p.odgovor1, p.odgovor2, p.odgovor3, p.odgovor4, p.tocan_odgovor, p.obrazlozenje, p.hint
        FROM ep_test_odgovori t_odg
        LEFT JOIN ep_pitanje p ON t_odg.question_text = p.pitanje
        WHERE t_odg.test_id = :exam_id
        ORDER BY t_odg.id ASC
    ");
    $stmt2->execute([':exam_id' => $exam_id]);
    $allAnswers = $stmt2->fetchAll();

    // Razdvoji točne i netočne odgovore
    $correctAnswers = [];
    $wrongAnswers   = [];
    foreach ($allAnswers as $row) {
        $entry = [
            "question"       => $row["pitanje"],
            "your_answer"    => $row["user_answer_text"],
            "correct_answer" => $row["correct_answer_text"],
            "options"        => [
                $row["odgovor1"],
                $row["odgovor2"],
                $row["odgovor3"],
                $row["odgovor4"]
            ],
            "correct_index"  => $row["tocan_odgovor"] - 1,
            "explanation"    => $row["hint"] ?? $row["obrazlozenje"] ?? "Nema dodatnog objašnjenja."
        ];
        if ($row["is_correct"] == 1) {
            $correctAnswers[] = $entry;
        } else {
            $wrongAnswers[]   = $entry;
        }
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
                opacity: 0;
                transform: translateY(20px);
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
            <span style="font-size: 2.2rem; color: #A8D25B;"><?= $score ?></span> / <?= $totalQuestions ?> 
            (<?= round(($score / $totalQuestions) * 100) ?>%)
            <?php if ($score == $totalQuestions): ?>
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

        <?php if (!empty($correctAnswers)): ?>
            <h2><i class="fas fa-check-circle"></i> Točni odgovori</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Pitanje</th>
                        <th>Vaš odgovor</th>
                        <th>Točan odgovor</th>
                        <th>Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($correctAnswers as $answer): ?>
                        <tr class="correct">
                            <?php
                            if (isset($answer['question'])) {
                                $question = htmlspecialchars($answer['question']);
                            } else {
                                $question = 'Nepoznato pitanje';
                            }

                            if (isset($answer['your_answer'])) {
                                $user_answer = htmlspecialchars($answer['your_answer']);
                            } else {
                                $user_answer = 'Nije odgovoreno';
                            }

                            if (isset($answer['correct_answer'])) {
                                $correct_answer = htmlspecialchars($answer['correct_answer']);
                            } else {
                                $correct_answer = 'Nije dostupno';
                            }

                            // Dohvati hint iz baze za ovo pitanje
                            $explanation = 'Nema dodatnog objašnjenja.';
                            try {
                                $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                                $hintStmt->execute([':pitanje' => $answer['question']]);
                                $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                                if ($hintRow && !empty($hintRow['hint'])) {
                                    $explanation = htmlspecialchars($hintRow['hint']);
                                } elseif (isset($answer['explanation']) && !empty($answer['explanation'])) {
                                    $explanation = htmlspecialchars($answer['explanation']);
                                }
                            } catch (Exception $e) {
                                // Tiho ignoriraj grešku
                            }
                            ?>
                            <td><?= $question ?></td>
                            <td><?= $user_answer ?></td>
                            <td><?= $correct_answer ?></td>
                            <td><?= $explanation ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema točnih odgovora.</div>
        <?php endif; ?>

        <?php if (!empty($wrongAnswers)): ?>
            <h2><i class="fas fa-times-circle"></i> Netočni odgovori</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Pitanje</th>
                        <th>Vaš odgovor</th>
                        <th>Točan odgovor</th>
                        <th>Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wrongAnswers as $answer): ?>
                        <tr class="incorrect">
                            <?php
                            if (isset($answer['question'])) {
                                $question = htmlspecialchars($answer['question']);
                            } else {
                                $question = 'Nepoznato pitanje';
                            }

                            if (isset($answer['your_answer'])) {
                                $user_answer = htmlspecialchars($answer['your_answer']);
                            } else {
                                $user_answer = 'Nije odgovoreno';
                            }

                            if (isset($answer['correct_answer'])) {
                                $correct_answer = htmlspecialchars($answer['correct_answer']);
                            } else {
                                $correct_answer = 'Nije dostupno';
                            }

                            // Dohvati hint iz baze za ovo pitanje
                            $explanation = 'Nema dodatnog objašnjenja.';
                            try {
                                $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                                $hintStmt->execute([':pitanje' => $answer['question']]);
                                $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                                if ($hintRow && !empty($hintRow['hint'])) {
                                    $explanation = htmlspecialchars($hintRow['hint']);
                                } elseif (isset($answer['explanation']) && !empty($answer['explanation'])) {
                                    $explanation = htmlspecialchars($answer['explanation']);
                                }
                            } catch (Exception $e) {
                                // Tiho ignoriraj grešku
                            }
                            ?>
                            <td><?= $question ?></td>
                            <td><?= $user_answer ?></td>
                            <td><?= $correct_answer ?></td>
                            <td><?= $explanation ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema netočnih odgovora.</div>
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
                const testId = <?= $testId ?>; // PHP varijabla s ID-om testa
                
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
    exit();
} else {
    // === REŽIM OBRADE KVIZA (nakon što je korisnik završio ispunjavanje) ===

    /**
     * Funkcija za dohvaćanje pitanja s index.php?getQuestions=1
     */
    function loadQuizQuestions() {
        $host = $_SERVER['HTTP_HOST'];
        $uri  = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
        $query = "getQuestions=1";

        // Ako postoji 'tema' u POST-u ili u sesiji, dodaj ju u URL
        if (isset($_POST['tema']) && trim($_POST['tema']) !== '') {
            $tema = urlencode($_POST['tema']);
            $query .= "&tema=" . $tema;
        } elseif (isset($_SESSION['temaID']) && trim($_SESSION['temaID']) !== '') {
            $tema = urlencode($_SESSION['temaID']);
            $query .= "&tema=" . $tema;
        }

        $url = "http://$host$uri/index.php?$query";
        $json = @file_get_contents($url);
        if ($json === false) {
            die("Greška pri dohvaćanju pitanja iz index.php");
        }
        $questions = json_decode($json, true);
        if ($questions === null && json_last_error() !== JSON_ERROR_NONE) {
            die("Greška pri dekodiranju JSON odgovora: " . json_last_error_msg());
        }
        if (!is_array($questions)) {
            die("Greška pri dekodiranju JSON-a s pitanjima");
        }
        return $questions;
    }

    // Učitaj pitanja
    $questions = loadQuizQuestions();

    $correctAnswers = [];
    $wrongAnswers   = [];

    // Provjeri ima li postanih score i total vrijednosti
    if (isset($_POST['score']) && isset($_POST['total'])) {
        $score = intval($_POST['score']);
        $totalQuestions = intval($_POST['total']);
        
        // Obrada JSON odgovora ako su poslani
        if (isset($_POST['answers'])) {
            $userAnswers = json_decode($_POST['answers'], true);
            if ($userAnswers !== null) {
                foreach ($userAnswers as $answer) {
                    $questionIndex = $answer['questionIndex'];
                    if (!isset($questions[$questionIndex])) continue;
                    
                    $question = $questions[$questionIndex];
                    $selectedAnswerIndex = $answer['selectedAnswer'];
                    $answerOptions = explode("|", $question["answers"]);
                    $correctIndex = $question["correctAnswer"];
                    
                    $userAnswerText = isset($answerOptions[$selectedAnswerIndex]) ? $answerOptions[$selectedAnswerIndex] : "Nije odabrano";
                    $correctAnswerText = isset($answerOptions[$correctIndex]) ? $answerOptions[$correctIndex] : "Nije definirano";
                    $explanationText = $question["hint"] ?? "Nema dodatnog objašnjenja.";
                    
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
            }
        }
    } else {
        // Stari način obrade ako score i total nisu dostupni
        foreach ($questions as $index => $question) {
            $qKey = "question" . $index;
            $userAnswer = $_POST[$qKey] ?? "";

            // Odgovori su pipe-odvojeni
            $answerOptions = explode("|", $question["answers"]);
            $correctIndex  = $question["correctAnswer"];

            $correctAnswerText = $answerOptions[$correctIndex] ?? "Nije definirano";
            $userAnswerText    = isset($answerOptions[$userAnswer]) ? $answerOptions[$userAnswer] : "Nije odabrano";
            $explanationText   = $question["hint"] ?? "Nema dodatnog objašnjenja.";
            
            $isCorrect = (string)$userAnswer === (string)$correctIndex;
            
            if ($isCorrect) {
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
        
        $totalQuestions = count($questions);
        $score = count($correctAnswers);
    }

    $correctCount   = count($correctAnswers);
    $incorrectCount = count($wrongAnswers);

    // Izračun postotka uspješnosti uz provjeru dijeljenja s nulom
    $scorePercentage = $totalQuestions > 0 ? ($score / $totalQuestions) * 100 : 0;

    // Pripremi $answers za kasniji unos u bazu
    $answers = [];
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

    // Povezivanje na bazu
    require_once 'db_connection.php';  // Uključivanje db_connection.php

    // Odredi vrijeme početka i kraja
    $vrijeme_pocetka = isset($_SESSION['quiz_start_time']) ? $_SESSION['quiz_start_time'] : date("Y-m-d H:i:s");
    $vrijeme_kraja   = date("Y-m-d H:i:s");
    
    // Osiguraj da vrijeme početka nije null prije korištenja strtotime funkcije
    if ($vrijeme_pocetka) {
        $diffSec = strtotime($vrijeme_kraja) - strtotime($vrijeme_pocetka);
        if ($diffSec < 0) {
            $diffSec = 0;
        }
    } else {
        // Ako vrijeme početka nije postavljeno, postavimo ga na trenutno vrijeme
        $vrijeme_pocetka = date("Y-m-d H:i:s");
        $diffSec = 0;
    }
    
    $trajanje = gmdate("H:i:s", $diffSec);

    // ID korisnika iz sesije s provjerom
    $korisnikID = isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0 ? intval($_SESSION['user_id']) : 1;
    
    // Provjera je li korisnik prijavljen i ima valjani ID
    if (!$isGuest && $korisnikID <= 0) {
        die("Nevažeći ID korisnika");
    }

    // Pokušaj dohvatiti kviz_id iz sesije ili POST podataka
    if (isset($_SESSION['temaID']) && intval($_SESSION['temaID']) > 0) {
        $kviz_id = intval($_SESSION['temaID']);
    } elseif (isset($_POST['tema']) && intval($_POST['tema']) > 0) {
        $kviz_id = intval($_POST['tema']);
    } else {
        $kviz_id = 0;
    }
    
    // Dohvati naziv teme ako je dostupan
    $tema_naziv = "Nepoznata tema";
    if ($kviz_id > 0) {
        try {
            $tema_stmt = $conn->prepare("SELECT naziv FROM ep_teme WHERE ID = :id");
            $tema_stmt->execute([':id' => $kviz_id]);
            $tema_row = $tema_stmt->fetch(PDO::FETCH_ASSOC);
            if ($tema_row) {
                $tema_naziv = $tema_row['naziv'];
            }
        } catch (PDOException $e) {
            // Tiho ignoriramo neuspjeh dohvaćanja teme
        }
    }

    $broj_pokusaja = 1;
    $rezultat = $scorePercentage;

    // Spremi glavni zapis u ep_test
    try {
        $conn->beginTransaction();
        
        $sql = "
            INSERT INTO ep_test 
            (
              korisnikID,
              vrijeme_pocetka,
              vrijeme_kraja,
              kviz_id,
              rezultat,
              ukupno_pitanja,
              tocno_odgovori,
              netocno_odgovori,
              trajanje,
              broj_pokusaja,
              vremensko_ogranicenje
            )
            VALUES
            (
              :korisnikID,
              :vrijeme_pocetka,
              :vrijeme_kraja,
              :kviz_id,
              :rezultat,
              :ukupno_pitanja,
              :tocno_odgovori,
              :netocno_odgovori,
              :trajanje,
              :broj_pokusaja,
              :vremensko_ogranicenje
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([ 
            ':korisnikID'      => $korisnikID,
            ':vrijeme_pocetka' => $vrijeme_pocetka,
            ':vrijeme_kraja'   => $vrijeme_kraja,
            ':kviz_id'         => $kviz_id,
            ':rezultat'        => $rezultat,
            ':ukupno_pitanja'  => $totalQuestions,
            ':tocno_odgovori'  => $correctCount,
            ':netocno_odgovori'=> $incorrectCount,
            ':trajanje'        => $trajanje,
            ':broj_pokusaja'   => $broj_pokusaja,
            ':vremensko_ogranicenje' => isset($vremensko_ogranicenje) ? $vremensko_ogranicenje : 0
        ]);

        if (!$stmt->rowCount()) {
            throw new Exception("Greška pri unosu u ep_test");
        }

        // Dohvati ID novounesenog ispita
        $testId = $conn->lastInsertId();
        
        if (!$testId) {
            throw new Exception("Greška pri dohvaćanju ID-a testa");
        }

        // Provjeri postoji li test s tim ID-em
        $checkSql = "SELECT ID FROM ep_test WHERE ID = :test_id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':test_id' => $testId]);
        
        if (!$checkStmt->fetch()) {
            throw new Exception("Test s ID-em $testId ne postoji");
        }

        // SQL za unos odgovora
        $sql2 = "
            INSERT INTO ep_test_odgovori 
            (test_id, user_answer_text, correct_answer_text, question_text, is_correct, explanation)
            VALUES 
            (:test_id, :user_answer_text, :correct_answer_text, :question_text, :is_correct, :explanation)
        ";
        
        $stmt2 = $conn->prepare($sql2);
        
        // Spremi sve odgovore
        foreach ($answers as $answer) {
            // Dohvati hint iz baze za ovo pitanje
            $hint = NULL;
            try {
                $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                $hintStmt->execute([':pitanje' => $answer['question']]);
                $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                if ($hintRow && !empty($hintRow['hint'])) {
                    $hint = $hintRow['hint'];
                }
            } catch (Exception $e) {
                // Tiho ignoriraj grešku
            }

            $params = [
                ':test_id' => $testId,
                ':user_answer_text' => $answer['user_answer'] ?? 'Nije odgovoreno',
                ':correct_answer_text' => $answer['correct_answer'] ?? 'Nije dostupno',
                ':question_text' => $answer['question'] ?? 'Nepoznato pitanje',
                ':is_correct' => $answer['is_correct'] ?? 0,
                ':explanation' => $hint ?? $answer['explanation'] ?? NULL
            ];
            
            $result2 = $stmt2->execute($params);
            
            if (!$result2) {
                throw new Exception("Greška pri unosu odgovora");
            }
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        die("Greška pri spremanju rezultata: " . $e->getMessage());
    }

    // Makni session start time
    unset($_SESSION['quiz_start_time']);
    ?>
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
                opacity: 0;
                transform: translateY(20px);
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
            <span style="font-size: 2.2rem; color: #A8D25B;"><?= $score ?></span> / <?= $totalQuestions ?> 
            (<?= round(($score / $totalQuestions) * 100) ?>%)
            <?php if ($score == $totalQuestions): ?>
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

        <?php if (!empty($correctAnswers)): ?>
            <h2><i class="fas fa-check-circle"></i> Točni odgovori</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Pitanje</th>
                        <th>Vaš odgovor</th>
                        <th>Točan odgovor</th>
                        <th>Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($correctAnswers as $answer): ?>
                        <tr class="correct">
                            <?php
                            if (isset($answer['question'])) {
                                $question = htmlspecialchars($answer['question']);
                            } else {
                                $question = 'Nepoznato pitanje';
                            }

                            if (isset($answer['your_answer'])) {
                                $user_answer = htmlspecialchars($answer['your_answer']);
                            } else {
                                $user_answer = 'Nije odgovoreno';
                            }

                            if (isset($answer['correct_answer'])) {
                                $correct_answer = htmlspecialchars($answer['correct_answer']);
                            } else {
                                $correct_answer = 'Nije dostupno';
                            }

                            // Dohvati hint iz baze za ovo pitanje
                            $explanation = 'Nema dodatnog objašnjenja.';
                            try {
                                $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                                $hintStmt->execute([':pitanje' => $answer['question']]);
                                $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                                if ($hintRow && !empty($hintRow['hint'])) {
                                    $explanation = htmlspecialchars($hintRow['hint']);
                                } elseif (isset($answer['explanation']) && !empty($answer['explanation'])) {
                                    $explanation = htmlspecialchars($answer['explanation']);
                                }
                            } catch (Exception $e) {
                                // Tiho ignoriraj grešku
                            }
                            ?>
                            <td><?= $question ?></td>
                            <td><?= $user_answer ?></td>
                            <td><?= $correct_answer ?></td>
                            <td><?= $explanation ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema točnih odgovora.</div>
        <?php endif; ?>

        <?php if (!empty($wrongAnswers)): ?>
            <h2><i class="fas fa-times-circle"></i> Netočni odgovori</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Pitanje</th>
                        <th>Vaš odgovor</th>
                        <th>Točan odgovor</th>
                        <th>Objašnjenje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wrongAnswers as $answer): ?>
                        <tr class="incorrect">
                            <?php
                            if (isset($answer['question'])) {
                                $question = htmlspecialchars($answer['question']);
                            } else {
                                $question = 'Nepoznato pitanje';
                            }

                            if (isset($answer['your_answer'])) {
                                $user_answer = htmlspecialchars($answer['your_answer']);
                            } else {
                                $user_answer = 'Nije odgovoreno';
                            }

                            if (isset($answer['correct_answer'])) {
                                $correct_answer = htmlspecialchars($answer['correct_answer']);
                            } else {
                                $correct_answer = 'Nije dostupno';
                            }

                            // Dohvati hint iz baze za ovo pitanje
                            $explanation = 'Nema dodatnog objašnjenja.';
                            try {
                                $hintStmt = $conn->prepare("SELECT hint FROM ep_pitanje WHERE pitanje = :pitanje LIMIT 1");
                                $hintStmt->execute([':pitanje' => $answer['question']]);
                                $hintRow = $hintStmt->fetch(PDO::FETCH_ASSOC);
                                if ($hintRow && !empty($hintRow['hint'])) {
                                    $explanation = htmlspecialchars($hintRow['hint']);
                                } elseif (isset($answer['explanation']) && !empty($answer['explanation'])) {
                                    $explanation = htmlspecialchars($answer['explanation']);
                                }
                            } catch (Exception $e) {
                                // Tiho ignoriraj grešku
                            }
                            ?>
                            <td><?= $question ?></td>
                            <td><?= $user_answer ?></td>
                            <td><?= $correct_answer ?></td>
                            <td><?= $explanation ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">Nema netočnih odgovora.</div>
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
                const testId = <?= $testId ?>; // PHP varijabla s ID-om testa
                
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

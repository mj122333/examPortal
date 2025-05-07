<?php
session_start();

// Provjera je li korisnik prijavljen
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';

$poruka = ""; // Poruka za status
$hint = ""; // Drži hint vrijednost

// 1) Dohvati teme (za <select> element)
try {
    $stmt = $conn->prepare("SELECT ID, naziv FROM ep_teme ORDER BY naziv");
    $stmt->execute();
    $popisTema = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Obrada forme kad se pošalje
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Dohvati podatke iz forme
        $question    = $_POST['question'] ?? '';
        $answer1     = $_POST['answer1'] ?? '';
        $answer2     = $_POST['answer2'] ?? '';
        $answer3     = $_POST['answer3'] ?? '';
        $answer4     = $_POST['answer4'] ?? '';
        $correct     = $_POST['correctAnswer'] ?? '';
        $selectedID  = $_POST['temaID'] ?? '';   // Odabrana tema iz dropdowna
        $newTheme    = trim($_POST['newTheme'] ?? ''); // Nova tema
        $hint        = $_POST['hint'] ?? '';    // Hint

        // Jednostavna validacija
        if (
            empty($question) || 
            empty($answer1)  || 
            empty($answer2)  || 
            empty($answer3)  || 
            empty($answer4)  || 
            empty($correct)
        ) {
            $poruka = "Molim popunite sva obavezna polja (pitanje, 4 odgovora i točan odgovor).";
        } else {
            // Ako je unesena nova tema, dodaj je i dohvatite ID
            if (!empty($newTheme)) {
                $sqlNovaTema = "INSERT INTO ep_teme (naziv) VALUES (:naziv)";
                $stmtTema = $conn->prepare($sqlNovaTema);
                $stmtTema->bindValue(':naziv', $newTheme);
                $stmtTema->execute();
                $temaID = $conn->lastInsertId();
            } else {
                // Ako nije unesena nova tema, koristi odabranu iz dropdowna
                if (empty($selectedID)) {
                    $poruka = "Molim odaberite postojeću temu ili unesite novu.";
                    goto skipInsert;
                }
                $temaID = $selectedID;
            }

            // Obrada upload-a slike za pitanje (ako postoji)
            $imagePath = null;
            if (isset($_FILES['questionImage']) && $_FILES['questionImage']['error'] === UPLOAD_ERR_OK) {
                $maxFileSize = 2 * 1024 * 1024; // 2 MB
                if ($_FILES['questionImage']['size'] <= $maxFileSize) {
                    $allowedTypes = ["image/jpeg", "image/png", "image/gif"];
                    if (in_array($_FILES['questionImage']['type'], $allowedTypes)) {
                        $uploadDir = "uploads/";
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileTmpPath = $_FILES['questionImage']['tmp_name'];
                        $fileName = basename($_FILES['questionImage']['name']);
                        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileName);
                        $destPath = $uploadDir . time() . "_" . $fileName;
                        if (move_uploaded_file($fileTmpPath, $destPath)) {
                            $imagePath = $destPath;
                        } else {
                            $poruka = "Greška pri uploadanju slike.";
                            goto skipInsert;
                        }
                    } else {
                        $poruka = "Format slike nije podržan. Koristite JPG, PNG ili GIF.";
                        goto skipInsert;
                    }
                } else {
                    $poruka = "Veličina slike prelazi maksimalnih 2MB.";
                    goto skipInsert;
                }
            }

            // 3) Unos pitanja u ep_pitanje tablicu
            $sqlPitanje = "INSERT INTO ep_pitanje (tekst_pitanja, korisnikID, brojBodova, temaID, slika, hint)
                           VALUES (:tekst, :korisnikID, :bodovi, :temaID, :slika, :hint)";
            $stmtPitanje = $conn->prepare($sqlPitanje);
            $stmtPitanje->bindValue(':tekst', $question);
            $stmtPitanje->bindValue(':korisnikID', $_SESSION['user_id']);
            $stmtPitanje->bindValue(':bodovi', 1); // Default bodovi 1
            $stmtPitanje->bindValue(':temaID', $temaID);
            $stmtPitanje->bindValue(':slika', $imagePath);
            $stmtPitanje->bindValue(':hint', $hint);
            $stmtPitanje->execute();

            $newQuestionID = $conn->lastInsertId();

            // 4) Unos 4 odgovora u op_odgovori tablicu
            $answers = [$answer1, $answer2, $answer3, $answer4];
            $correctIndex = (int)$correct - 1;
            for ($i = 0; $i < 4; $i++) {
                $sqlOdgovori = "INSERT INTO ep_odgovori (tekst, pitanjeID, tocno, korisnikID, aktivno)
                                VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, 1)";
                $stmtOdgovor = $conn->prepare($sqlOdgovori);
                $stmtOdgovor->bindValue(':tekst', $answers[$i]);
                $stmtOdgovor->bindValue(':pitanjeID', $newQuestionID);
                $stmtOdgovor->bindValue(':tocno', ($i === $correctIndex) ? 1 : 0);
                $stmtOdgovor->bindValue(':korisnikID', $_SESSION['user_id']);
                $stmtOdgovor->execute();
            }

            $poruka = "Pitanje uspješno dodano u bazu!";
        }
        skipInsert:;
    }
} catch (PDOException $e) {
    die("Greška s bazom: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Dodaj pitanje</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styling for the form */
        .dodaj-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .dodaj-form .form-group {
            display: flex;
            flex-direction: column;
        }
        .dodaj-form label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #ffae00;
            text-shadow: 0 0 5px #ffae00;
        }
        /* Common style for both textareas */
        #question,
        #hint {
            background-color: #2a2a2a;
            border: 2px solid #ff00ff;
            border-radius: 5px;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(255, 0, 255, 0.3);
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box;
            display: block;
        }

        #question {
            height: 80px;  /* Height for the question box */
        }
        #hint {
            height: calc(105px / 3); 
        }

        /* Styles for text inputs and select */
        .dodaj-form input[type="text"],
        .dodaj-form select {
            background-color: #1c1c1c;
            border: 2px solid #ff00ff;
            border-radius: 5px;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(255, 0, 255, 0.3);
            font-size: 1rem;
        }

        /* Radio buttons styling */
        .radio-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .radio-group label {
            margin: 0;
        }

        /* Submit button styling */
        .dodaj-form button[type="submit"] {
            align-self: flex-start;
            background-color: #ff00ff;
            color: #fff;
            padding: 14px 36px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1rem;
            margin-top: 10px;
            box-shadow: 0 0 5px #ff00ff, 0 0 10px #ff00ff;
            transition: 0.3s ease;
        }
        .dodaj-form button[type="submit"]:hover {
            background-color: #d100d1;
            box-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff;
        }

        /* Message style */
        .dodaj-poruka {
            color: #40ffe5;
            margin-bottom: 10px;
            text-shadow: 0 0 5px #40ffe5;
        }
    </style>
</head>
<body>
    <div class="quiz-container">
        <h2>Dodaj novo pitanje</h2>
        <?php if (!empty($poruka)) : ?>
            <p class="dodaj-poruka"><?= htmlspecialchars($poruka) ?></p>
        <?php endif; ?>
        <form method="POST" class="dodaj-form" enctype="multipart/form-data">
            <!-- Form fields for question, answers, hint, etc. -->
            <button type="submit">Spremi pitanje</button>
        </form>
        <br><br>
        <a href="odabir_teme.php" style="text-decoration: none;">
            <button class="tema-btn" type="button">Natrag na odabir tema</button>
        </a>
    </div>
</body>
</html>

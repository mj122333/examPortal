<?php
session_start();

// 1) Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2) Must be a teacher (razina = 1)
if (!isset($_SESSION['razina']) || $_SESSION['razina'] != 1) {
    die("Samo profesori mogu dodavati pitanja.");
}

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Ovdje uključujemo db_connection.php

$poruka = ""; // Status message
$hint   = ""; // Hint text

try {
    // Fetch all topics (since teacher can see all)
    $stmt = $conn->prepare("SELECT ID, naziv FROM ep_teme ORDER BY naziv");
    $stmt->execute();
    $popisTema = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve data
        $question    = $_POST['question'] ?? '';
        $answer1     = $_POST['answer1'] ?? '';
        $answer2     = $_POST['answer2'] ?? '';
        $answer3     = $_POST['answer3'] ?? '';
        $answer4     = $_POST['answer4'] ?? '';
        $correct     = $_POST['correctAnswer'] ?? '';
        $selectedID  = $_POST['temaID'] ?? '';           // Existing topic
        $newTheme    = trim($_POST['newTheme'] ?? '');   // New topic
        $hint        = $_POST['hint'] ?? '';
        $points      = $_POST['points'] ?? 1;

        // Basic validation
        if (empty($question) || empty($answer1) || empty($answer2) || empty($correct)) {
            $poruka = "Molim popunite obavezna polja: pitanje, minimalno 2 odgovora i točan odgovor.";
        } else {
            $answers = [$answer1, $answer2, $answer3, $answer4];
            $brojOdgovora = 0;
            foreach ($answers as $odg) {
                if (!empty(trim($odg))) {
                    $brojOdgovora++;
                }
            }
            if ($brojOdgovora < 2) {
                $poruka = "Unesite minimalno 2 odgovora.";
                goto skipInsert;
            }

            $correctIndex = (int)$correct - 1;
            if (empty(trim($answers[$correctIndex]))) {
                $poruka = "Točan odgovor mora biti popunjen.";
                goto skipInsert;
            }

            // If teacher entered a new topic, insert it
            if (!empty($newTheme)) {
                $sqlNovaTema = "INSERT INTO ep_teme (naziv) VALUES (:naziv)";
                $stmtTema = $conn->prepare($sqlNovaTema);
                $stmtTema->bindValue(':naziv', $newTheme);
                $stmtTema->execute();
                $temaID = $conn->lastInsertId();
            } else {
                // Must pick an existing topic if no new one is provided
                if (empty($selectedID)) {
                    $poruka = "Molim odaberite postojeću temu ili unesite novu.";
                    goto skipInsert;
                }
                $temaID = $selectedID;
            }

            // Handle uploaded image (if any)
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
                        $fileName    = basename($_FILES['questionImage']['name']);
                        // Clean up filename
                        $fileName    = preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileName);
                        $destPath    = $uploadDir . time() . "_" . $fileName;
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

            // Insert question
            $sqlPitanje = "INSERT INTO ep_pitanje
                           (tekst_pitanja, korisnikID, brojBodova, broj_ponudenih, temaID, slika, hint)
                           VALUES (:tekst, :korisnikID, :bodovi, :brojOdgovora, :temaID, :slika, :hint)";
            $stmtPitanje = $conn->prepare($sqlPitanje);
            $stmtPitanje->bindValue(':tekst', $question);
            $stmtPitanje->bindValue(':korisnikID', $_SESSION['user_id']);
            $stmtPitanje->bindValue(':bodovi', $points);
            $stmtPitanje->bindValue(':brojOdgovora', $brojOdgovora);
            $stmtPitanje->bindValue(':temaID', $temaID);
            $stmtPitanje->bindValue(':slika', $imagePath);
            $stmtPitanje->bindValue(':hint', $hint);
            $stmtPitanje->execute();

            $newQuestionID = $conn->lastInsertId();

            // Insert answers
            for ($i = 0; $i < 4; $i++) {
                if (!empty(trim($answers[$i]))) {
                    $sqlOdgovori = "INSERT INTO ep_odgovori
                                    (tekst, pitanjeID, tocno, korisnikID, aktivno)
                                    VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, 1)";
                    $stmtOdgovor = $conn->prepare($sqlOdgovori);
                    $stmtOdgovor->bindValue(':tekst', $answers[$i]);
                    $stmtOdgovor->bindValue(':pitanjeID', $newQuestionID);
                    $stmtOdgovor->bindValue(':tocno', ($i === $correctIndex) ? 1 : 0);
                    $stmtOdgovor->bindValue(':korisnikID', $_SESSION['user_id']);
                    $stmtOdgovor->execute();
                }
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
        /* Styling for textareas */
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
        #question { height: 80px; }
        #hint { height: calc(105px / 3); }
        /* Styling for text inputs, selects */
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
        /* Styling for radio buttons */
        .radio-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .radio-group label { margin: 0; }
        /* Styling for the submit button */
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
        /* Styling for the status message */
        .dodaj-poruka {
            color: #40ffe5;
            margin-bottom: 10px;
            text-shadow: 0 0 5px #40ffe5;
        }
        /* Style for the back-to-theme button */
        .tema-btn {
            background: linear-gradient(to right, rgb(223, 5, 146), rgb(106, 7, 227));
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .tema-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        /* File-upload style (optional) */
        .file-upload {
            margin-top: 5px;
        }
        .upload-btn {
            color: #ff00ff;
            cursor: pointer;
            border: 1px solid #ff00ff;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            text-shadow: 0 0 5px #ff00ff;
            transition: 0.3s;
        }
        .upload-btn:hover {
            background-color: #ff00ff;
            color: #fff;
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
            <!-- Question text -->
            <div class="form-group">
                <label for="question">Tekst pitanja:</label>
                <textarea id="question" name="question" rows="3" cols="50"></textarea>
            </div>

            <!-- Answers 1-4 -->
            <div class="form-group">
                <label for="answer1">Odgovor 1:</label>
                <input type="text" id="answer1" name="answer1">
            </div>
            <div class="form-group">
                <label for="answer2">Odgovor 2:</label>
                <input type="text" id="answer2" name="answer2">
            </div>
            <div class="form-group">
                <label for="answer3">Odgovor 3:</label>
                <input type="text" id="answer3" name="answer3">
            </div>
            <div class="form-group">
                <label for="answer4">Odgovor 4:</label>
                <input type="text" id="answer4" name="answer4">
            </div>

            <!-- Hint -->
            <div class="form-group">
                <label for="hint">Hint (savjet):</label>
                <textarea id="hint" name="hint" rows="3" cols="50"><?= htmlspecialchars($hint) ?></textarea>
            </div>

            <!-- Points drop-down -->
            <div class="form-group">
                <label for="points">Broj bodova:</label>
                <select id="points" name="points">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </div>

            <!-- Radio buttons for correct answer -->
            <div class="form-group">
                <label>Koji je točan odgovor?</label>
                <div class="radio-group">
                    <input type="radio" id="correct1" name="correctAnswer" value="1">
                    <label for="correct1">1</label>
                    <input type="radio" id="correct2" name="correctAnswer" value="2">
                    <label for="correct2">2</label>
                    <input type="radio" id="correct3" name="correctAnswer" value="3">
                    <label for="correct3">3</label>
                    <input type="radio" id="correct4" name="correctAnswer" value="4">
                    <label for="correct4">4</label>
                </div>
            </div>

            <!-- Topic selection -->
            <div class="form-group">
                <label for="temaID">Odaberite postojeću temu:</label>
                <select name="temaID" id="temaID">
                    <option value="">-- Odaberite temu --</option>
                    <?php foreach($popisTema as $t) : ?>
                        <option value="<?= htmlspecialchars($t['ID']) ?>">
                            <?= htmlspecialchars($t['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="newTheme">Ili upišite novu temu (ostavite prazno ako birate postojeću):</label>
                <input type="text" id="newTheme" name="newTheme">
            </div>

            <!-- Upload image -->
            <div class="form-group">
                <label for="questionImage">Dodajte sliku za pitanje (maks. 2MB, format: JPG, PNG, GIF):</label>
                <div class="file-upload">
                    <label for="questionImage" class="upload-btn">📸 Odaberi sliku</label>
                    <input type="file" id="questionImage" name="questionImage" accept="image/*" hidden>
                </div>
                <p id="imageName" class="image-name"></p>
                <p id="imageError" class="error-message" style="display: none;">❌ Slika mora biti manja od 2 MB!</p>
                <div id="imagePreviewContainer" class="image-preview-container" style="display: none;">
                    <img id="imagePreview" src="" alt="Pregled slike">
                </div>
            </div>

            <button type="submit">Spremi pitanje</button>
        </form>

        <br><br>
        <!-- Styled button to go back to topic selection -->
        <a href="odabir_teme.php" class="tema-btn">Natrag na odabir tema</a>
    </div>

    <script>
    // Image preview logic
    document.getElementById('questionImage').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const maxSize = 2 * 1024 * 1024; // 2 MB
        const imageNameDisplay = document.getElementById('imageName');
        const imageErrorDisplay = document.getElementById('imageError');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreview = document.getElementById('imagePreview');

        if (file) {
            if (file.size > maxSize) {
                imageErrorDisplay.style.display = 'block';
                event.target.value = ""; // Reset input
                imagePreviewContainer.style.display = 'none';
                imageNameDisplay.innerHTML = "";
                return;
            } else {
                imageErrorDisplay.style.display = 'none';
            }
            // Show file name
            imageNameDisplay.innerHTML = `📂 Odabrana slika: <strong>${file.name}</strong>`;
            // Show image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreviewContainer.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>

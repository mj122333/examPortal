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
    <title>Tehnička škola Čakovec | Dodaj pitanje</title>
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
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #A8D25B; /* Zelena boja */
            text-shadow: 0 0 5px #A8D25B;
            font-family: 'Roboto', sans-serif;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            border-bottom: 2px solid #A6CEE3; /* Svijetloplava linija ispod */
            padding-bottom: 10px;
            text-align: center;
        }
        /* Additional styling for the form */
        .dodaj-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: linear-gradient(145deg, #3a3a3a, #222222); /* Tamno sivi gradijent */
            border: 2px solid #A8D25B; /* Zelena granica */
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            padding: 30px;
            border-radius: 0; /* Oštre ravne linije za tehnički stil */
        }
        .dodaj-form .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .dodaj-form label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #A6CEE3; /* Svijetloplava boja */
            text-shadow: 0 0 3px rgba(166, 206, 227, 0.5);
        }
        /* Styling for textareas */
        #question,
        #hint {
            background-color: #333333;
            border: 2px solid #A6CEE3; /* Svijetloplava granica */
            border-radius: 0;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(166, 206, 227, 0.3);
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box;
            display: block;
        }
        #question { height: 80px; }
        #hint { height: calc(105px / 3); }
        /* Styling for text inputs, selects */
        .dodaj-form input[type="text"],
        .dodaj-form select,
        .dodaj-form input[type="number"],
        .dodaj-form input[type="file"] {
            background-color: #333333;
            border: 2px solid #A6CEE3; /* Svijetloplava granica */
            border-radius: 0;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(166, 206, 227, 0.3);
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
            background-color: #A6CEE3; /* Svijetloplava */
            color: #2e2e2e;
            padding: 14px 36px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1.1rem;
            margin-top: 10px;
            box-shadow: 0 0 5px #A6CEE3, 0 0 10px #A6CEE3;
            transition: 0.3s ease;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .dodaj-form button[type="submit"]:hover {
            background-color: #A8D25B; /* Zelena na hover */
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
        }
        /* Styling for the status message */
        .dodaj-poruka {
            color: #A8D25B; /* Zelena boja za poruke */
            margin-bottom: 20px;
            text-shadow: 0 0 5px rgba(168, 210, 91, 0.5);
            padding: 10px;
            background-color: rgba(168, 210, 91, 0.1);
            border-left: 4px solid #A8D25B;
        }
        /* Style for the back-to-theme button */
        .tema-btn {
            background-color: #A6CEE3; /* Svijetloplava */
            color: #2e2e2e;
            border: none;
            padding: 12px 24px;
            border-radius: 0;
            font-size: 16px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: bold;
            box-shadow: 0 0 5px #A6CEE3, 0 0 10px #A6CEE3;
        }
        .tema-btn:hover {
            background-color: #A8D25B; /* Zelena na hover */
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
        }
        /* Naslovi sekcija */
        .section-title {
            color: #A8D25B;
            margin: 20px 0 10px 0;
            font-size: 1.3rem;
            border-bottom: 1px solid #A6CEE3;
            padding-bottom: 5px;
        }
        /* Stilovi za prikaz odabrane slike */
        .image-preview-container {
            margin-top: 10px;
            text-align: center;
            display: none;
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            border-radius: 5px;
        }
        .image-preview {
            max-width: 600px;
            max-height: 600px;
            margin: 10px auto;
            border: 2px solid #A8D25B;
            box-shadow: 0 0 10px rgba(168, 210, 91, 0.3);
            display: block;
            transition: all 0.3s ease;
        }
        .image-preview-label {
            color: #A8D25B;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        /* Stilovi za slider */
        .image-size-control {
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .image-size-control label {
            color: #A6CEE3;
            min-width: 120px;
        }
        .image-size-control input[type="range"] {
            width: 200px;
            height: 5px;
            background: #A6CEE3;
            border-radius: 5px;
            outline: none;
            -webkit-appearance: none;
        }
        .image-size-control input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 15px;
            height: 15px;
            background: #A8D25B;
            border-radius: 50%;
            cursor: pointer;
            transition: 0.3s ease;
        }
        .image-size-control input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
        .image-size-value {
            color: #A8D25B;
            font-weight: bold;
            min-width: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit" style="margin-right: 10px;"></i>Dodaj novo pitanje</h1>
        
        <?php if (!empty($poruka)): ?>
            <div class="dodaj-poruka"><?= htmlspecialchars($poruka) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="dodaj-form">
            <div class="form-group">
                <label for="question"><i class="fas fa-question-circle" style="margin-right: 5px;"></i>Tekst pitanja:</label>
                <textarea id="question" name="question" required><?= htmlspecialchars($question ?? '') ?></textarea>
            </div>
            
            <div class="section-title"><i class="fas fa-list-ol" style="margin-right: 5px;"></i>Ponuđeni odgovori</div>
            
            <div class="form-group">
                <label for="answer1">Odgovor 1:</label>
                <input type="text" id="answer1" name="answer1" value="<?= htmlspecialchars($answer1 ?? '') ?>" required>
                <div class="radio-group">
                    <input type="radio" id="correct1" name="correctAnswer" value="1" <?= (($correct ?? '1') == '1') ? 'checked' : '' ?>>
                    <label for="correct1">Točan odgovor</label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="answer2">Odgovor 2:</label>
                <input type="text" id="answer2" name="answer2" value="<?= htmlspecialchars($answer2 ?? '') ?>" required>
                <div class="radio-group">
                    <input type="radio" id="correct2" name="correctAnswer" value="2" <?= (($correct ?? '') == '2') ? 'checked' : '' ?>>
                    <label for="correct2">Točan odgovor</label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="answer3">Odgovor 3 (opcionalno):</label>
                <input type="text" id="answer3" name="answer3" value="<?= htmlspecialchars($answer3 ?? '') ?>">
                <div class="radio-group">
                    <input type="radio" id="correct3" name="correctAnswer" value="3" <?= (($correct ?? '') == '3') ? 'checked' : '' ?>>
                    <label for="correct3">Točan odgovor</label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="answer4">Odgovor 4 (opcionalno):</label>
                <input type="text" id="answer4" name="answer4" value="<?= htmlspecialchars($answer4 ?? '') ?>">
                <div class="radio-group">
                    <input type="radio" id="correct4" name="correctAnswer" value="4" <?= (($correct ?? '') == '4') ? 'checked' : '' ?>>
                    <label for="correct4">Točan odgovor</label>
                </div>
            </div>
            
            <div class="section-title"><i class="fas fa-folder" style="margin-right: 5px;"></i>Kategorizacija</div>
            
            <div class="form-group">
                <label for="temaID">Odaberi postojeću temu:</label>
                <select id="temaID" name="temaID">
                    <option value="">-- Odaberi temu --</option>
                    <?php foreach ($popisTema as $tema): ?>
                        <option value="<?= $tema['ID'] ?>" <?= (($selectedID ?? '') == $tema['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tema['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="newTheme">Ili unesi novu temu:</label>
                <input type="text" id="newTheme" name="newTheme" value="<?= htmlspecialchars($newTheme ?? '') ?>">
            </div>
            
            <div class="section-title"><i class="fas fa-cog" style="margin-right: 5px;"></i>Dodatne opcije</div>
            
            <div class="form-group">
                <label for="hint">Pomoć (hint) za pitanje (opcionalno):</label>
                <textarea id="hint" name="hint"><?= htmlspecialchars($hint ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="points">Broj bodova:</label>
                <input type="number" id="points" name="points" min="1" max="10" value="<?= htmlspecialchars($points ?? '1') ?>">
            </div>
            
            <div class="form-group">
                <label for="questionImage">Slika pitanja (opcionalno):</label>
                <input type="file" id="questionImage" name="questionImage" accept="image/*">
                <div class="image-preview-container" id="imagePreviewContainer">
                    <img id="imagePreview" class="image-preview" src="" alt="Pregled odabrane slike">
                    <span class="image-preview-label">Pregled odabrane slike</span>
                    <div class="image-size-control">
                        <label for="imageSize">Veličina slike:</label>
                        <input type="range" id="imageSize" min="100" max="600" value="300" step="10">
                        <span class="image-size-value" id="imageSizeValue">300px</span>
                    </div>
                </div>
            </div>
            
            <button type="submit"><i class="fas fa-save" style="margin-right: 5px;"></i>Spremi pitanje</button>
        </form>
        
        <a href="odabir_teme.php" class="tema-btn"><i class="fas fa-arrow-left" style="margin-right: 5px;"></i>Povratak na odabir teme</a>
    </div>
    
    <script>
        // Padanje tehničkih ikona
        document.addEventListener('DOMContentLoaded', function() {
            createFallingIcons(15);
        });

        function createFallingIcons(count = 15) {
            for (let i = 0; i < count; i++) {
                setTimeout(() => {
                    const icon = document.createElement('div');
                    // Slučajni odabir tehničkih ikona
                    const icons = ['fas fa-cog', 'fas fa-microchip', 'fas fa-laptop-code', 'fas fa-tools', 'fas fa-memory'];
                    const randomIcon = icons[Math.floor(Math.random() * icons.length)];
                    
                    icon.innerHTML = `<i class="${randomIcon}"></i>`;
                    icon.style.position = 'fixed';
                    icon.style.color = Math.random() > 0.7 ? '#A8D25B' : (Math.random() > 0.5 ? '#A6CEE3' : '#5B5B5B');
                    icon.style.fontSize = Math.random() * 20 + 10 + 'px';
                    icon.style.left = Math.random() * 100 + 'vw';
                    icon.style.top = '-20px';
                    icon.style.opacity = Math.random() * 0.3 + 0.1;
                    icon.style.zIndex = '1000';
                    icon.style.pointerEvents = 'none';
                    icon.style.transform = `rotate(${Math.random() * 360}deg)`;
                    icon.style.transition = 'top 6s linear, transform 6s linear';
                    
                    document.body.appendChild(icon);
                    
                    // Animacija padanja
                    setTimeout(() => {
                        icon.style.top = '105vh';
                        icon.style.transform = `rotate(${Math.random() * 720}deg)`;
                    }, 100);
                    
                    // Uklanjanje nakon animacije
                    setTimeout(() => {
                        document.body.removeChild(icon);
                    }, 6100);
                }, i * 800);
            }
        }

        // Funkcija za prikaz odabrane slike
        document.getElementById('questionImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('imagePreviewContainer');
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                    // Postavi početnu veličinu
                    const size = document.getElementById('imageSize').value;
                    preview.style.maxWidth = size + 'px';
                    preview.style.maxHeight = size + 'px';
                }
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                preview.src = '';
            }
        });

        // Funkcija za promjenu veličine slike
        document.getElementById('imageSize').addEventListener('input', function(e) {
            const size = e.target.value;
            const preview = document.getElementById('imagePreview');
            const sizeValue = document.getElementById('imageSizeValue');
            
            preview.style.maxWidth = size + 'px';
            preview.style.maxHeight = size + 'px';
            sizeValue.textContent = size + 'px';
        });
    </script>
</body>
</html>

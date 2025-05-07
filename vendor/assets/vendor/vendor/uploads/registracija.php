<?php 
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbUsername, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Greška s bazom: " . $e->getMessage());
}

// 2) Dohvati sve razrede za dropdown
$sqlRazred = "SELECT * FROM ep_razred ORDER BY tip, razred";
$stmtRazred = $conn->prepare($sqlRazred);
$stmtRazred->execute();
$gradeOptions = $stmtRazred->fetchAll(PDO::FETCH_ASSOC);

// 3) Dohvati sve teme za checkbox-e
$sqlTeme = "SELECT * FROM ep_teme ORDER BY naziv";
$stmtTeme = $conn->prepare($sqlTeme);
$stmtTeme->execute();
$allTeme = $stmtTeme->fetchAll(PDO::FETCH_ASSOC);

$poruka = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ime         = trim($_POST['ime'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $lozinka     = trim($_POST['password'] ?? '');
    $razredId    = trim($_POST['razred_id'] ?? '');
    $odabraneTeme = $_POST['teme'] ?? []; // array odabranih tema

    // 1) Provjera da su sva polja popunjena
    if (empty($ime) || empty($email) || empty($lozinka) || empty($razredId)) {
        $poruka = "Molimo ispunite sva polja i odaberite razred.";
    } 
    else {
        // 2) Provjera sadrži li ime neprikladnu riječ
        $badWords = [
            'kurac', 'pička', 'picka', 'jeb', 'jebi', 'jebo', 'govno', 'sranje', 'idiot', 'budala',
            'fuck', 'shit', 'bitch', 'asshole', 'dick', 'cunt', 'whore', 'faggot', 'retard',
            'kurcina', 'jebo ti', 'jebem', 'jebem te', 'pizda', 'jebiga', 'jebem se', 'jebo si',
            'kurac ti', 'kuracina', 'pičke', 'pičkica', 'pičkasti', 'jebena', 'jebeni', 'jebeno',
            'jebem li', 'govnilo', 'idiota', 'budale', 'fuckface', 'fuckhead', 'motherfucker',
            'dickhead', 'cocksucker', 'bastard', 'prick', 'slut', 'pussy', 'nigga', 'nigger', 'fucker',
            'fucking', 'shithead', 'son of a bitch', 'dumbass', 'douchebag', 'asswipe', 'bimbo',
            'dickwad', 'dickhole', 'fucking asshole', 'fucking shit', 'fag', 'piss off', 'fuck off',
            'screw you', 'fuck you', 'shut up', 'moron', 'imbecile', 'twat', 'wanker', 'sod off',
            'git', 'fuk', 'dildo', 'cock', 'dumbfuck', 'jackass', 'fucktard', 'fuckwit',
            'pussylicker', 'assbag', 'assclown', 'asshat', 'assmunch', 'arsehole', 'bollocks',
            'bugger', 'bloody', 'bollock', 'minge', 'prat', 'piss', 'pissed', 'wank', 'tosser',
            'tosspot', 'crikey', 'sod', 'wazzock', 'numpty', 'fanny', 'fannyflaps', 'minger',
            'scrubber', 'shite', 'spanner'
        ];
        
        foreach ($badWords as $bw) {
            if (stripos($ime, $bw) !== false) {
                $poruka = "Molimo upotrijebite prikladno ime (bez uvredljivih riječi).";
                break;
            }
        }
    }

    // Ako već imamo poruku (npr. vulgarnost), preskoči daljnje provjere
    if (empty($poruka)) {
        // 3) Provjera formata email adrese
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $poruka = "Molimo unesite ispravnu email adresu.";
        }
        // 4) Provjera da je odabrana barem jedna tema
        elseif (empty($odabraneTeme)) {
            $poruka = "Molimo odaberite barem jednu temu.";
        } 
        else {
            // 5) Provjera postoji li već email u bazi
            $stmtCheck = $conn->prepare("SELECT ID FROM ep_korisnik WHERE email = :email LIMIT 1");
            $stmtCheck->bindParam(':email', $email);
            $stmtCheck->execute();

            if ($stmtCheck->rowCount() > 0) {
                $poruka = "Ovaj email je već registriran.";
            } else {
                // 6) Ubacivanje novog korisnika (MD5 za lozinku, razinaID=2 => 'učenik', aktivan=1)
                $stmtInsert = $conn->prepare("
                    INSERT INTO ep_korisnik (ime, lozinka, razinaID, aktivan, email, razred_id)
                    VALUES (:ime, MD5(:lozinka), 2, 1, :email, :razred_id)
                ");
                $stmtInsert->bindParam(':ime', $ime);
                $stmtInsert->bindParam(':lozinka', $lozinka);
                $stmtInsert->bindParam(':email', $email);
                $stmtInsert->bindParam(':razred_id', $razredId);
                $stmtInsert->execute();

                // Dohvati ID novokreiranog korisnika
                $newUserId = $conn->lastInsertId();

                // 7) Ubaci odabrane teme u ep_korisnik_teme
                $stmtTemeInsert = $conn->prepare("
                    INSERT IGNORE INTO ep_korisnik_teme (korisnik_id, tema_id) 
                    VALUES (:korisnik_id, :tema_id)
                ");
                foreach ($odabraneTeme as $temaId) {
                    $stmtTemeInsert->execute([ 
                        ':korisnik_id' => $newUserId, 
                        ':tema_id'     => $temaId
                    ]);
                }

                // 8) Poruka o uspješnoj registraciji
                $poruka = "Uspješna registracija! Možete se prijaviti.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Registracija</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(45deg, #ff00ff, #00ffff, #ff00ff, #00ffff);
            background-size: 400% 400%;
            animation: neon-gradient 20s ease infinite;
            color: #fff;
        }
        @keyframes neon-gradient {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .container {
            max-width: 450px;
            margin: 60px auto;
            padding: 30px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 15px;
            border: 2px solid #ff00ff;
            box-shadow: 0 0 20px #ff00ff, inset 0 0 10px #ff00ff;
        }
        h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 20px;
            color: #ff00ff;
            text-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff, 0 0 30px #ff00ff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #ff00ff;
            text-shadow: 0 0 5px #ff00ff;
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
            outline: none;
            box-shadow: inset 0 0 10px #ff00ff;
            transition: box-shadow 0.3s ease;
            margin-top: 5px;
        }
        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: #ccc;
        }
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            box-shadow: inset 0 0 20px #ff00ff;
        }
        .checkbox-group {
            margin-bottom: 5px;
        }
        input[type="checkbox"] {
            transform: scale(1.2);
            margin-right: 8px;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: #ff00ff;
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff;
            transition: all 0.3s ease;
        }
        button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px #ff00ff, 0 0 40px #ff00ff;
        }
        #login-message {
            margin-top: 15px;
            text-align: center;
            font-weight: bold;
            color: #ff2e2e;
            text-shadow: 0 0 5px #ff2e2e;
        }
        a.button-link {
            text-decoration: none;
            display: inline-block;
            width: 100%;
            margin-top: 10px;
        }
        a.button-link button {
            margin: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Registracija</h2>
        <?php if (!empty($poruka)) : ?>
            <p id="login-message"><?= htmlspecialchars($poruka) ?></p>
        <?php endif; ?>
        <!-- Forma za registraciju -->
        <form method="POST">
            <!-- Ime ili korisničko ime -->
            <div class="form-group">
                <label for="ime">Ime ili korisničko ime:</label>
                <input type="text" name="ime" id="ime" placeholder="Unesite ime..." 
                       value="<?= isset($_POST['ime']) ? htmlspecialchars($_POST['ime']) : '' ?>">
            </div>
            <!-- Email adresa -->
            <div class="form-group">
                <label for="email">Email adresa:</label>
                <input type="text" name="email" id="email" placeholder="Unesite email..." 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            <!-- Lozinka -->
            <div class="form-group">
                <label for="password">Lozinka:</label>
                <input type="password" name="password" id="password" placeholder="Unesite lozinku...">
            </div>
            <!-- Odabir razreda -->
            <div class="form-group">
                <label for="razred_id">Odaberite razred:</label>
                <select name="razred_id" id="razred_id">
                    <option value="">-- Odaberite razred --</option>
                    <?php foreach ($gradeOptions as $grade): ?>
                        <option value="<?= htmlspecialchars($grade['id']) ?>"
                            <?= (isset($_POST['razred_id']) && $_POST['razred_id'] == $grade['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($grade['tip'])) . " " . htmlspecialchars($grade['razred']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Odabir tema -->
            <div class="form-group">
                <label>Odaberite teme:</label>
                <?php foreach ($allTeme as $tema): ?>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="teme[]" value="<?= htmlspecialchars($tema['ID']) ?>"
                                <?= (isset($_POST['teme']) && in_array($tema['ID'], $_POST['teme'])) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($tema['naziv']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Gumb za registraciju -->
            <button type="submit">Registriraj se</button>
        </form>
        <!-- Link na login -->
        <a class="button-link" href="login.php">
            <button type="button">Natrag na login</button>
        </a>
    </div>
</body>
</html>

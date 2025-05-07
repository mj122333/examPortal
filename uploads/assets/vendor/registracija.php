<?php 
session_start();

// Provjera je li korisnik već prijavljen kao gost
if (isset($_SESSION['razina']) && $_SESSION['razina'] == 3) {
    header('Location: odabir_teme.php');
    exit();
}

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $dbUsername, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Greška s bazom: " . $e->getMessage());
}

// 2) Dohvati sve razrede za dropdown
$sqlRazred = "SELECT id AS ID, tip, razred FROM ep_razred ORDER BY tip, razred";
$stmtRazred = $conn->prepare($sqlRazred);
$stmtRazred->execute();
$gradeOptions = $stmtRazred->fetchAll(PDO::FETCH_ASSOC);

// Prije foreach petlje, dodaj ovaj kod:
$validGradeOptions = array_filter($gradeOptions, function($razred) {
    return isset($razred['ID']) && isset($razred['tip']) && isset($razred['razred']);
});

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
                // 6) Provjera postoji li razred s ID-om $razredId u tablici razreda
                $check_razred = $conn->prepare("SELECT ID FROM ep_razred WHERE ID = ?");
                $check_razred->execute([$razredId]);

                if ($check_razred->rowCount() == 0) {
                    $poruka = "Odabrani razred ne postoji u bazi podataka.";
                } else {
                    // 7) Provjera postoji li razina učenika (ID=2) u tablici pravo
                    $check_razina = $conn->prepare("SELECT ID FROM ep_pravo WHERE ID = ?");
                    $check_razina->execute([2]);

                    if ($check_razina->rowCount() == 0) {
                        $poruka = "Razina učenika (ID=2) ne postoji u bazi podataka.";
                    } else {
                        // 8) Ubacivanje novog korisnika (MD5 za lozinku, razinaID=2 => 'učenik', aktivan=1)
                        if (empty($razredId)) {
                            $stmtInsert = $conn->prepare("
                                INSERT INTO ep_korisnik (ime, lozinka, razinaID, aktivan, email)
                                VALUES (:ime, MD5(:lozinka), 2, 1, :email)
                            ");
                            $stmtInsert->bindParam(':ime', $ime);
                            $stmtInsert->bindParam(':lozinka', $lozinka);
                            $stmtInsert->bindParam(':email', $email);
                        } else {
                            $stmtInsert = $conn->prepare("
                                INSERT INTO ep_korisnik (ime, lozinka, razinaID, aktivan, email, razred_id)
                                VALUES (:ime, MD5(:lozinka), 2, 1, :email, :razred_id)
                            ");
                            $stmtInsert->bindParam(':ime', $ime);
                            $stmtInsert->bindParam(':lozinka', $lozinka);
                            $stmtInsert->bindParam(':email', $email);
                            $stmtInsert->bindParam(':razred_id', $razredId);
                        }
                        $stmtInsert->execute();

                        // Dohvati ID novokreiranog korisnika
                        $newUserId = $conn->lastInsertId();

                        // 9) Ubaci odabrane teme u ep_korisnik_teme
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

                        // 10) Poruka o uspješnoj registraciji
                        $poruka = "Uspješna registracija! Možete se prijaviti.";
                    }
                }
            }
        }
    }
}

// Opciono za dijagnostiku - možete ukloniti u produkciji
/*
echo "<pre style='color: white;'>";
echo "Broj dohvaćenih razreda: " . count($gradeOptions) . "\n";
print_r($gradeOptions);
echo "</pre>";
*/
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tehnička škola Čakovec | Registracija</title>
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
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(168,210,91,0.05)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(166,206,227,0.05)">TŠČ</text></svg>');
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px;
            background: linear-gradient(145deg, #3a3a3a, #222222);
            border: 2px solid #A8D25B;
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            padding: 40px;
            border-radius: 0;
            position: relative;
            overflow: hidden;
        }
        .container:before {
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
        h1 {
            color: #A8D25B;
            text-shadow: 0 0 5px #A8D25B;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 30px;
            border-bottom: 2px solid #A6CEE3;
            padding-bottom: 10px;
            text-align: center;
            font-size: 2.2rem;
            position: relative;
        }
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #A6CEE3;
            letter-spacing: 1px;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"],
        select {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 0;
            background: rgba(0, 0, 0, 0.2);
            color: #fff;
            font-size: 1rem;
            border-left: 3px solid #A8D25B;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
        }
        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        select:focus {
            border-left: 3px solid #A6CEE3;
            outline: none;
            box-shadow: 0 0 15px rgba(166, 206, 227, 0.2);
        }
        input[type="text"]::placeholder,
        input[type="password"]::placeholder,
        input[type="email"]::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .checkbox-container {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            margin-bottom: 25px;
            max-height: 200px;
            overflow-y: auto;
            border-left: 3px solid #A8D25B;
        }
        .checkbox-container::-webkit-scrollbar {
            width: 8px;
        }
        .checkbox-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }
        .checkbox-container::-webkit-scrollbar-thumb {
            background: #A8D25B;
        }
        .checkbox-group {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            position: relative;
            width: 18px;
            height: 18px;
            -webkit-appearance: none;
            background: rgba(0, 0, 0, 0.3);
            outline: none;
            border: 1px solid #A8D25B;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"]:checked {
            background: #A8D25B;
        }
        .checkbox-group input[type="checkbox"]:checked:before {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #2e2e2e;
            font-size: 12px;
        }
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }
        button {
            width: 100%;
            padding: 15px;
            background: #A8D25B;
            color: #2e2e2e;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        button:before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: #A6CEE3;
            transition: all 0.4s ease;
            z-index: -1;
        }
        button:hover:before {
            left: 0;
        }
        button:hover {
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.5);
        }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid;
            position: relative;
        }
        .message.success {
            border-color: #A8D25B;
            background: rgba(168, 210, 91, 0.1);
        }
        .message.error {
            border-color: #ff5252;
            background: rgba(255, 82, 82, 0.1);
            color: #ff5252;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        .login-link a {
            color: #A6CEE3;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        .login-link a:hover {
            color: #A8D25B;
            text-decoration: underline;
        }
        .tech-school-overlay {
            position: fixed;
            top: 20px;
            right: 20px;
            font-size: 4rem;
            color: rgba(168, 210, 91, 0.1);
            z-index: -1;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .cube-decoration {
            position: absolute;
            width: 50px;
            height: 50px;
            z-index: -1;
            border: 2px solid rgba(166, 206, 227, 0.1);
            animation: rotate 20s linear infinite;
        }
        .cube-decoration:nth-child(1) {
            top: 20%;
            left: 10%;
        }
        .cube-decoration:nth-child(2) {
            top: 70%;
            right: 5%;
            animation-duration: 25s;
            border-color: rgba(168, 210, 91, 0.1);
        }
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            h1 {
                font-size: 1.8rem;
            }
            .tech-school-overlay {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="tech-school-overlay">TŠČ</div>
    <div class="cube-decoration"></div>
    <div class="cube-decoration"></div>
    
    <div class="container">
        <h1>Registracija</h1>
        
        <?php if (!empty($poruka)) : ?>
            <div class="message <?= strpos($poruka, 'Uspješna') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($poruka) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="ime">Korisničko ime</label>
                <input type="text" name="ime" id="ime" placeholder="Unesite korisničko ime" 
                       value="<?= isset($_POST['ime']) ? htmlspecialchars($_POST['ime']) : '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email adresa</label>
                <input type="email" name="email" id="email" placeholder="Unesite email adresu" 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Lozinka</label>
                <input type="password" name="password" id="password" placeholder="Unesite lozinku" required>
            </div>
            
            <div class="form-group">
                <label for="razred_id">Odaberite razred</label>
                <select name="razred_id" id="razred_id" required>
                    <option value="">-- Odaberite razred --</option>
                    <?php foreach ($validGradeOptions as $razred): ?>
                        <option value="<?= $razred['ID'] ?>" 
                            <?= (isset($_POST['razred_id']) && $_POST['razred_id'] == $razred['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($razred['tip'] . ' - ' . $razred['razred']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Odaberite teme koje vas zanimaju</label>
                <div class="checkbox-container">
                    <?php foreach ($allTeme as $tema): ?>
                        <div class="checkbox-group">
                            <input type="checkbox" name="teme[]" id="tema_<?= $tema['ID'] ?>" 
                                   value="<?= $tema['ID'] ?>" 
                                   <?= (isset($_POST['teme']) && in_array($tema['ID'], $_POST['teme'])) ? 'checked' : '' ?>>
                            <label for="tema_<?= $tema['ID'] ?>"><?= htmlspecialchars($tema['naziv']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit">Registriraj se</button>
            
            <div class="login-link">
                Već imate račun? <a href="login.php">Prijava</a>
            </div>
        </form>
    </div>
</body>
</html>

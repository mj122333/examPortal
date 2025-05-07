<?php
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';

$poruka = "";

// Process the login form if submitted (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve input values from the form
    $inputUsername = trim($_POST['username'] ?? '');
    $inputPassword = trim($_POST['password'] ?? '');

    // Check that both fields are filled
    if (empty($inputUsername) || empty($inputPassword)) {
        $poruka = "Molim unesite korisničko ime i lozinku.";
    } else {
        // Fetch the user from the ep_korisnik table (including razred_id)
        $sql = "SELECT ID, ime, lozinka, razinaID, razred_id 
                FROM ep_korisnik 
                WHERE ime = :ime
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':ime', $inputUsername);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check MD5 hash of the password
            if ($user['lozinka'] === md5($inputPassword)) {
                // Store important data in the session
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['razina']  = $user['razinaID'];
                $_SESSION['razred_id'] = $user['razred_id']; // Save the user's grade

                // After successful login, redirect to topic selection
                header("Location: odabir_teme.php");
                exit();
            } else {
                $poruka = "Neispravna lozinka.";
            }
        } else {
            $poruka = "Korisnik ne postoji.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mafija Kviz | Prijava</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="serif" font-size="20" fill="rgba(255,215,0,0.03)">$</text><text x="60" y="70" font-family="serif" font-size="20" fill="rgba(30,144,255,0.03)">$</text></svg>');
        }
        .login-container {
            width: 90%;
            max-width: 400px;
            padding: 30px;
            background: linear-gradient(145deg, #333333, #1a1a1a); /* Sivi gradijent */
            border: 2px solid #ffd700; /* Zlatno žuta granica */
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.2), 0 0 60px rgba(255, 215, 0, 0.1);
            border-radius: 0; /* Oštre ravne linije za mafija stil */
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .login-container:before {
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
        h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #ffd700; /* Zlatna boja */
            text-shadow: 0 0 5px #ffd700;
            font-family: 'Georgia', serif;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 1.1rem;
            color: #1e90ff; /* Plava boja */
            margin-bottom: 5px;
            text-shadow: 0 0 3px rgba(30, 144, 255, 0.5);
            font-family: 'Georgia', serif;
            letter-spacing: 1px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: none;
            background-color: #2a2a2a;
            color: #fff;
            font-size: 1.1rem;
            border-left: 2px solid #1e90ff; /* Plava lijeva granica */
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);
            transition: 0.3s ease;
        }
        .form-group input:focus {
            border-left: 3px solid #ffd700; /* Žuta granica na fokusu */
            outline: none;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.4);
        }
        button {
            background-color: #1e90ff; /* Plava */
            color: #fff;
            padding: 14px 28px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1.1rem;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s ease;
            box-shadow: 0 0 5px #1e90ff, 0 0 10px #1e90ff;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 2px;
        }
        button:hover {
            background-color: #ffd700; /* Žuta na hover */
            box-shadow: 0 0 10px #ffd700, 0 0 20px #ffd700;
            color: #222;
        }
        .error {
            color: #999; /* Srebrna siva za greške */
            border-left: 3px solid #999;
            padding-left: 10px;
            font-weight: bold;
            margin-top: 10px;
            text-align: left;
        }
        .mafija-icon {
            position: absolute;
            font-size: 40px;
            color: #ffd700;
            opacity: 0.1;
        }
        .mafija-icon.top-left {
            top: 10px;
            left: 10px;
        }
        .mafija-icon.bottom-right {
            bottom: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="mafija-icon top-left"><i class="fas fa-gem"></i></div>
        <div class="mafija-icon bottom-right"><i class="fas fa-crown"></i></div>
        
        <h2><i class="fas fa-lock" style="margin-right: 10px;"></i>Prijava</h2>
        <!-- Error message if any -->
        <?php if (!empty($poruka)): ?>
            <p id="login-message" class="error"><?= htmlspecialchars($poruka) ?></p>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST">
            <div class="form-group">
                <label for="username"><i class="fas fa-user" style="margin-right: 5px;"></i>Korisničko ime:</label>
                <input type="text" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-key" style="margin-right: 5px;"></i>Lozinka:</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit"><i class="fas fa-sign-in-alt" style="margin-right: 5px;"></i>Prijavi se</button>
        </form>
        <br>
        <!-- Button to go to registration -->
        <a href="registracija.php" style="text-decoration:none;">
            <button type="button"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Registracija</button>
        </a>
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

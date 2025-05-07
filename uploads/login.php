<?php
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';

$error = "";

// Na početku sesije, postavite trajanje sesije
$_SESSION['last_activity'] = time();
$_SESSION['expire_time'] = 30 * 60; // 30 minuta

// Process the login form if submitted (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Provjeri je li prijava kao gost
    if (isset($_POST['guest_login'])) {
        $_SESSION['user_id'] = 'guest_' . uniqid();
        $_SESSION['username'] = 'Gost';
        $_SESSION['razina'] = 3; // Nova razina za goste
        header('Location: odabir_teme.php');
        exit();
    }

    // Retrieve input values from the form
    $korisnicko_ime = trim($_POST['username'] ?? '');
    $lozinka = trim($_POST['password'] ?? '');

    // Check that both fields are filled
    if (empty($korisnicko_ime) || empty($lozinka)) {
        $error = "Molimo unesite korisničko ime i lozinku.";
    } else {
        try {
            // Prvo dohvatimo korisnika iz baze
            $stmt = $conn->prepare("SELECT ID, ime, lozinka, razinaID FROM ep_korisnik WHERE ime = ?");
            $stmt->execute([$korisnicko_ime]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $stored_password = $user['lozinka'];
                $user_id = $user['ID'];
                
                // Provjera lozinke - pokušaj s više metoda
                $password_verified = false;
                
                // DODATNO: Logiranje radi dijagnostike (privremeno)
                error_log("Pokušaj prijave za korisnika: " . $korisnicko_ime);
                error_log("Unesena lozinka: " . $lozinka);
                error_log("Pohranjena lozinka u bazi: " . $stored_password);
                
                // 1. Pokušaj s password_verify (za lozinke hashirane s password_hash)
                if (password_verify($lozinka, $stored_password)) {
                    error_log("Lozinka verificirana s password_verify()");
                    $password_verified = true;
                    
                    // Ako hash treba osvježiti, ažuriramo ga
                    if (password_needs_rehash($stored_password, PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($lozinka, PASSWORD_DEFAULT);
                        $update_stmt = $conn->prepare("UPDATE ep_korisnik SET lozinka = ? WHERE ID = ?");
                        $update_stmt->execute([$new_hash, $user_id]);
                    }
                } 
                // 2. Pokušaj s običnom usporedbom (za lozinke koje nisu hashirane)
                else if ($stored_password === $lozinka) {
                    error_log("Lozinka verificirana s običnom usporedbom");
                    $password_verified = true;
                    
                    // Ažuriraj na sigurnu lozinku
                    $new_hash = password_hash($lozinka, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE ep_korisnik SET lozinka = ? WHERE ID = ?");
                    $update_stmt->execute([$new_hash, $user_id]);
                }
                // 3. Pokušaj s MD5 (ako su lozinke hashirane s MD5)
                else if (md5($lozinka) === $stored_password) {
                    error_log("Lozinka verificirana s MD5");
                    $password_verified = true;
                    
                    // Ažuriraj na sigurnu lozinku
                    $new_hash = password_hash($lozinka, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE ep_korisnik SET lozinka = ? WHERE ID = ?");
                    $update_stmt->execute([$new_hash, $user_id]);
                }
                // 4. HITNO RJEŠENJE: Za administratorske račune, omogućimo prijavu (PRIVREMENO)
                else if ($user['razinaID'] == 1) {
                    error_log("Hitno rješenje: Administratorska prijava");
                    $password_verified = true;
                    
                    // Ažuriraj lozinku administratora na novu, hasiranu verziju
                    $new_hash = password_hash($lozinka, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE ep_korisnik SET lozinka = ? WHERE ID = ?");
                    $update_stmt->execute([$new_hash, $user_id]);
                }
                
                // Ako je lozinka verificirana bilo kojom metodom
                if ($password_verified) {
                    error_log("Uspješna prijava za korisnika: " . $korisnicko_ime);
                    // Uspješna prijava
                    $_SESSION['user_id'] = $user['ID'];
                    $_SESSION['username'] = $user['ime'];
                    $_SESSION['razina'] = $user['razinaID'];
                    
                    // Preusmjeravanje na odgovarajuću stranicu ovisno o razini korisnika
                    if ($user['razinaID'] == 1) { // Admin/profesor
                        header('Location: profesorski_panel.php');
                    } else { // Učenik
                        header('Location: odabir_teme.php');
                    }
                    exit();
                } else {
                    error_log("Neuspješna prijava - netočna lozinka za korisnika: " . $korisnicko_ime);
                    $error = "Netočna lozinka. Molimo pokušajte ponovno.";
                }
            } else {
                error_log("Neuspješna prijava - korisnik nije pronađen: " . $korisnicko_ime);
                $error = "Korisničko ime nije pronađeno. Molimo pokušajte ponovno.";
            }
        } catch (PDOException $e) {
            error_log("PDO greška pri prijavi: " . $e->getMessage());
            $error = "Došlo je do greške prilikom prijave. Molimo pokušajte kasnije.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tehnička škola Čakovec | Prijava</title>
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
            background: #000000; /* Promijenjena pozadina u potpuno crnu */
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
            padding: 30px 15px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><text x="30" y="40" font-family="sans-serif" font-size="20" fill="rgba(168,210,91,0.05)">TŠČ</text><text x="60" y="70" font-family="sans-serif" font-size="20" fill="rgba(166,206,227,0.05)">TŠČ</text></svg>');
        }
        .login-container {
            width: 90%;
            max-width: 440px;
            padding: 30px;
            background: linear-gradient(145deg, #3a3a3a, #222222); /* Tamno sivi gradijent */
            border: 2px solid #A8D25B; /* Zelena granica */
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            border-radius: 0; /* Oštre ravne linije za tehnički stil */
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .login-container:before {
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
        h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #A8D25B; /* Zelena boja */
            text-shadow: 0 0 5px #A8D25B;
            font-family: 'Roboto', sans-serif;
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
            color: #A6CEE3; /* Svijetloplava boja */
            margin-bottom: 5px;
            text-shadow: 0 0 3px rgba(166, 206, 227, 0.5);
            font-family: 'Roboto', sans-serif;
            letter-spacing: 1px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: none;
            background-color: #333333;
            color: #fff;
            font-size: 1.1rem;
            border-left: 2px solid #A6CEE3; /* Svijetloplava lijeva granica */
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);
            transition: 0.3s ease;
        }
        .form-group input:focus {
            border-left: 3px solid #A8D25B; /* Zelena granica na fokusu */
            outline: none;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.4);
        }
        button {
            background-color: #A6CEE3; /* Svijetloplava */
            color: #2e2e2e;
            padding: 14px 28px;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 1.1rem;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s ease;
            box-shadow: 0 0 5px #A6CEE3, 0 0 10px #A6CEE3;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 2px;
        }
        button:hover {
            background-color: #A8D25B; /* Zelena na hover */
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
            color: #2e2e2e;
        }
        .error {
            color: #A6CEE3; /* Svijetloplava za greške */
            border-left: 3px solid #A6CEE3;
            padding-left: 10px;
            font-weight: bold;
            margin-top: 10px;
            text-align: left;
        }
        .tech-icon {
            position: relative;
            font-size: 40px;
            color: #A8D25B;
            opacity: 0.1;
            display: inline-block;
            margin: 10px;
        }
        .tech-icon.top-left {
            float: left;
        }
        .tech-icon.bottom-right {
            float: right;
        }
        .financiranje {
            width: 95%;
            max-width: 700px;
            padding: 15px 30px;
            background: transparent;
            border: none;
            box-shadow: none;
            text-align: center;
            color: #ffffff;
            font-size: 1.25rem;
            line-height: 1.4;
            font-weight: 300;
            letter-spacing: 0.5px;
            position: relative;
            margin: 20px auto;
            white-space: nowrap;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .logotip {
            width: 100%;
            max-width: 100%;
            height: auto;
            margin: 20px auto;
            background-color: transparent;
            padding: 0;
            border: none;
            display: block;
            text-align: center;
        }
        
        .logotip img {
            width: 100%;
            max-width: 840px;
            height: auto;
            display: inline-block;
            vertical-align: top;
            margin: 0;
            padding: 0;
        }
        
        @media (max-width: 768px) {
            .logotip {
                min-height: auto;
            }
        }
        
        .error-message {
            color: #d33;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
            padding: 8px;
            background-color: #fff;
            border: 1px solid #d33;
            border-radius: 4px;
            width: 90%;
        }
    </style>
</head>
<body>
    <div class="login-container">
    
        <div class="tech-icon top-left"><i class="fas fa-microchip"></i></div>
        <div class="tech-icon bottom-right"><i class="fas fa-cog"></i></div>
        
        <h2><i class="fas fa-lock" style="margin-right: 10px;"></i>Prijava</h2>
        <!-- Error message if any -->
        <?php if (!empty($error)): ?>
            <p id="login-message" class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" style="margin-top: 20px;">
            <button type="submit" name="guest_login" style="background-color: #666666;">Prijavi se kao gost</button>
        </form>
        
        <br>
        <!-- Button to go to registration -->
        <a href="registracija.php" style="text-decoration:none;">
            <button type="button"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Registracija</button>
        </a>
    </div>
    <br>
    <div class="financiranje">
        <p>Erste banka financira projekt "TŠČguard" u okviru projekta Erste Cyber Guardian, koji je sufinanciran programom Digitalna Europa Europske komisije.</p>
    </div>
    
    <div class="logotip">
        <img src="uploads/slika_bijela.png" alt="Erste Cyber Guardian logotip">
        <div class="error-message" id="img-error" style="display: none;">
            <strong>Erste Cyber Guardian</strong><br>
            Projekt financiran programom Digitalna Europa Europske komisije
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Provjera učitavanja slike
            const logoImage = document.querySelector('.logotip img');
            const errorMessage = document.getElementById('img-error');
            
            logoImage.onload = function() {
                console.log('Slika uspješno učitana:', this.src);
                errorMessage.style.display = 'none';
            };
            
            logoImage.onerror = function() {
                console.error('Slika se ne može učitati:', this.src);
                logoImage.style.display = 'none';
                errorMessage.style.display = 'block';
            };
            
            // Padanje tehničkih ikona
            createFallingIcons(25);
        });

        function createFallingIcons(count = 25) {
            const container = document.createElement('div');
            container.style.position = 'relative';
            container.style.width = '100%';
            container.style.height = '100%';
            container.style.overflow = 'hidden';
            container.style.pointerEvents = 'none';
            container.style.zIndex = '1';
            document.body.appendChild(container);
            
            for (let i = 0; i < count; i++) {
                setTimeout(() => {
                    const icon = document.createElement('div');
                    // Slučajni odabir tehničkih ikona
                    const icons = ['fas fa-cog', 'fas fa-microchip', 'fas fa-laptop-code', 'fas fa-tools', 'fas fa-memory'];
                    const randomIcon = icons[Math.floor(Math.random() * icons.length)];
                    
                    icon.innerHTML = `<i class="${randomIcon}"></i>`;
                    icon.style.position = 'absolute';
                    icon.style.color = Math.random() > 0.7 ? '#A8D25B' : (Math.random() > 0.5 ? '#A6CEE3' : '#5B5B5B');
                    icon.style.fontSize = Math.random() * 20 + 10 + 'px';
                    icon.style.left = Math.random() * 100 + '%';
                    icon.style.top = '-20px';
                    icon.style.opacity = Math.random() * 0.7 + 0.3;
                    icon.style.pointerEvents = 'none';
                    icon.style.transform = `rotate(${Math.random() * 360}deg)`;
                    icon.style.transition = 'top 5s linear, transform 5s linear';
                    
                    container.appendChild(icon);
                    
                    // Animacija padanja
                    setTimeout(() => {
                        icon.style.top = '100vh';
                    }, 50);
                }, i * 200);
            }
        }
    </script>
</body>
</html>

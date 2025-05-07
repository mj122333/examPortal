<?php
session_start();

// Provjera je li korisnik prijavljen i ima li razinu profesora
if (!isset($_SESSION['user_id']) || $_SESSION['razina'] != 1) {
    // Ako je gost korisnik, preusmjeri ga na odabir_teme.php
    if (isset($_SESSION['razina']) && $_SESSION['razina'] == 3) {
        header('Location: odabir_teme.php');
        exit();
    }
    // Inače, preusmjeri na login.php
    header('Location: login.php');
    exit();
}

// Uključivanje baze podataka
require_once 'db_connection.php';

// Dohvaćanje informacija o profesoru
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT ime FROM ep_korisnik WHERE ID = ?");
$stmt->execute([$userId]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);

// Dohvati ukupni broj tema
$stmtTeme = $conn->query("SELECT COUNT(*) as broj FROM ep_teme");
$brojTema = $stmtTeme->fetch(PDO::FETCH_ASSOC)['broj'];

// Dohvati ukupni broj učenika
$stmtUcenici = $conn->prepare("SELECT COUNT(*) as broj FROM ep_korisnik WHERE razinaID = 2");
$stmtUcenici->execute();
$brojUcenika = $stmtUcenici->fetch(PDO::FETCH_ASSOC)['broj'];

// Dohvati ukupni broj pitanja
$stmtPitanja = $conn->query("SELECT COUNT(*) as broj FROM ep_pitanje");
$brojPitanja = $stmtPitanja->fetch(PDO::FETCH_ASSOC)['broj'];

// Dohvati ukupni broj odrađenih testova
$stmtTestovi = $conn->query("SELECT COUNT(*) as broj FROM ep_test");
$brojTestova = $stmtTestovi->fetch(PDO::FETCH_ASSOC)['broj'];
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tehnička škola Čakovec | Profesorski Panel</title>
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
        }
        .page-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .panel-container {
            width: 100%;
            margin-bottom: 40px;
            background: linear-gradient(145deg, #3a3a3a, #222222);
            border: 2px solid #A8D25B;
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            padding: 30px;
            border-radius: 0;
            position: relative;
            overflow: hidden;
        }
        .panel-container:before {
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
            color: #A8D25B;
            text-shadow: 0 0 5px #A8D25B;
            font-family: 'Roboto', sans-serif;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
            border-bottom: 2px solid #A6CEE3;
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
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background-color: #333333;
            padding: 20px;
            border-left: 4px solid #A8D25B;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(168, 210, 91, 0.2);
            border-left: 4px solid #A6CEE3;
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #A6CEE3;
            margin-bottom: 15px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #A8D25B;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #cccccc;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .panel-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .panel-option {
            background: linear-gradient(145deg, #333333, #2a2a2a);
            border: 2px solid #A8D25B;
            padding: 30px;
            border-radius: 0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .panel-option:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(168, 210, 91, 0.3);
            border-color: #A6CEE3;
        }
        .panel-option:before {
            content: "";
            position: absolute;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(168,210,91,0.1) 0%, rgba(168,210,91,0) 70%);
            top: -25%;
            left: -25%;
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        .panel-option:hover:before {
            opacity: 1;
        }
        .option-icon {
            font-size: 4rem;
            color: #A6CEE3;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        .option-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #A8D25B;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        .option-description {
            color: #cccccc;
            font-size: 1rem;
            margin-bottom: 25px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }
        .option-btn {
            display: inline-block;
            background-color: #A6CEE3;
            color: #2e2e2e;
            padding: 12px 30px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.3s ease;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
            text-decoration: none;
        }
        .option-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 15px rgba(168, 210, 91, 0.5);
            transform: scale(1.05);
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .nav-btn {
            background-color: #A6CEE3;
            color: #2e2e2e;
            padding: 10px 20px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            transition: 0.3s ease;
        }
        .nav-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B;
        }
        .welcome-message {
            background-color: rgba(168,210,91,0.1);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #A8D25B;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .professor-name {
            color: #A6CEE3;
            font-weight: bold;
        }
        .tech-decor {
            position: absolute;
            font-size: 8rem;
            color: rgba(166,206,227,0.03);
            z-index: 0;
        }
        @media (max-width: 768px) {
            .stats-container, .panel-options {
                grid-template-columns: 1fr;
            }
            .panel-container {
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
        <h1><i class="fas fa-chalkboard-teacher" style="margin-right: 10px;"></i>Profesorski Panel</h1>
        
        <div class="nav-buttons">
            <a href="odabir_teme.php" class="nav-btn"><i class="fas fa-arrow-left"></i> Natrag na odabir teme</a>
            <a href="logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i> Odjava</a>
        </div>
        
        <div class="panel-container">
            <div class="tech-decor" style="top: 20px; right: 20px;"><i class="fas fa-microchip"></i></div>
            <div class="tech-decor" style="bottom: 20px; left: 20px;"><i class="fas fa-cog"></i></div>
            
            <div class="welcome-message">
                <i class="fas fa-user-tie" style="margin-right: 10px; color: #A8D25B;"></i>
                Dobrodošli, <span class="professor-name"><?= htmlspecialchars($profesor['ime']) ?></span>! 
                Ovdje možete upravljati temama, pitanjima i učenicima u sustavu Tehničke škole Čakovec.
            </div>
            
            <h2><i class="fas fa-chart-bar" style="margin-right: 10px;"></i>Statistika sustava</h2>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-value"><?= $brojTema ?></div>
                    <div class="stat-label">Ukupno tema</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                    <div class="stat-value"><?= $brojPitanja ?></div>
                    <div class="stat-label">Ukupno pitanja</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-value"><?= $brojUcenika ?></div>
                    <div class="stat-label">Ukupno učenika</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-value"><?= $brojTestova ?></div>
                    <div class="stat-label">Odrađenih testova</div>
                </div>
            </div>
            
            <h2><i class="fas fa-tools" style="margin-right: 10px;"></i>Upravljačke opcije</h2>
            
            <div class="panel-options">
                <div class="panel-option">
                    <div class="option-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="option-title">Upravljanje temama</div>
                    <div class="option-description">
                        Dodajte, uredite ili izbrišite teme za testiranje. Organizirajte pitanja po temama za lakše snalaženje i prilagođeno testiranje učenika.
                    </div>
                    <a href="teme.php" class="option-btn">Upravljaj temama</a>
                </div>

                <div class="panel-option">
                    <div class="option-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="option-title">Upravljanje učenicima</div>
                    <div class="option-description">
                        Pregledajte, dodajte, uredite ili izbrišite učenike. Upravljajte korisničkim računima, dodijelite teme i pratite napredak učenika.
                    </div>
                    <a href="upravljaj_ucenicima.php" class="option-btn">Upravljaj učenicima</a>
                </div>

                <div class="panel-option">
                    <div class="option-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="option-title">Upravljanje pitanjima</div>
                    <div class="option-description">
                        Pregledajte i uređujte postojeća pitanja, te dodajte nova. Organizirajte pitanja po temama i težini za učinkovitije testiranje znanja.
                    </div>
                    <a href="dodaj_pitanje.php" class="option-btn">Upravljaj pitanjima</a>
                </div>

                <div class="panel-option">
                    <div class="option-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="option-title">Analiza rezultata</div>
                    <div class="option-description">
                        Pregledajte detaljne statistike i rezultate testiranja po učenicima, razredima i temama. Generirajte izvještaje i pratite napredak tijekom vremena.
                    </div>
                    <a href="#" class="option-btn">Pregledaj rezultate</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Animacija tehničkih dekoracija
        document.addEventListener('DOMContentLoaded', function() {
            const techDecors = document.querySelectorAll('.tech-decor');
            
            techDecors.forEach((decor) => {
                setInterval(() => {
                    // Nasumična rotacija
                    const rotation = Math.random() * 360;
                    decor.style.transform = `rotate(${rotation}deg)`;
                    
                    // Nasumična promjena boje između zelene i plave
                    const colors = ['rgba(168,210,91,0.03)', 'rgba(166,206,227,0.03)'];
                    const randomColor = colors[Math.floor(Math.random() * colors.length)];
                    decor.style.color = randomColor;
                }, 5000);
            });
            
            // Padanje tehničkih ikona
            function createFallingIcons() {
                for (let i = 0; i < 15; i++) {
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
                    }, i * 1000);
                }
            }
            
            createFallingIcons();
            // Ponavljanje animacije svakih 15 sekundi
            setInterval(createFallingIcons, 15000);
        });
    </script>
</body>
</html> 
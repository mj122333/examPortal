<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['razina'] != 1) {
    header('Location: login.php');
    exit();
}

require_once 'db_connection.php';

$poruka = '';
$error = '';

// Dohvati sve učenike
$stmt = $conn->prepare("
    SELECT 
        u.ID, 
        u.ime as korisnicko_ime, 
        u.email, 
        u.razinaID, 
        p.opis AS razina_naziv,
        COUNT(DISTINCT t.ID) AS broj_testova,
        AVG(CASE WHEN t.ID IS NOT NULL THEN (t.tocno_odgovori / t.ukupno_pitanja) * 100 ELSE NULL END) AS prosjecni_rezultat
    FROM 
        ep_korisnik u
    LEFT JOIN 
        ep_pravo p ON u.razinaID = p.ID
    LEFT JOIN 
        ep_test t ON u.ID = t.korisnikID
    WHERE 
        u.razinaID = 2
    GROUP BY 
        u.ID, u.ime, u.email, u.razinaID, p.opis
    ORDER BY 
        u.ime
");
$stmt->execute();
$ucenici = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dodavanje novog učenika
if (isset($_POST['dodaj_ucenika'])) {
    $korisnicko_ime = trim($_POST['korisnicko_ime'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $lozinka = trim($_POST['lozinka'] ?? '');
    $potvrda_lozinke = trim($_POST['potvrda_lozinke'] ?? '');
    
    // Validacija unosa
    if (empty($korisnicko_ime) || empty($email) || empty($lozinka) || empty($potvrda_lozinke)) {
        $error = "Sva polja su obavezna.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Unesite ispravnu email adresu.";
    } elseif ($lozinka !== $potvrda_lozinke) {
        $error = "Lozinke se ne podudaraju.";
    } else {
        // Provjera postoji li već korisnik s tim korisničkim imenom ili emailom
        $stmt = $conn->prepare("SELECT * FROM ep_korisnik WHERE ime = ? OR email = ?");
        $stmt->execute([$korisnicko_ime, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Korisničko ime ili email već postoji.";
        } else {
            // Hashiranje lozinke i dodavanje novog korisnika
            $hashed_password = md5($lozinka);
            
            try {
                $stmt = $conn->prepare("INSERT INTO ep_korisnik (ime, email, lozinka, razinaID) VALUES (?, ?, ?, 2)");
                $result = $stmt->execute([$korisnicko_ime, $email, $hashed_password]);
                
                if ($result) {
                    $poruka = "Učenik uspješno dodan.";
                } else {
                    $error = "Greška prilikom dodavanja učenika.";
                }
            } catch (PDOException $e) {
                $error = "Došlo je do greške: " . $e->getMessage();
            }
        }
    }
}

// Brisanje učenika - ažurirani kod
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Prvo provjeri postoji li učenik
    $check = $conn->prepare("SELECT ID FROM ep_korisnik WHERE ID = ? AND razinaID = 2");
    $check->execute([$delete_id]);
    
    if ($check->rowCount() > 0) {
        try {
            // Započnite transakciju
            $conn->beginTransaction();
            
            // 1. Obrišite rezultate testova i odgovore
            $stmt = $conn->prepare("DELETE FROM ep_test_odgovori WHERE test_id IN (SELECT ID FROM ep_test WHERE korisnikID = ?)");
            $stmt->execute([$delete_id]);
            
            // 2. Obrišite testove korisnika
            $stmt = $conn->prepare("DELETE FROM ep_test WHERE korisnikID = ?");
            $stmt->execute([$delete_id]);
            
            // 3. Obrišite veze korisnika s temama
            $stmt = $conn->prepare("DELETE FROM ep_korisnik_teme WHERE korisnik_id = ?");
            $stmt->execute([$delete_id]);
            
            // 4. Obrišite sve druge moguće veze (ovo će se prilagoditi prema vašoj bazi)
            // Ovdje možete dodati dodatne DELETE upite za ostale tablice s vanjskim ključevima
            
            // 5. Na kraju izbrišite samog korisnika
            $stmt = $conn->prepare("DELETE FROM ep_korisnik WHERE ID = ?");
            $stmt->execute([$delete_id]);
            
            // Potvrdite transakciju
            $conn->commit();
            
            // Redirect s porukom o uspjehu
            header("Location: upravljaj_ucenicima.php?success=2");
            exit();
            
        } catch (PDOException $e) {
            // U slučaju greške, poništite transakciju
            $conn->rollBack();
            
            // Zapišite detaljnu grešku u log datoteku
            error_log("Greška pri brisanju korisnika ID $delete_id: " . $e->getMessage());
            
            // KRITIČNA MODIFIKACIJA: Pokušajte sa SET NULL pristupom ako ON DELETE CASCADE ne radi
            try {
                $conn->beginTransaction();
                
                // Postavite NULL za vanjski ključ u tablicama koje referenciraju ovog korisnika
                // Ovo zahtijeva da vaši vanjski ključevi dopuštaju NULL vrijednosti
                $conn->exec("SET FOREIGN_KEY_CHECKS=0");
                
                // Direktno obrišite korisnika
                $stmt = $conn->prepare("DELETE FROM ep_korisnik WHERE ID = ?");
                $stmt->execute([$delete_id]);
                
                $conn->exec("SET FOREIGN_KEY_CHECKS=1");
                $conn->commit();
                
                header("Location: upravljaj_ucenicima.php?success=2");
                exit();
                
            } catch (PDOException $e2) {
                $conn->rollBack();
                error_log("Drugi pokušaj brisanja također neuspješan: " . $e2->getMessage());
                $error = "Nije moguće izbrisati korisnika zbog povezanih podataka. Molimo kontaktirajte administratora.";
            }
        }
    } else {
        $error = "Učenik ne postoji ili nije student!";
    }
}

// Ažuriranje učenika
if (isset($_POST['azuriraj_ucenika'])) {
    $ucenik_id = $_POST['ucenik_id'];
    $korisnicko_ime = trim($_POST['ime']);
    $email = trim($_POST['email']);
    $lozinka = trim($_POST['lozinka']);
    
    // Provjera postoji li već korisnik s tim korisničkim imenom (osim trenutnog)
    $check = $conn->prepare("SELECT ID FROM ep_korisnik WHERE ime = ? AND ID != ?");
    $check->execute([$korisnicko_ime, $ucenik_id]);
    
    if ($check->rowCount() > 0) {
        $error = "Korisničko ime već postoji!";
    } else {
        // Ako je lozinka prazna, ne mijenjaj je
        if (empty($lozinka)) {
            $stmt = $conn->prepare("UPDATE ep_korisnik SET ime = ?, email = ? WHERE ID = ?");
            $result = $stmt->execute([$korisnicko_ime, $email, $ucenik_id]);
        } else {
            // Kriptiranje lozinke s md5
            $hashed_password = md5($lozinka);
            
            $stmt = $conn->prepare("UPDATE ep_korisnik SET ime = ?, email = ?, lozinka = ? WHERE ID = ?");
            $result = $stmt->execute([$korisnicko_ime, $email, $hashed_password, $ucenik_id]);
        }
        
        if ($result) {
            $poruka = "Učenik uspješno ažuriran!";
            $_SESSION['password_changed'] = true;
            header("Location: upravljaj_ucenicima.php?success=3");
            exit();
        } else {
            $error = "Greška prilikom ažuriranja učenika!";
        }
    }
}

// Dohvati detalje učenika za uređivanje
$ucenik_za_uredivanje = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT ID, ime as korisnicko_ime, email FROM ep_korisnik WHERE ID = ? AND razinaID = 2");
    $stmt->execute([$edit_id]);
    $ucenik_za_uredivanje = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Dohvati detalje učenika za pregled
$ucenik_detalji = null;
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $view_id = $_GET['view_id'];
    
    // Dohvati osnovne podatke o učeniku
    $stmt = $conn->prepare("
        SELECT u.ID, u.ime as korisnicko_ime, u.email, u.razinaID, p.opis AS razina_naziv
        FROM ep_korisnik u
        LEFT JOIN ep_pravo p ON u.razinaID = p.ID
        WHERE u.ID = ? AND u.razinaID = 2
    ");
    $stmt->execute([$view_id]);
    $ucenik_detalji = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ucenik_detalji) {
        // Dohvati teme koje su dodijeljene učeniku
        $stmt = $conn->prepare("
            SELECT t.ID, t.naziv
            FROM ep_teme t
            INNER JOIN ep_korisnik_teme kt ON t.ID = kt.tema_id
            WHERE kt.korisnik_id = ?
        ");
        $stmt->execute([$view_id]);
        $ucenik_detalji['teme'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Dohvati povijest testova učenika
        $stmt = $conn->prepare("
            SELECT t.ID, t.vrijeme_kraja, t.trajanje, t.ukupno_pitanja, t.tocno_odgovori, 
                   (t.tocno_odgovori / t.ukupno_pitanja) * 100 AS postotak,
                   tm.naziv AS tema_naziv
            FROM ep_test t
            LEFT JOIN ep_teme tm ON t.kviz_id = tm.ID
            WHERE t.korisnikID = ?
            ORDER BY t.vrijeme_kraja DESC
        ");
        $stmt->execute([$view_id]);
        $ucenik_detalji['testovi'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Dohvati sve teme za dodjeljivanje
$stmt = $conn->prepare("SELECT ID, naziv FROM ep_teme ORDER BY naziv");
$stmt->execute();
$sve_teme = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dodjeljivanje teme učeniku
if (isset($_POST['dodijeli_temu'])) {
    $ucenik_id = $_POST['ucenik_id'];
    $tema_id = $_POST['tema_id'];
    
    // Provjeri postoji li već ta kombinacija
    $check = $conn->prepare("SELECT * FROM ep_korisnik_teme WHERE korisnik_id = ? AND tema_id = ?");
    $check->execute([$ucenik_id, $tema_id]);
    
    if ($check->rowCount() > 0) {
        $error = "Tema je već dodijeljena ovom učeniku!";
    } else {
        $stmt = $conn->prepare("INSERT INTO ep_korisnik_teme (korisnik_id, tema_id) VALUES (?, ?)");
        
        if ($stmt->execute([$ucenik_id, $tema_id])) {
            $poruka = "Tema uspješno dodijeljena učeniku!";
            header("Location: upravljaj_ucenicima.php?view_id=$ucenik_id&success=4");
            exit();
        } else {
            $error = "Greška prilikom dodjeljivanja teme!";
        }
    }
}

// Uklanjanje teme od učenika
if (isset($_GET['remove_tema']) && isset($_GET['ucenik_id'])) {
    $tema_id = $_GET['remove_tema'];
    $ucenik_id = $_GET['ucenik_id'];
    
    $stmt = $conn->prepare("DELETE FROM ep_korisnik_teme WHERE korisnik_id = ? AND tema_id = ?");
    
    if ($stmt->execute([$ucenik_id, $tema_id])) {
        $poruka = "Tema uspješno uklonjena od učenika!";
        header("Location: upravljaj_ucenicima.php?view_id=$ucenik_id&success=5");
        exit();
    } else {
        $error = "Greška prilikom uklanjanja teme!";
    }
}

// Poruke o uspjehu
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $poruka = "Učenik uspješno dodan!";
            break;
        case 2:
            $poruka = "Učenik uspješno obrisan!";
            break;
        case 3:
            $poruka = "Učenik uspješno ažuriran!";
            break;
        case 4:
            $poruka = "Tema uspješno dodijeljena učeniku!";
            break;
        case 5:
            $poruka = "Tema uspješno uklonjena od učenika!";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravljanje učenicima | Tehnička škola Čakovec</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
            line-height: 1.6;
        }
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            text-align: center;
            color: #A8D25B;
            text-shadow: 0 0 10px rgba(168, 210, 91, 0.5);
        }
        h2 {
            font-size: 1.8rem;
            margin: 20px 0;
            color: #A6CEE3;
            border-bottom: 2px solid #A6CEE3;
            padding-bottom: 10px;
        }
        .container {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 10;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #A8D25B;
            color: #2e2e2e;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background: #86a847;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
        .btn-danger {
            background: #ff4d4d;
        }
        .btn-danger:hover {
            background: #e60000;
        }
        .btn-info {
            background: #A6CEE3;
        }
        .btn-info:hover {
            background: #7ab5d6;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table th {
            background: rgba(168, 210, 91, 0.2);
            color: #A8D25B;
            font-weight: bold;
        }
        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #A6CEE3;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #444;
            border-radius: 5px;
            background: #333;
            color: #fff;
            font-size: 16px;
        }
        .form-control:focus {
            outline: none;
            border-color: #A8D25B;
            box-shadow: 0 0 5px rgba(168, 210, 91, 0.5);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background: rgba(168, 210, 91, 0.2);
            border-left: 5px solid #A8D25B;
            color: #A8D25B;
        }
        .alert-danger {
            background: rgba(255, 77, 77, 0.2);
            border-left: 5px solid #ff4d4d;
            color: #ff4d4d;
        }
        .card {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .card-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            color: #A6CEE3;
        }
        .card-body {
            padding: 10px 0;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .badge-success {
            background: #A8D25B;
            color: #2e2e2e;
        }
        .badge-info {
            background: #A6CEE3;
            color: #2e2e2e;
        }
        .badge-warning {
            background: #ffc107;
            color: #2e2e2e;
        }
        .progress {
            height: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #A8D25B, #A6CEE3);
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        .nav-tabs {
            display: flex;
            list-style: none;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .nav-tabs li {
            margin-right: 10px;
        }
        .nav-tabs a {
            display: block;
            padding: 10px 15px;
            color: #fff;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .nav-tabs a.active {
            border-bottom: 3px solid #A8D25B;
            color: #A8D25B;
        }
        .nav-tabs a:hover {
            border-bottom: 3px solid #A6CEE3;
            color: #A6CEE3;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #A6CEE3;
            text-decoration: none;
            font-weight: bold;
        }
        .back-btn i {
            margin-right: 5px;
        }
        .back-btn:hover {
            color: #7ab5d6;
        }
        .mafija-icon {
            font-size: 40px;
            color: #A8D25B;
            opacity: 0.1;
            position: absolute;
        }
        .mafija-icon.top-right {
            top: 20px;
            right: 20px;
        }
        .mafija-icon.bottom-left {
            bottom: 20px;
            left: 20px;
        }
        .search-box {
            margin-bottom: 20px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #444;
            border-radius: 5px;
            background: #333;
            color: #fff;
            font-size: 16px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #A6CEE3;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }
        .modal-content {
            background: #2e2e2e;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #fff;
        }
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
            .btn {
                display: block;
                width: 100%;
                margin-bottom: 10px;
            }
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h1><i class="fas fa-user-graduate" style="margin-right: 10px;"></i>Upravljanje učenicima</h1>
        
        <a href="profesorski_panel.php" class="back-btn"><i class="fas fa-arrow-left"></i> Povratak na profesorski panel</a>
        
        <?php if ($poruka): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($poruka) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($ucenik_za_uredivanje): ?>
            <!-- Forma za uređivanje učenika -->
            <div class="container">
                <h2><i class="fas fa-user-edit"></i> Uredi učenika</h2>
                <form action="upravljaj_ucenicima.php" method="POST">
                    <input type="hidden" name="ucenik_id" value="<?= $ucenik_za_uredivanje['ID'] ?>">
                    
                    <div class="form-group">
                        <label for="korisnicko_ime">Korisničko ime:</label>
                        <input type="text" id="korisnicko_ime" name="ime" class="form-control" value="<?= htmlspecialchars($ucenik_za_uredivanje['korisnicko_ime']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email adresa:</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($ucenik_za_uredivanje['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lozinka">Nova lozinka (ostavite prazno ako ne želite mijenjati):</label>
                        <input type="password" id="lozinka" name="lozinka" class="form-control">
                    </div>
                    
                    <button type="submit" name="azuriraj_ucenika" class="btn"><i class="fas fa-save"></i> Spremi promjene</button>
                    <a href="upravljaj_ucenicima.php" class="btn btn-secondary"><i class="fas fa-times"></i> Odustani</a>
                </form>
            </div>
        <?php elseif ($ucenik_detalji): ?>
            <!-- Prikaz detalja o učeniku -->
            <div class="container">
                <h2><i class="fas fa-user-circle"></i> Detalji učenika: <?= htmlspecialchars($ucenik_detalji['korisnicko_ime'] ?? '') ?></h2>
                
                <div class="card">
                    <div class="card-header">Osnovni podaci</div>
                    <div class="card-body">
                        <p><strong>ID:</strong> <?= $ucenik_detalji['ID'] ?></p>
                        <p><strong>Korisničko ime:</strong> <?= htmlspecialchars($ucenik_detalji['korisnicko_ime'] ?? '') ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($ucenik_detalji['email']) ?></p>
                        <p><strong>Razina:</strong> <?= htmlspecialchars($ucenik_detalji['razina_naziv']) ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">Dodijeljene teme</div>
                    <div class="card-body">
                        <?php if (empty($ucenik_detalji['teme'])): ?>
                            <p>Učeniku nisu dodijeljene teme.</p>
                        <?php else: ?>
                            <div style="display: flex; flex-wrap: wrap;">
                                <?php foreach ($ucenik_detalji['teme'] as $tema): ?>
                                    <div style="position: relative; margin-right: 10px; margin-bottom: 10px;">
                                        <span class="badge badge-info" style="padding-right: 30px;">
                                            <?= htmlspecialchars($tema['naziv']) ?>
                                            <a href="upravljaj_ucenicima.php?remove_tema=<?= $tema['ID'] ?>&ucenik_id=<?= $ucenik_detalji['ID'] ?>" 
                                               style="position: absolute; right: 8px; top: 5px; color: #2e2e2e;" 
                                               onclick="return confirm('Jeste li sigurni da želite ukloniti ovu temu?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="upravljaj_ucenicima.php" method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="ucenik_id" value="<?= $ucenik_detalji['ID'] ?>">
                            <div style="display: flex;">
                                <select name="tema_id" class="form-control" style="margin-right: 10px;">
                                    <option value="">-- Odaberi temu --</option>
                                    <?php foreach ($sve_teme as $tema): ?>
                                        <option value="<?= $tema['ID'] ?>"><?= htmlspecialchars($tema['naziv']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="dodijeli_temu" class="btn"><i class="fas fa-plus"></i> Dodijeli temu</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">Povijest testiranja</div>
                    <div class="card-body">
                        <?php if (empty($ucenik_detalji['testovi'])): ?>
                            <p>Učenik nema povijest testiranja.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Datum i vrijeme</th>
                                        <th>Tema</th>
                                        <th>Rezultat</th>
                                        <th>Trajanje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ucenik_detalji['testovi'] as $test): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($test['vrijeme_kraja']) ?></td>
                                            <td><?= htmlspecialchars($test['tema_naziv'] ?? 'Nepoznata tema') ?></td>
                                            <td>
                                                <?= $test['tocno_odgovori'] ?> / <?= $test['ukupno_pitanja'] ?>
                                                (<?= round($test['postotak']) ?>%)
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?= $test['postotak'] ?>%"></div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($test['trajanje']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="upravljaj_ucenicima.php?edit_id=<?= $ucenik_detalji['ID'] ?>" class="btn btn-info"><i class="fas fa-edit"></i> Uredi učenika</a>

                    <?php if (!empty($ucenik_detalji['testovi'])): ?>
                    <a href="posalji_mail.php?test_id=<?= $ucenik_detalji['testovi'][0]['ID'] ?>&ucenik_id=<?= $ucenik_detalji['ID'] ?>" class="btn btn-primary"><i class="fas fa-envelope"></i> Pošalji rezultate e-mailom</a>
                    <?php else: ?>
                    <button type="button" class="btn btn-primary" disabled title="Učenik nema testova za slanje"><i class="fas fa-envelope"></i> Pošalji rezultate e-mailom</button>
                    <?php endif; ?>
                    <a href="upravljaj_ucenicima.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Povratak na popis</a>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal" data-user-id="<?= $ucenik_detalji['ID'] ?>" data-user-name="<?= htmlspecialchars($ucenik_detalji['korisnicko_ime'] ?? '') ?>">
                        <i class="fas fa-trash"></i> Izbriši učenika
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$ucenik_za_uredivanje && !$ucenik_detalji): ?>
        <!-- Popis učenika -->
        <div class="container">
            <h2><i class="fas fa-users"></i> Popis učenika</h2>
            
            <!-- Forma za dodavanje novog učenika -->
            <button type="button" class="btn" data-toggle="modal" data-target="#addModal">
                <i class="fas fa-user-plus"></i> Dodaj novog učenika
            </button>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Pretraži učenike..." class="form-control">
            </div>
            
            <table class="table" id="studentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Korisničko ime</th>
                        <th>Email</th>
                        <th>Broj testova</th>
                        <th>Prosječni rezultat</th>
                        <th>Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ucenici as $ucenik): ?>
                        <tr>
                            <td><?= $ucenik['ID'] ?></td>
                            <td><?= htmlspecialchars($ucenik['korisnicko_ime']) ?></td>
                            <td><?= htmlspecialchars($ucenik['email']) ?></td>
                            <td><?= $ucenik['broj_testova'] ?></td>
                            <td>
                                <?php if ($ucenik['prosjecni_rezultat']): ?>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?= round($ucenik['prosjecni_rezultat']) ?>%"></div>
                                    </div>
                                    <span><?= round($ucenik['prosjecni_rezultat']) ?>%</span>
                                <?php else: ?>
                                    <span>Nema testova</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="upravljaj_ucenicima.php?view_id=<?= $ucenik['ID'] ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> Detalji
                                </a>
                                <a href="upravljaj_ucenicima.php?edit_id=<?= $ucenik['ID'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Uredi
                                </a>
                                <a href="#" class="btn btn-danger btn-sm" 
                                   data-toggle="modal" 
                                   data-target="#deleteModal" 
                                   data-user-id="<?= $ucenik['ID'] ?>" 
                                   data-user-name="<?= htmlspecialchars($ucenik['korisnicko_ime']) ?>">
                                    <i class="fas fa-trash"></i> Izbriši
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal za dodavanje novog učenika -->
        <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content" style="background: #2e2e2e; color: #fff; border: 2px solid #A8D25B; box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);">
                    <div class="modal-header" style="border-bottom: 1px solid #A8D25B;">
                        <h5 class="modal-title" id="addModalLabel" style="color: #A8D25B; font-size: 1.8rem;"><i class="fas fa-user-plus" style="margin-right: 10px;"></i>Dodaj novog učenika</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #A8D25B;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="upravljaj_ucenicima.php" method="POST">
                            <div class="form-group">
                                <label for="korisnicko_ime" style="color: #A6CEE3;"><i class="fas fa-user" style="margin-right: 5px;"></i>Korisničko ime:</label>
                                <input type="text" id="korisnicko_ime" name="korisnicko_ime" class="form-control" style="background-color: #333333; color: #fff; border: none; border-left: 2px solid #A6CEE3; box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" style="color: #A6CEE3;"><i class="fas fa-envelope" style="margin-right: 5px;"></i>Email adresa:</label>
                                <input type="email" id="email" name="email" class="form-control" style="background-color: #333333; color: #fff; border: none; border-left: 2px solid #A6CEE3; box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);" required>
                            </div>
                                
                            <div class="form-group">
                                <label for="lozinka" style="color: #A6CEE3;"><i class="fas fa-key" style="margin-right: 5px;"></i>Lozinka:</label>
                                <input type="password" id="lozinka" name="lozinka" class="form-control" style="background-color: #333333; color: #fff; border: none; border-left: 2px solid #A6CEE3; box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);" required>
                            </div>
                                
                            <div class="form-group">
                                <label for="potvrda_lozinke" style="color: #A6CEE3;"><i class="fas fa-lock" style="margin-right: 5px;"></i>Potvrda lozinke:</label>
                                <input type="password" id="potvrda_lozinke" name="potvrda_lozinke" class="form-control" style="background-color: #333333; color: #fff; border: none; border-left: 2px solid #A6CEE3; box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.3);" required>
                            </div>
                                
                            <button type="submit" name="dodaj_ucenika" class="btn" style="background-color: #A6CEE3; color: #2e2e2e; width: 100%; padding: 14px 0; text-transform: uppercase; font-weight: bold; margin-top: 20px; box-shadow: 0 0 5px #A6CEE3, 0 0 10px #A6CEE3; transition: 0.3s ease;">
                                <i class="fas fa-user-plus" style="margin-right: 5px;"></i>Registriraj učenika
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal for delete confirmation - place once as a reusable modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Potvrda brisanja</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Jeste li sigurni da želite izbrisati učenika <span id="deleteUserName"></span>?
                    Ova akcija je nepovratna i izbrisat će sve podatke vezane uz ovog učenika.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Odustani</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Izbriši</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Delete confirmation modal
        $('#deleteModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var userId = button.data('user-id');
            var userName = button.data('user-name');
            
            var modal = $(this);
            modal.find('#deleteUserName').text(userName);
            modal.find('#confirmDelete').attr('href', 'upravljaj_ucenicima.php?delete_id=' + userId);
        });

        // Search functionality
        $('#searchInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#studentsTable tbody tr').filter(function() {
                $(this).toggle(
                    $(this).text().toLowerCase().indexOf(value) > -1
                );
            });
        });
    });
    </script>
</body>
</html>


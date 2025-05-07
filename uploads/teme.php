<?php
session_start();

// Provjera je li korisnik prijavljen i ima li razinu profesora
if (!isset($_SESSION['user_id']) || $_SESSION['razina'] != 1) {
    header('Location: login.php');
    exit();
}

// Uključivanje baze podataka
require_once 'db_connection.php';

// Obrada brisanja teme
if (isset($_GET['delete_tema'])) {
    $tema_id = $_GET['delete_tema'];
    
    try {
        $conn->beginTransaction();
        
        // Prvo provjeri postoji li tema
        $checkTemaQuery = "SELECT ID FROM ep_teme WHERE ID = :tema_id";
        $checkTemaStmt = $conn->prepare($checkTemaQuery);
        $checkTemaStmt->execute(['tema_id' => $tema_id]);
        
        if (!$checkTemaStmt->fetch()) {
            throw new Exception("Tema ne postoji.");
        }
        
        // Prvo brišemo sva pitanja iz teme
        $deleteQuestionsQuery = "DELETE FROM ep_pitanje WHERE temaID = :tema_id";
        $conn->prepare($deleteQuestionsQuery)->execute(['tema_id' => $tema_id]);
        
        // Zatim brišemo veze između korisnika i teme
        $deleteUserThemeQuery = "DELETE FROM ep_korisnik_teme WHERE tema_id = :tema_id";
        $conn->prepare($deleteUserThemeQuery)->execute(['tema_id' => $tema_id]);
        
        // Na kraju brišemo samu temu
        $deleteQuery = "DELETE FROM ep_teme WHERE ID = :tema_id";
        $conn->prepare($deleteQuery)->execute(['tema_id' => $tema_id]);
        
        $conn->commit();
        $poruka = "Tema i sva njezina pitanja su uspješno obrisana.";
        $tipPoruke = "success";
    } catch (Exception $e) {
        $conn->rollBack();
        $poruka = "Greška pri brisanju teme: " . $e->getMessage();
        $tipPoruke = "error";
    }
}

// Obrada dodavanja nove teme
if (isset($_POST['nova_tema'])) {
    $naziv_teme = trim($_POST['naziv_teme']);
    
    if (empty($naziv_teme)) {
        $poruka = "Greška: Naziv teme ne može biti prazan.";
        $tipPoruke = "error";
    } else {
        try {
            // Provjera postoji li već tema s istim imenom
            $checkQuery = "SELECT COUNT(*) as broj FROM ep_teme WHERE naziv = :naziv";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute(['naziv' => $naziv_teme]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['broj'] > 0) {
                $poruka = "Greška: Tema s nazivom '$naziv_teme' već postoji.";
                $tipPoruke = "error";
            } else {
                // Dodavanje nove teme
                $insertQuery = "INSERT INTO ep_teme (naziv) VALUES (:naziv)";
                $conn->prepare($insertQuery)->execute(['naziv' => $naziv_teme]);
                
                $poruka = "Nova tema '$naziv_teme' je uspješno dodana.";
                $tipPoruke = "success";
            }
        } catch (PDOException $e) {
            $poruka = "Greška pri dodavanju teme: " . $e->getMessage();
            $tipPoruke = "error";
        }
    }
}

// Obrada uređivanja teme
if (isset($_POST['uredi_temu'])) {
    $tema_id = $_POST['tema_id'];
    $novi_naziv = trim($_POST['novi_naziv']);
    
    if (empty($novi_naziv)) {
        $poruka = "Greška: Naziv teme ne može biti prazan.";
        $tipPoruke = "error";
    } else {
        try {
            // Provjera postoji li već tema s istim imenom
            $checkQuery = "SELECT COUNT(*) as broj FROM ep_teme WHERE naziv = :naziv AND ID != :id";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute(['naziv' => $novi_naziv, 'id' => $tema_id]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['broj'] > 0) {
                $poruka = "Greška: Tema s nazivom '$novi_naziv' već postoji.";
                $tipPoruke = "error";
            } else {
                // Ažuriranje naziva teme
                $updateQuery = "UPDATE ep_teme SET naziv = :naziv WHERE ID = :id";
                $conn->prepare($updateQuery)->execute(['naziv' => $novi_naziv, 'id' => $tema_id]);
                
                $poruka = "Tema je uspješno preimenovana u '$novi_naziv'.";
                $tipPoruke = "success";
            }
        } catch (PDOException $e) {
            $poruka = "Greška pri uređivanju teme: " . $e->getMessage();
            $tipPoruke = "error";
        }
    }
}

// Dohvat svih tema s brojem pitanja
$query = "
    SELECT t.ID, t.naziv, COUNT(p.ID) as broj_pitanja, t.aktivno
    FROM ep_teme t
    LEFT JOIN ep_pitanje p ON t.ID = p.temaID
    GROUP BY t.ID, t.naziv, t.aktivno
    ORDER BY t.naziv ASC
";
$stmt = $conn->query($query);
$teme = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dodavanje novih tema ako ne postoje
$nove_teme = [
    'Digitalni Labirint',
    'Tehnički Izazov 2025'
];

foreach ($nove_teme as $naziv_teme) {
    // Provjeri postoji li tema
    $checkQuery = "SELECT COUNT(*) as broj FROM ep_teme WHERE naziv = :naziv";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute(['naziv' => $naziv_teme]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['broj'] == 0) {
        // Dodavanje nove teme
        $insertQuery = "INSERT INTO ep_teme (naziv) VALUES (:naziv)";
        $conn->prepare($insertQuery)->execute(['naziv' => $naziv_teme]);
        
        // Dohvati ID nove teme
        $temaId = $conn->lastInsertId();
        
        // Dodaj primjer pitanja za novu temu
        if ($naziv_teme == 'Digitalni Labirint') {
            // Dodaj primjer pitanja za Digitalni Labirint
            $pitanjeQuery = "INSERT INTO ep_pitanje (tekst_pitanja, korisnikID, brojBodova, hint, broj_ponudenih, aktivno, temaID) 
                            VALUES (:tekst, :korisnikID, :bodovi, :hint, :broj_ponudenih, :aktivno, :temaID)";
            $stmt = $conn->prepare($pitanjeQuery);
            $stmt->execute([
                'tekst' => 'Što je algoritam?',
                'korisnikID' => $_SESSION['user_id'],
                'bodovi' => 5,
                'hint' => 'Korak po korak rješavanje problema',
                'broj_ponudenih' => 4,
                'aktivno' => 1,
                'temaID' => $temaId
            ]);
            
            $pitanjeId = $conn->lastInsertId();
            
            // Dodaj odgovore za pitanje
            $odgovori = [
                ['tekst' => 'Korak po korak postupak za rješavanje problema', 'tocno' => 1],
                ['tekst' => 'Računalni program', 'tocno' => 0],
                ['tekst' => 'Tip podataka', 'tocno' => 0],
                ['tekst' => 'Računalni jezik', 'tocno' => 0]
            ];
            
            foreach ($odgovori as $odgovor) {
                $odgovorQuery = "INSERT INTO ep_odgovori (tekst, pitanjeID, tocno, korisnikID, aktivno) 
                                VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, :aktivno)";
                $stmt = $conn->prepare($odgovorQuery);
                $stmt->execute([
                    'tekst' => $odgovor['tekst'],
                    'pitanjeID' => $pitanjeId,
                    'tocno' => $odgovor['tocno'],
                    'korisnikID' => $_SESSION['user_id'],
                    'aktivno' => 1
                ]);
            }
        } else if ($naziv_teme == 'Tehnički Izazov 2025') {
            // Dodaj primjer pitanja za Tehnički Izazov 2025
            $pitanjeQuery = "INSERT INTO ep_pitanje (tekst_pitanja, korisnikID, brojBodova, hint, broj_ponudenih, aktivno, temaID) 
                            VALUES (:tekst, :korisnikID, :bodovi, :hint, :broj_ponudenih, :aktivno, :temaID)";
            $stmt = $conn->prepare($pitanjeQuery);
            $stmt->execute([
                'tekst' => 'Koji je glavni cilj Tehničkog Izazova 2025?',
                'korisnikID' => $_SESSION['user_id'],
                'bodovi' => 5,
                'hint' => 'Povezuje obrazovanje i tehnologiju',
                'broj_ponudenih' => 4,
                'aktivno' => 1,
                'temaID' => $temaId
            ]);
            
            $pitanjeId = $conn->lastInsertId();
            
            // Dodaj odgovore za pitanje
            $odgovori = [
                ['tekst' => 'Povezati obrazovanje s modernim tehnologijama', 'tocno' => 1],
                ['tekst' => 'Održati tradicionalne metode učenja', 'tocno' => 0],
                ['tekst' => 'Zamijeniti nastavnike robotima', 'tocno' => 0],
                ['tekst' => 'Ukinuti sve ispite', 'tocno' => 0]
            ];
            
            foreach ($odgovori as $odgovor) {
                $odgovorQuery = "INSERT INTO ep_odgovori (tekst, pitanjeID, tocno, korisnikID, aktivno) 
                                VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, :aktivno)";
                $stmt = $conn->prepare($odgovorQuery);
                $stmt->execute([
                    'tekst' => $odgovor['tekst'],
                    'pitanjeID' => $pitanjeId,
                    'tocno' => $odgovor['tocno'],
                    'korisnikID' => $_SESSION['user_id'],
                    'aktivno' => 1
                ]);
            }
        }
    } else {
        // Ako tema već postoji, provjeri ima li pitanja
        $temaQuery = "SELECT ID FROM ep_teme WHERE naziv = :naziv";
        $temaStmt = $conn->prepare($temaQuery);
        $temaStmt->execute(['naziv' => $naziv_teme]);
        $temaId = $temaStmt->fetch(PDO::FETCH_ASSOC)['ID'];
        
        // Provjeri ima li pitanja za ovu temu
        $pitanjaQuery = "SELECT COUNT(*) as broj FROM ep_pitanje WHERE temaID = :temaID";
        $pitanjaStmt = $conn->prepare($pitanjaQuery);
        $pitanjaStmt->execute(['temaID' => $temaId]);
        $pitanjaResult = $pitanjaStmt->fetch(PDO::FETCH_ASSOC);
        
        // Ako nema pitanja, dodaj primjer
        if ($pitanjaResult['broj'] == 0) {
            if ($naziv_teme == 'Digitalni Labirint') {
                // Dodaj primjer pitanja za Digitalni Labirint
                $pitanjeQuery = "INSERT INTO ep_pitanje (tekst_pitanja, korisnikID, brojBodova, hint, broj_ponudenih, aktivno, temaID) 
                                VALUES (:tekst, :korisnikID, :bodovi, :hint, :broj_ponudenih, :aktivno, :temaID)";
                $stmt = $conn->prepare($pitanjeQuery);
                $stmt->execute([
                    'tekst' => 'Što je algoritam?',
                    'korisnikID' => $_SESSION['user_id'],
                    'bodovi' => 5,
                    'hint' => 'Korak po korak rješavanje problema',
                    'broj_ponudenih' => 4,
                    'aktivno' => 1,
                    'temaID' => $temaId
                ]);
                
                $pitanjeId = $conn->lastInsertId();
                
                // Dodaj odgovore za pitanje
                $odgovori = [
                    ['tekst' => 'Korak po korak postupak za rješavanje problema', 'tocno' => 1],
                    ['tekst' => 'Računalni program', 'tocno' => 0],
                    ['tekst' => 'Tip podataka', 'tocno' => 0],
                    ['tekst' => 'Računalni jezik', 'tocno' => 0]
                ];
                
                foreach ($odgovori as $odgovor) {
                    $odgovorQuery = "INSERT INTO ep_odgovori (tekst, pitanjeID, tocno, korisnikID, aktivno) 
                                    VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, :aktivno)";
                    $stmt = $conn->prepare($odgovorQuery);
                    $stmt->execute([
                        'tekst' => $odgovor['tekst'],
                        'pitanjeID' => $pitanjeId,
                        'tocno' => $odgovor['tocno'],
                        'korisnikID' => $_SESSION['user_id'],
                        'aktivno' => 1
                    ]);
                }
            } else if ($naziv_teme == 'Tehnički Izazov 2025') {
                // Dodaj primjer pitanja za Tehnički Izazov 2025
                $pitanjeQuery = "INSERT INTO ep_pitanje (tekst_pitanja, korisnikID, brojBodova, hint, broj_ponudenih, aktivno, temaID) 
                                VALUES (:tekst, :korisnikID, :bodovi, :hint, :broj_ponudenih, :aktivno, :temaID)";
                $stmt = $conn->prepare($pitanjeQuery);
                $stmt->execute([
                    'tekst' => 'Koji je glavni cilj Tehničkog Izazova 2025?',
                    'korisnikID' => $_SESSION['user_id'],
                    'bodovi' => 5,
                    'hint' => 'Povezuje obrazovanje i tehnologiju',
                    'broj_ponudenih' => 4,
                    'aktivno' => 1,
                    'temaID' => $temaId
                ]);
                
                $pitanjeId = $conn->lastInsertId();
                
                // Dodaj odgovore za pitanje
                $odgovori = [
                    ['tekst' => 'Povezati obrazovanje s modernim tehnologijama', 'tocno' => 1],
                    ['tekst' => 'Održati tradicionalne metode učenja', 'tocno' => 0],
                    ['tekst' => 'Zamijeniti nastavnike robotima', 'tocno' => 0],
                    ['tekst' => 'Ukinuti sve ispite', 'tocno' => 0]
                ];
                
                foreach ($odgovori as $odgovor) {
                    $odgovorQuery = "INSERT INTO ep_odgovori (tekst, pitanjeID, tocno, korisnikID, aktivno) 
                                    VALUES (:tekst, :pitanjeID, :tocno, :korisnikID, :aktivno)";
                    $stmt = $conn->prepare($odgovorQuery);
                    $stmt->execute([
                        'tekst' => $odgovor['tekst'],
                        'pitanjeID' => $pitanjeId,
                        'tocno' => $odgovor['tocno'],
                        'korisnikID' => $_SESSION['user_id'],
                        'aktivno' => 1
                    ]);
                }
            }
        }
    }
}

// Obrada promjene statusa aktivnosti teme
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $tema_id = $_GET['toggle_status'];
    
    try {
        // Dohvati trenutni status teme
        $stmt = $conn->prepare("SELECT aktivno FROM ep_teme WHERE ID = ?");
        $stmt->execute([$tema_id]);
        $rezultat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rezultat) {
            // Promijeni status aktivnosti (0->1 ili 1->0)
            $novi_status = $rezultat['aktivno'] ? 0 : 1;
            
            // Ažuriraj status teme
            $updateQuery = "UPDATE ep_teme SET aktivno = :aktivno WHERE ID = :id";
            $conn->prepare($updateQuery)->execute(['aktivno' => $novi_status, 'id' => $tema_id]);
            
            $poruka = "Status teme je uspješno promijenjen.";
            $tipPoruke = "success";
        }
    } catch (PDOException $e) {
        $poruka = "Greška pri promjeni statusa teme: " . $e->getMessage();
        $tipPoruke = "error";
    }
}

// Ponovno dohvaćanje tema nakon eventualnog dodavanja
$query = "
    SELECT t.ID, t.naziv, COUNT(p.ID) as broj_pitanja, t.aktivno
    FROM ep_teme t
    LEFT JOIN ep_pitanje p ON t.ID = p.temaID
    GROUP BY t.ID, t.naziv, t.aktivno
    ORDER BY t.naziv ASC
";
$stmt = $conn->query($query);
$teme = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dohvaćanje detalja teme ako je zatražen pregled
$tema_detalji = null;
$pitanja_teme = [];
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $tema_id = $_GET['view_id'];
    
    // Dohvaćanje detalja teme
    $stmt = $conn->prepare("SELECT ID, naziv FROM ep_teme WHERE ID = ?");
    $stmt->execute([$tema_id]);
    $tema_detalji = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tema_detalji) {
        // Dohvaćanje svih pitanja za ovu temu
        $stmt = $conn->prepare("
            SELECT p.ID, p.tekst_pitanja, p.brojBodova, p.hint, p.aktivno
            FROM ep_pitanje p 
            WHERE p.temaID = ? 
            ORDER BY p.ID DESC
        ");
        $stmt->execute([$tema_id]);
        $pitanja_teme = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Dodavanje/uređivanje pitanja u temu
if (isset($_POST['dodaj_pitanje'])) {
    $tema_id = $_POST['tema_id'];
    $tekst = $_POST['tekst_pitanja'];
    $bodovi = $_POST['bodovi'];
    $hint = $_POST['hint'];
    $aktivno = isset($_POST['aktivno']) ? 1 : 0;
    $pitanje_id = $_POST['pitanje_id'];
    
    try {
        // Prvo provjeri postoji li tema
        $checkTemaQuery = "SELECT ID FROM ep_teme WHERE ID = :tema_id";
        $checkTemaStmt = $conn->prepare($checkTemaQuery);
        $checkTemaStmt->execute(['tema_id' => $tema_id]);
        
        if (!$checkTemaStmt->fetch()) {
            $poruka = "Greška: Odabrana tema ne postoji.";
            $tipPoruke = "error";
            goto skipInsert;
        }
        
        // Dohvati odgovore i točan odgovor
        $answers = [
            $_POST['answer1'] ?? '',
            $_POST['answer2'] ?? '',
            $_POST['answer3'] ?? '',
            $_POST['answer4'] ?? ''
        ];
        $correctAnswer = intval($_POST['correctAnswer'] ?? 1);
        
        // Osnovna validacija
        if (empty($tekst) || empty($answers[0]) || empty($answers[1])) {
            $poruka = "Greška: Molim popunite obavezna polja: pitanje, minimalno 2 odgovora.";
            $tipPoruke = "error";
        } else {
            $brojOdgovora = 0;
            foreach ($answers as $odg) {
                if (!empty(trim($odg))) {
                    $brojOdgovora++;
                }
            }
            
            if ($brojOdgovora < 2) {
                $poruka = "Greška: Unesite minimalno 2 odgovora.";
                $tipPoruke = "error";
            } else {
                // Obrada uploadane slike (ako postoji)
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
                            // Čišćenje imena datoteke
                            $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileName);
                            $destPath = $uploadDir . time() . "_" . $fileName;
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                $imagePath = $destPath;
                            } else {
                                $poruka = "Greška pri uploadanju slike.";
                                $tipPoruke = "error";
                                goto skipInsert;
                            }
                        } else {
                            $poruka = "Format slike nije podržan. Koristite JPG, PNG ili GIF.";
                            $tipPoruke = "error";
                            goto skipInsert;
                        }
                    } else {
                        $poruka = "Veličina slike prelazi maksimalnih 2MB.";
                        $tipPoruke = "error";
                        goto skipInsert;
                    }
                }
                
                if ($pitanje_id > 0) {
                    // Ažuriranje postojećeg pitanja
                    $updateQuery = "UPDATE ep_pitanje SET 
                                    tekst_pitanja = :tekst, 
                                    brojBodova = :bodovi, 
                                    hint = :hint, 
                                    aktivno = :aktivno,
                                    broj_ponudenih = :brojOdgovora";
                    
                    // Ako ima nove slike, dodaj je u upit
                    if ($imagePath) {
                        $updateQuery .= ", slika = :slika";
                    }
                    
                    $updateQuery .= " WHERE ID = :id";
                    
                    $stmt = $conn->prepare($updateQuery);
                    $params = [
                        'tekst' => $tekst,
                        'bodovi' => $bodovi,
                        'hint' => $hint,
                        'aktivno' => $aktivno,
                        'brojOdgovora' => $brojOdgovora,
                        'id' => $pitanje_id
                    ];
                    
                    if ($imagePath) {
                        $params['slika'] = $imagePath;
                    }
                    
                    $stmt->execute($params);
                    
                    // Obriši postojeće odgovore
                    $deleteAnswers = "DELETE FROM ep_odgovori WHERE pitanjeID = :pitanje_id";
                    $conn->prepare($deleteAnswers)->execute(['pitanje_id' => $pitanje_id]);
                    
                    // Dodaj nove odgovore
                    foreach ($answers as $index => $answer) {
                        if (!empty(trim($answer))) {
                            $isCorrect = ($index + 1) == $correctAnswer ? 1 : 0;
                            $insertStmt = $conn->prepare("
                                INSERT INTO ep_odgovori (pitanjeID, tekst, tocno, korisnikID, aktivno) 
                                VALUES (:pitanje_id, :tekst, :tocno, :korisnikID, :aktivno)
                            ");
                            $insertStmt->execute([
                                ':pitanje_id' => $pitanje_id,
                                ':tekst' => $answer,
                                ':tocno' => $isCorrect,
                                ':korisnikID' => $_SESSION['user_id'],
                                ':aktivno' => 1
                            ]);
                        }
                    }
                    
                    $poruka = "Pitanje je uspješno ažurirano.";
                    $tipPoruke = "success";
                } else {
                    // Dodavanje novog pitanja
                    $insertQuery = "INSERT INTO ep_pitanje (
                                    temaID, tekst_pitanja, brojBodova, hint, aktivno, 
                                    korisnikID, broj_ponudenih, slika) 
                                   VALUES (
                                    :tema_id, :tekst, :bodovi, :hint, :aktivno, 
                                    :korisnikID, :brojOdgovora, :slika)";
                    
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->execute([
                        'tema_id' => $tema_id,
                        'tekst' => $tekst,
                        'bodovi' => $bodovi,
                        'hint' => $hint,
                        'aktivno' => $aktivno,
                        'korisnikID' => $_SESSION['user_id'],
                        'brojOdgovora' => $brojOdgovora,
                        'slika' => $imagePath
                    ]);
                    
                    $novoPitanjeId = $conn->lastInsertId();
                    
                    // Dodaj odgovore
                    foreach ($answers as $index => $answer) {
                        if (!empty(trim($answer))) {
                            $isCorrect = ($index + 1) == $correctAnswer ? 1 : 0;
                            $insertStmt = $conn->prepare("
                                INSERT INTO ep_odgovori (pitanjeID, tekst, tocno, korisnikID, aktivno) 
                                VALUES (:pitanje_id, :tekst, :tocno, :korisnikID, :aktivno)
                            ");
                            $insertStmt->execute([
                                ':pitanje_id' => $novoPitanjeId,
                                ':tekst' => $answer,
                                ':tocno' => $isCorrect,
                                ':korisnikID' => $_SESSION['user_id'],
                                ':aktivno' => 1
                            ]);
                        }
                    }
                    
                    $poruka = "Novo pitanje je uspješno dodano.";
                    $tipPoruke = "success";
                }
                
                // Preusmjeravanje na istu stranicu s porukom o uspjehu
                header("Location: teme.php?view_id=$tema_id&success=1");
                exit();
            }
        }
        skipInsert:;
    } catch (PDOException $e) {
        $poruka = "Greška: " . $e->getMessage();
        $tipPoruke = "error";
    }
}

// Brisanje pitanja iz teme
if (isset($_GET['delete_pitanje']) && isset($_GET['tema_id'])) {
    $pitanje_id = $_GET['delete_pitanje'];
    $tema_id = $_GET['tema_id'];
    
    try {
        $conn->beginTransaction();
        
        // Provjera pripada li pitanje toj temi
        $stmt = $conn->prepare("SELECT ID FROM ep_pitanje WHERE ID = ? AND temaID = ?");
        $stmt->execute([$pitanje_id, $tema_id]);
        
        if ($stmt->rowCount() > 0) {
            // Prvo obriši odgovore za pitanje
            $deleteAnswersQuery = "DELETE FROM ep_odgovori WHERE pitanjeID = :pitanje_id";
            $conn->prepare($deleteAnswersQuery)->execute(['pitanje_id' => $pitanje_id]);
            
            // Zatim obriši pitanje
            $stmt = $conn->prepare("DELETE FROM ep_pitanje WHERE ID = ?");
            $stmt->execute([$pitanje_id]);
            
            $conn->commit();
            $poruka = "Pitanje je uspješno obrisano.";
            $tipPoruke = "success";
        } else {
            $conn->rollBack();
            $poruka = "Greška: Pitanje nije pronađeno ili ne pripada odabranoj temi.";
            $tipPoruke = "error";
        }
        
        // Preusmjeri na pregled teme
        header("Location: teme.php?view_id=$tema_id&success=2");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $poruka = "Greška pri brisanju pitanja: " . $e->getMessage();
        $tipPoruke = "error";
    }
}

// Poruke o uspjehu
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $poruka = "Pitanje je uspješno dodano u temu.";
            $tipPoruke = "success";
            break;
        case 2:
            $poruka = "Pitanje je uspješno obrisano.";
            $tipPoruke = "success";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tehnička škola Čakovec | Upravljanje temama</title>
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            min-width: 120px;
            height: 40px;
            cursor: pointer;
        }
        .nav-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B;
        }
        .nav-btn i {
            margin-right: 8px;
        }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        .message.success {
            background-color: rgba(168, 210, 91, 0.1);
            border-color: #A8D25B;
        }
        .message.error {
            background-color: rgba(231, 76, 60, 0.1);
            border-color: #e74c3c;
        }
        .tema-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tema-table th {
            background-color: rgba(166, 206, 227, 0.2);
            border-bottom: 2px solid #A8D25B;
            text-align: left;
            padding: 12px 15px;
            color: #A8D25B;
            font-weight: bold;
        }
        .tema-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(168, 210, 91, 0.1);
        }
        .tema-table tr:hover {
            background-color: rgba(166, 206, 227, 0.05);
        }
        .action-btn,
        .submit-btn,
        button[type="submit"],
        button[type="button"] {
            background-color: #A6CEE3;
            color: #2e2e2e;
            padding: 10px 20px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            transition: 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            min-width: 120px;
            height: 40px;
            cursor: pointer;
            margin: 2px;
        }
        .action-btn:hover,
        .submit-btn:hover,
        button[type="submit"]:hover,
        button[type="button"]:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B;
        }
        .action-btn i,
        .submit-btn i,
        button[type="submit"] i,
        button[type="button"] i {
            margin-right: 8px;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }
        .delete-btn:hover {
            background-color: #c0392b;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
        }
        .delete-btn:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .add-form, .edit-form {
            background-color: #333333;
            padding: 25px;
            border-left: 4px solid #A8D25B;
            margin-bottom: 30px;
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            display: block;
            margin-bottom: 8px;
            color: #A6CEE3;
            font-weight: bold;
        }
        .form-row input[type="text"] {
            width: 100%;
            padding: 10px;
            background-color: #444;
            border: 1px solid #555;
            color: white;
            transition: 0.3s ease;
        }
        .form-row input[type="text"]:focus {
            border-color: #A6CEE3;
            box-shadow: 0 0 5px rgba(166, 206, 227, 0.5);
            outline: none;
        }
        .form-row .submit-btn {
            padding: 12px 20px;
            background-color: #A6CEE3;
            color: #2e2e2e;
            border: none;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s ease;
        }
        .form-row .submit-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B;
        }
        .form-title {
            margin-bottom: 20px;
            color: #A8D25B;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            overflow: auto;
        }
        .modal-content {
            background: linear-gradient(145deg, #3a3a3a, #222222);
            margin: 10% auto;
            padding: 30px;
            width: 60%;
            max-width: 500px;
            border: 2px solid #A8D25B;
            box-shadow: 0 0 25px rgba(168, 210, 91, 0.3);
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #A6CEE3;
            font-size: 28px;
            font-weight: bold;
            transition: 0.3s ease;
            cursor: pointer;
        }
        .close-modal:hover {
            color: #A8D25B;
        }
        .modal-title {
            color: #A8D25B;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #A6CEE3;
        }
        .edit-form-container {
            padding: 10px;
        }
        @media (max-width: 768px) {
            .nav-buttons {
                flex-direction: column;
                gap: 10px;
            }
            .modal-content {
                width: 90%;
                margin: 20% auto;
            }
        }
        .no-teme {
            padding: 20px;
            background-color: rgba(166, 206, 227, 0.1);
            text-align: center;
            margin-top: 20px;
            border-left: 4px solid #A6CEE3;
        }
        .highlight {
            color: #A6CEE3;
            font-weight: bold;
        }
        .tema-counter {
            font-size: 1rem;
            color: #ccc;
            margin-left: 10px;
            font-weight: normal;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            background-color: #444;
            border: 1px solid #555;
            color: white;
            transition: 0.3s ease;
            margin-bottom: 15px;
        }
        .form-control:focus {
            border-color: #A6CEE3;
            box-shadow: 0 0 5px rgba(166, 206, 227, 0.5);
            outline: none;
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .pitanje {
            cursor: pointer;
            padding: 15px;
            margin-bottom: 10px;
            background-color: rgba(166, 206, 227, 0.1);
            border-left: 3px solid #A6CEE3;
            transition: 0.3s ease;
        }
        .pitanje:hover {
            background-color: rgba(168, 210, 91, 0.1);
            border-left-color: #A8D25B;
        }
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        /* Novi stilovi za uređivanje pitanja */
        .pitanje-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }

        .pitanje-list {
            flex: 1;
            min-width: 300px;
            background-color: #333333;
            padding: 20px;
            border-left: 4px solid #A6CEE3;
        }

        .pitanje-form {
            flex: 2;
            min-width: 400px;
            background-color: #333333;
            padding: 25px;
            border-left: 4px solid #A8D25B;
        }

        .pitanje-list h3, .pitanje-form h3 {
            color: #A8D25B;
            margin-bottom: 20px;
            border-bottom: 2px solid #A6CEE3;
            padding-bottom: 10px;
        }

        .pitanje-card {
            cursor: pointer;
            padding: 15px;
            margin-bottom: 15px;
            background-color: rgba(166, 206, 227, 0.1);
            border-left: 3px solid #A6CEE3;
            transition: 0.3s ease;
            position: relative;
        }

        .pitanje-card:hover {
            background-color: rgba(168, 210, 91, 0.1);
            border-left-color: #A8D25B;
        }

        .pitanje-card.active {
            border-left-color: #A8D25B;
            background-color: rgba(168, 210, 91, 0.2);
        }

        .pitanje-actions {
            position: absolute;
            right: 10px;
            top: 10px;
            display: flex;
            gap: 5px;
        }

        .pitanje-text {
            margin-right: 70px;  /* Povećana margina za akcijske gumbe */
            font-size: 0.95rem;
        }

        .pitanje-bodovi {
            font-weight: bold;
            color: #A6CEE3;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #A6CEE3;
            font-weight: bold;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .opcija-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .opcija-row .form-control {
            flex: 1;
            margin-bottom: 0;
        }

        .tocno-label {
            display: flex;
            align-items: center;
            white-space: nowrap;
            gap: 5px;
            color: #A6CEE3;
        }

        .tocno-label input {
            margin-right: 5px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }

        #opcije-container {
            background-color: rgba(166, 206, 227, 0.05);
            padding: 15px;
            border-left: 3px solid #A6CEE3;
            margin-bottom: 20px;
        }

        .tezina-badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 0.8rem;
            border-radius: 3px;
            margin-left: 8px;
            font-weight: bold;
        }

        .tezina-1 {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .tezina-2 {
            background-color: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
        }

        .tezina-3 {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .section-title {
            color: #A8D25B;
            margin: 20px 0 10px 0;
            font-size: 1.3rem;
            border-bottom: 1px solid #A6CEE3;
            padding-bottom: 5px;
        }

        .radio-group {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 5px;
        }

        .radio-group label {
            margin: 0;
            font-weight: normal;
            color: #fff;
        }

        textarea.form-control {
            background-color: #333333;
            border: 2px solid #A6CEE3; 
            border-radius: 0;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(166, 206, 227, 0.3);
            font-size: 1rem;
            min-height: 80px;
        }

        input[type="file"].form-control {
            background-color: #333333;
            border: 2px solid #A6CEE3;
            padding: 10px;
            color: #fff;
        }

        .submit-btn {
            background-color: #A6CEE3; 
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

        .submit-btn:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
        }

        .input-group {
            margin-bottom: 20px;
        }

        /* Stilovi za forme */
        .dodaj-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: linear-gradient(145deg, #3a3a3a, #222222);
            border: 2px solid #A8D25B;
            box-shadow: 0 0 20px rgba(168, 210, 91, 0.2), 0 0 60px rgba(168, 210, 91, 0.1);
            padding: 30px;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #A6CEE3;
            text-shadow: 0 0 3px rgba(166, 206, 227, 0.5);
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            background-color: #333333;
            border: 2px solid #A6CEE3;
            border-radius: 0;
            padding: 10px;
            color: #fff;
            box-shadow: inset 0 0 5px rgba(166, 206, 227, 0.3);
            font-size: 1rem;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .radio-group {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 5px;
        }
        .radio-group label {
            margin: 0;
        }
        .section-title {
            color: #A8D25B;
            margin: 20px 0 10px 0;
            font-size: 1.3rem;
            border-bottom: 1px solid #A6CEE3;
            padding-bottom: 5px;
        }
        button[type="submit"] {
            background-color: #A6CEE3;
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
        button[type="submit"]:hover {
            background-color: #A8D25B;
            box-shadow: 0 0 10px #A8D25B, 0 0 20px #A8D25B;
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
    <div class="page-container">
        <h1>
            <?php if ($tema_detalji): ?>
                <i class="fas fa-book-open"></i> Tema: <?= htmlspecialchars($tema_detalji['naziv']) ?>
            <?php else: ?>
                <i class="fas fa-book"></i> Upravljanje temama
            <?php endif; ?>
        </h1>
        
        <div class="nav-buttons">
            <?php if ($tema_detalji): ?>
                <a href="teme.php" class="nav-btn"><i class="fas fa-arrow-left"></i> Povratak na popis tema</a>
            <?php else: ?>
                <a href="profesorski_panel.php" class="nav-btn"><i class="fas fa-tachometer-alt"></i> Profesorski panel</a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($poruka)): ?>
            <div class="message <?= $tipPoruke ?>">
                <?= htmlspecialchars($poruka) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($tema_detalji): ?>
            <!-- Prikaz detalja teme i pitanja -->
            <div class="panel-container">
                <h2>Pitanja u temi</h2>
                
                <div class="pitanje-container">
                    <!-- Lijevi dio - Lista pitanja -->
                    <div class="pitanje-list">
                        <h3><i class="fas fa-list"></i> Popis pitanja</h3>
                        
                        <?php
                        if (count($pitanja_teme) > 0) {
                            foreach($pitanja_teme as $row) {
                                $tekst_pitanja = isset($row["tekst_pitanja"]) ? $row["tekst_pitanja"] : '';
                                $hint = isset($row["hint"]) ? $row["hint"] : '';
                                
                                // Dohvati odgovore za pitanje
                                $stmt = $conn->prepare("
                                    SELECT ID, tekst, tocno 
                                    FROM ep_odgovori 
                                    WHERE pitanjeID = :pitanje_id 
                                    ORDER BY ID ASC
                                ");
                                $stmt->execute([':pitanje_id' => $row["ID"]]);
                                $odgovori = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Kodiraj JSON podatke na način koji će biti siguran za HTML
                                $odgovoriJson = json_encode($odgovori);
                                $odgovoriJson = str_replace('"', '&quot;', $odgovoriJson);
                                
                                echo "<div class='pitanje-card' onclick='popuniObrazac({$row["ID"]})' 
                                      data-id='{$row["ID"]}' 
                                      data-tekst='" . htmlspecialchars($tekst_pitanja, ENT_QUOTES) . "' 
                                      data-bodovi='{$row["brojBodova"]}' 
                                      data-hint='" . htmlspecialchars($hint, ENT_QUOTES) . "' 
                                      data-aktivno='{$row["aktivno"]}'
                                      data-odgovori='{$odgovoriJson}'>";
                                
                                echo "<div class='pitanje-actions'>";
                                echo "<a href='teme.php?delete_pitanje={$row["ID"]}&tema_id={$tema_detalji['ID']}' class='action-btn delete-btn' 
                                      onclick='return confirm(\"Jeste li sigurni da želite obrisati ovo pitanje?\")'>";
                                echo "<i class='fas fa-trash-alt'></i></a>";
                                echo "</div>";
                                
                                echo "<div class='pitanje-text'>" . htmlspecialchars($tekst_pitanja) . "</div>";
                                echo "<div class='pitanje-bodovi'>";
                                echo "<i class='fas fa-star'></i> {$row["brojBodova"]} bodova";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='no-teme'><i class='fas fa-info-circle'></i> Nema pitanja za ovu temu.</div>";
                        }
                        ?>
                        </div>
                        
                    <!-- Desni dio - Obrazac za dodavanje/uređivanje pitanja -->
                    <div class="pitanje-form">
                        <div class="form-header">
                            <h3 id="form-title"><i class="fas fa-plus-circle"></i> Dodaj novo pitanje</h3>
                        </div>
                        
                        <form method="post" id="pitanje-form" enctype="multipart/form-data">
                            <input type="hidden" name="tema_id" value="<?php echo $tema_detalji['ID']; ?>">
                            <input type="hidden" name="pitanje_id" id="pitanje_id" value="0">
                            
                            <div class="input-group">
                                <label for="pitanje_tekst"><i class="fas fa-question-circle"></i> Tekst pitanja:</label>
                                <textarea id="pitanje_tekst" name="tekst_pitanja" required class="form-control"></textarea>
                            </div>
                            
                            <div class="section-title"><i class="fas fa-list-ol"></i> Ponuđeni odgovori</div>
                            
                            <div class="input-group">
                                <label for="answer1">Odgovor 1:</label>
                                <input type="text" id="answer1" name="answer1" class="form-control" required>
                                <div class="radio-group">
                                    <input type="radio" id="correct1" name="correctAnswer" value="1" checked>
                                    <label for="correct1">Točan odgovor</label>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label for="answer2">Odgovor 2:</label>
                                <input type="text" id="answer2" name="answer2" class="form-control" required>
                                <div class="radio-group">
                                    <input type="radio" id="correct2" name="correctAnswer" value="2">
                                    <label for="correct2">Točan odgovor</label>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label for="answer3">Odgovor 3 (opcionalno):</label>
                                <input type="text" id="answer3" name="answer3" class="form-control">
                                <div class="radio-group">
                                    <input type="radio" id="correct3" name="correctAnswer" value="3">
                                    <label for="correct3">Točan odgovor</label>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label for="answer4">Odgovor 4 (opcionalno):</label>
                                <input type="text" id="answer4" name="answer4" class="form-control">
                                <div class="radio-group">
                                    <input type="radio" id="correct4" name="correctAnswer" value="4">
                                    <label for="correct4">Točan odgovor</label>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label for="pitanje_bodovi"><i class="fas fa-star"></i> Broj bodova:</label>
                                <input type="number" name="bodovi" id="pitanje_bodovi" min="1" max="10" value="1" required class="form-control">
                            </div>
                            
                            <div class="input-group">
                                <label for="pitanje_hint"><i class="fas fa-lightbulb"></i> Pomoć (hint) za pitanje (opcionalno):</label>
                                <textarea name="hint" id="pitanje_hint" class="form-control"></textarea>
                            </div>
                            
                            <div class="input-group">
                                <label for="questionImage"><i class="fas fa-image"></i> Slika uz pitanje (opcionalno):</label>
                                <input type="file" id="questionImage" name="questionImage" accept="image/*" class="form-control">
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
                            
                            <div class="input-group checkbox-group">
                                <label>
                                    <input type="checkbox" name="aktivno" id="pitanje_aktivno" value="1" checked>
                                    <i class="fas fa-toggle-on"></i> Pitanje je aktivno
                                </label>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" name="dodaj_pitanje" class="submit-btn" id="submit-pitanje">
                                    <i class="fas fa-save"></i> Spremi pitanje
                                </button>
                                <button type="button" class="action-btn" onclick="resetirajObrazac()">
                                    <i class="fas fa-times-circle"></i> Odustani
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Prikaz forme za dodavanje nove teme -->
            <div class="panel-container">
                <h2>Dodaj novu temu</h2>
                
                <div class="add-form">
                    <form action="teme.php" method="POST">
                        <div class="form-row">
                            <label for="naziv_teme">Naziv teme:</label>
                            <input type="text" id="naziv_teme" name="naziv_teme" required>
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" name="nova_tema" class="submit-btn">
                                <i class="fas fa-plus"></i> Dodaj temu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Prikaz tablice svih tema -->
            <div class="panel-container">
                <h2>Popis tema</h2>
                
                <table class="tema-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naziv teme</th>
                            <th>Broj pitanja</th>
                            <th>Akcije</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teme as $tema): ?>
                            <tr>
                                <td><?= $tema['ID'] ?></td>
                                <td><?= htmlspecialchars($tema['naziv']) ?></td>
                                <td><?= $tema['broj_pitanja'] ?></td>
                                <td>
                                    <a href="teme.php?view_id=<?= $tema['ID'] ?>" class="action-btn">
                                        <i class="fas fa-eye"></i> Pregledaj
                                    </a>
                                    <button type="button" class="action-btn" onclick="showEditModal(<?= $tema['ID'] ?>, '<?= htmlspecialchars(addslashes($tema['naziv'])) ?>')">
                                        <i class="fas fa-edit"></i> Uredi
                                    </button>
                                    <a href="teme.php?delete_tema=<?= $tema['ID'] ?>" class="action-btn delete-btn" onclick="return confirm('Jeste li sigurni da želite obrisati ovu temu i sva njezina pitanja?')">
                                        <i class="fas fa-trash-alt"></i> Obriši
                                    </a>
                                    <a href="teme.php?toggle_status=<?= $tema['ID'] ?>" class="action-btn <?= $tema['aktivno'] ? 'submit-btn' : 'delete-btn' ?>">
                                        <i class="fas <?= $tema['aktivno'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i> <?= $tema['aktivno'] ? 'Aktivna' : 'Neaktivna' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal za uređivanje teme -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <div class="form-title">Uredi temu</div>
            <form action="teme.php" method="POST">
                <input type="hidden" id="edit_tema_id" name="tema_id">
                
                <div class="form-row">
                    <label for="novi_naziv">Novi naziv teme:</label>
                    <input type="text" id="novi_naziv" name="novi_naziv" required>
                </div>
                
                <div class="form-row">
                    <button type="submit" name="uredi_temu" class="submit-btn">
                        <i class="fas fa-save"></i> Spremi promjene
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Funkcija za prikazivanje modala za uređivanje
        function showEditModal(id, naziv) {
            document.getElementById('edit_tema_id').value = id;
            document.getElementById('novi_naziv').value = naziv;
            document.getElementById('editModal').style.display = 'block';
        }
        
        // Funkcija za zatvaranje modala
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Zatvori modal ako korisnik klikne izvan njega
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Funkcija za resetiranje obrasca
        function resetirajObrazac() {
            document.getElementById('pitanje-form').reset();
            document.getElementById('pitanje_id').value = '0';
            document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Dodaj novo pitanje';
            document.getElementById('submit-pitanje').innerHTML = '<i class="fas fa-save"></i> Spremi pitanje';
            document.getElementById('imagePreviewContainer').style.display = 'none';
            document.getElementById('imagePreview').src = '';
        }

        // Event listener za prikaz pregleda slike
        document.getElementById('questionImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Event listener za kontrolu veličine slike
        document.getElementById('imageSize').addEventListener('input', function(e) {
            const size = e.target.value;
            document.getElementById('imageSizeValue').textContent = size + 'px';
            document.getElementById('imagePreview').style.maxWidth = size + 'px';
        });

        // Event listener za promjenu teksta pitanja
        document.getElementById('pitanje_tekst').addEventListener('input', function(e) {
            const pitanjeId = document.getElementById('pitanje_id').value;
            if (pitanjeId !== '0') {
                document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Uredi pitanje';
                document.getElementById('submit-pitanje').innerHTML = '<i class="fas fa-save"></i> Spremi promjene';
            }
        });

        // Event listener za promjenu odgovora
        document.querySelectorAll('input[name^="answer"]').forEach(input => {
            input.addEventListener('input', function(e) {
                const pitanjeId = document.getElementById('pitanje_id').value;
                if (pitanjeId !== '0') {
                    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Uredi pitanje';
                    document.getElementById('submit-pitanje').innerHTML = '<i class="fas fa-save"></i> Spremi promjene';
                }
            });
        });

        function popuniObrazac(pitanjeId) {
            // Ukloni aktivnu klasu sa svih pitanja
            document.querySelectorAll('.pitanje-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Dohvati element pitanja i dodaj mu aktivnu klasu
            const pitanjeCard = document.querySelector(`[data-id="${pitanjeId}"]`);
            if (!pitanjeCard) return;
            pitanjeCard.classList.add('active');

            // Popuni osnovne podatke
            document.getElementById('pitanje_id').value = pitanjeId;
            document.getElementById('pitanje_tekst').value = pitanjeCard.getAttribute('data-tekst');
            document.getElementById('pitanje_bodovi').value = pitanjeCard.getAttribute('data-bodovi');
            document.getElementById('pitanje_hint').value = pitanjeCard.getAttribute('data-hint');
            document.getElementById('pitanje_aktivno').checked = pitanjeCard.getAttribute('data-aktivno') === '1';
            
            // Dohvati i parsiraj odgovore
            const odgovoriJson = pitanjeCard.getAttribute('data-odgovori');
            let odgovori = [];
            try {
                // Zamijeni HTML entitete za navodnike natrag u stvarne navodnike
                const cleanJson = odgovoriJson.replace(/&quot;/g, '"');
                odgovori = JSON.parse(cleanJson);
            } catch (e) {
                console.error('Greška pri parsiranju odgovora:', e);
                console.error('Problematični JSON:', odgovoriJson);
                return;
            }

            // Popuni odgovore u formu
            odgovori.forEach((odgovor, index) => {
                const answerInput = document.getElementById(`answer${index + 1}`);
                const correctRadio = document.getElementById(`correct${index + 1}`);
                if (answerInput && correctRadio) {
                    answerInput.value = odgovor.tekst || '';
                    correctRadio.checked = odgovor.tocno == 1;
                }
            });

            // Očisti preostale odgovore ako ih ima manje od 4
            for (let i = odgovori.length + 1; i <= 4; i++) {
                const answerInput = document.getElementById(`answer${i}`);
                const correctRadio = document.getElementById(`correct${i}`);
                if (answerInput && correctRadio) {
                    answerInput.value = '';
                    correctRadio.checked = false;
                }
            }

            // Ažuriraj naslov forme i tekst gumba
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Uredi pitanje';
            document.getElementById('submit-pitanje').innerHTML = '<i class="fas fa-save"></i> Spremi promjene';
        }
    </script>
</body>
</html>
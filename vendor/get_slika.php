<?php
// Uključivanje datoteke za povezivanje s bazom
require_once 'db_connection.php';

// Provjera je li prisutan ID slike
$id = isset($_GET['id']) ? intval($_GET['id']) : 2; // Zadani ID je 2 ako nije specificiran

// Dodajemo logiranje za dijagnostiku
error_log("Pokušavam dohvatiti sliku s ID: " . $id);

// Funkcija za vraćanje privremene slike ako dohvaćanje ne uspije
function returnFallbackImage() {
    // Jednostavna slika generirana kao base64
    $fallbackImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASwAAABkCAYAAAA8AQ3AAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAJOSURBVHhe7dYxDoJAEAXQOZctLbmFrYWVJZ2Npae0tLawsLPwCt7Gi1BggrLG82VEMsn+P5nUVDWwyCGTJG10nxrbtgEAEQisygOAKAQWAEQhsAAgCoEFAFEILACIQmABQBQCCwCiEFgAEIXAAoAoBBYARCGwACAKgQUAUQgsAIhCYAFAFAILAKIQWAAQhcACgCgEFgBEIbAAIAqBBQBRCCwAiEJgAUAUAgsAohBYABCFwAKAKAQWAEQhsAAgCoEFAFEILACIQmABQBQCCwCiEFgAEIXAAoAoBBYARCGwACAKgQUAUQgsAIhCYAFAFAILAKIQWAAQhcACgCgEFgBEIbAAIAqBBQBRCCwAiEJgAUAUAgsAohBYABCFwAKAKAQWAEQhsAAgCoEFAFEILACIQmABQBQCCwCiEFgAEIXAAoAoBBYARCGwACAKgQUAUQgsAIhCYAFAFAILAKIQWAAQhcACgCgEFgBEIbAAIAqBBQBRCCwAiEJgAUAUAgsAohBYABCFwAKAKAQWAEQhsAAgCoEFAFEILACIQmABQBQCCwCiEFgAEIXAAoAoBBYARCGwACAKgQUAUQgsAIhCYAFAFAILAGIA9gP3DWJICGBouwAAAABJRU5ErkJggg==';
    
    // Strip the data URL prefix and decode
    $fallbackImage = str_replace('data:image/png;base64,', '', $fallbackImage);
    $fallbackImage = base64_decode($fallbackImage);
    
    header('Content-Type: image/png');
    header('Cache-Control: max-age=86400, public');
    echo $fallbackImage;
    exit;
}

try {
    // Prvo probamo direktno dohvatiti sliku
    $stmt = $conn->prepare("SELECT slika FROM ep_pozadina WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $slika = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($slika && isset($slika['slika']) && !empty($slika['slika'])) {
            // Pokušaj detektirati format
            $hex = bin2hex(substr($slika['slika'], 0, 4));
            error_log("Signature: " . $hex);
            
            // Postavi MIME tip na temelju potpisa
            if (stripos($hex, 'ffd8ff') === 0) {
                header("Content-Type: image/jpeg");
            } elseif (stripos($hex, '89504e47') === 0) {
                header("Content-Type: image/png");
            } elseif (stripos($hex, '47494638') === 0) {
                header("Content-Type: image/gif");
            } else {
                // Pretpostavi PNG ako ne možemo detektirati
                header("Content-Type: image/png");
            }
            
            // Dodaj zaglavlja za optimizaciju
            header("Cache-Control: max-age=604800, public");
            header("Pragma: public");
            header("Expires: " . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');
            
            // Onemogući kompresiju za binarne podatke
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            
            // Ispiši binarni sadržaj slike
            echo $slika['slika'];
            exit;
        }
    }
    
    // Alternativni pristup s potpuno drugačijom SQL naredbom
    $stmt = $conn->prepare("SELECT * FROM ep_pozadina WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Traženje potencijalnih stupaca za sliku
        foreach ($row as $col => $val) {
            if (is_resource($val) || (is_string($val) && strlen($val) > 100)) {
                error_log("Pronađen potencijalni podatak u stupcu: " . $col);
                
                // Ako je resurs, koristi stream_get_contents
                if (is_resource($val)) {
                    $imageData = stream_get_contents($val);
                } else {
                    $imageData = $val;
                }
                
                if (!empty($imageData)) {
                    header("Content-Type: image/png");
                    header("Cache-Control: max-age=604800, public");
                    echo $imageData;
                    exit;
                }
            }
        }
    }
    
    // Ako nije pronađena slika, vrati fallback
    returnFallbackImage();
    
} catch (PDOException $e) {
    error_log("Greška pri dohvaćanju slike: " . $e->getMessage());
    returnFallbackImage();
} 
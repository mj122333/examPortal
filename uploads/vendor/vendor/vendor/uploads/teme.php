<?php
session_start();

// Uključi datoteku za konekciju s bazom
require_once 'db_connection.php';  // Uključivanje db_connection.php





// Fetch available themes
$stmt = $conn->query("SELECT ID, naziv FROM ep_teme");
$teme = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Odabir tema</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form method="POST" action="spremi_teme.php">
        <h2>Odaberite željene teme</h2>

        <?php foreach ($teme as $tema): ?>
            <label>
                <input type="checkbox" name="teme[]" value="<?= htmlspecialchars($tema['ID']) ?>">
                <?= htmlspecialchars($tema['naziv']) ?>
            </label><br>
        <?php endforeach; ?>

        <button type="submit">Spremi teme</button>
    </form>
</body>
</html>

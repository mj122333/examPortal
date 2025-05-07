<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Executor</title>
</head>
<body>
    <h1>SQL Executor</h1>
    <form method="POST" action="">
        <textarea name="query" rows="5" cols="50" placeholder="Unesite SQL kod ovdje..."></textarea><br>
        <button type="submit">Izvrši</button>
    </form>

    <h2>Rezultat:</h2>
    <pre>
<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    include 'db_connection.php';

    $query = $_POST['query'] ?? '';
    echo "Vaša komanda: " . htmlspecialchars($query) . "\n\n";

    if (!empty($query)) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();

            // Provera da li upit vraća rezultate
            $result = $stmt->fetchAll();
            if (!empty($result)) {
                foreach ($result as $row) {
                    print_r($row);
                    echo "\n";
                }
            } else {
                echo "SQL upit uspešno izvršen, ali nema rezultata.";
            }
        } catch (PDOException $e) {
            echo "Greška: " . $e->getMessage();
        }
    } else {
        echo "Niste uneli SQL upit.";
    }
}
?>
    </pre>
</body>
</html>
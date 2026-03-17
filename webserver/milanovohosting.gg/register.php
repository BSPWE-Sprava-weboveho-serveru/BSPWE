<?php
// Připojení se k databázi
require_once 'db.php';

// Kontrola, jestli k nám data přišla z formuláře (přes POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Načtení dat z políček formuláře
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $domain = $_POST['domain'];

    $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

    try {
        // Uložení uživatele a jeho domény do databáze
        $sql = "INSERT INTO users (username, password, domain) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user, $hashedPassword, $domain]);

        // Vytvoření složky pro web
        $path = "../" . $domain;

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            
            file_put_contents($path . "/index.html", "<h1>Web pro doménu $domain běží!</h1>");
        }

        echo "<h2>Hotovo! Hosting byl úspěšně zřízen.</h2>";
        echo "<p>Doména: <strong>$domain</strong></p>";
        echo "<p>Uživatel pro FTP: <strong>$user</strong></p>";
        echo "<hr>";
        echo "<a href='index.php'>Zpět na registraci</a>";

    } catch (Exception $e) {
        die("Chyba při vytváření hostingu: " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit;
}
?>
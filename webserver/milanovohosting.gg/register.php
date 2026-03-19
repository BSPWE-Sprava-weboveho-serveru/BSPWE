<?php
// Pøipojení se k databázi
require_once 'db.php';

// Kontrola, jestli k nám data pøišla z formuláøe (pøes POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Naètení dat z políèek formuláøe
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $domain = $_POST['domain'];

    // Kontrola, jestli uÅ¾ neexistuje domÃ©na
    $checkSql = "SELECT COUNT(*) FROM users WHERE domain = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$domain]);
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        echo "<h2>DomÃ©na jiÅ¾ existuje!</h2>";
        echo "<p>DomÃ©na <strong>" . htmlspecialchars($domain) . "</strong> je jiÅ¾ registrovÃ¡na. Zvolte prosÃ­m jinou domÃ©nu.</p>";
        echo "<a href='index.php'>ZpÄ›t na registraci</a>";
        exit; 
    }

    // Kontrola, jestli uÅ¾ neexistuje username
    $checkSql = "SELECT COUNT(*) FROM users WHERE username = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$user]);
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        echo "<h2>UÅ¾ivatel jiÅ¾ existuje!</h2>";
        echo "<p>UÅ¾ivatel <strong>" . htmlspecialchars($user) . "</strong> je jiÅ¾ zaregistrovÃ¡n. Zvolte prosÃ­m jinÃ© uÅ¾ivatelskÃ© jmÃ©no.</p>";
        echo "<a href='index.php'>ZpÄ›t na registraci</a>";
        exit; 
    }

    $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

    try {
        //Uloení uivatele a jeho domény do databáze
        $sql = "INSERT INTO users (username, password, domain) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user, $hashedPassword, $domain]);

        //Vytvoøení sloky pro web
        $path = "../" . $domain;

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            
            file_put_contents($path . "/index.html", "<h1>Web pro doménu $domain bìí!</h1>");
        }

        echo "<h2>Hotovo! Hosting byl úspìšnì zøízen.</h2>";
        echo "<p>Doména: <strong>$domain</strong></p>";
        echo "<p>Uivatel pro FTP: <strong>$user</strong></p>";
        echo "<hr>";
        echo "<a href='index.php'>Zpìt na registraci</a>";

    } catch (Exception $e) {
        die("Chyba pøi vytváøení hostingu: " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit;
}
?>
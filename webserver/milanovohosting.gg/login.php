<?php
session_start();

// Pøipojení k databázi
require_once 'db.php';

// Zpracování pøihlášení
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Nalezení uživatele v databázi podle jména
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $dbUser = $stmt->fetch();

    // Pokud uživatel existuje, kontrola zahashovaného hesla
    if ($dbUser && password_verify($pass, $dbUser['password'])) {
        
        // Zápis do session, že je uživatel pøihlášen (pøi úspìchu)
        $_SESSION['user_id'] = $dbUser['id'];
        $_SESSION['username'] = $dbUser['username'];

        // Pøesmìrování na dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Špatné jméno nebo heslo.";
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Pøihlášení - Milanovo Hosting</title>
</head>
<body>
    <h1>Pøihlášení do správy hostingu</h1>

    <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>

    <form method="POST">
        <label>Uživatelské jméno:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Heslo:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Pøihlásit se</button>
    </form>
    
    <p>Nemáte úèet? <a href="index.php">Zaregistrujte se</a></p>
</body>
</html>
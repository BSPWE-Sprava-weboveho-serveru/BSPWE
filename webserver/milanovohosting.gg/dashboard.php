<?php
session_start();
require_once 'db.php';

// Pokud uživatel není pøihlášen, vyhodí ho na login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vytáhne údaje o pøihlášeném uživateli z DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Administrace - Milanovo Hosting</title>
</head>
<body>
    <h1>Vítejte, <?php echo htmlspecialchars($user['username']); ?>!</h1>
    <p>Zde je správa vašeho hostingu.</p>

    <table border="1">
        <tr>
            <th>Vaše doména</th>
            <th>FTP Hostitel</th>
            <th>FTP Uživatel</th>
            <th>Stav</th>
        </tr>
        <tr>
            <td><strong><?php echo htmlspecialchars($user['domain']); ?></strong></td>
            <td>localhost (port 21)</td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td style="color: green;">Aktivní</td>
        </tr>
    </table>

    <br>
    <p><a href="logout.php">Odhlásit se</a></p>
</body>
</html>
<?php
// jen čisté HTML pro testování backendu
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Milanovo Hosting - Registrace</title>
</head>
<body>
    <h1>Vytvořte si svůj vlastní hosting</h1>

    <form action="register.php" method="POST">
        <div>
            <label>Uživatelské jméno (pro FTP):</label><br>
            <input type="text" name="username" required>
        </div>
        
        <br>

        <div>
            <label>Heslo:</label><br>
            <input type="password" name="password" required>
        </div>

        <br>

        <div>
            <label>Název vaší domény (např. moje-firma.cz):</label><br>
            <input type="text" name="domain" required>
        </div>

        <br>

        <button type="submit">Založit hosting</button>
    </form>

    <p>Máte účet? <a href="login.php">Příhlásit se</a></p>
</body>
</html>
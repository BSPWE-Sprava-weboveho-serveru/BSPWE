<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $domain = trim($_POST['domain']);

    if ($user === '' || $pass === '' || $domain === '') {
        $error = "Všechna pole musí být vyplněná.";
    } else {
        $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            // Uložení uživatele
            $sqlUser = "INSERT INTO users (username, password) VALUES (?, ?)";
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute([$user, $hashedPassword]);

            // Získání ID nového uživatele
            $userId = $pdo->lastInsertId();

            // Uložení první domény uživatele
            $sqlDomain = "INSERT INTO domains (user_id, domain_name) VALUES (?, ?)";
            $stmtDomain = $pdo->prepare($sqlDomain);
            $stmtDomain->execute([$userId, $domain]);

            $pdo->commit();

            // Vytvoření složky pro web
            $path = "../" . $domain;

            if (!file_exists($path)) {
                mkdir($path, 0777, true);
                file_put_contents($path . "/index.html", "<h1>Web pro doménu " . htmlspecialchars($domain) . " běží!</h1>");
            }

            $success = true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Chyba při vytváření hostingu: " . $e->getMessage();
        }
    }
} else {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Výsledek registrace - Milanovo Hosting</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="theme-toggle">
            <button id="themeToggle" type="button" aria-label="Přepnout režim">🌙</button>
        </div>

        <main class="dashboard-layout">
            <section class="dashboard-header">
                <div class="eyebrow">Registrace hostingu</div>

                <h1>
                    <?php if (isset($success) && $success): ?>
                        Registrace proběhla úspěšně
                    <?php else: ?>
                        Registraci se nepodařilo dokončit
                    <?php endif; ?>
                </h1>

                <p class="hero-text">
                    <?php if (isset($success) && $success): ?>
                        Za <span id="countdown">5</span> vteřin budete automaticky přesměrováni na přihlášení.
                    <?php else: ?>
                        Výsledek zpracování vašeho požadavku na vytvoření hostingu.
                    <?php endif; ?>
                </p>
            </section>

            <section class="dashboard-panel">
                <?php if (isset($success) && $success): ?>

                    <div class="dashboard-grid">
                        <div class="dashboard-item dashboard-item-wide">
                            <span class="dashboard-label">Vaše doména</span>
                            <span class="dashboard-value"><?php echo htmlspecialchars($domain); ?></span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">FTP uživatel</span>
                            <span class="dashboard-value"><?php echo htmlspecialchars($user); ?></span>
                            <span class="dashboard-note">Přihlašovací jméno k účtu</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">Stav registrace</span>
                            <div class="status-row">
                                <span class="status-active">Úspěšně vytvořeno</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-buttons">
                        <a href="login.php" class="primary-link-btn">Přejít hned na přihlášení</a>
                    </div>

                <?php else: ?>

                    <div class="dashboard-panel-top">
                        <div>
                            <div class="card-badge">Chyba</div>
                            <h2>Něco se nepovedlo</h2>
                            <p>Hosting se nepodařilo vytvořit.</p>
                        </div>
                    </div>

                    <p class="form-error"><?php echo htmlspecialchars($error); ?></p>

                    <div class="dashboard-buttons">
                        <a href="index.php" class="primary-link-btn">Zpět na registraci</a>
                    </div>

                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="theme.js"></script>

    <?php if (isset($success) && $success): ?>
    <script>
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');

        const interval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(interval);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
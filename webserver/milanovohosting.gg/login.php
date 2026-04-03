<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === '' || $pass === '') {
        $error = "Vyplňte uživatelské jméno i heslo.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$user]);
        $dbUser = $stmt->fetch();

        if ($dbUser && password_verify($pass, $dbUser['password'])) {
            $_SESSION['user_id'] = $dbUser['id'];
            $_SESSION['username'] = $dbUser['username'];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Špatné jméno nebo heslo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Milanovo Hosting</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="theme-toggle">
            <button id="themeToggle" type="button" aria-label="Přepnout režim">🌙</button>
        </div>

        <main class="hero">
            <section class="hero-copy">
                <div class="eyebrow">Moderní webhosting</div>
                <h1>Přihlaste se do svého účtu</h1>
                <p class="hero-text">
                    Přístup ke správě vašeho hostingu, domény a administrace v moderním a přehledném rozhraní.
                </p>

                <div class="hero-points">
                    <div class="point">
                        <span class="point-icon">✓</span>
                        <span>Bezpečné přihlášení</span>
                    </div>

                    <div class="point">
                        <span class="point-icon">✓</span>
                        <span>Správa hostingu</span>
                    </div>
                </div>
            </section>

            <section class="form-wrap">
                <div class="form-card">
                    <div class="card-top">
                        <div>
                            <div class="card-badge">Login</div>
                            <h2>Přihlášení</h2>
                            <p>Zadejte své přihlašovací údaje.</p>
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <p class="form-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <form method="POST" class="register-form">
                        <div class="form-group">
                            <label for="username">Uživatelské jméno</label>
                            <input type="text" id="username" name="username" placeholder="Zadejte uživatelské jméno" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Heslo</label>
                            <input type="password" id="password" name="password" placeholder="Zadejte heslo" required>
                        </div>

                        <button type="submit" class="primary-btn">Přihlásit se</button>
                    </form>

                    <p class="bottom-text">
                        Nemáte účet?
                        <a href="index.php">Zaregistrujte se</a>
                    </p>
                </div>
            </section>
        </main>
    </div>

    <script src="theme.js"></script>
</body>
</html>
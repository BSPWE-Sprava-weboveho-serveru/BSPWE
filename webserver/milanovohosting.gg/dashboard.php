<?php
session_start();
require_once 'db.php';

// Pokud uživatel není přihlášen, vyhodí ho na login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vytáhne údaje o přihlášeném uživateli z DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrace - Milanovo Hosting</title>

    <!-- Připojení CSS souboru -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <!-- Přepínač světlého a tmavého režimu -->
        <div class="theme-toggle">
            <button id="themeToggle" type="button" aria-label="Přepnout režim">🌙</button>
        </div>

        <main class="dashboard-layout">
            <!-- Horní uvítací část -->
            <section class="dashboard-header">
                <div class="eyebrow">Administrace hostingu</div>
                <h1>Vítejte, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <p class="hero-text">
                    Přehled vašeho hostingu, domény a FTP přístupů na jednom místě.
                </p>
            </section>

            <!-- Hlavní dashboard -->
            <section class="dashboard-panel">
                <div class="dashboard-panel-top">
                    <div>
                        <div class="card-badge">Dashboard</div>
                        <h2>Správa hostingu</h2>
                        <p>Přehled vašeho účtu a aktuálního nastavení.</p>
                    </div>

                    <a href="logout.php" class="secondary-btn dashboard-logout">Odhlásit se</a>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-item dashboard-item-wide">
                        <span class="dashboard-label">Vaše doména</span>
                        <span class="dashboard-value"><?php echo htmlspecialchars($user['domain']); ?></span>
                    </div>

                    <div class="dashboard-item">
                        <span class="dashboard-label">FTP hostitel</span>
                        <span class="dashboard-value">localhost</span>
                        <span class="dashboard-note">Port 21</span>
                    </div>

                    <div class="dashboard-item">
                        <span class="dashboard-label">FTP uživatel</span>
                        <span class="dashboard-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="dashboard-note">Přístup k účtu</span>
                    </div>

                    <div class="dashboard-item dashboard-item-wide">
                        <span class="dashboard-label">Stav hostingu</span>
                        <div class="status-row">
                            <span class="status-active">Aktivní</span>
                            <span class="dashboard-note">Služba je dostupná a připravena k použití</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

<script src="theme.js"></script>
</body>
</html>
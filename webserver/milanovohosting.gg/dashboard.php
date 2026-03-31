<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Načtení uživatele
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// SMAZÁNÍ DOMÉNY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain_id'])) {
    $deleteDomainId = (int) $_POST['delete_domain_id'];

    try {
        // Ověření, že doména patří přihlášenému uživateli
        $stmtCheck = $pdo->prepare("SELECT id, domain_name FROM domains WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$deleteDomainId, $userId]);
        $domainToDelete = $stmtCheck->fetch();

        if (!$domainToDelete) {
            $error = "Tuto doménu nelze smazat, protože nepatří k vašemu účtu.";
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM domains WHERE id = ? AND user_id = ?");
            $stmtDelete->execute([$deleteDomainId, $userId]);

            // volitelně smazání složky domény zatím neprovádíme
            $success = "Doména " . $domainToDelete['domain_name'] . " byla úspěšně smazána.";
        }
    } catch (Exception $e) {
        $error = "Při mazání domény došlo k chybě.";
    }
}

// PŘIDÁNÍ NOVÉ DOMÉNY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_domain'])) {
    $newDomain = trim($_POST['new_domain']);

    if ($newDomain === '') {
        $error = "Zadejte název domény.";
    } else {
        try {
            // Kontrola, jestli už doména existuje v systému
            $stmtCheckDomain = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ?");
            $stmtCheckDomain->execute([$newDomain]);
            $existingDomain = $stmtCheckDomain->fetch();

            if ($existingDomain) {
                $error = "Tato doména už je v systému obsazená. Vyberte jinou.";
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO domains (user_id, domain_name) VALUES (?, ?)");
                $stmtInsert->execute([$userId, $newDomain]);

                $path = "../" . $newDomain;

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                    file_put_contents(
                        $path . "/index.html",
                        "<h1>Web pro doménu " . htmlspecialchars($newDomain) . " běží!</h1>"
                    );
                }

                $success = "Doména byla úspěšně přidána.";
            }
        } catch (Exception $e) {
            $error = "Při přidávání domény došlo k chybě.";
        }
    }
}

// Načtení všech domén uživatele
$stmtDomains = $pdo->prepare("SELECT id, domain_name, created_at FROM domains WHERE user_id = ? ORDER BY id ASC");
$stmtDomains->execute([$userId]);
$domains = $stmtDomains->fetchAll();
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrace - Milanovo Hosting</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <div class="theme-toggle">
            <button id="themeToggle" type="button" aria-label="Přepnout režim">🌙</button>
        </div>

        <main class="dashboard-layout dashboard-layout-wide">
            <section class="dashboard-header dashboard-header-wide">
                <div class="eyebrow">Administrace hostingu</div>
                <h1>Vítejte, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <p class="hero-text">
                    Přehled vašeho hostingu, domén a FTP přístupů na jednom místě.
                </p>
            </section>

            <section class="dashboard-panel">
                <div class="dashboard-panel-top">
                    <div>
                        <div class="card-badge">Dashboard</div>
                        <h2>Správa hostingu</h2>
                        <p>Přehled vašeho účtu a aktuálního nastavení.</p>
                    </div>

                    <a href="logout.php" class="secondary-btn dashboard-logout">Odhlásit se</a>
                </div>

                <?php if (isset($success)): ?>
                    <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <p class="form-error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="dashboard-item dashboard-item-wide">
                        <div class="domain-card-head">
                            <span class="dashboard-label">Vaše domény</span>
                            <span class="domains-count"><?php echo count($domains); ?> celkem</span>
                        </div>

                        <?php if (!empty($domains)): ?>
                            <div class="domains-list domains-list-with-actions">
                                <?php foreach ($domains as $domain): ?>
                                    <div class="domain-row">
                                        <div class="domain-pill">
                                            <span class="domain-pill-dot"></span>
                                            <span class="domain-pill-text"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                                        </div>

                                        <form method="POST" class="domain-delete-form" onsubmit="return confirm('Opravdu chcete smazat doménu <?php echo htmlspecialchars($domain['domain_name']); ?>?');">
                                            <input type="hidden" name="delete_domain_id" value="<?php echo (int) $domain['id']; ?>">
                                            <button type="submit" class="delete-domain-btn">Smazat</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="dashboard-value dashboard-value-empty">Žádná doména nebyla nalezena</span>
                        <?php endif; ?>
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

                <div class="dashboard-divider"></div>

                <div class="dashboard-panel-top dashboard-panel-top-compact">
                    <div>
                        <div class="card-badge">Nová doména</div>
                        <h2>Přidat další doménu</h2>
                        <p>Zadejte název nové domény, kterou chcete přidat ke svému účtu.</p>
                    </div>
                </div>

                <form method="POST" class="register-form add-domain-form">
                    <div class="form-group">
                        <label for="new_domain">Název nové domény</label>
                        <input type="text" id="new_domain" name="new_domain" placeholder="např. moje-dalsi-domena.cz" required>
                    </div>

                    <button type="submit" class="primary-btn">Přidat doménu</button>
                </form>
            </section>
        </main>
    </div>

    <script src="theme.js"></script>
</body>
</html>
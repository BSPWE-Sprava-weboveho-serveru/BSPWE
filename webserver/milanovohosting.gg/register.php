<?php
session_start();
require_once 'db.php';
require_once 'ftp_crypto.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $domain = trim($_POST['domain'] ?? '');

    if ($user === '' || $pass === '' || $domain === '') {
        $error = "Všechna pole musí být vyplněná.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $user)) {
        $error = "Uživatelské jméno obsahuje nepovolené znaky.";
    } elseif (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
        $error = "Doména nemá správný formát.";
    } else {
        $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

        // Vygenerované FTP heslo
        $ftpPasswordPlain = bin2hex(random_bytes(4));
        $ftpPasswordEncrypted = encryptFtpPassword($ftpPasswordPlain);

        // Cesty
        $userRoot = __DIR__ . "/../users/" . $user;
        $domainPath = $userRoot . "/" . $domain;
        $requestDir = "/ftp-requests";
        $requestFile = $requestDir . "/ftp_create_user_" . $user . "_" . time() . ".json";

        try {
            $pdo->beginTransaction();

            // Kontrola unikátního username
            $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheckUser->execute([$user]);

            if ($stmtCheckUser->fetch()) {
                throw new Exception("Uživatel už existuje.");
            }

            // Kontrola unikátní domény
            $stmtCheckDomain = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ?");
            $stmtCheckDomain->execute([$domain]);

            if ($stmtCheckDomain->fetch()) {
                throw new Exception("Tato doména už je v systému obsazená.");
            }

            // Vložení uživatele
            $sqlUser = "INSERT INTO users (username, password, ftp_password) VALUES (?, ?, ?)";
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute([$user, $hashedPassword, $ftpPasswordEncrypted]);

            $userId = $pdo->lastInsertId();

            // Vložení první domény
            $sqlDomain = "INSERT INTO domains (user_id, domain_name) VALUES (?, ?)";
            $stmtDomain = $pdo->prepare($sqlDomain);
            $stmtDomain->execute([$userId, $domain]);

            // Vytvoření adresáře uživatele
            if (!is_dir($userRoot) && !mkdir($userRoot, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit složku uživatele.");
            }

            // Vytvoření adresáře domény
            if (!is_dir($domainPath) && !mkdir($domainPath, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit složku domény.");
            }

            // Výchozí index.html
            $indexFile = $domainPath . "/index.html";
            if (!file_exists($indexFile)) {
                $content = "<h1>Web pro doménu " . htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') . " běží!</h1>";
                if (file_put_contents($indexFile, $content) === false) {
                    throw new Exception("Nepodařilo se vytvořit index.html.");
                }
            }

            // Cesta k adresáři se symlinky (musíš ho předem vytvořit: mkdir -p ./webserver/domains)
            $domainsDir = __DIR__ . "/../domains";
            if (!is_dir($domainsDir) && !mkdir($domainsDir, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit hlavní složku domains.");
            }

            // Vytvoření symlinku: /var/www/html/domains/moje-firma.cz -> /var/www/html/users/milan/moje-firma.cz
            $symlinkPath = $domainsDir . "/" . $domain;
            if (!file_exists($symlinkPath)) {
                // Relativní symlinky jsou v Dockeru bezpečnější než absolutní cesty
                $targetPath = "../users/" . $user . "/" . $domain; 
                if (!symlink($targetPath, $symlinkPath)) {
                    throw new Exception("Nepodařilo se vytvořit symlink pro doménu.");
                }
            }

            $dnsmasqConfig = "/etc/dnsmasq.conf";
            $newLine = "address=/$domain/127.0.0.1";

            if (file_exists($dnsmasqConfig)) {
                $currentContent = file_get_contents($dnsmasqConfig);
                
                if (strpos($currentContent, "/$domain/") === false) {
                    file_put_contents($dnsmasqConfig, "\n" . $newLine, FILE_APPEND | LOCK_EX);
                }
            }

            // Vytvoření request adresáře pro FTP worker
            if (!is_dir($requestDir) && !mkdir($requestDir, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit request adresář.");
            }

            // Request pro FTP kontejner
            $requestData = [
                'type' => 'create_user',
                'username' => $user,
                'ftp_password_plain' => $ftpPasswordPlain
            ];

            $json = json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new Exception("Nepodařilo se vytvořit JSON pro FTP request.");
            }

            if (file_put_contents($requestFile, $json) === false) {
                throw new Exception("Nepodařilo se zapsat FTP request.");
            }

            $pdo->commit();

            $_SESSION['ftp_password_plain_once'] = $ftpPasswordPlain;
            $success = true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (isset($requestFile) && file_exists($requestFile)) {
                unlink($requestFile);
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
                        Hosting a FTP účet byly vytvořeny.
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
                            <span class="dashboard-note">Přihlašovací jméno k FTP účtu</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">FTP heslo</span>
                            <span class="dashboard-value"><?php echo htmlspecialchars($ftpPasswordPlain); ?></span>
                            <span class="dashboard-note">Tohle heslo se bude zobrazovat i v administraci</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">Stav registrace</span>
                            <div class="status-row">
                                <span class="status-active">Úspěšně vytvořeno</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-buttons">
                        <a href="login.php" class="primary-link-btn">Přejít na přihlášení</a>
                    </div>

                <?php else: ?>

                    <div class="dashboard-panel-top">
                        <div>
                            <div class="card-badge">Chyba</div>
                            <h2>Něco se nepovedlo</h2>
                            <p>Hosting se nepodařilo vytvořit.</p>
                        </div>
                    </div>

                    <p class="form-error"><?php echo htmlspecialchars($error ?? 'Došlo k neznámé chybě.'); ?></p>

                    <div class="dashboard-buttons">
                        <a href="index.php" class="primary-link-btn">Zpět na registraci</a>
                    </div>

                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="theme.js"></script>
</body>
</html>
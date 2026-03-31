<?php
session_start();
require_once 'db.php';
require_once 'ftp_crypto.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function rrmdir(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }

    $items = scandir($dir);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            if (!rrmdir($path)) {
                return false;
            }
        } else {
            if (!unlink($path)) {
                return false;
            }
        }
    }

    return rmdir($dir);
}

function writeFtpRequest(string $type, string $username, ?string $plainPassword = null): void
{
    $requestDir = '/ftp-requests';

    if (!is_dir($requestDir) && !mkdir($requestDir, 0775, true)) {
        throw new Exception('Nepodařilo se vytvořit request adresář pro FTP.');
    }

    $data = [
        'type' => $type,
        'username' => $username,
    ];

    if ($plainPassword !== null) {
        $data['ftp_password_plain'] = $plainPassword;
    }

    $requestFile = $requestDir . '/ftp_' . $type . '_' . $username . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.json';

    $written = file_put_contents(
        $requestFile,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    if ($written === false) {
        throw new Exception('Nepodařilo se zapsat FTP request.');
    }
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, username, ftp_password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$username = $user['username'];
$userRoot = __DIR__ . "/../users/" . $username;

$ftpPasswordDecrypted = null;
try {
    if (!empty($user['ftp_password'])) {
        $ftpPasswordDecrypted = decryptFtpPassword($user['ftp_password']);
    }
} catch (Exception $e) {
    $ftpPasswordDecrypted = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain_id'])) {
    $deleteDomainId = (int) $_POST['delete_domain_id'];

    try {
        $stmtCheck = $pdo->prepare("SELECT id, domain_name FROM domains WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$deleteDomainId, $userId]);
        $domainToDelete = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$domainToDelete) {
            throw new Exception("Tuto doménu nelze smazat, protože nepatří k vašemu účtu.");
        }

        $domainPath = $userRoot . "/" . $domainToDelete['domain_name'];

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("DELETE FROM domains WHERE id = ? AND user_id = ?");
        $stmtDelete->execute([$deleteDomainId, $userId]);

        $pdo->commit();

        if (is_dir($domainPath) && !rrmdir($domainPath)) {
            $error = "Doména byla smazána z databáze, ale nepodařilo se odstranit její složku.";
        } else {
            $success = "Doména " . $domainToDelete['domain_name'] . " byla úspěšně smazána.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Při mazání domény došlo k chybě: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_domain'])) {
    $newDomain = trim($_POST['new_domain']);

    if ($newDomain === '') {
        $error = "Zadejte název domény.";
    } elseif (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $newDomain)) {
        $error = "Doména nemá správný formát.";
    } else {
        try {
            $stmtCheckDomain = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ?");
            $stmtCheckDomain->execute([$newDomain]);
            $existingDomain = $stmtCheckDomain->fetch(PDO::FETCH_ASSOC);

            if ($existingDomain) {
                throw new Exception("Tato doména už je v systému obsazená. Vyberte jinou.");
            }

            $domainPath = $userRoot . "/" . $newDomain;

            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("INSERT INTO domains (user_id, domain_name) VALUES (?, ?)");
            $stmtInsert->execute([$userId, $newDomain]);

            $pdo->commit();

            if (!is_dir($userRoot) && !mkdir($userRoot, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit složku uživatele.");
            }

            if (!is_dir($domainPath) && !mkdir($domainPath, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit složku domény.");
            }

            $indexFile = $domainPath . "/index.html";
            if (!file_exists($indexFile)) {
                $content = "<h1>Web pro doménu " . htmlspecialchars($newDomain, ENT_QUOTES, 'UTF-8') . " běží!</h1>";
                if (file_put_contents($indexFile, $content) === false) {
                    throw new Exception("Nepodařilo se vytvořit index.html.");
                }
            }

            $success = "Doména byla úspěšně přidána.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Při přidávání domény došlo k chybě: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_ftp_password'])) {
    try {
        $newFtpPasswordPlain = bin2hex(random_bytes(4));
        $newFtpPasswordEncrypted = encryptFtpPassword($newFtpPasswordPlain);

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("UPDATE users SET ftp_password = ? WHERE id = ?");
        $stmtUpdate->execute([$newFtpPasswordEncrypted, $userId]);

        writeFtpRequest('reset_password', $username, $newFtpPasswordPlain);

        $pdo->commit();

        $_SESSION['ftp_password_plain_once'] = $newFtpPasswordPlain;
        header("Location: admin.php");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Nepodařilo se resetovat FTP heslo: " . $e->getMessage();
    }
}

$ftpPasswordPlainOnce = $_SESSION['ftp_password_plain_once'] ?? null;
unset($_SESSION['ftp_password_plain_once']);

$stmtDomains = $pdo->prepare("SELECT id, domain_name, created_at FROM domains WHERE user_id = ? ORDER BY id ASC");
$stmtDomains->execute([$userId]);
$domains = $stmtDomains->fetchAll(PDO::FETCH_ASSOC);

$displayedFtpPassword = $ftpPasswordPlainOnce !== null ? $ftpPasswordPlainOnce : $ftpPasswordDecrypted;
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

                <?php if ($ftpPasswordPlainOnce !== null): ?>
                    <p class="success-message">
                        Nové FTP heslo bylo vygenerováno: <strong><?php echo htmlspecialchars($ftpPasswordPlainOnce); ?></strong>
                    </p>
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
                        <span class="dashboard-value">127.0.0.1</span>
                        <span class="dashboard-note">Port 21</span>
                    </div>

                    <div class="dashboard-item">
                        <span class="dashboard-label">FTP uživatel</span>
                        <span class="dashboard-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="dashboard-note">Přístup k vašemu účtu</span>
                    </div>

                    <div class="dashboard-item">
                        <span class="dashboard-label">FTP heslo</span>
                        <span class="dashboard-value">
                            <?php echo $displayedFtpPassword !== null ? htmlspecialchars($displayedFtpPassword) : 'Nelze zobrazit heslo'; ?>
                        </span>
                        <span class="dashboard-note">Heslo k FTP účtu</span>
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
                        <div class="card-badge">FTP přístup</div>
                        <h2>Obnovit FTP heslo</h2>
                        <p>Vygeneruje nové FTP heslo a staré přestane platit.</p>
                    </div>
                </div>

                <form method="POST" class="register-form add-domain-form">
                    <button type="submit" name="reset_ftp_password" value="1" class="primary-btn">
                        Vygenerovat nové FTP heslo
                    </button>
                </form>

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
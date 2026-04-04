<?php
session_start();
require_once 'db.php';
require_once 'ftp_crypto.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$dbAdminUrl = 'http://localhost:8888';

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

function writeFtpRequest(string $type, string $username, ?string $plainPassword = null, ?array $fileData = null): void
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

    if ($fileData !== null) {
        $data['fileData'] = $fileData;
    }

    $requestFile = $requestDir . '/ftp_' . $type . '_' . $username . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.json';

    $written = file_put_contents(
        $requestFile,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    if ($written === false) {
        throw new Exception('Nepodařilo se zapsat FTP request.');
    }
}

function getFilesRecursive(string $dir, string $baseDir = ''): array
{
    $files = [];
    $items = scandir($dir);
    if ($items === false) {
        return $files;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $relativePath = $baseDir === '' ? $item : $baseDir . '/' . $item;

        if (is_dir($path)) {
            $files = array_merge($files, getFilesRecursive($path, $relativePath));
        } else {
            $files[] = $relativePath;
        }
    }

    return $files;
}

function sanitizeForDbName(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim($value, '_');

    if ($value === '') {
        throw new Exception("Nepodařilo se vytvořit platný databázový název.");
    }

    return $value;
}

function escapeMysqlIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function escapeMysqlString(string $value): string
{
    return str_replace(
        ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
        ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
        $value
    );
}

function escapeMysqlUser(string $value): string
{
    return "'" . escapeMysqlString($value) . "'";
}

function createDatabaseForDomain(PDO $pdo, string $username, string $domain, int $domainId): array
{
    $domainKey = sanitizeForDbName($domain);
    $userKey = sanitizeForDbName($username);
    $uniqueSuffix = substr(hash('sha256', $username . '|' . $domain . '|' . microtime(true)), 0, 8);

    $dbName = substr("db_{$domainKey}_{$uniqueSuffix}", 0, 64);
    $dbUser = substr("u_{$userKey}_{$uniqueSuffix}", 0, 32);
    $dbPasswordPlain = bin2hex(random_bytes(8));

    $stmtCheckDb = $pdo->prepare("SELECT id FROM user_databases WHERE db_name = ? OR db_user = ?");
    $stmtCheckDb->execute([$dbName, $dbUser]);
    if ($stmtCheckDb->fetch()) {
        throw new Exception("Nepodařilo se vygenerovat unikátní databázové údaje.");
    }

    $escapedDbName = escapeMysqlIdentifier($dbName);
    $escapedDbUser = escapeMysqlUser($dbUser);
    $escapedDbPassword = "'" . escapeMysqlString($dbPasswordPlain) . "'";

    $dbCreated = false;
    $dbUserCreated = false;

    try {
        $pdo->exec("CREATE DATABASE {$escapedDbName}");
        $dbCreated = true;

        $pdo->exec("CREATE USER {$escapedDbUser}@'%' IDENTIFIED BY {$escapedDbPassword}");
        $dbUserCreated = true;

        $pdo->exec("GRANT ALL PRIVILEGES ON {$escapedDbName}.* TO {$escapedDbUser}@'%'");
        $pdo->exec("FLUSH PRIVILEGES");

        $stmtDb = $pdo->prepare("
            INSERT INTO user_databases (domain_id, db_name, db_user, db_password)
            VALUES (?, ?, ?, ?)
        ");
        $stmtDb->execute([$domainId, $dbName, $dbUser, $dbPasswordPlain]);

        return [
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPasswordPlain,
        ];
    } catch (Exception $e) {
        try {
            if ($dbUserCreated) {
                $pdo->exec("DROP USER IF EXISTS {$escapedDbUser}@'%'");
            }
            if ($dbCreated) {
                $pdo->exec("DROP DATABASE IF EXISTS {$escapedDbName}");
            }
        } catch (Exception $cleanupException) {
        }

        throw $e;
    }
}

function dropDatabaseForDomain(PDO $pdo, int $domainId): void
{
    $stmtDb = $pdo->prepare("SELECT db_name, db_user FROM user_databases WHERE domain_id = ?");
    $stmtDb->execute([$domainId]);
    $dbData = $stmtDb->fetch(PDO::FETCH_ASSOC);

    if (!$dbData) {
        return;
    }

    $escapedDbName = escapeMysqlIdentifier($dbData['db_name']);
    $escapedDbUser = escapeMysqlUser($dbData['db_user']);

    $pdo->exec("DROP USER IF EXISTS {$escapedDbUser}@'%'");
    $pdo->exec("DROP DATABASE IF EXISTS {$escapedDbName}");
    $pdo->exec("FLUSH PRIVILEGES");
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

if (isset($_GET['action']) && $_GET['action'] === 'get_files' && isset($_GET['domain'])) {
    header('Content-Type: application/json');
    $domain = trim($_GET['domain']);

    $stmtCheck = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ? AND user_id = ?");
    $stmtCheck->execute([$domain, $userId]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['error' => 'Doména neexistuje nebo k ní nemáte přístup.']);
        exit;
    }

    $domainPath = $userRoot . "/" . $domain;
    if (is_dir($domainPath)) {
        $files = getFilesRecursive($domainPath);
        echo json_encode(['files' => $files]);
    } else {
        echo json_encode(['files' => []]);
    }
    exit;
}

// Smazání domény + DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain_id'])) {
    $deleteDomainId = (int) $_POST['delete_domain_id'];
    $selectedDomain = $_POST['selected_domain'] ?? '';

    try {
        $stmtCheck = $pdo->prepare("
            SELECT d.id, d.domain_name
            FROM domains d
            WHERE d.id = ? AND d.user_id = ?
        ");
        $stmtCheck->execute([$deleteDomainId, $userId]);
        $domainToDelete = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$domainToDelete) {
            throw new Exception("Tuto doménu nelze smazat, protože nepatří k vašemu účtu.");
        }

        $domainPath = $userRoot . "/" . $domainToDelete['domain_name'];
        $domainsDir = __DIR__ . "/../domains";
        $symlinkPath = $domainsDir . "/" . $domainToDelete['domain_name'];

        dropDatabaseForDomain($pdo, $deleteDomainId);

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("DELETE FROM domains WHERE id = ? AND user_id = ?");
        $stmtDelete->execute([$deleteDomainId, $userId]);

        $pdo->commit();

        if (is_link($symlinkPath) || file_exists($symlinkPath)) {
            @unlink($symlinkPath);
        }

        if (is_dir($domainPath) && !rrmdir($domainPath)) {
            $error = "Doména byla smazána z databáze, ale nepodařilo se odstranit její složku.";
        } else {
            $success = "Doména " . $domainToDelete['domain_name'] . " byla úspěšně smazána včetně databáze.";
        }

        $redirectDomain = ($selectedDomain !== $domainToDelete['domain_name']) ? $selectedDomain : '';
        header("Location: dashboard.php?selected_domain=" . urlencode($redirectDomain));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Při mazání domény došlo k chybě: " . $e->getMessage();
    }
}

// Přidání nové domény + DB
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
            $domainId = (int) $pdo->lastInsertId();

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

            $domainsDir = __DIR__ . "/../domains";
            if (!is_dir($domainsDir) && !mkdir($domainsDir, 0775, true)) {
                throw new Exception("Nepodařilo se vytvořit hlavní složku domains.");
            }

            $symlinkPath = $domainsDir . "/" . $newDomain;
            if (!file_exists($symlinkPath)) {
                $targetPath = "../users/" . $username . "/" . $newDomain;
                if (!symlink($targetPath, $symlinkPath)) {
                    throw new Exception("Nepodařilo se vytvořit symlink pro doménu.");
                }
            }

            $newDbInfo = createDatabaseForDomain($pdo, $username, $newDomain, $domainId);

            $success = "Doména byla úspěšně přidána včetně databáze.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Při přidávání domény došlo k chybě: " . $e->getMessage();
        }
    }
}

// Reset FTP hesla
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
        header("Location: dashboard.php");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Nepodařilo se resetovat FTP heslo: " . $e->getMessage();
    }
}

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $domainName = trim($_POST['domain_name'] ?? '');
    $files = $_FILES['files_to_upload'] ?? null;

    try {
        if (empty($domainName)) {
            throw new Exception("Musíte vybrat doménu.");
        }

        $stmtCheckDomain = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ? AND user_id = ?");
        $stmtCheckDomain->execute([$domainName, $userId]);
        if (!$stmtCheckDomain->fetch()) {
            throw new Exception("Doména neexistuje nebo k ní nemáte přístup.");
        }

        if (empty($files) || !isset($files['error'])) {
            throw new Exception("Nebyly odeslány žádné soubory.");
        }

        $uploadedCount = 0;
        $errors = [];
        $filePaths = $_POST['file_paths'] ?? [];

        foreach ($files['error'] as $index => $uploadError) {
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = "Soubor '{$files['name'][$index]}' – chyba nahrávání (kód $uploadError).";
                continue;
            }

            $remotePath = isset($filePaths[$index]) ? trim($filePaths[$index]) : basename($files['name'][$index]);

            if (strpos($remotePath, '..') !== false || strpos($remotePath, '/') === 0) {
                $errors[] = "Soubor '{$remotePath}' – neplatná cesta.";
                continue;
            }

            if ($remotePath === '') {
                $errors[] = "Soubor '{$files['name'][$index]}' – prázdná cesta.";
                continue;
            }

            $fileContent = file_get_contents($files['tmp_name'][$index]);
            if ($fileContent === false) {
                $errors[] = "Soubor '{$remotePath}' – nepodařilo se přečíst.";
                continue;
            }

            $base64Content = base64_encode($fileContent);

            $fileData = [
                'domain' => $domainName,
                'remote_path' => $remotePath,
                'content_base64' => $base64Content,
            ];

            try {
                writeFtpRequest('upload_file', $username, null, $fileData);
                $uploadedCount++;
            } catch (Exception $e) {
                $errors[] = "Soubor '{$remotePath}' – " . $e->getMessage();
            }
        }

        if ($uploadedCount > 0) {
            $success = "$uploadedCount souborů bylo úspěšně odesláno k nahrání na FTP server.";
            if (!empty($errors)) {
                $error = implode(' | ', $errors);
            }
        } else {
            throw new Exception("Žádný soubor nebyl nahrán. " . implode('; ', $errors));
        }

        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success ?? null, 'error' => $error ?? null]);
            exit;
        }
    } catch (Exception $e) {
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
        $error = "Nahrání selhalo: " . $e->getMessage();
    }
}

// Smazání souboru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $domainName = trim($_POST['domain_name'] ?? '');
    $remotePath = trim($_POST['remote_path'] ?? '');
    $selectedDomain = $_POST['selected_domain'] ?? '';

    try {
        if (empty($domainName) || empty($remotePath)) {
            throw new Exception("Chybí údaje pro smazání.");
        }

        $stmtCheckDomain = $pdo->prepare("SELECT id FROM domains WHERE domain_name = ? AND user_id = ?");
        $stmtCheckDomain->execute([$domainName, $userId]);
        if (!$stmtCheckDomain->fetch()) {
            throw new Exception("Doména neexistuje nebo k ní nemáte přístup.");
        }

        if (strpos($remotePath, '..') !== false) {
            throw new Exception("Cesta k souboru obsahuje zakázané znaky.");
        }

        $fileData = [
            'domain' => $domainName,
            'remote_path' => $remotePath,
        ];

        writeFtpRequest('delete_file', $username, null, $fileData);

        header("Location: dashboard.php?selected_domain=" . urlencode($selectedDomain));
        exit;
    } catch (Exception $e) {
        $error = "Smazání selhalo: " . $e->getMessage();
    }
}

$ftpPasswordPlainOnce = $_SESSION['ftp_password_plain_once'] ?? null;
unset($_SESSION['ftp_password_plain_once']);

$stmtDomains = $pdo->prepare("
    SELECT
        d.id,
        d.domain_name,
        d.created_at,
        ud.db_name,
        ud.db_user,
        ud.db_password
    FROM domains d
    LEFT JOIN user_databases ud ON ud.domain_id = d.id
    WHERE d.user_id = ?
    ORDER BY d.id ASC
");
$stmtDomains->execute([$userId]);
$domains = $stmtDomains->fetchAll(PDO::FETCH_ASSOC);

$selectedDomainForFiles = $_GET['selected_domain'] ?? '';
if ($selectedDomainForFiles && !in_array($selectedDomainForFiles, array_column($domains, 'domain_name'), true)) {
    $selectedDomainForFiles = '';
}
if (!$selectedDomainForFiles && !empty($domains)) {
    $selectedDomainForFiles = $domains[0]['domain_name'];
}

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
                <h1>Vítejte, <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                <p class="hero-text">
                    Přehled vašeho hostingu, FTP a databází na jednom místě.
                </p>
            </section>

            <section class="dashboard-panel">
                <div class="dashboard-panel-top">
                    <div>
                        <div class="card-badge">Dashboard</div>
                        <h2>Správa hostingu</h2>
                        <p>Přehled účtu, FTP přístupů a databázových údajů.</p>
                    </div>

                    <a href="logout.php" class="secondary-btn dashboard-logout">Odhlásit se</a>
                </div>

                <?php if (isset($success)): ?>
                    <p class="success-message"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <p class="form-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($ftpPasswordPlainOnce !== null): ?>
                    <p class="success-message">
                        Nové FTP heslo bylo vygenerováno:
                        <strong><?php echo htmlspecialchars($ftpPasswordPlainOnce, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                <?php endif; ?>

                <?php if (isset($newDbInfo)): ?>
                    <p class="success-message">
                        Nová databáze:
                        <strong><?php echo htmlspecialchars($newDbInfo['db_name'], ENT_QUOTES, 'UTF-8'); ?></strong>,
                        uživatel:
                        <strong><?php echo htmlspecialchars($newDbInfo['db_user'], ENT_QUOTES, 'UTF-8'); ?></strong>,
                        heslo:
                        <strong><?php echo htmlspecialchars($newDbInfo['db_password'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                <?php endif; ?>

                <div class="service-section">
                    <div class="dashboard-panel-top dashboard-panel-top-compact">
                        <div>
                            <div class="card-badge">FTP</div>
                            <h2>FTP přístup</h2>
                            <p>Přístupy pro nahrávání souborů na web.</p>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="dashboard-item">
                            <span class="dashboard-label">FTP hostitel</span>
                            <span class="dashboard-value">127.0.0.1</span>
                            <span class="dashboard-note">Port 21</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">FTP uživatel</span>
                            <span class="dashboard-value"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dashboard-note">Přístup k vašemu účtu</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">FTP heslo</span>
                            <span class="dashboard-value value-mono">
                                <?php echo $displayedFtpPassword !== null ? htmlspecialchars($displayedFtpPassword, ENT_QUOTES, 'UTF-8') : 'Nelze zobrazit heslo'; ?>
                            </span>
                            <span class="dashboard-note">Heslo k FTP účtu</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-divider"></div>

                <div class="service-section">
                    <div class="dashboard-panel-top dashboard-panel-top-compact">
                        <div>
                            <div class="card-badge">Databáze</div>
                            <h2>Databázové přístupy</h2>
                            <p>Každá doména má vlastní databázi a vlastního DB uživatele.</p>
                        </div>

                        <a href="<?php echo htmlspecialchars($dbAdminUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="primary-link-btn">
                            Otevřít DB administraci
                        </a>
                    </div>

                    <div class="dashboard-grid">
                        <div class="dashboard-item">
                            <span class="dashboard-label">DB server</span>
                            <span class="dashboard-value">database</span>
                            <span class="dashboard-note">Host databáze v Dockeru</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">DB port</span>
                            <span class="dashboard-value">3306</span>
                            <span class="dashboard-note">Standardní MariaDB port</span>
                        </div>

                        <div class="dashboard-item">
                            <span class="dashboard-label">DB administrace</span>
                            <span class="dashboard-value">Adminer</span>
                            <span class="dashboard-note">Přes odkaz výše otevřete správu databází</span>
                        </div>

                        <div class="dashboard-item dashboard-item-wide">
                            <div class="domain-card-head">
                                <span class="dashboard-label">Domény a databáze</span>
                                <span class="domains-count"><?php echo count($domains); ?> celkem</span>
                            </div>

                            <?php if (!empty($domains)): ?>
                                <div class="db-domains-list">
                                    <?php foreach ($domains as $domain): ?>
                                        <div class="db-domain-card">
                                            <div class="db-domain-top">
                                                <div class="domain-pill">
                                                    <span class="domain-pill-dot"></span>
                                                    <span class="domain-pill-text"><?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>

                                                <form method="POST" class="domain-delete-form" onsubmit="return confirm('Opravdu chcete smazat doménu <?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?> včetně databáze?');">
                                                    <input type="hidden" name="delete_domain_id" value="<?php echo (int) $domain['id']; ?>">
                                                    <input type="hidden" name="selected_domain" value="<?php echo htmlspecialchars($selectedDomainForFiles, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="delete-domain-btn">Smazat</button>
                                                </form>
                                            </div>

                                            <div class="db-info-grid">
                                                <div class="db-info-item">
                                                    <span class="dashboard-label">Databáze</span>
                                                    <span class="dashboard-value value-mono"><?php echo htmlspecialchars($domain['db_name'] ?? 'Nevytvořena', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>

                                                <div class="db-info-item">
                                                    <span class="dashboard-label">DB uživatel</span>
                                                    <span class="dashboard-value value-mono"><?php echo htmlspecialchars($domain['db_user'] ?? 'Nevytvořen', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>

                                                <div class="db-info-item">
                                                    <span class="dashboard-label">DB heslo</span>
                                                    <span class="dashboard-value value-mono"><?php echo htmlspecialchars($domain['db_password'] ?? 'Není dostupné', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="dashboard-value dashboard-value-empty">Žádná doména nebyla nalezena</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-divider"></div>

                <div class="dashboard-item dashboard-item-wide">
                    <span class="dashboard-label">Stav hostingu</span>
                    <div class="status-row">
                        <span class="status-active">Aktivní</span>
                        <span class="dashboard-note">Služba je dostupná a připravena k použití</span>
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
                        <p>Zadáním nové domény se vytvoří i její samostatná databáze.</p>
                    </div>
                </div>

                <form method="POST" class="register-form add-domain-form">
                    <div class="form-group">
                        <label for="new_domain">Název nové domény</label>
                        <input type="text" id="new_domain" name="new_domain" placeholder="např. moje-dalsi-domena.cz" required>
                    </div>

                    <button type="submit" class="primary-btn">Přidat doménu</button>
                </form>

                <div class="dashboard-divider"></div>

                <div class="dashboard-panel-top dashboard-panel-top-compact">
                    <div>
                        <div class="card-badge">Správa souborů</div>
                        <h2>Nahrát soubory přes FTP</h2>
                        <p>Vyberte soubory nebo celou složku. Před nahráním lze jednotlivé soubory odebrat.</p>
                    </div>
                </div>

                <form id="uploadForm" method="POST" enctype="multipart/form-data" class="register-form add-domain-form">
                    <div class="form-group">
                        <label for="domain_name_upload">Doména</label>
                        <select name="domain_name" id="domain_name_upload" required>
                            <option value="">Vyberte doménu</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Výběr souborů / složek</label>
                        <div id="fileDropZone" class="file-drop-zone" style="background: 0;">
                            <button type="button" id="selectFilesBtn" class="secondary-btn">Vybrat soubory</button>
                            <button type="button" id="selectFolderBtn" class="secondary-btn">Vybrat složku</button>
                            <input type="file" id="hiddenFileInput" multiple style="display: none;">
                            <input type="file" id="hiddenFolderInput" webkitdirectory directory style="display: none;">
                            <div id="fileListContainer" class="file-list-container"></div>
                        </div>
                    </div>

                    <button type="submit" id="uploadSubmitBtn" class="primary-btn">Nahrát soubory</button>
                </form>

                <div id="uploadMessages" style="margin-top: 1rem;"></div>

                <div class="dashboard-divider"></div>

                <div class="dashboard-panel-top dashboard-panel-top-compact">
                    <div>
                        <div class="card-badge">Správa souborů</div>
                        <h2>Existující soubory</h2>
                        <p>Vyberte doménu a zobrazí se její soubory. Kliknutím na křížek soubor smažete.</p>
                    </div>
                </div>

                <div class="domain-selector">
                    <label for="domain_selector">Doména:</label>
                    <select id="domain_selector" name="domain_selector">
                        <?php foreach ($domains as $domain): ?>
                            <option value="<?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDomainForFiles === $domain['domain_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domain['domain_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="refreshFilesBtn" class="secondary-btn">Aktualizovat</button>
                </div>

                <div id="filesForDomain">
                    <p class="loading">Načítám soubory...</p>
                </div>

                <script>
                    const domainSelector = document.getElementById('domain_selector');
                    const filesContainer = document.getElementById('filesForDomain');
                    const refreshBtn = document.getElementById('refreshFilesBtn');
                    let currentDomain = <?php echo json_encode($selectedDomainForFiles); ?>;

                    if (refreshBtn) {
                        refreshBtn.addEventListener('click', function() {
                            const selectedDomain = domainSelector.value;
                            if (selectedDomain) {
                                loadFilesForDomain(selectedDomain);
                            } else {
                                filesContainer.innerHTML = '<p class="no-files">Vyberte doménu.</p>';
                            }
                        });
                    }

                    const selectFolderBtn = document.getElementById('selectFolderBtn');
                    const hiddenFolderInput = document.getElementById('hiddenFolderInput');

                    selectFolderBtn.addEventListener('click', function() {
                        hiddenFolderInput.click();
                    });

                    hiddenFolderInput.addEventListener('change', function(e) {
                        const files = Array.from(e.target.files);
                        files.forEach(file => {
                            let relativePath = file.webkitRelativePath || file.name;
                            const exists = selectedFiles.some(f => f.remotePath === relativePath && f.file.size === file.size);
                            if (!exists) {
                                selectedFiles.push({ file, remotePath: relativePath });
                            }
                        });
                        renderFileList();
                        hiddenFolderInput.value = '';
                    });

                    async function loadFilesForDomain(domain) {
                        if (!domain) {
                            filesContainer.innerHTML = '<p class="no-files">Vyberte doménu.</p>';
                            return;
                        }

                        filesContainer.innerHTML = '<p class="loading">Načítám soubory...</p>';

                        try {
                            const response = await fetch(`?action=get_files&domain=${encodeURIComponent(domain)}`);
                            const data = await response.json();

                            if (data.error) {
                                filesContainer.innerHTML = `<p class="form-error">${escapeHtml(data.error)}</p>`;
                                return;
                            }

                            const files = data.files || [];
                            if (files.length === 0) {
                                filesContainer.innerHTML = '<p class="no-files">Žádné soubory</p>';
                                return;
                            }

                            let html = '<ul class="file-list">';
                            files.forEach(file => {
                                html += `
                                    <li class="file-item">
                                        <span class="file-name">${escapeHtml(file)}</span>
                                        <form method="POST" class="delete-file-form" style="display: inline;" onsubmit="return confirm('Opravdu chcete smazat soubor ${escapeHtml(file)}?');">
                                            <input type="hidden" name="domain_name" value="${escapeAttr(domain)}">
                                            <input type="hidden" name="remote_path" value="${escapeAttr(file)}">
                                            <input type="hidden" name="selected_domain" value="${escapeAttr(domain)}">
                                            <button type="submit" name="delete_file" value="1" class="delete-file-btn" title="Smazat soubor">✖</button>
                                        </form>
                                    </li>
                                `;
                            });
                            html += '</ul>';
                            filesContainer.innerHTML = html;
                        } catch (err) {
                            filesContainer.innerHTML = `<p class="form-error">Chyba při načítání: ${escapeHtml(err.message)}</p>`;
                        }
                    }

                    function escapeHtml(str) {
                        return String(str).replace(/[&<>"']/g, function(m) {
                            return {
                                '&': '&amp;',
                                '<': '&lt;',
                                '>': '&gt;',
                                '"': '&quot;',
                                "'": '&#039;'
                            }[m];
                        });
                    }

                    function escapeAttr(str) {
                        return escapeHtml(str);
                    }

                    domainSelector.addEventListener('change', function() {
                        const newDomain = this.value;
                        currentDomain = newDomain;
                        loadFilesForDomain(newDomain);
                        const url = new URL(window.location.href);
                        url.searchParams.set('selected_domain', newDomain);
                        window.history.replaceState({}, '', url);
                    });

                    if (currentDomain) {
                        loadFilesForDomain(currentDomain);
                    } else {
                        filesContainer.innerHTML = '<p class="no-files">Vyberte doménu.</p>';
                    }

                    const selectBtn = document.getElementById('selectFilesBtn');
                    const hiddenInput = document.getElementById('hiddenFileInput');
                    const fileListContainer = document.getElementById('fileListContainer');
                    const uploadForm = document.getElementById('uploadForm');
                    const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
                    const messagesDiv = document.getElementById('uploadMessages');

                    let selectedFiles = [];

                    selectBtn.addEventListener('click', function() {
                        hiddenInput.click();
                    });

                    hiddenInput.addEventListener('change', function(e) {
                        const files = Array.from(e.target.files);
                        files.forEach(file => {
                            const remotePath = file.name;
                            const exists = selectedFiles.some(f => f.remotePath === remotePath && f.file.size === file.size);
                            if (!exists) {
                                selectedFiles.push({ file, remotePath: remotePath });
                            }
                        });
                        renderFileList();
                        hiddenInput.value = '';
                    });

                    function renderFileList() {
                        if (selectedFiles.length === 0) {
                            fileListContainer.innerHTML = '<p class="no-files">Zatím nebyly vybrány žádné soubory.</p>';
                            return;
                        }

                        let html = '<ul class="file-list">';
                        selectedFiles.forEach((item, index) => {
                            html += `
                                <li class="file-item">
                                    <span class="file-name">${escapeHtml(item.remotePath)}</span>
                                    <button type="button" class="remove-file-btn" data-index="${index}">✖</button>
                                </li>
                            `;
                        });
                        html += '</ul>';
                        fileListContainer.innerHTML = html;

                        document.querySelectorAll('.remove-file-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const idx = parseInt(this.getAttribute('data-index'), 10);
                                selectedFiles.splice(idx, 1);
                                renderFileList();
                            });
                        });
                    }

                    uploadForm.addEventListener('submit', async function(e) {
                        e.preventDefault();

                        if (selectedFiles.length === 0) {
                            showMessage('Žádné soubory k nahrání.', 'error');
                            return;
                        }

                        const domain = document.getElementById('domain_name_upload').value;
                        if (!domain) {
                            showMessage('Vyberte doménu.', 'error');
                            return;
                        }

                        const formData = new FormData();
                        formData.append('upload_file', '1');
                        formData.append('ajax', '1');
                        formData.append('domain_name', domain);

                        selectedFiles.forEach(item => {
                            formData.append('files_to_upload[]', item.file, item.file.name);
                            formData.append('file_paths[]', item.remotePath);
                        });

                        uploadSubmitBtn.disabled = true;
                        uploadSubmitBtn.textContent = 'Nahrávám...';

                        try {
                            const response = await fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();

                            if (data.error) {
                                showMessage(data.error, 'error');
                            } else if (data.success) {
                                showMessage(data.success, 'success');
                                selectedFiles = [];
                                renderFileList();
                                const currentDomainForFiles = document.getElementById('domain_selector').value;
                                loadFilesForDomain(currentDomainForFiles);
                            } else {
                                showMessage('Neznámá odpověď serveru.', 'error');
                            }
                        } catch (err) {
                            showMessage('Chyba při odesílání: ' + err.message, 'error');
                        } finally {
                            uploadSubmitBtn.disabled = false;
                            uploadSubmitBtn.textContent = 'Nahrát soubory';
                        }
                    });

                    function showMessage(msg, type) {
                        messagesDiv.innerHTML = `<p class="${type === 'error' ? 'form-error' : 'success-message'}">${escapeHtml(msg)}</p>`;
                        setTimeout(() => {
                            messagesDiv.innerHTML = '';
                        }, 5000);
                    }
                </script>
            </section>
        </main>
    </div>

    <script src="theme.js"></script>
</body>
</html>
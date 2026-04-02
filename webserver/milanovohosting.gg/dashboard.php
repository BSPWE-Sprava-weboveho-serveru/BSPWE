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

// --- Pomocná funkce pro rekurzivní získání souborů ---
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
$userRoot = __DIR__ . "/../users/" . $username;  // Původní funkční cesta

$ftpPasswordDecrypted = null;
try {
    if (!empty($user['ftp_password'])) {
        $ftpPasswordDecrypted = decryptFtpPassword($user['ftp_password']);
    }
} catch (Exception $e) {
    $ftpPasswordDecrypted = null;
}

// --- AJAX endpoint pro získání seznamu souborů pro danou doménu ---
if (isset($_GET['action']) && $_GET['action'] === 'get_files' && isset($_GET['domain'])) {
    header('Content-Type: application/json');
    $domain = trim($_GET['domain']);
    
    // Ověření, že doména patří uživateli
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

// --- Smazání domény ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_domain_id'])) {
    $deleteDomainId = (int) $_POST['delete_domain_id'];
    $selectedDomain = $_POST['selected_domain'] ?? '';

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

        // Přesměrování pro zachování vybrané domény (po smazání může být vybraná doména neplatná)
        $redirectDomain = ($selectedDomain !== $domainToDelete['domain_name']) ? $selectedDomain : '';
        header("Location: dashboard.php?selected_domain=" . urlencode($redirectDomain));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Při mazání domény došlo k chybě: " . $e->getMessage();
        // Zůstaneme na stránce s chybou
    }
}

// --- Přidání nové domény ---
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
            // Po přidání domény zůstaneme na stránce
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Při přidávání domény došlo k chybě: " . $e->getMessage();
        }
    }
}

// --- Reset FTP hesla ---
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

// --- Nahrání souborů přes FTP (více souborů, AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $ajax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $domainName = trim($_POST['domain_name']);
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

        foreach ($files['error'] as $index => $error) {
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "Soubor '{$files['name'][$index]}' – chyba nahrávání (kód $error).";
                continue;
            }

            // Získáme relativní cestu, pokud byla odeslána, jinak použijeme název souboru
            $remotePath = isset($filePaths[$index]) ? trim($filePaths[$index]) : basename($files['name'][$index]);
            
            // Bezpečnostní kontrola – nesmí obsahovat ".." a nesmí začínat lomítkem
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
                $error = implode('<br>', $errors);
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

// --- Smazání souboru přes FTP (přes křížek v seznamu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $domainName = trim($_POST['domain_name']);
    $remotePath = trim($_POST['remote_path']);
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

        // Po úspěšném odeslání requestu přesměrujeme zpět na dashboard.php s vybranou doménou
        header("Location: dashboard.php?selected_domain=" . urlencode($selectedDomain));
        exit;
    } catch (Exception $e) {
        $error = "Smazání selhalo: " . $e->getMessage();
        // Zůstaneme na stránce s chybou
    }
}

$ftpPasswordPlainOnce = $_SESSION['ftp_password_plain_once'] ?? null;
unset($_SESSION['ftp_password_plain_once']);

$stmtDomains = $pdo->prepare("SELECT id, domain_name, created_at FROM domains WHERE user_id = ? ORDER BY id ASC");
$stmtDomains->execute([$userId]);
$domains = $stmtDomains->fetchAll(PDO::FETCH_ASSOC);

// Určení aktuálně vybrané domény pro zobrazení souborů
$selectedDomainForFiles = $_GET['selected_domain'] ?? '';
if ($selectedDomainForFiles && !in_array($selectedDomainForFiles, array_column($domains, 'domain_name'))) {
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
    <style>
        .file-drop-zone {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            background: #f9f9f9;
        }
        .file-list-container {
            margin-top: 1rem;
            text-align: left;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        .file-name {
            word-break: break-all;
        }
        .remove-file-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 0.5rem;
        }
        .remove-file-btn:hover {
            color: #a71d2a;
        }
        .domain-selector {
            margin-bottom: 1rem;
        }
        .loading {
            color: #666;
            font-style: italic;
        }
        .domain-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
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
                                            <input type="hidden" name="selected_domain" value="<?php echo htmlspecialchars($selectedDomainForFiles); ?>">
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

                <div class="dashboard-divider"></div>

                <!-- Nahrání souboru (více souborů, AJAX) -->
                <div class="dashboard-panel-top dashboard-panel-top-compact">
                    <div>
                        <div class="card-badge">Správa souborů</div>
                        <h2>Nahrát soubory přes FTP</h2>
                        <p>Vyberte soubory, zobrazí se jejich seznam. Můžete jednotlivé soubory odebrat před nahráním.</p>
                    </div>
                </div>

                <form id="uploadForm" method="POST" enctype="multipart/form-data" class="register-form add-domain-form">
                    <div class="form-group">
                        <label for="domain_name_upload">Doména</label>
                        <select name="domain_name" id="domain_name_upload" required>
                            <option value="">Vyberte doménu</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain['domain_name']); ?>"><?php echo htmlspecialchars($domain['domain_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Výběr souborů / složek</label>
                        <div id="fileDropZone" class="file-drop-zone">
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

                <!-- Seznam souborů pro vybranou doménu (načítá se AJAXem) -->
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
                            <option value="<?php echo htmlspecialchars($domain['domain_name']); ?>" <?php echo $selectedDomainForFiles === $domain['domain_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domain['domain_name']); ?>
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
                            // Získáme relativní cestu (např. "slozka/podadresar/soubor.txt")
                            let relativePath = file.webkitRelativePath || file.name;
                            // Ověříme, zda už stejná cesta není v seznamu (podle cesty a velikosti)
                            const exists = selectedFiles.some(f => f.remotePath === relativePath && f.file.size === file.size);
                            if (!exists) {
                                selectedFiles.push({ file, remotePath: relativePath });
                            }
                        });
                        renderFileList();
                        hiddenFolderInput.value = '';
                    });    

                    // Funkce pro načtení souborů pro danou doménu
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
                                            <input type="hidden" name="domain_name" value="${escapeHtml(domain)}">
                                            <input type="hidden" name="remote_path" value="${escapeHtml(file)}">
                                            <input type="hidden" name="selected_domain" value="${escapeHtml(domain)}">
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
                        return str.replace(/[&<>]/g, function(m) {
                            if (m === '&') return '&amp;';
                            if (m === '<') return '&lt;';
                            if (m === '>') return '&gt;';
                            return m;
                        });
                    }
                    
                    // Při změně domény načteme soubory a aktualizujeme URL
                    domainSelector.addEventListener('change', function() {
                        const newDomain = this.value;
                        currentDomain = newDomain;
                        loadFilesForDomain(newDomain);
                        const url = new URL(window.location.href);
                        url.searchParams.set('selected_domain', newDomain);
                        window.history.replaceState({}, '', url);
                    });
                    
                    // Při prvním načtení stránky načteme soubory pro výchozí doménu
                    if (currentDomain) {
                        loadFilesForDomain(currentDomain);
                    } else {
                        filesContainer.innerHTML = '<p class="no-files">Vyberte doménu.</p>';
                    }
                    
                    // ------------------------------------------------------------
                    // Kód pro nahrávání souborů (AJAX)
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
                                // Po nahrání obnovíme seznam souborů pro aktuální doménu
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
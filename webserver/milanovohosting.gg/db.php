<?php
// Parametry pro připojení (shodují se s docker-compose.yaml)
$host = 'database';      // Název služby v Dockeru
$user = 'root';          // Výchozí uživatel
$pass = 'maria';         // Heslo, které je v docker-compose
$db   = 'hosting_centrum'; // Název datáze (tuhle se pak vytvoří v Admineru)
$charset = 'utf8mb4';    // Podpora pro českou diakritiku

// DSN (Data Source Name) - takový ?"�t�tek" pro ovladač
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Nastavení, jak se má PHP chovat při chybách
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hlásit chyby jako vyjímky
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Výsledky z DB vracet jako pole
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ?Zv��en� bezpečnosti
];

try {
    // Pokus o vytvoření spojení
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Pokud se to nepovede, vypiš chybu
    die("Nepodařilo se připojit k databázi: " . $e->getMessage());
}
?>
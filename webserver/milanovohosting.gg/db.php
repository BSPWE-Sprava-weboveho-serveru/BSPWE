<?php
// Parametry pro pøipojení (shodují se s docker-compose.yaml)
$host = 'database';      // Název služby v Dockeru
$user = 'root';          // Výchozí uživatel
$pass = 'maria';         // Heslo, které je v docker-compose
$db   = 'hosting_centrum'; // Název databáze (tuhle se pak vytvoøí v Admineru)
$charset = 'utf8mb4';    // Podpora pro èeskou diakritiku

// DSN (Data Source Name) - takový "štítek" pro ovladaè
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Nastavení, jak se má PHP chovat pøi chybách
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hlásit chyby jako výjimky
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Výsledky z DB vracet jako pole
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Zvýšení bezpeènosti
];

try {
    // Pokus o vytvoøení spojení
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Pokud se to nepovede, vypíše to chybu
    die("Nepodaøilo se pøipojit k databázi: " . $e->getMessage());
}
?>
<?php
$host = 'database';
$user = 'root';
$pass = 'maria';
$db   = 'mysql';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "<p style='color: green;'>Database Connection Successful!</p>";
} catch (PDOException $e) {
    echo "<h1>Connection Failed</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
<?php
// db.php - Database connection for drvmeditation.com
$db_host = 'localhost';
$db_name = 'drv_healing_db';
$db_user = 'drv_user';
$db_pass = 'DrV_Healing_2026_SecureDb!';

function getDb() {
    global $db_host, $db_name, $db_user, $db_pass;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

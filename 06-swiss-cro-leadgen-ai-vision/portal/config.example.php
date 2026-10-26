<?php
// Configuration Template for Swiss CRO Audit & Lead Portal
session_start();

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'swiss_leads';
$db_user = getenv('DB_USER') ?: 'lead_user';
$db_pass = getenv('DB_PASS') ?: 'YOUR_SECURE_DB_PASSWORD';

// Portal Access Password
define('PORTAL_PASSWORD', getenv('PORTAL_ACCESS_KEY') ?: 'SecureAccess2026!');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

function check_auth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: login.php');
        exit;
    }
}

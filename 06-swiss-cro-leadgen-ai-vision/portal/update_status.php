<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['new', 'reviewed', 'contacted'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

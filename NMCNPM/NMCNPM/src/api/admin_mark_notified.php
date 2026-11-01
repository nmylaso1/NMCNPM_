<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Cho phép Admin và Nhân viên
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

session_write_close();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$all = isset($_GET['all']) ? (int)$_GET['all'] : 0;

if ($all === 1) {
    $query = "UPDATE orders SET is_notified = 1 WHERE is_notified = 0";
} elseif ($id > 0) {
    $query = "UPDATE orders SET is_notified = 1 WHERE id = $id";
} else {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

if ($conn->query($query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB Error']);
}
?>

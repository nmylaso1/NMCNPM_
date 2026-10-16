<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$note     = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đơn hàng không hợp lệ.']);
    exit;
}

$stmt = $conn->prepare("UPDATE orders SET note = ? WHERE id = ?");
$stmt->bind_param("si", $note, $order_id);

if ($stmt->execute()) {
    $ord = $conn->query("SELECT order_code FROM orders WHERE id = $order_id")->fetch_assoc();
    $code = $ord['order_code'] ?? "#$order_id";
    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'order_note', $code, "Cập nhật ghi chú cho đơn $code: $note");
    echo json_encode(['success' => true, 'message' => 'Cập nhật ghi chú thành công.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật ghi chú.']);
}
?>

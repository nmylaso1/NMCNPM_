<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Cho phép Admin và Nhân viên xem
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đơn hàng không hợp lệ.']);
    exit;
}

$query = "SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = $order_id";
$res = $conn->query($query);

$items = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    echo json_encode(['success' => true, 'items' => $items]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy vật phẩm nào.']);
}
?>

<?php
session_start();
require_once '../includes/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đơn hàng không hợp lệ.']);
    exit;
}

// Lấy thông tin đơn hàng (đảm bảo đơn hàng thuộc về user này)
$order_stmt = $conn->prepare("SELECT id, order_code, total_amount, status, payment_method, note, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_res = $order_stmt->get_result();

if ($order_res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập.']);
    exit;
}

$order = $order_res->fetch_assoc();

// Lấy danh sách món ăn
$items_stmt = $conn->prepare("SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_res = $items_stmt->get_result();

$items = [];
while ($row = $items_res->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
?>

<?php
session_start();
header('Content-Type: application/json');

// Admin và nhân viên được phép
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';



$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$action   = $_POST['action'] ?? ''; // 'confirm' hoặc 'reject'

if (!$order_id || !in_array($action, ['confirm', 'reject', 'cooking', 'ready'])) {
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ']);
    exit;
}

// Lấy thông tin đơn hàng
$order = $conn->query("SELECT * FROM orders WHERE id = $order_id")->fetch_assoc();
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
    exit;
}
if (in_array($order['status'], ['paid', 'rejected', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng đã được xử lý hoặc hủy bỏ rồi']);
    exit;
}

if ($action === 'cooking') {
    $conn->query("UPDATE orders SET status='cooking' WHERE id=$order_id");
    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'order_cooking', $order['order_code'], "Chuyển đơn {$order['order_code']} sang Đang nấu");
    echo json_encode([
        'success' => true,
        'message' => 'Đã chuyển đơn ' . $order['order_code'] . ' sang Đang nấu',
        'new_status' => 'cooking'
    ]);
} elseif ($action === 'ready') {
    $conn->query("UPDATE orders SET status='ready' WHERE id=$order_id");
    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'order_ready', $order['order_code'], "Báo đơn {$order['order_code']} đã chế biến xong");
    echo json_encode([
        'success' => true,
        'message' => 'Đã báo xong đơn ' . $order['order_code'],
        'new_status' => 'ready'
    ]);
} elseif ($action === 'confirm') {
    $conn->query("UPDATE orders SET status='paid', confirmed_at=NOW() WHERE id=$order_id");
    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'order_paid', $order['order_code'], "Hoàn tất & xác nhận thanh toán đơn {$order['order_code']} (" . number_format($order['total_amount'], 0, ',', '.') . "đ)");

    // Increment sales_count for each product in this order
    $itemsRes = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
    if ($itemsRes) {
        while ($item = $itemsRes->fetch_assoc()) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $conn->query("UPDATE products SET sales_count = sales_count + $qty WHERE id = $pid");
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đã hoàn tất đơn ' . $order['order_code'],
        'new_status' => 'paid'
    ]);

} else {
    // Reject
    $conn->query("UPDATE orders SET status='rejected', confirmed_at=NOW() WHERE id=$order_id");
    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'order_reject', $order['order_code'], "Từ chối / Hủy đơn hàng {$order['order_code']}");

    echo json_encode([
        'success' => true,
        'message' => 'Đã từ chối đơn ' . $order['order_code'],
        'new_status' => 'rejected'
    ]);
}
exit;
?>

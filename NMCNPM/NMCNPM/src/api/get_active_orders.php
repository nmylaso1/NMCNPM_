<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$user_id = (int)$_SESSION['user_id'];

// Thêm cái này để lúc mình poll (8s - 25s một lần) nó không khóa session của mấy trang chính
session_write_close();

// Lấy các đơn hàng đang ở trạng thái pending, cooking, ready
// Thêm cả paid/rejected trong 45 giây gần nhất để bên Widget kịp bắt được trạng thái cuối
$sql = "SELECT id, order_code, status 
        FROM orders 
        WHERE user_id = $user_id 
          AND (status IN ('pending', 'cooking', 'ready') 
               OR (status IN ('paid', 'rejected') AND confirmed_at >= NOW() - INTERVAL 45 SECOND))
        ORDER BY created_at ASC";

if (!$conn) {
    echo json_encode(['success' => true, 'orders' => []]);
    exit;
}

$result = $conn->query($sql);
$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'orders' => $orders
]);
?>

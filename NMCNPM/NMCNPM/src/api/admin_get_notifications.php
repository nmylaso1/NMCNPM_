<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Cho phép Admin và Nhân viên
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Đóng khóa session sớm để không chặn các yêu cầu chuyển trang của người dùng
session_write_close();

// Lấy tất cả đơn hàng chưa được thông báo cho Admin
$query = "
    SELECT o.id, o.order_code, o.total_amount, a.full_name, o.created_at
    FROM orders o
    JOIN accounts a ON o.user_id = a.id
    WHERE o.is_notified = 0
    ORDER BY o.created_at DESC
    LIMIT 10
";

if (!$conn) {
    echo json_encode(['success' => true, 'notifications' => []]);
    exit;
}

$res = $conn->query($query);
$notifications = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
    // Thêm khoảng thời gian cách đây bao lâu (Đơn giản hóa)
    $createdAt = strtotime($row['created_at']);
    $diff = time() - $createdAt;
    
    if ($diff < 60) {
        $row['time_ago'] = 'Vừa xong';
    } elseif ($diff < 3600) {
        $row['time_ago'] = floor($diff / 60) . ' phút trước';
    } else {
        $row['time_ago'] = date('H:i', $createdAt);
    }
    
    $notifications[] = $row;
    }
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>

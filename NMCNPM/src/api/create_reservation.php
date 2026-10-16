<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$customer_name = trim($_POST['customer_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$reservation_date = trim($_POST['reservation_date'] ?? '');
$reservation_time = trim($_POST['reservation_time'] ?? '');
$guest_count = max(1, (int)($_POST['guest_count'] ?? 2));
$seating_area = trim($_POST['seating_area'] ?? 'standard');
$note = trim($_POST['note'] ?? '');

// Validation
if (empty($customer_name) || empty($phone) || empty($reservation_date) || empty($reservation_time)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ họ tên, số điện thoại, ngày và giờ đặt bàn!']);
    exit;
}

// Kiểm tra ngày không được ở quá khứ
$resDateTimeStr = $reservation_date . ' ' . $reservation_time;
if (strtotime($resDateTimeStr) < (time() - 300)) {
    echo json_encode(['success' => false, 'message' => 'Thời gian đặt bàn không thể ở quá khứ!']);
    exit;
}

// Sinh mã đặt bàn duy nhất
$reservation_code = 'RES' . strtoupper(substr(uniqid(), -6));

$stmt = $conn->prepare("INSERT INTO reservations (reservation_code, user_id, customer_name, phone, email, reservation_date, reservation_time, guest_count, seating_area, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param('sisssssiss', $reservation_code, $user_id, $customer_name, $phone, $email, $reservation_date, $reservation_time, $guest_count, $seating_area, $note);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Đặt bàn thành công! Nhà hàng sẽ liên hệ xác nhận trong ít phút.',
        'reservation_code' => $reservation_code
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể lưu đặt bàn: ' . $conn->error
    ]);
}
$stmt->close();

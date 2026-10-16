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

$table_number = trim($_POST['table_number'] ?? ($_SESSION['table_number'] ?? ''));
$request_type = trim($_POST['request_type'] ?? 'staff');
$note = trim($_POST['note'] ?? '');

if (empty($table_number)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy số bàn của bạn! Vui lòng quét lại mã QR tại bàn.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO service_requests (table_number, request_type, note, status) VALUES (?, ?, ?, 'pending')");
$stmt->bind_param('sss', $table_number, $request_type, $note);

if ($stmt->execute()) {
    $msgMap = [
        'staff' => 'Đã gửi yêu cầu nhân viên hỗ trợ đến ' . $table_number . '. Nhân viên sẽ có mặt ngay!',
        'water' => 'Đã gửi yêu cầu thêm nước lọc / đá cho ' . $table_number . '!',
        'cutlery' => 'Đã gửi yêu cầu thêm dụng cụ / khăn cho ' . $table_number . '!',
        'bill' => 'Đã gửi yêu cầu in hóa đơn / thanh toán cho ' . $table_number . '. Nhân viên sẽ mang bill ra ngay!'
    ];
    $msg = $msgMap[$request_type] ?? ('Đã ghi nhận yêu cầu từ ' . $table_number . '!');
    
    echo json_encode([
        'success' => true,
        'message' => $msg
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể gửi yêu cầu: ' . $conn->error
    ]);
}
$stmt->close();

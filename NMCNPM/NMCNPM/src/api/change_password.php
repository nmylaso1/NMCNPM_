<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập!']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['user_id'];
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đủ các trường!']);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!']);
        exit();
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải từ 6 ký tự trở lên!']);
        exit();
    }

    // Lấy mật khẩu cũ từ DB
    $stmt = $conn->prepare("SELECT password FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if ($user['password'] === $oldPassword) {
            // Mật khẩu cũ khớp -> Tiến hành cập nhật
            $updateStmt = $conn->prepare("UPDATE accounts SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newPassword, $userId);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi cập nhật CSDL!']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không chính xác!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản!']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
?>

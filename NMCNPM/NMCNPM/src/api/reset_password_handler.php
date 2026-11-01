<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$email = $_POST['email'];
$new_password = $_POST['new_password']; // Lưu thô theo CSDL hiện tại (không hash để login.php đọc được)
$code = $_POST['code'];

// 1. Check OTP và thời gian
if (!isset($_SESSION['code_time']) || (time() - $_SESSION['code_time']) > 300) {
    echo "Mã xác thực đã hết hạn (quá 5 phút). Hãy lấy lại mã!";
    exit;
}
if ($code != $_SESSION['code']) {
    echo "Sai mã xác thực!";
    exit;
}

// 2. Cập nhật vào MySQL
$sql = "UPDATE accounts SET password = ? WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $new_password, $email);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo "Đổi mật khẩu thành công!";
        unset($_SESSION['code']);
        unset($_SESSION['code_time']);
    } else {
        // Cập nhật không có gì hoặc email không tồn tại
        // Đôi khi affected_rows == 0 nếu password mới y hệt password cũ.
        echo "Email không tồn tại trong hệ thống (hoặc pass mới bị trùng pass cũ)!";
    }
} else {
    echo "Lỗi hệ thống: " . mysqli_error($conn);
}
?>

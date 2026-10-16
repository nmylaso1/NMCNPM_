<?php
session_start();
include "../includes/config.php";

$username = $_POST['username']; // Lưu thành full_name
$email = $_POST['email'];
$password = $_POST['password']; // Lưu mật khẩu thô theo CSDL hiện tại (không hash để login.php đọc được)
$mssv = $_POST['mssv'] ?? '';
$is_student = !empty($mssv) ? 1 : 0;
$role = $is_student ? 'user' : 'guest';
$code = $_POST['code'];

// 1. Check OTP (Mã và 5 phút)
if (!isset($_SESSION['code_time']) || (time() - $_SESSION['code_time']) > 300) {
    echo "Mã OTP đã hết hạn (quá 5 phút). Hãy lấy lại mã!";
    exit;
}
if ($code != $_SESSION['code']) {
    echo "Sai mã xác thực!";
    exit;
}

// 2. Check trùng email (Dùng mysqli)
$sql_check = "SELECT id FROM accounts WHERE email = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "s", $email);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result) > 0) {
    echo "Email đã tồn tại!";
    exit;
}

// 3. Insert vào MySQL
$sql = "INSERT INTO accounts (full_name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $password, $role);

if (mysqli_stmt_execute($stmt)) {
    echo "Đăng ký thành công!";
    unset($_SESSION['code']); // Xóa code sau khi dùng xong
}
else {
    echo "Lỗi: " . mysqli_error($conn);
}
?>

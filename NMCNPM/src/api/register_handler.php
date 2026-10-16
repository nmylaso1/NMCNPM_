<?php
session_start();
include "../includes/config.php";

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = 'user';
$code = trim($_POST['code'] ?? '');

// 1. Check OTP (Mã và 5 phút)
if (!isset($_SESSION['code_time']) || (time() - $_SESSION['code_time']) > 300) {
    echo "Mã OTP đã hết hạn (quá 5 phút). Hãy lấy lại mã!";
    exit;
}
if ($code != ($_SESSION['code'] ?? '')) {
    echo "Sai mã xác thực!";
    exit;
}

// 2. Check trùng email hoặc username (Dùng mysqli)
$sql_check = "SELECT id FROM accounts WHERE email = ? OR username = ?";
$stmt_check = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt_check, "ss", $email, $username);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result) > 0) {
    echo "Email hoặc Tên đăng nhập đã tồn tại!";
    exit;
}

// 3. Insert vào MySQL
$sql = "INSERT INTO accounts (username, full_name, email, password, role) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $username, $username, $email, $password, $role);

if (mysqli_stmt_execute($stmt)) {
    echo "Đăng ký thành công!";
    unset($_SESSION['code']); // Xóa code sau khi dùng xong
}
else {
    echo "Lỗi: " . mysqli_error($conn);
}
?>

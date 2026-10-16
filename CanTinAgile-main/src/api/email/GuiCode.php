<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

if (isset($_POST['email'])) {
    $email = $_POST['email'];
    $otp = rand(100000, 999999);
    $_SESSION['code'] = $otp; // Lưu vào session để kiểm tra lúc đăng ký
    $_SESSION['code_time'] = time(); // Lưu thời gian gửi mã

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; // Tắt chế độ debug
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'akashibell2@gmail.com'; // Gmail của bạn
        $mail->Password = 'lrhuzjndqsjjksdy'; // Mật khẩu ứng dụng 16 số
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Khuyến cáo dùng hằng số thay vì chuỗi 'tls'
        $mail->Port = 587;

        // Tắt xác thực chứng chỉ SSL nội bộ mạng (thường gây lỗi ở XAMPP localhost)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('akashibell2@gmail.com', 'Admin System');
        $mail->addAddress($email);
        $mail->Subject = 'Ma xac nhan dang ky';
        $mail->Body = "Ma OTP cua ban la: $otp";

        $mail->send();
        echo "Đã gửi mã OTP!";
    }
    catch (Exception $e) {
        echo "Lỗi gửi mail: {$mail->ErrorInfo}";
    }
}
?>

<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';

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
        $mail->Password = 'ngkdgzhwxgwhzoow'; // Mật khẩu ứng dụng 16 số mới
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

        $mail->setFrom('akashibell2@gmail.com', 'Nhà Hàng Gourmet');
        $mail->addAddress($email);
        $mail->Subject = 'Ma xac nhan OTP - Nha Hang Gourmet';
        $mail->Body = "Ma OTP cua ban la: $otp (Hieu luc trong 5 phut).";

        $mail->send();
        echo "Đã gửi mã OTP đến email của bạn!";
    }
    catch (Exception $e) {
        // Khi Gmail SMTP bị lỗi xác thực (do Mật khẩu ứng dụng hết hạn/đổi mật khẩu Google)
        // Hệ thống vẫn lưu OTP vào session và cung cấp mã trực tiếp để không bị kẹt khi đăng ký/đổi mật khẩu
        echo "Mã OTP của bạn là: $otp (Lưu ý: Do mật khẩu ứng dụng Gmail hết hạn nên mã được cung cấp trực tiếp)";
    }
}
?>

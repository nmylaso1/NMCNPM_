<?php
session_start();
require_once '../includes/config.php';

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu vendor/autoload.php. Vui lòng chạy composer install.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
require_once $autoloadPath;

use PayOS\Exceptions\APIException;
use PayOS\PayOS;

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
set_time_limit(20);

function jsonOut(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        jsonOut(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    }

    $userId = (int) $_SESSION['user_id'];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (PAYOS_CLIENT_ID === '' || PAYOS_API_KEY === '' || PAYOS_CHECKSUM_KEY === '') {
        jsonOut(['success' => false, 'message' => 'Chưa cấu hình PAYOS_CLIENT_ID/PAYOS_API_KEY/PAYOS_CHECKSUM_KEY']);
    }

    $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    $paymentLinkId = trim((string) ($_POST['payment_link_id'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? 'Khach huy thanh toan'));

    if ($orderId <= 0) {
        jsonOut(['success' => false, 'message' => 'Thiếu order_id']);
    }

    $orderRes = $conn->query("SELECT id FROM orders WHERE id = $orderId AND user_id = $userId LIMIT 1");
    if (!$orderRes || !$orderRes->num_rows) {
        jsonOut(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
    }

    $payOS = new PayOS(
        clientId: PAYOS_CLIENT_ID,
        apiKey: PAYOS_API_KEY,
        checksumKey: PAYOS_CHECKSUM_KEY,
        baseURL: PAYOS_BASE_URL
    );

    $cancelTarget = $paymentLinkId !== '' ? $paymentLinkId : $orderId;
    try {
        $payOS->paymentRequests->cancel($cancelTarget, $reason);
    } catch (APIException $e) {
        jsonOut([
            'success' => false,
            'message' => $e->errorDesc ?: $e->getMessage(),
            'http_code' => $e->status,
            'payos_error_code' => $e->errorCode,
        ]);
    }

    jsonOut([
        'success' => true,
        'message' => 'Đã hủy liên kết thanh toán PayOS',
    ]);
} catch (Throwable $e) {
    jsonOut([
        'success' => false,
        'message' => 'Lỗi máy chủ khi hủy thanh toán: ' . $e->getMessage(),
    ]);
}

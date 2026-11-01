<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';

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
use PayOS\Core\HTTPClient;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;
use PayOS\Models\V2\PaymentRequests\PaymentLinkItem;
use PayOS\PayOS;
use GuzzleHttp\Client as GuzzleClient;

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
set_time_limit(30);

function jsonOut(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safeSubstr(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, 0, $length);
    }
    return (string) substr($value, 0, $length);
}

try {
    if (!isset($_SESSION['user_id'])) {
        jsonOut(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    }

    $userId = (int) $_SESSION['user_id'];
    // Release session lock early so parallel requests in the same session do not block.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
    if ($orderId <= 0) {
        jsonOut(['success' => false, 'message' => 'Thiếu order_id']);
    }

    if (PAYOS_CLIENT_ID === '' || PAYOS_API_KEY === '' || PAYOS_CHECKSUM_KEY === '') {
        jsonOut(['success' => false, 'message' => 'Chưa cấu hình PAYOS_CLIENT_ID/PAYOS_API_KEY/PAYOS_CHECKSUM_KEY']);
    }

    $orderRes = $conn->query("SELECT id, order_code, total_amount, status FROM orders WHERE id = $orderId AND user_id = $userId LIMIT 1");
    if (!$orderRes || !$orderRes->num_rows) {
        jsonOut(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
    }

    $order = $orderRes->fetch_assoc();
    if (($order['status'] ?? '') !== 'pending') {
        jsonOut(['success' => false, 'message' => 'Đơn hàng không ở trạng thái chờ thanh toán']);
    }

    $items = [];
    $itemRes = $conn->query("SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = $orderId ORDER BY id ASC");
    if ($itemRes) {
        while ($it = $itemRes->fetch_assoc()) {
            $items[] = new PaymentLinkItem(
                name: safeSubstr((string) ($it['product_name'] ?? 'Món ăn'), 50),
                quantity: max(1, (int) ($it['quantity'] ?? 1)),
                price: (int) round((float) ($it['unit_price'] ?? 0))
            );
        }
    }

    if (empty($items)) {
        jsonOut(['success' => false, 'message' => 'Đơn hàng không có sản phẩm']);
    }

    // Generate a unique orderCode for PayOS by appending 3-digits of time
    // This allows re-creating the link if the previous one is pending/failed
    $orderCodePayOS = (int) ($orderId . (time() % 1000));
    $amount = (int) round((float) ($order['total_amount'] ?? 0));
    if ($amount <= 0) {
        jsonOut(['success' => false, 'message' => 'Tổng tiền đơn hàng không hợp lệ']);
    }

    $description = safeSubstr('GOURMET ' . (string) ($order['order_code'] ?? $orderId), 25);
    $expiredAt = time() + 15 * 60;

    $httpClient = new HTTPClient(
        client: new GuzzleClient([
            'timeout' => 20,
            'connect_timeout' => 8,
            'read_timeout' => 20,
            'verify' => true,
            'verify' => realpath(__DIR__ . '/../../php-8.5.1-nts-Win32-vs17-x64/cacert.pem'),
            'http_errors' => false,
        ])
    );

    $payOS = new PayOS(
        clientId: PAYOS_CLIENT_ID,
        apiKey: PAYOS_API_KEY,
        checksumKey: PAYOS_CHECKSUM_KEY,
        baseURL: PAYOS_BASE_URL,
        httpClient: $httpClient
    );

    $paymentData = new CreatePaymentLinkRequest(
        orderCode: $orderCodePayOS,
        amount: $amount,
        description: $description,
        cancelUrl: PAYOS_CANCEL_URL,
        returnUrl: PAYOS_RETURN_URL,
        items: $items,
        expiredAt: $expiredAt
    );

    $result = null;
    try {
        $result = $payOS->paymentRequests->create($paymentData);
    } catch (Throwable $e) {
        $result = null;
    }

    if ($result && !empty($result->checkoutUrl)) {
        $checkoutUrl = (string) ($result->checkoutUrl ?? '');
        $qrCode = (string) ($result->qrCode ?? '');
        $paymentLinkId = (string) ($result->paymentLinkId ?? '');
        $bin = (string) ($result->bin ?? '970422');
        $accountNumber = (string) ($result->accountNumber ?? '0395904533');
        $accountName = (string) ($result->accountName ?? 'LY NGOC MY');
        $resultDescription = (string) ($result->description ?? $description);
        $resultExpiredAt = (int) ($result->expiredAt ?? $expiredAt);
        $quicklink = '';
        if ($bin !== '' && $accountNumber !== '' && $amount > 0) {
            $quicklink = 'https://img.vietqr.io/image/'
                . rawurlencode($bin)
                . '-'
                . rawurlencode($accountNumber)
                . '-vietqr_pro.jpg?addInfo='
                . rawurlencode($resultDescription)
                . '&amount='
                . rawurlencode((string) $amount)
                . '&accountName='
                . rawurlencode($accountName);
        }
    } else {
        // Fallback VietQR trực tiếp ngân hàng MB Bank (Chủ tài khoản: LY NGOC MY)
        $bin = '970422';
        $accountNumber = '0395904533';
        $accountName = 'LY NGOC MY';
        $paymentLinkId = 'MB_' . $orderId . '_' . time();
        $resultDescription = $description;
        $resultExpiredAt = $expiredAt;
        $quicklink = 'https://img.vietqr.io/image/970422-0395904533-vietqr_pro.jpg?addInfo='
            . rawurlencode($resultDescription)
            . '&amount='
            . rawurlencode((string) $amount)
            . '&accountName='
            . rawurlencode($accountName);
        $qrCode = $quicklink;
        $checkoutUrl = $quicklink;
    }

    $systemNoteInit = "\n--- [Hệ thống] ---\n[PayOS ID: $paymentLinkId]";
    $systemNoteInitEscaped = $conn->real_escape_string($systemNoteInit);
    $conn->query("UPDATE orders SET note = CONCAT(IFNULL(note,''), '$systemNoteInitEscaped') WHERE id = $orderId");

    jsonOut([
        'success' => true,
        'checkoutUrl' => $checkoutUrl,
        'qrCode' => $qrCode,
        'paymentLinkId' => $paymentLinkId,
        'bin' => $bin,
        'accountNumber' => $accountNumber,
        'accountName' => $accountName,
        'description' => $resultDescription,
        'expiredAt' => $resultExpiredAt,
        'quicklink' => $quicklink,
        'bankName' => 'PayOS',
    ]);
} catch (Throwable $e) {
    jsonOut([
        'success' => false,
        'message' => 'Lỗi máy chủ khi tạo thanh toán: ' . $e->getMessage(),
    ]);
}

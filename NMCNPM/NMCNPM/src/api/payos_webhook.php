<?php
/**
 * PayOS Webhook Handler
 * Endpoint nhận callback từ PayOS khi thanh toán thành công
 * Docs: https://payos.vn/docs/api/
 */

require_once __DIR__ . '/../includes/config.php';

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu vendor/autoload.php. Vui lòng chạy composer install.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
require_once $autoloadPath;

use PayOS\PayOS;

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
set_time_limit(8);

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Health check: GET requests
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respond(200, ['success' => true, 'message' => 'Webhook endpoint is alive']);
    }

    // Validate config
    if (!defined('PAYOS_CLIENT_ID') || !defined('PAYOS_API_KEY') || !defined('PAYOS_CHECKSUM_KEY')) {
        respond(500, ['success' => false, 'message' => 'Server config error']);
    }

    if (trim((string) PAYOS_CLIENT_ID) === '' || trim((string) PAYOS_API_KEY) === '' || trim((string) PAYOS_CHECKSUM_KEY) === '') {
        respond(500, ['success' => false, 'message' => 'Server config error']);
    }

    // Read raw body
    $rawBody = (string) file_get_contents('php://input');

    if (trim($rawBody) === '') {
        respond(200, ['success' => true, 'message' => 'Empty body (health check)']);
    }

    // Decode JSON
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        respond(400, ['success' => false, 'message' => 'Invalid JSON']);
    }

    // Verify webhook signature using PayOS SDK
    try {
        $payOS = new PayOS(
            clientId: (string) PAYOS_CLIENT_ID,
            apiKey: (string) PAYOS_API_KEY,
            checksumKey: (string) PAYOS_CHECKSUM_KEY,
            baseURL: (string) PAYOS_BASE_URL
        );

        $verified = $payOS->webhooks->verify($payload, ['asArray' => true]);
    } catch (Throwable $e) {
        respond(400, ['success' => false, 'message' => 'Invalid signature']);
    }

    // Extract order info (payload thực tế thường chỉ có code + amount, không có status/amountPaid)
    // Real orderId is the original code minus the 3-digit salt
    $rawOrderCode = (int) ($verified['orderCode'] ?? 0);
    $orderCode = (int) substr((string) $rawOrderCode, 0, -3);
    $amount = (int) round((float) ($verified['amount'] ?? 0));
    $amountPaid = (int) round((float) ($verified['amountPaid'] ?? 0));
    $status = strtoupper((string) ($verified['status'] ?? ''));
    $paymentLinkId = trim((string) ($verified['paymentLinkId'] ?? ''));
    $reference = trim((string) ($verified['reference'] ?? ''));
    $webhookCode = strtoupper((string) ($verified['code'] ?? ($payload['code'] ?? '')));
    $webhookSuccess = array_key_exists('success', $payload) ? (bool) $payload['success'] : null;

    // Validate order data
    if ($orderCode <= 0) {
        respond(200, ['success' => true, 'message' => 'No orderCode to process']);
    }

    if ($webhookCode !== '00') {
        respond(200, ['success' => true, 'message' => 'Transaction not successful']);
    }

    if ($webhookSuccess !== null && $webhookSuccess !== true) {
        respond(200, ['success' => true, 'message' => 'Transaction not successful']);
    }

    // Chỉ kiểm tra status khi payload có field này
    if ($status !== '' && $status !== 'PAID') {
        respond(200, ['success' => true, 'message' => 'Status is not PAID']);
    }

    // Lookup order in DB
    $orderRes = $conn->query("
        SELECT id, user_id, status, total_amount 
        FROM orders 
        WHERE id = " . (int) $orderCode . " 
        LIMIT 1
    ");

    if (!$orderRes || $orderRes->num_rows === 0) {
        respond(200, ['success' => true, 'message' => 'Order not found']);
    }

    $order = $orderRes->fetch_assoc();

    // Validate amount theo dữ liệu webhook + số tiền đơn nội bộ
    $requiredAmount = (int) round((float) ($order['total_amount'] ?? 0));
    $effectivePaidAmount = $amountPaid > 0 ? $amountPaid : $amount;
    if ($effectivePaidAmount <= 0) {
        respond(200, ['success' => true, 'message' => 'Missing paid amount']);
    }
    if ($requiredAmount > 0 && $effectivePaidAmount < $requiredAmount) {
        respond(200, ['success' => true, 'message' => 'Amount mismatch']);
    }

    // Skip if already paid
    if (($order['status'] ?? '') === 'paid') {
        respond(200, ['success' => true, 'message' => 'Order already paid']);
    }

    // Build webhook note
    $note = '[PAYOS_WEBHOOK_PAID]';
    if ($paymentLinkId !== '') {
        $note .= ' linkId=' . $paymentLinkId;
    }
    if ($reference !== '') {
        $note .= ' ref=' . $reference;
    }
    $note .= ' amount=' . $effectivePaidAmount;

    $noteEscaped = $conn->real_escape_string($note);

    // Update DB to paid
    $updateResult = $conn->query("
        UPDATE orders 
        SET status = 'paid', 
            confirmed_at = NOW(), 
            note = CONCAT(IFNULL(note, ''), IF(IFNULL(note, '') = '', '', '\n'), '" . $noteEscaped . "')
        WHERE id = " . (int) $orderCode . " 
        AND status = 'pending'
    ");

    if ($conn->affected_rows > 0) {
        // Cộng lượt đã bán cho từng sản phẩm trong đơn hàng
        $oid = (int) $orderCode;
        $itemsRes = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $oid");
        if ($itemsRes) {
            while ($item = $itemsRes->fetch_assoc()) {
                $pid = (int) $item['product_id'];
                $qty = (int) $item['quantity'];
                $conn->query("UPDATE products SET sales_count = sales_count + $qty WHERE id = $pid");
            }
        }
        respond(200, ['success' => true, 'message' => 'Order marked paid']);
    } else {
        respond(200, ['success' => true, 'message' => 'Already processed or not pending']);
    }

} catch (Throwable $e) {
    respond(500, ['success' => false, 'message' => 'Server error']);
}

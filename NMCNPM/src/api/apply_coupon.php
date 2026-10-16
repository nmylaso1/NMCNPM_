<?php
session_start();
require_once '../includes/config.php';
header('Content-Type: application/json');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để sử dụng mã giảm giá']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0;

    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá']);
        exit;
    }

    // 1. Find coupon
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn']);
        exit;
    }

    // 2. Check dates
    $today = date('Y-m-d');
    if ($coupon['start_date'] && $coupon['start_date'] > $today) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá này chưa đến ngày sử dụng']);
        exit;
    }
    if ($coupon['end_date'] && $coupon['end_date'] < $today) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết hạn']);
        exit;
    }

    // 3. Check total usage limit
    if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng']);
        exit;
    }

    // 4. Check user usage limit
    if ($coupon['user_limit'] > 0) {
        $stmt_user = $conn->prepare("SELECT COUNT(*) as usage_count FROM orders WHERE user_id = ? AND coupon_id = ? AND status != 'cancelled'");
        $stmt_user->bind_param("ii", $user_id, $coupon['id']);
        $stmt_user->execute();
        $res_user = $stmt_user->get_result()->fetch_assoc();
        if ($res_user['usage_count'] >= $coupon['user_limit']) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã sử dụng mã giảm giá này rồi']);
            exit;
        }
    }

    // 5. Check min order value
    if ($subtotal < $coupon['min_order_value']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Đơn hàng tối thiểu ' . number_format($coupon['min_order_value'], 0, ',', '.') . 'đ mới áp dụng được mã này'
        ]);
        exit;
    }

    // 6. Calculate discount
    $discount_amount = 0;
    if ($coupon['discount_type'] === 'fixed') {
        $discount_amount = (float)$coupon['discount_value'];
    } else {
        $discount_amount = $subtotal * ($coupon['discount_value'] / 100);
        if ($coupon['max_discount_amount'] !== null && $discount_amount > $coupon['max_discount_amount']) {
            $discount_amount = (float)$coupon['max_discount_amount'];
        }
    }

    // Ensure discount doesn't exceed subtotal
    $discount_amount = min($discount_amount, $subtotal);

    echo json_encode([
        'success' => true,
        'message' => 'Áp dụng mã giảm giá thành công!',
        'coupon_id' => $coupon['id'],
        'code' => $coupon['code'],
        'discount_amount' => $discount_amount,
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $coupon['discount_value']
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
exit;

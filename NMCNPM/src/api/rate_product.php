<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đánh giá']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($product_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// 1. Ensure the product exists
$checkProd = $conn->query("SELECT id FROM products WHERE id = $product_id");
if (!$checkProd || $checkProd->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Món ăn không tồn tại']);
    exit;
}

// 2. CHECK ELIGIBILITY: Must have at least one PAID order with this product
// We use a prepared statement for security
$eligibilityStmt = $conn->prepare("
    SELECT 1 FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'paid' 
    LIMIT 1
");
if (!$eligibilityStmt) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi kiểm tra đơn hàng.']);
    exit;
}
$eligibilityStmt->bind_param("ii", $user_id, $product_id);
$eligibilityStmt->execute();
$resEligible = $eligibilityStmt->get_result();
if ($resEligible->num_rows === 0) {
    echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Bạn chỉ có thể đánh giá những món ăn đã đặt và thanh toán thành công.']);
    exit;
}

// 3. Manual Check for existing review (Robust approach)
$checkStmt = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
$checkStmt->bind_param("ii", $user_id, $product_id);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();

if ($checkRes && $checkRes->num_rows > 0) {
    // UPDATE existing
    $existing = $checkRes->fetch_assoc();
    $stmt = $conn->prepare("UPDATE product_reviews SET rating = ?, comment = ? WHERE id = ?");
    $stmt->bind_param("isi", $rating, $comment, $existing['id']);
} else {
    // INSERT new
    $stmt = $conn->prepare("INSERT INTO product_reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);
}
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống database.']);
    exit;
}


if ($stmt->execute()) {
    // Recalculate average rating and review count
    $avgRes = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as r_count FROM product_reviews WHERE product_id = $product_id");
    if ($avgRes && $row = $avgRes->fetch_assoc()) {
        $new_avg = round((float)$row['avg_rating'], 1);
        $new_count = (int)$row['r_count'];
        
        // Update the products table with the new stats
        $conn->query("UPDATE products SET rating = $new_avg, reviews_count = $new_count WHERE id = $product_id");
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Đánh giá thành công',
        'new_rating' => isset($new_avg) ? $new_avg : $rating,
        'new_count' => isset($new_count) ? $new_count : 1
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi lưu đánh giá']);
}
?>

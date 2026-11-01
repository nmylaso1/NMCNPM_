<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Món ăn không hợp lệ']);
    exit;
}

// 1. Delete review (only if it belongs to the current user)
$stmt = $conn->prepare("DELETE FROM product_reviews WHERE user_id = ? AND product_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống database.']);
    exit;
}

$stmt->bind_param("ii", $user_id, $product_id);

if ($stmt->execute()) {
    // 2. Recalculate Aggregates
    $avgRes = $conn->query("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM product_reviews WHERE product_id = $product_id");
    if ($avgRes && $avgRow = $avgRes->fetch_assoc()) {
        $new_avg = round((float)($avgRow['avg_r'] ?? 0), 1);
        $new_count = (int)($avgRow['cnt'] ?? 0);
        $conn->query("UPDATE products SET rating = $new_avg, reviews_count = $new_count WHERE id = $product_id");
    }

    echo json_encode(['success' => true, 'message' => 'Đã xóa đánh giá thành công', 'new_rating' => $new_avg, 'new_count' => $new_count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi xóa đánh giá']);
}
?>

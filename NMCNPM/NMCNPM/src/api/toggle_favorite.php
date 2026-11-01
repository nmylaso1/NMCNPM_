<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để yêu thích món ăn']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ']);
    exit;
}

try {
    // Check if already favorited
    $check = $conn->query("SELECT id FROM favorites WHERE user_id = $user_id AND product_id = $product_id");
    
    if ($check && $check->num_rows > 0) {
        // Remove from favorites
        $conn->query("DELETE FROM favorites WHERE user_id = $user_id AND product_id = $product_id");
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Đã bỏ yêu thích']);
    } else {
        // Add to favorites
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Đã thêm vào yêu thích']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu yêu thích']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
?>

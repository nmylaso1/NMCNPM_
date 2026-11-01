<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã món ăn không hợp lệ']);
    exit;
}

// 1. Fetch all reviews
$reviews = [];
$avg_rating = 0;
$total_count = 0;

$stmt = $conn->prepare("
    SELECT pr.*, a.full_name
    FROM product_reviews pr
    LEFT JOIN accounts a ON a.id = pr.user_id
    WHERE pr.product_id = ?
    ORDER BY pr.id DESC
");
if ($stmt) {
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['is_mine'] = ($user_id > 0 && (int)$row['user_id'] === $user_id);
            $reviews[] = $row;
        }
    }
}

// 2. Fetch Aggregates
$aggRes = $conn->query("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM product_reviews WHERE product_id = $product_id");
if ($aggRes && $aggRow = $aggRes->fetch_assoc()) {
    $avg_rating = round((float)$aggRow['avg_r'], 1);
    $total_count = (int)$aggRow['cnt'];
}

// 3. Check Eligibility for current user
$eligible = false;
$my_review = null;

if ($user_id > 0) {
    // Check if purchased
    $eligStmt = $conn->prepare("
        SELECT 1 FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'paid' 
        LIMIT 1
    ");
    $eligStmt->bind_param("ii", $user_id, $product_id);
    $eligStmt->execute();
    if ($eligStmt->get_result()->num_rows > 0) {
        $eligible = true;
    }

    // Find my existing review
    foreach ($reviews as $rv) {
        if ($rv['is_mine']) {
            $my_review = $rv;
            break;
        }
    }
}

echo json_encode([
    'success' => true,
    'reviews' => $reviews,
    'average' => $avg_rating,
    'count' => $total_count,
    'eligible' => $eligible,
    'my_review' => $my_review
]);
?>

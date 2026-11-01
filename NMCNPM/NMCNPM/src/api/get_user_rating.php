<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'rating' => 0]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'rating' => 0]);
    exit;
}

$rating = 0;
// fetch existing rating
$stmt = $conn->prepare("SELECT rating FROM product_reviews WHERE user_id = ? AND product_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $rating = (int)$row['rating'];
    }
}

echo json_encode(['success' => true, 'rating' => $rating]);
?>

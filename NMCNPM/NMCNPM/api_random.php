<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/src/includes/config.php';

$response = ['success' => false, 'data' => null];

if (isset($conn) && $conn) {
    $sql = "SELECT id, title, price, image, description, rating FROM products WHERE is_hidden = 0 ORDER BY RAND() LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $dish = $res->fetch_assoc();
        $response['success'] = true;
        $response['data'] = $dish;
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;


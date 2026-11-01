<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';
header('Content-Type: application/json');



$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    $user_id = ensureSessionUser($conn);
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Không thể tạo phiên đặt món, vui lòng thử lại!']);
        exit;
    }
}

// === KIỂM TRA backend: Không cho phép thêm nếu ngoài giờ phục vụ ===
$canteenSettings = getCanteenSettings($conn);
$open_time_str = $canteenSettings['open_time'] ?? '06:30:00';
$close_time_str = $canteenSettings['close_time'] ?? '20:00:00';

date_default_timezone_set('Asia/Ho_Chi_Minh'); 
$current_time = time();
$open_time = strtotime($open_time_str);
$close_time = strtotime($close_time_str);

if ($current_time < $open_time || $current_time > $close_time) {
    $formatted_hours = date('H:i', $open_time) . ' - ' . date('H:i', $close_time);
    echo json_encode(['success' => false, 'message' => "Chưa tới giờ phục vụ! Thời gian mở cửa trong hôm nay từ: $formatted_hours"]);
    exit;
}
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id > 0) {
        $cart_id = getOrCreateCartId($conn, $user_id);
        if ($cart_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Không thể khởi tạo giỏ hàng']);
            exit;
        }

        $productRes = $conn->query("SELECT id, title, price, image FROM products WHERE id = $product_id LIMIT 1");
        if (!$productRes || !$productRes->num_rows) {
            echo json_encode(['success' => false, 'message' => 'Rất tiếc, món này hiện không còn khả dụng 😥']);
            exit;
        }
        $product = $productRes->fetch_assoc();
        
        // Use custom title and price if sent from frontend (e.g. from Detail Modal with add-ons)
        $display_title = isset($_POST['title']) ? $conn->real_escape_string($_POST['title']) : $conn->real_escape_string($product['title']);
        $unit_price = isset($_POST['price']) ? (float)$_POST['price'] : (float)($product['price'] ?? 0);
        $requested_qty = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

        $old_qty = 0;
        $new_qty = $requested_qty;
        $action_type = 'add';

        // Check if this exact variant (product_id + custom title) already exists in cart
        $itemRes = $conn->query("SELECT quantity FROM cart_items 
                                WHERE cart_id = $cart_id AND product_id = $product_id AND product_name = '$display_title' LIMIT 1");
        if ($itemRes && $itemRes->num_rows > 0) {
            $row = $itemRes->fetch_assoc();
            $old_qty = (int)($row['quantity'] ?? 0);
            $new_qty = $old_qty + $requested_qty;
            $action_type = 'update_quantity';
            $conn->query("UPDATE cart_items 
                         SET quantity = $new_qty, unit_price = $unit_price, is_selected_for_checkout = 1
                         WHERE cart_id = $cart_id AND product_id = $product_id AND product_name = '$display_title'");
        } else {
            $conn->query("INSERT INTO cart_items (cart_id, product_id, product_name, quantity, unit_price, is_selected_for_checkout)
                         VALUES ($cart_id, $product_id, '$display_title', $requested_qty, $unit_price, 1)");
        }

        if ($conn->error) {
            echo json_encode(['success' => false, 'message' => 'Lỗi lưu vào giỏ hàng: ' . $conn->error]);
            exit;
        }

        logCartAction($conn, $cart_id, $user_id, $product_id, $action_type, $old_qty, $new_qty);
        syncSessionCartFromDb($conn, $user_id);
        
        // Hoàn tất ghi session, giải phóng lock
        session_write_close();

        $total_items = getCartCountFromDb($conn, $user_id);

        echo json_encode([
            'success' => true,
            'message' => htmlspecialchars($product['title'] ?? '') . ' đã được thêm vào giỏ hàng!',
            'cart_count' => $total_items
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm vào giỏ hàng']);
exit;
?>

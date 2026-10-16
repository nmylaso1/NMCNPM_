<?php
session_start();


require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

// Bắt tham số mã bàn nếu quét QR (vd: order.php?table=05)
if (isset($_GET['table']) && trim($_GET['table']) !== '') {
    $tNum = trim($_GET['table']);
    $_SESSION['table_number'] = str_starts_with(mb_strtolower($tNum), 'bàn') ? $tNum : ('Bàn ' . $tNum);
    $_SESSION['order_type'] = 'dine_in';
}

// Hỗ trợ cả khách đăng nhập và khách vãng lai / đặt tại bàn
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && isset($conn)) {
    $user_id = ensureSessionUser($conn);
}

$canteenSettings = getCanteenSettings($conn);
$full_name = $_SESSION['full_name'] ?? 'Quý khách';
$role = $_SESSION['role'] ?? 'guest';

$shipping_policy = getActiveShippingPolicy($conn);
$cart_id = getOrCreateCartId($conn, (int) $user_id);
syncSessionCartFromDb($conn, (int) $user_id);



// Lấy gợi ý món ăn (Lưu vào session để không đổi mỗi khi reload)
if (!isset($_SESSION['cart_suggestions']) || empty($_SESSION['cart_suggestions'])) {
    $suggestions = [];
    if (isset($conn)) {
        $sqlSuggest = "
            SELECT p.id, p.title, p.price, p.image, p.description, dm.is_highlight
            FROM daily_menu dm
            JOIN products p ON p.id = dm.product_id
            WHERE dm.serving_date = CURDATE()
            ORDER BY RAND() LIMIT 4
        ";
        $suggestRes = $conn->query($sqlSuggest);
        if ($suggestRes && $suggestRes->num_rows > 0) {
            while ($row = $suggestRes->fetch_assoc()) {
                $suggestions[] = $row;
            }
        } else {
            // Nếu không có thực đơn hôm nay, lấy ngẫu nhiên 4 món từ bảng products
            $sqlSuggest = "SELECT id, title, price, image, description, 0 as is_highlight FROM products ORDER BY RAND() LIMIT 4";
            $suggestRes = $conn->query($sqlSuggest);
            if ($suggestRes) {
                while ($row = $suggestRes->fetch_assoc()) {
                    $suggestions[] = $row;
                }
            }
        }
        $_SESSION['cart_suggestions'] = $suggestions;
    }
}
$suggestions = $_SESSION['cart_suggestions'] ?? [];

// Giải phóng khóa session cho các request GET (tải trang)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

// Xử lý cập nhật / xóa giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cart_item_id = isset($_POST['cart_item_id']) ? (int) $_POST['cart_item_id'] : 0;

    if ($action === 'remove' && $cart_item_id > 0) {
        $old_qty = isset($_SESSION['cart'][$cart_item_id]) ? (int) $_SESSION['cart'][$cart_item_id]['quantity'] : 0;
        if ($cart_id > 0) {
            $conn->query("DELETE FROM cart_items WHERE cart_id = $cart_id AND id = $cart_item_id");
            if ($old_qty > 0) {
                // For logging, we still use the product_id
                $pid = isset($_SESSION['cart'][$cart_item_id]) ? $_SESSION['cart'][$cart_item_id]['id'] : 0;
                logCartAction($conn, $cart_id, (int) $user_id, $pid, 'remove', $old_qty, 0);
            }
        }
        syncSessionCartFromDb($conn, (int) $user_id);

    } elseif ($action === 'update' && $cart_item_id > 0) {
        $qty = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
        $old_qty = isset($_SESSION['cart'][$cart_item_id]) ? (int) $_SESSION['cart'][$cart_item_id]['quantity'] : 0;

        if ($qty > 0) {
            if ($cart_id > 0) {
                // Optimized: We preserve the current unit_price already stored in cart_items
                // instead of resetting it from the products table.
                $conn->query("UPDATE cart_items 
                             SET quantity = $qty
                             WHERE cart_id = $cart_id AND id = $cart_item_id");

                if ($old_qty !== $qty) {
                    $pid = isset($_SESSION['cart'][$cart_item_id]) ? $_SESSION['cart'][$cart_item_id]['id'] : 0;
                    logCartAction($conn, $cart_id, (int) $user_id, $pid, 'update_quantity', $old_qty, $qty);
                }
            }
            syncSessionCartFromDb($conn, (int) $user_id);

        } else {
            if ($cart_id > 0) {
                $conn->query("DELETE FROM cart_items WHERE cart_id = $cart_id AND id = $cart_item_id");
                if ($old_qty > 0) {
                    $pid = isset($_SESSION['cart'][$cart_item_id]) ? $_SESSION['cart'][$cart_item_id]['id'] : 0;
                    logCartAction($conn, $cart_id, (int) $user_id, $pid, 'remove', $old_qty, 0);
                }
            }
            syncSessionCartFromDb($conn, (int) $user_id);
        }

    } elseif ($action === 'clear') {
        if ($cart_id > 0) {
            $conn->query("DELETE FROM cart_items WHERE cart_id = $cart_id");
            logCartAction($conn, $cart_id, (int) $user_id, 0, 'clear', 0, 0);
        }
        $_SESSION['cart'] = [];
        session_write_close();

    } elseif ($action === 'sync_selection' && $user_id > 0) {
        if ($cart_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy giỏ hàng']);
            exit;
        }

        $selected_cart_item_ids_raw = isset($_POST['selected_ids']) ? json_decode($_POST['selected_ids'], true) : [];
        if (!is_array($selected_cart_item_ids_raw))
            $selected_cart_item_ids_raw = [];
        $selected_cart_item_ids = array_values(array_unique(array_filter(array_map('intval', (array) $selected_cart_item_ids_raw), fn($id) => $id > 0)));

        if (empty($selected_cart_item_ids)) {
            $conn->query("UPDATE cart_items SET is_selected_for_checkout = 0 WHERE cart_id = $cart_id");
        } else {
            $selected_csv = implode(',', $selected_cart_item_ids);
            $conn->query("UPDATE cart_items
                         SET is_selected_for_checkout = IF(id IN ($selected_csv), 1, 0)
                         WHERE cart_id = $cart_id");
        }

        $cartData = syncSessionCartFromDb($conn, (int) $user_id);
        $selected_count = 0;
        foreach ($cartData as $it) {
            if ((int) ($it['is_selected_for_checkout'] ?? 1) === 1) {
                $selected_count++;
            }
        }

        echo json_encode([
            'success' => true,
            'selected_count' => $selected_count,
        ]);
        exit;

    } elseif ($action === 'prepare_order' && $user_id > 0) {
        // === KIỂM TRA backend: Không cho phép xử lý thanh toán nếu ngoài giờ phục vụ ===
        $open_time_str = $canteenSettings['open_time'] ?? '06:30:00';
        $close_time_str = $canteenSettings['close_time'] ?? '20:00:00';
        $current_time = time();
        $open_time = strtotime($open_time_str);
        $close_time = strtotime($close_time_str);
        
        if ($current_time < $open_time || $current_time > $close_time) {
            $formatted_hours = date('H:i', $open_time) . ' - ' . date('H:i', $close_time);
            echo json_encode(['success' => false, 'message' => "Hệ thống đang đóng cửa tạm thời! Thời gian phục vụ mua hàng trải dài từ: $formatted_hours"]);
            exit;
        }
        // ====================================================================

        $selected_cart_item_ids = isset($_POST['selected_ids']) ? json_decode($_POST['selected_ids'], true) : [];
        $order_type = trim((string)($_POST['order_type'] ?? ($_SESSION['order_type'] ?? 'dine_in')));
        if (!in_array($order_type, ['dine_in', 'delivery', 'takeaway'])) {
            $order_type = 'dine_in';
        }
        $table_number = trim((string)($_POST['table_number'] ?? ($_SESSION['table_number'] ?? '')));
        $customer_name = trim((string)($_POST['customer_name'] ?? ($_SESSION['full_name'] ?? 'Khách hàng')));
        $customer_phone = trim((string)($_POST['customer_phone'] ?? ''));

        $delivery_room = trim((string) ($_POST['delivery_room'] ?? ''));
        $coupon_id = isset($_POST['coupon_id']) ? (int) $_POST['coupon_id'] : 0;

        if ($order_type === 'delivery') {
            $delivery_requested = true;
            if ($delivery_room === '') {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập địa chỉ nhận hàng']);
                exit;
            }
        } else {
            $delivery_requested = false;
        }

        if ($order_type === 'dine_in' && empty($table_number)) {
            $table_number = 'Bàn tự chọn';
        }

        if (empty($selected_cart_item_ids)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ít nhất một món để thanh toán']);
            exit;
        }

        $selected_cart_item_ids = array_values(array_filter(array_map('intval', $selected_cart_item_ids), fn($id) => $id > 0));
        if (empty($selected_cart_item_ids) || $cart_id <= 0) {
            error_log("prepare_order failed: selected_cart_item_ids empty or cart_id=0 for user_id $user_id");
            echo json_encode(['success' => false, 'message' => 'Dữ liệu giỏ hàng không hợp lệ']);
            exit;
        }

        $selected_csv = implode(',', $selected_cart_item_ids);
        $conn->query("UPDATE cart_items 
                     SET is_selected_for_checkout = IF(id IN ($selected_csv), 1, 0)
                     WHERE cart_id = $cart_id");

        $items_to_process = [];
        $total_now = 0;
        $itemsRes = $conn->query("SELECT ci.product_id, ci.quantity, ci.unit_price, ci.product_name, p.title AS base_title
                                 FROM cart_items ci
                                 JOIN products p ON p.id = ci.product_id
                                 WHERE ci.cart_id = $cart_id AND ci.is_selected_for_checkout = 1");
        if ($itemsRes) {
            while ($row = $itemsRes->fetch_assoc()) {
                $qty = (int) ($row['quantity'] ?? 0);
                $price = (float) ($row['unit_price'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                // Use the custom name from cart if available, fallback to base product title
                $row['title_to_save'] = !empty($row['product_name']) ? $row['product_name'] : $row['base_title'];
                $row['line_total'] = $price * $qty;
                $total_now += $row['line_total'];
                $items_to_process[] = $row;
            }
        }

        if ($total_now <= 0 || empty($items_to_process)) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy món ăn đã chọn trong giỏ']);
            exit;
        }

        $shipping_threshold = isset($canteenSettings['free_delivery_from']) ? (float) $canteenSettings['free_delivery_from'] : (float) ($shipping_policy['threshold_1'] ?? 100000);
        $shipping_fee_below_threshold = isset($canteenSettings['delivery_fee']) ? (float) $canteenSettings['delivery_fee'] : (float) ($shipping_policy['fee_1'] ?? 10000);

        $shipping_fee = 0;
        if ($delivery_requested) {
            $shipping_fee = $total_now < $shipping_threshold ? $shipping_fee_below_threshold : 0;
        }

        // Re-validate Coupon and Calculate Discount
        $discount_amount_val = 0;
        $validated_coupon_id = null;
        if ($coupon_id > 0) {
            $stmt_cp = $conn->prepare("SELECT * FROM coupons WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt_cp->bind_param("i", $coupon_id);
            $stmt_cp->execute();
            $cp_res = $stmt_cp->get_result()->fetch_assoc();
            if ($cp_res) {
                // Basic checks
                $today = date('Y-m-d');
                $is_valid = true;
                
                // Date check (inclusive)
                if ($cp_res['start_date'] && $cp_res['start_date'] > $today) $is_valid = false;
                if ($cp_res['end_date'] && $cp_res['end_date'] < $today) $is_valid = false;
                
                // Total usage limit check
                if ($is_valid && $cp_res['usage_limit'] > 0 && $cp_res['used_count'] >= $cp_res['usage_limit'])
                    $is_valid = false;
                
                // User usage limit check
                if ($is_valid && $cp_res['user_limit'] > 0) {
                    $stmt_user = $conn->prepare("SELECT COUNT(*) as u_count FROM orders WHERE user_id = ? AND coupon_id = ? AND status != 'cancelled'");
                    $stmt_user->bind_param("ii", $user_id, $cp_res['id']);
                    $stmt_user->execute();
                    $u_count = $stmt_user->get_result()->fetch_assoc()['u_count'] ?? 0;
                    if ($u_count >= $cp_res['user_limit']) $is_valid = false;
                }

                // Min order value check
                if ($is_valid && $total_now < $cp_res['min_order_value'])
                    $is_valid = false;

                if ($is_valid) {
                    $validated_coupon_id = $cp_res['id'];
                    if ($cp_res['discount_type'] === 'fixed') {
                        $discount_amount_val = (float) $cp_res['discount_value'];
                    } else {
                        $discount_amount_val = $total_now * ($cp_res['discount_value'] / 100);
                        if ($cp_res['max_discount_amount'] > 0 && $discount_amount_val > $cp_res['max_discount_amount']) {
                            $discount_amount_val = (float) $cp_res['max_discount_amount'];
                        }
                    }
                    $discount_amount_val = min($discount_amount_val, $total_now);
                }
            }
        }

        $grand_total = $total_now + $shipping_fee - $discount_amount_val;

        $order_code_new = 'HDPT' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . date('mdHi') . '-' . rand(10, 99);
        $order_code_escaped = $conn->real_escape_string($order_code_new);
        $note_escaped = $conn->real_escape_string($_POST['note'] ?? '');
        $delivery_room_escaped = $delivery_requested ? $conn->real_escape_string($delivery_room) : '';
        $pm_raw = $_POST['payment_method'] ?? 'direct';
        $payment_method_escaped = ($pm_raw === 'transfer' || $pm_raw === 'payos') ? 'payos' : 'direct';
        $coupon_id_val = $validated_coupon_id ? $validated_coupon_id : "NULL";

        $order_type_escaped = $conn->real_escape_string($order_type);
        $table_number_escaped = $conn->real_escape_string($table_number);
        $customer_name_escaped = $conn->real_escape_string($customer_name);
        $customer_phone_escaped = $conn->real_escape_string($customer_phone);

        $conn->query("INSERT INTO orders (order_code, user_id, cart_id, coupon_id, subtotal_amount, shipping_fee, discount_amount, total_amount, delivery_room, status, payment_method, note, order_type, table_number, customer_name, customer_phone)
                      VALUES ('$order_code_escaped', $user_id, $cart_id, $coupon_id_val, $total_now, $shipping_fee, $discount_amount_val, $grand_total, " . ($delivery_requested ? "'$delivery_room_escaped'" : "NULL") . ", 'pending', '$payment_method_escaped', '$note_escaped', '$order_type_escaped', '$table_number_escaped', '$customer_name_escaped', '$customer_phone_escaped')");
        $order_id_new = $conn->insert_id;

        if ($order_id_new > 0) {
            foreach ($items_to_process as $item) {
                $pid_int = (int) ($item['product_id'] ?? 0);
                $title_e = $conn->real_escape_string($item['title_to_save'] ?? '');
                $price_e = (float) ($item['unit_price'] ?? 0);
                $qty_e = (int) ($item['quantity'] ?? 0);
                $line_total = (float) ($item['line_total'] ?? 0);
                $conn->query("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
                              VALUES ($order_id_new, $pid_int, '$title_e', $price_e, $qty_e, $line_total)");
            }

        }

        echo json_encode([
            'success' => $order_id_new > 0,
            'order_id' => (int) $order_id_new,
            'order_code' => $order_code_new,
            'order_type' => $order_type,
            'table_number' => $table_number,
            'subtotal_amount' => $total_now,
            'total_amount' => $grand_total,
            'shipping_fee' => $shipping_fee,
            'delivery_room' => $delivery_requested ? $delivery_room : null,
            'message' => $order_id_new > 0 ? 'Đơn hàng đã được tạo thành công! ✨' : 'Lỗi tạo đơn hàng',
        ]);
        exit;

    } elseif ($action === 'submit_order' && $user_id > 0) {
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $payment_mode = trim((string) ($_POST['payment_mode'] ?? 'direct'));
        $is_transfer_payment = $payment_mode === 'transfer';
        if ($order_id <= 0 || $cart_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin đơn hàng']);
            exit;
        }

        $orderRes = $conn->query("SELECT id, status, total_amount, coupon_id FROM orders WHERE id = $order_id AND user_id = $user_id LIMIT 1");
        if (!$orderRes || !$orderRes->num_rows) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }
        $orderRow = $orderRes->fetch_assoc();
        $currentStatus = strtolower(trim((string) ($orderRow['status'] ?? '')));
        $used_coupon_id = $orderRow['coupon_id'] ? (int) $orderRow['coupon_id'] : 0;

        if ($is_transfer_payment && $currentStatus === 'paid') {
            syncSessionCartFromDb($conn, (int) $user_id);
            echo json_encode([
                'success' => true,
                'message' => 'Đơn đã được thanh toán và xác nhận thành công! ✨',
                'new_status' => 'paid'
            ]);
            exit;
        }

        if ($currentStatus !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không còn ở trạng thái chờ']);
            exit;
        }

        $cartItemIds = [];
        $itemRes = $conn->query("SELECT product_id FROM order_items WHERE order_id = $order_id");
        // We actually want to delete the specific cart items that were checked out
        $conn->query("DELETE FROM cart_items WHERE cart_id = $cart_id AND is_selected_for_checkout = 1");

        // Increment coupon usage
        if ($used_coupon_id > 0) {
            $conn->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = $used_coupon_id");
        }

        syncSessionCartFromDb($conn, (int) $user_id);

        echo json_encode([
            'success' => true,
            'message' => $is_transfer_payment
                ? 'Đã ghi nhận đơn. Đơn hàng sẽ tự động cập nhật ngay khi thanh toán hoàn tất.'
                : 'Đơn hàng đã được gửi!',
            'new_status' => 'pending'
        ]);
        exit;

    } elseif ($action === 'get_order_status' && $user_id > 0) {
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        if ($order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
            exit;
        }

        $orderRes = $conn->query("SELECT id, status, confirmed_at FROM orders WHERE id = $order_id AND user_id = $user_id LIMIT 1");
        if (!$orderRes || !$orderRes->num_rows) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
            exit;
        }

        $orderRow = $orderRes->fetch_assoc();
        $status = strtolower(trim((string) ($orderRow['status'] ?? '')));
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'status' => $status,
            'paid' => $status === 'paid',
            'confirmed_at' => $orderRow['confirmed_at'] ?? null,
        ]);
        exit;

    } elseif ($action === 'cancel_prepared_order' && $user_id > 0) {
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        if ($order_id > 0) {
            $conn->query("UPDATE orders SET status='cancelled' WHERE id = $order_id AND user_id = $user_id AND status='pending'");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Đã hủy đơn nháp'
        ]);
        exit;
    }

    // AJAX response
    if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
        syncSessionCartFromDb($conn, (int) $user_id);
        $total_items = 0;
        $total_price = 0;
        if (isset($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $total_items += $item['quantity'];
                $total_price += $item['price'] * $item['quantity'];
            }
        }
        echo json_encode([
            'success' => true,
            'cart_count' => $total_items,
            'total_price' => number_format($total_price, 0, ',', '.') . 'đ',
            'total_price_raw' => $total_price,
        ]);
        exit;
    }

    header("Location: order.php");
    exit();
}

// Tính tổng
syncSessionCartFromDb($conn, (int) $user_id);
$total_items = 0;
$total_price = 0;
$cart = $_SESSION['cart'] ?? [];

foreach ($cart as $item) {
    $total_items += $item['quantity'];
    $total_price += $item['price'] * $item['quantity'];
}

$selected_items_count = 0;
foreach ($cart as $item) {
    if ((int) ($item['is_selected_for_checkout'] ?? 1) === 1) {
        $selected_items_count++;
    }
}
$all_items_selected = !empty($cart) && $selected_items_count === count($cart);

// Mã đơn hàng
$order_code = 'HDPT' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . date('mdHi');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet'); ?></title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Quicksand', 'sans-serif'],
                        serif: ['Quicksand', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.payos.vn/payos-checkout/v1/stable/payos-initialize.js"></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <?php
        $open_time_str = $canteenSettings['open_time'] ?? '06:30:00';
        $close_time_str = $canteenSettings['close_time'] ?? '20:00:00';
        $current_time = time();
        $open_time = strtotime($open_time_str);
        $close_time = strtotime($close_time_str);
        $is_open_php = ($current_time >= $open_time && $current_time <= $close_time);
        $formatted_canteen_hours = date('H:i', $open_time) . ' - ' . date('H:i', $close_time);
    ?>
    <script>
        window.IS_CANTEEN_OPEN = <?php echo $is_open_php ? 'true' : 'false'; ?>;
        window.CANTEEN_HOURS = "<?php echo htmlspecialchars($formatted_canteen_hours); ?>";
        
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.IS_CANTEEN_OPEN) {
                // Vô hiệu hoá nút thanh toán
                const payBtns = document.querySelectorAll('.pay-btn');
                payBtns.forEach(btn => {
                    btn.classList.add('opacity-50', 'grayscale', 'cursor-not-allowed', 'pointer-events-none');
                    const span = btn.querySelector('span') || btn;
                    span.textContent = 'Ngoài giờ phục vụ (' + window.CANTEEN_HOURS + ')';
                });
            }
        });
    </script>
    <style>
        /* Chuyển overflow-x lên html để giấu thanh cuộn ngang mà không gây lỗi double scrollbar */
        html {
            overflow-x: hidden;
        }

        * {
            font-family: 'Quicksand', sans-serif;
        }

        body {
            background-color: #f3f4f6;
            min-height: 100vh;
            color: #423826;
            position: relative;
        }

        .blob-1 {
            border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
        }

        .blob-2 {
            border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        }

        /* Cart item card */
        .cart-card {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(217, 119, 6, 0.1);
            box-shadow: 0 2px 12px rgba(217, 119, 6, 0.06);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .cart-card:hover {
            box-shadow: none;
            transform: translateY(-2px);
        }

        /* Quantity control */
        .qty-control {
            display: inline-flex;
            align-items: center;
            gap: 0;
            padding: 0;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .qty-btn {
            width: 34px;
            height: 34px;
            border-radius: 0;
            border: 0;
            background: #ffffff;
            color: #374151;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.16s ease, background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .qty-btn:first-child {
            border-right: 1px solid #e5e7eb;
        }

        .qty-btn:last-child {
            border-left: 1px solid #e5e7eb;
        }

        .qty-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
            transform: translateY(-1px);
        }

        .qty-btn:active {
            transform: scale(0.95);
        }

        .qty-display {
            min-width: 44px;
            height: 34px;
            border-radius: 0;
            border: 0;
            background: #ffffff;
            text-align: center;
            font-weight: 800;
            font-size: 1rem;
            color: #2d1f0e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
        }

        @media (max-width: 640px) {
            .qty-control {
                gap: 0;
                padding: 0;
            }

            .qty-btn {
                width: 32px;
                height: 32px;
            }

            .qty-display {
                min-width: 38px;
                height: 32px;
                font-size: 0.95rem;
                padding: 0 7px;
            }
        }

        /* Summary card */
        .summary-card {
            background: white;
            border-radius: 24px;
            border: 1px solid rgba(217, 119, 6, 0.1);
            box-shadow: 0 4px 24px rgba(217, 119, 6, 0.1);
        }

        /* Pay button */
        .pay-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1.05rem;
            padding: 14px 24px;
            width: 100%;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.35);
            transition: all 0.25s;
            letter-spacing: 0.3px;
        }

        .pay-btn:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            box-shadow: 0 8px 28px rgba(180, 83, 9, 0.4);
            transform: translateY(-1px);
        }

        .pay-btn:active {
            transform: translateY(1px) scale(0.98);
        }

        /* Delete button */
        .del-btn {
            background: #fff1f2;
            border: 1.5px solid #fecdd3;
            border-radius: 10px;
            color: #e11d48;
            padding: 6px 12px;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .del-btn:hover {
            background: #ffe4e6;
            border-color: #fda4af;
            color: #be123c;
        }

        /* Empty state */
        .empty-box {
            background: white;
            border-radius: 28px;
            box-shadow: 0 2px 24px rgba(0, 0, 0, 0.04);
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            background: #fef3c7;
            color: #92400e;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
            border: 1px solid #fcd369;
        }

        /* Progress steps */
        .step-active {
            color: #d97706;
            font-weight: 700;
        }

        .step-inactive {
            color: #9ca3af;
        }

        /* Item removing animation */
        .removing {
            opacity: 0;
            transform: translateX(30px) scale(0.95);
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 12px 22px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transition: all 0.35s cubic-bezier(.4, 0, .2, 1);
            z-index: 9999;
            pointer-events: none;
            border: 1px solid transparent;
        }

        #toast.toast-success {
            background: #fff7ed;
            color: #9a3412;
            border-color: #fdba74;
            box-shadow: 0 10px 26px rgba(217, 119, 6, 0.22);
        }

        #toast.toast-success #toast-icon {
            color: #d97706;
        }

        #toast.toast-error {
            background: #fff1f2;
            color: #9f1239;
            border-color: #fda4af;
            box-shadow: 0 10px 26px rgba(225, 29, 72, 0.18);
        }

        #toast.toast-error #toast-icon {
            color: #e11d48;
        }

        #toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* SweetAlert override */
        .swal2-popup {
            font-family: 'Quicksand', sans-serif !important;
        }

        /* Payment modal styles */
        .payment-qr-wrapper {
            background: white;
            border-radius: 18px;
            padding: 12px;
            border: 2px solid #fcd369;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.15);
        }

        .bank-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-radius: 12px;
            margin-bottom: 6px;
        }

        .bank-info-row:nth-child(odd) {
            background: #fef9f0;
        }

        .bank-info-row:nth-child(even) {
            background: #fffbf0;
        }

        /* Bỏ giới hạn chiều cao để danh sách cuộn tự nhiên theo trang gốc */
        #cart-list {
            overflow-x: hidden;
            padding-right: 8px;
        }

        #cart-list::-webkit-scrollbar {
            width: 6px;
        }

        #cart-list::-webkit-scrollbar-track {
            background: transparent;
        }

        #cart-list::-webkit-scrollbar-thumb {
            background: #fcd369;
            border-radius: 10px;
        }

        #cart-list::-webkit-scrollbar-thumb:hover {
            background: #d97706;
        }

        /* Food Suggestion Cards (dashboard style) */
        .food-card {
            background-color: #e4ead3;
            border-radius: 2rem;
            transition: all 0.3s ease;
        }

        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05);
        }

        .food-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%;
            border-radius: 50%;
            overflow: hidden;
            background-color: #fcfbf8;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        .food-img-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .add-suggest-btn {
            background-color: #d97706;
            /* amber-600 */
            color: white;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .add-suggest-btn:hover {
            background-color: #b45309;
            transform: scale(1.05);
        }

        /* Selective Checkout Checkbox */
        .cart-checkbox {
            appearance: none;
            width: 24px;
            height: 24px;
            border: 2px solid #d97706;
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            background-color: white;
            transition: all 0.2s;
        }

        .cart-checkbox:checked {
            background-color: #d97706;
            outline: none;
        }

        .cart-checkbox:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 16px;
            font-weight: 900;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .cart-checkbox:hover {
            border-color: #b45309;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.1);
        }
    </style>
</head>

<body>

    <div
        class="absolute top-0 right-0 w-[600px] h-[600px] bg-[#e4ead3]/60 blob-1 -z-10 -translate-y-1/4 translate-x-1/4">
    </div>
    <div
        class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-[#f2e6d8]/60 blob-2 -z-10 translate-y-1/4 -translate-x-1/4">
    </div>

    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1 whitespace-nowrap flex-shrink-0">
                        <?php 
                        $cName = $canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet';
                        $parts = explode(' ', $cName, 2);
                        if (count($parts) > 1) {
                            echo htmlspecialchars($parts[0]) . ' <span>' . htmlspecialchars($parts[1]) . '</span>';
                        } else {
                            echo htmlspecialchars($cName);
                        }
                        ?>
                    </div>
                </a>

                <div class="navbar-items">
                    <a href="dashboard.php" class="navbar-item">
                        <i data-lucide="house" class="w-4 h-4"></i>
                        Trang chủ
                    </a>
                    <a href="menu.php" class="navbar-item">
                        <i data-lucide="utensils-crossed" class="w-4 h-4"></i>
                        Thực đơn
                    </a>
                    <a href="profile.php" class="navbar-item">
                        <i data-lucide="user-circle" class="w-4 h-4"></i>
                        Hồ sơ
                    </a>
                    <a href="order.php" class="navbar-item navbar-item-active relative pr-7">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <span id="cart-count-badge"
                            class="absolute -top-1 -right-1 bg-white text-[#d97706] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-amber-200"><?php echo $total_items; ?></span>
                    </a>

                    <!-- Nút Giới Thiệu (Icon nhỏ góc riêng) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>

                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                        <a href="src/admin/index.php" class="navbar-item"
                            style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:12px;padding:6px 12px;margin-left:2px;">
                            <i data-lucide="<?php echo $_SESSION['role'] === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                            <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Quản lý'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-5 pt-6 pb-0">
        <h1 class="text-2xl font-extrabold text-[#2d1f0e] mb-6 flex items-center gap-2.5">
            <i data-lucide="shopping-cart" class="w-7 h-7 text-amber-500"></i>
            Giỏ hàng của bạn
            <span class="text-sm font-bold text-gray-400 ml-1">(<?php echo $total_items; ?> món)</span>
        </h1>

        <?php if (empty($cart)): ?>
            <div class="empty-box p-16 text-center max-w-md mx-auto mt-6">
                <div class="w-28 h-28 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-5 text-5xl">🛒
                </div>
                <h3 class="text-xl font-extrabold text-gray-800 mb-2">Giỏ hàng đang trống</h3>
                <p class="text-gray-400 font-medium mb-7">Bạn chưa chọn món nào. Hãy quay lại thực đơn để chọn những món bạn
                    yêu thích nhé!</p>
                <a href="menu.php"
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-amber-600 text-white px-7 py-3.5 rounded-2xl hover:from-amber-600 hover:to-amber-700 transition shadow-lg shadow-amber-200 font-bold text-base">
                    <i data-lucide="utensils" class="w-5 h-5"></i> Xem thực đơn ngay
                </a>
            </div>

        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-7">

                <div class="lg:col-span-3 space-y-4">
                    <div
                        class="flex items-center justify-between gap-3 mb-2 px-4 py-3 bg-white/60 rounded-2xl border border-amber-100 flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <input type="checkbox" id="select-all" <?php echo $all_items_selected ? 'checked' : ''; ?>
                                onchange="toggleSelectAll(this.checked)" class="cart-checkbox">
                            <label for="select-all"
                                class="text-sm font-bold text-amber-900 cursor-pointer flex items-center gap-2 min-w-0">
                                Chọn tất cả <span class="text-xs font-medium text-amber-600/70">(Bấm để chọn/bỏ tất cả
                                    món)</span>
                            </label>
                        </div>
                        <button onclick="clearCart()"
                            class="text-sm text-gray-400 hover:text-red-400 font-semibold flex items-center gap-1.5 transition-colors whitespace-nowrap">
                            <i data-lucide="trash" class="w-4 h-4"></i> Xóa tất cả
                        </button>
                    </div>

                    <div id="cart-list" class="space-y-3">
                        <?php foreach ($cart as $id => $item): ?>
                            <div class="cart-card flex items-center gap-4 p-4 transition-all duration-300"
                                id="cart-item-<?php echo $id; ?>">
                                <div class="flex-shrink-0 flex items-center pr-1 h-full">
                                    <input type="checkbox" <?php echo ((int) ($item['is_selected_for_checkout'] ?? 1) === 1) ? 'checked' : ''; ?> value="<?php echo $id; ?>" class="cart-checkbox item-checkbox"
                                        onchange="onItemCheckboxChange()">
                                </div>
                                <div class="flex-shrink-0 cursor-pointer"
                                    onclick='openFoodDetailModal(<?php echo json_encode($item); ?>)'>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>"
                                        alt="<?php echo htmlspecialchars($item['title']); ?>"
                                        class="w-20 h-20 object-cover rounded-2xl bg-amber-50 hover:scale-110 transition-transform duration-500"
                                        onerror="this.src='https://via.placeholder.com/80x80/fef3c7/d97706?text=🍲'">
                                </div>

                                <div class="flex-grow min-w-0">
                                    <h3 class="font-bold text-gray-800 text-base leading-tight truncate mb-1 cursor-pointer hover:text-amber-600 transition-colors"
                                        onclick='openFoodDetailModal(<?php echo json_encode($item); ?>)'>
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </h3>
                                    <div class="text-amber-600 font-bold text-sm mb-3" id="price-<?php echo $id; ?>"
                                        data-price="<?php echo $item['price']; ?>">
                                        <?php echo number_format($item['price'], 0, ',', '.'); ?>đ / phần
                                    </div>

                                    <div class="flex items-center gap-3 flex-wrap">
                                        <div class="qty-control" role="group" aria-label="Điều chỉnh số lượng">
                                            <button class="qty-btn" onclick="updateQty(<?php echo $id; ?>, -1)">
                                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <div class="qty-display" id="qty-<?php echo $id; ?>">
                                                <?php echo $item['quantity']; ?>
                                            </div>
                                            <button class="qty-btn" onclick="updateQty(<?php echo $id; ?>, 1)">
                                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </div>

                                        <button class="del-btn" onclick="removeItem(<?php echo $id; ?>)">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Xóa
                                        </button>
                                    </div>
                                </div>

                                <div class="text-right flex-shrink-0 hidden sm:block">
                                    <div class="text-xs text-gray-400 font-semibold mb-1">Thành tiền</div>
                                    <div class="font-extrabold text-gray-800 text-base" id="subtotal-<?php echo $id; ?>">
                                        <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>đ
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

                <div class="lg:col-span-2 sticky top-24 h-fit">
                    <div class="summary-card p-6">
                        <h3
                            class="font-extrabold text-base text-gray-800 border-b border-dashed border-amber-100 pb-4 mb-4 flex items-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4 text-amber-500"></i>
                            Tóm tắt đơn hàng
                        </h3>

                        <div class="space-y-3 mb-5">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span class="font-semibold">Số lượng</span>
                                <span class="font-bold text-gray-800" id="summary-count"><?php echo $total_items; ?>
                                    món</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span class="font-semibold">Tạm tính</span>
                                <span class="font-bold text-gray-800"
                                    id="summary-subtotal"><?php echo number_format($total_price, 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="rounded-2xl border border-orange-200/80 bg-orange-50/50 p-3.5 space-y-3">
                                <div class="text-xs font-bold text-stone-700 uppercase tracking-wider flex items-center justify-between">
                                    <span class="flex items-center gap-1.5"><i data-lucide="compass" class="w-3.5 h-3.5 text-orange-600"></i> Hình thức đặt món:</span>
                                    <span id="current-mode-label" class="text-[10px] text-emerald-700 font-extrabold px-2 py-0.5 bg-emerald-100/80 rounded-full">Tại bàn</span>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-1.5 p-1 bg-stone-200/60 rounded-xl">
                                    <button type="button" id="btn-mode-dinein" onclick="setOrderMode('dine_in')" class="py-2 text-xs font-extrabold rounded-lg transition-all text-center bg-white text-orange-600 shadow-sm border border-orange-200">
                                        🍽️ Tại bàn
                                    </button>
                                    <button type="button" id="btn-mode-delivery" onclick="setOrderMode('delivery')" class="py-2 text-xs font-bold rounded-lg transition-all text-center text-stone-600 hover:text-stone-900">
                                        🛵 Giao hàng
                                    </button>
                                    <button type="button" id="btn-mode-takeaway" onclick="setOrderMode('takeaway')" class="py-2 text-xs font-bold rounded-lg transition-all text-center text-stone-600 hover:text-stone-900">
                                        🛍️ Mang về
                                    </button>
                                </div>

                                <!-- Details for Dine-In -->
                                <div id="mode-detail-dinein" class="space-y-2">
                                    <input type="text" id="order-table-input" value="<?php echo htmlspecialchars($_SESSION['table_number'] ?? 'Bàn 01'); ?>" placeholder="Số bàn (vd: Bàn 01, Bàn 05)"
                                        class="w-full border border-orange-200 rounded-xl px-3 py-2 text-sm font-bold focus:outline-none focus:border-orange-500 bg-white placeholder-stone-400 text-stone-800">
                                    <p class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1">
                                        <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i> Phục vụ tận bàn · Miễn phí giao 0đ
                                    </p>
                                </div>

                                <!-- Details for Delivery -->
                                <div id="mode-detail-delivery" class="space-y-2 hidden">
                                    <input type="text" id="delivery-address-input" placeholder="Địa chỉ nhận món (Số nhà, đường, quận...)"
                                        class="w-full border border-orange-200 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:border-orange-500 bg-white placeholder-stone-400 text-stone-800"
                                        oninput="recalculateSelected()">
                                    <input type="tel" id="delivery-phone-input" placeholder="Số điện thoại nhận hàng"
                                        class="w-full border border-orange-200 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:border-orange-500 bg-white placeholder-stone-400 text-stone-800">
                                    <p class="text-[11px] text-stone-600 leading-relaxed">
                                        Dưới <span class="font-bold"><?php echo number_format((float) $shipping_policy['threshold_1'], 0, ',', '.'); ?>đ</span>:
                                        phí <span class="font-bold"><?php echo number_format((float) $shipping_policy['fee_1'], 0, ',', '.'); ?>đ</span>.
                                        Từ <span class="font-bold"><?php echo number_format((float) $shipping_policy['threshold_1'], 0, ',', '.'); ?>đ</span>:
                                        <span class="font-bold text-emerald-600">Free ship</span>.
                                    </p>
                                </div>

                                <!-- Details for Takeaway -->
                                <div id="mode-detail-takeaway" class="space-y-2 hidden">
                                    <input type="tel" id="takeaway-phone-input" placeholder="Số điện thoại nhận thông báo khi món chuẩn bị xong"
                                        class="w-full border border-orange-200 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:border-orange-500 bg-white placeholder-stone-400 text-stone-800">
                                    <p class="text-[11px] text-purple-700 font-semibold flex items-center gap-1">
                                        <i data-lucide="bell" class="w-3 h-3 text-purple-600"></i> Nhân viên sẽ liên hệ khi bếp hoàn tất món để quý khách đến lấy
                                    </p>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span class="font-semibold">Phí giao hàng</span>
                                <span class="font-bold" id="summary-shipping">0đ</span>
                            </div>
                            <div id="summary-discount-row" class="hidden flex justify-between text-sm text-green-600">
                                <span class="font-semibold">Giảm giá</span>
                                <span class="font-bold" id="summary-discount">-0đ</span>
                            </div>
                            <div class="border-t border-dashed border-amber-200 pt-3 mt-1">
                                <div class="flex justify-between items-center">
                                    <span class="font-extrabold text-gray-800">Tổng cộng</span>
                                    <span class="font-extrabold text-amber-600 text-xl" id="summary-total">
                                        <?php echo number_format($total_price, 0, ',', '.'); ?>đ
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 mb-5">
                            <div class="relative flex-1">
                                <input type="text" placeholder="Nhập mã giảm giá..." id="voucher-input"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:outline-none focus:border-amber-400 bg-gray-50 placeholder-gray-300">
                                <button id="remove-voucher-btn" onclick="removeVoucher()"
                                    class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-xs font-bold text-red-500 hover:text-red-600 bg-red-50 px-2 py-1 rounded-lg">
                                    Hủy
                                </button>
                            </div>
                            <button id="apply-voucher-btn" onclick="applyVoucher()"
                                class="bg-amber-100 border border-amber-300 text-amber-700 font-bold text-sm px-3 py-2 rounded-xl hover:bg-amber-200 transition">
                                Áp dụng
                            </button>
                        </div>

                        <div class="mb-5">
                            <label for="order-note"
                                class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Ghi chú
                                (Tùy chọn)</label>
                            <textarea id="order-note" placeholder="Ghi chú cho bếp / nhà hàng: ví dụ ít cay, thêm nước sốt..."
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:outline-none focus:border-amber-400 bg-gray-50 placeholder-gray-300 resize-none"
                                rows="2"></textarea>
                        </div>

                        <button class="pay-btn" onclick="showPaymentModal()">
                            <span class="flex items-center justify-center gap-2">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                                Mua hàng
                            </span>
                        </button>

                        <p class="text-center text-xs text-gray-400 mt-3 font-medium">
                            🔒 Thông tin được bảo mật an toàn
                        </p>
                    </div>

                </div>
            </div>


            <form id="cart-form" method="POST" class="hidden">
                <input type="hidden" name="action" id="form-action" value="">
                <input type="hidden" name="product_id" id="form-product-id" value="">
                <input type="hidden" name="quantity" id="form-quantity" value="">
                <input type="hidden" name="ajax" value="1">
            </form>
        <?php endif; ?>

    </div>

    <div id="toast" class="toast-success">
        <i id="toast-icon" data-lucide="check-circle" class="w-4 h-4"></i>
        <span id="toast-msg">Đã cập nhật giỏ hàng</span>
    </div>

    <script>
        window.onerror = function (msg, url, lineNo, columnNo, error) {
            console.error('Lỗi ứng dụng:', msg, 'tại', url, 'dòng:', lineNo, error);
            return false;
        };

        try {
            lucide.createIcons();
        } catch (e) {
            console.error('Lucide error:', e);
        }
        let toastTimer = null;
        let appliedCoupon = null; // Store coupon data {id, code, type, value, amount}

        // -------------------- Toast --------------------
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            document.getElementById('toast-msg').innerText = msg;
            toast.classList.remove('toast-success', 'toast-error');
            toast.classList.add(type === 'error' ? 'toast-error' : 'toast-success');
            toast.classList.add('show');
            if (toastTimer) {
                clearTimeout(toastTimer);
            }
            toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
        }

        // -------------------- Voucher AJAX --------------------
        async function applyVoucher() {
            const val = document.getElementById('voucher-input').value.trim();
            if (!val) { showToast('Vui lòng nhập mã giảm giá', 'error'); return; }

            const subtotal = getSubtotalFromSelected();
            if (subtotal <= 0) {
                showToast('Giỏ hàng trống, không thể áp dụng mã', 'error');
                return;
            }

            const btn = document.querySelector('button[onclick="applyVoucher()"]');
            const originalText = btn.innerText;
            btn.innerText = 'Đang áp dụng...';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('code', val);
                fd.append('subtotal', subtotal);

                const res = await fetch('src/api/apply_coupon.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    appliedCoupon = {
                        id: data.coupon_id,
                        code: data.code,
                        type: data.discount_type,
                        value: data.discount_value,
                        amount: data.discount_amount
                    };

                    // Show detailed success popup
                    const discountText = data.discount_type === 'percentage'
                        ? `${data.discount_value}% (Tối đa ${formatVnd(data.discount_amount)})`
                        : formatVnd(data.discount_amount);

                    Swal.fire({
                        icon: 'success',
                        title: 'Áp dụng mã thành công!',
                        html: `
                            <div class="text-left space-y-2 mt-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Mã giảm giá:</span>
                                    <span class="font-bold text-amber-600">${data.code}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Mức giảm:</span>
                                    <span class="font-bold text-green-600">${discountText}</span>
                                </div>
                                <div class="pt-2 border-t border-dashed border-gray-200 mt-2">
                                    <p class="text-xs text-gray-400">Số tiền giảm sẽ được trừ trực tiếp vào tổng đơn hàng của bạn. Mã đã được áp dụng và không thể thay đổi.</p>
                                </div>
                            </div>
                        `,
                        confirmButtonColor: '#d97706',
                        confirmButtonText: 'Tuyệt vời',
                        customClass: { popup: 'rounded-[1.5rem]' }
                    });

                    // Update UI state for applied coupon
                    const voucherInput = document.getElementById('voucher-input');
                    const applyBtn = document.getElementById('apply-voucher-btn');
                    const removeBtn = document.getElementById('remove-voucher-btn');

                    if (voucherInput) {
                        voucherInput.disabled = true;
                        voucherInput.classList.add('bg-gray-100');
                        voucherInput.value = data.code;
                    }
                    if (applyBtn) {
                        applyBtn.classList.add('hidden');
                    }
                    if (removeBtn) {
                        removeBtn.classList.remove('hidden');
                    }

                    recalculateSelected();
                } else {
                    appliedCoupon = null;
                    showToast(data.message || 'Mã không hợp lệ', 'error');
                    recalculateSelected();
                }
            } catch (err) {
                console.error(err);
                showToast('Lỗi kết nối khi áp dụng mã', 'error');
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        // -------------------- Remove Voucher --------------------
        function removeVoucher() {
            appliedCoupon = null;

            const voucherInput = document.getElementById('voucher-input');
            const applyBtn = document.getElementById('apply-voucher-btn');
            const removeBtn = document.getElementById('remove-voucher-btn');

            if (voucherInput) {
                voucherInput.value = '';
                voucherInput.disabled = false;
                voucherInput.classList.remove('bg-gray-100');
            }
            if (applyBtn) {
                applyBtn.classList.remove('hidden');
            }
            if (removeBtn) {
                removeBtn.classList.add('hidden');
            }

            recalculateSelected();
            showToast('Đã hủy áp dụng mã giảm giá');
        }

        // -------------------- Selection & Checkbox Logic --------------------
        const SHIPPING_FREE_THRESHOLD = <?php echo (float) $shipping_policy['threshold_1']; ?>;
        const SHIPPING_FEE_BELOW_THRESHOLD = <?php echo (float) $shipping_policy['fee_1']; ?>;

        function formatVnd(amount) {
            return new Intl.NumberFormat('vi-VN').format(Math.max(0, amount)) + 'đ';
        }

        function getSubtotalFromSelected() {
            let subtotal = 0;
            const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
            selectedCheckboxes.forEach(cb => {
                const id = cb.value;
                const price = parseFloat(document.getElementById('price-' + id).dataset.price || '0');
                const qty = parseInt(document.getElementById('qty-' + id).innerText || '0', 10);
                subtotal += price * qty;
            });
            return subtotal;
        }

        let currentOrderMode = '<?php echo $_SESSION['order_type'] ?? 'dine_in'; ?>';

        function setOrderMode(mode) {
            currentOrderMode = mode;
            const btnDinein = document.getElementById('btn-mode-dinein');
            const btnDelivery = document.getElementById('btn-mode-delivery');
            const btnTakeaway = document.getElementById('btn-mode-takeaway');

            const detailDinein = document.getElementById('mode-detail-dinein');
            const detailDelivery = document.getElementById('mode-detail-delivery');
            const detailTakeaway = document.getElementById('mode-detail-takeaway');
            const labelEl = document.getElementById('current-mode-label');

            const inactiveCls = 'py-2 text-xs font-bold rounded-lg transition-all text-center text-stone-600 hover:text-stone-900';
            const activeCls = 'py-2 text-xs font-extrabold rounded-lg transition-all text-center bg-white text-orange-600 shadow-sm border border-orange-200';

            if (btnDinein) btnDinein.className = inactiveCls;
            if (btnDelivery) btnDelivery.className = inactiveCls;
            if (btnTakeaway) btnTakeaway.className = inactiveCls;

            if (detailDinein) detailDinein.classList.add('hidden');
            if (detailDelivery) detailDelivery.classList.add('hidden');
            if (detailTakeaway) detailTakeaway.classList.add('hidden');

            if (mode === 'dine_in') {
                if (btnDinein) btnDinein.className = activeCls;
                if (detailDinein) detailDinein.classList.remove('hidden');
                if (labelEl) {
                    labelEl.innerText = 'Tại bàn';
                    labelEl.className = 'text-[10px] text-emerald-700 font-extrabold px-2 py-0.5 bg-emerald-100/80 rounded-full';
                }
            } else if (mode === 'delivery') {
                if (btnDelivery) btnDelivery.className = activeCls;
                if (detailDelivery) detailDelivery.classList.remove('hidden');
                if (labelEl) {
                    labelEl.innerText = 'Giao hàng';
                    labelEl.className = 'text-[10px] text-orange-700 font-extrabold px-2 py-0.5 bg-orange-100/80 rounded-full';
                }
            } else {
                if (btnTakeaway) btnTakeaway.className = activeCls;
                if (detailTakeaway) detailTakeaway.classList.remove('hidden');
                if (labelEl) {
                    labelEl.innerText = 'Mang về';
                    labelEl.className = 'text-[10px] text-purple-700 font-extrabold px-2 py-0.5 bg-purple-100/80 rounded-full';
                }
            }
            recalculateSelected();
        }

        function isRoomDeliveryEnabled() {
            return currentOrderMode === 'delivery';
        }

        function calcShippingFee(subtotal) {
            if (currentOrderMode !== 'delivery') {
                return 0;
            }
            return subtotal < SHIPPING_FREE_THRESHOLD ? SHIPPING_FEE_BELOW_THRESHOLD : 0;
        }

        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
            recalculateSelected();
            queueSelectionSync();
        }

        function onItemCheckboxChange() {
            recalculateSelected();
            queueSelectionSync();
        }

        let selectionSyncTimer = null;
        function queueSelectionSync() {
            if (selectionSyncTimer) {
                clearTimeout(selectionSyncTimer);
            }
            selectionSyncTimer = setTimeout(syncSelectionToDb, 180);
        }

        async function syncSelectionToDb() {
            const selectedIds = Array.from(document.querySelectorAll('.item-checkbox:checked')).map((cb) => cb.value);
            const fd = new FormData();
            fd.append('action', 'sync_selection');
            fd.append('selected_ids', JSON.stringify(selectedIds));
            fd.append('ajax', '1');

            try {
                await fetch('order.php', { method: 'POST', body: fd });
            } catch (err) {
                // Keep UI responsive even if sync fails temporarily.
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function normalizePayOSCheckoutUrl(checkoutUrl) {
            if (!checkoutUrl) {
                return '';
            }
            return checkoutUrl;
        }

        function createPayOSLink(orderId, timeoutMs = 25000) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'src/api/payos_create_payment.php', true);
                xhr.timeout = timeoutMs;

                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== 4) {
                        return;
                    }

                    let payload = null;
                    try {
                        payload = JSON.parse(xhr.responseText || '{}');
                    } catch (parseErr) {
                        reject(new Error('Phản hồi từ máy chủ thanh toán không hợp lệ'));
                        return;
                    }

                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(new Error(payload?.message || 'Máy chủ thanh toán phản hồi lỗi'));
                        return;
                    }

                    if (!payload.success) {
                        reject(new Error(payload?.message || 'Không tạo được liên kết thanh toán'));
                        return;
                    }

                    resolve(payload);
                };

                xhr.ontimeout = () => {
                    reject(new Error('Yêu cầu tạo liên kết thanh toán bị quá thời gian. Vui lòng thử lại.'));
                };

                xhr.onerror = () => {
                    reject(new Error('Không thể kết nối máy chủ thanh toán'));
                };

                const formData = new FormData();
                formData.append('order_id', String(orderId));
                xhr.send(formData);
            });
        }

        function checkOrderStatus(orderId, timeoutMs = 10000) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'order.php', true);
                xhr.responseType = 'json';
                xhr.timeout = timeoutMs;

                xhr.onload = () => {
                    const payload = xhr.response;
                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(new Error((payload && payload.message) || `Lỗi máy chủ (${xhr.status})`));
                        return;
                    }
                    if (!payload || typeof payload !== 'object') {
                        reject(new Error('Phản hồi trạng thái đơn hàng không hợp lệ'));
                        return;
                    }
                    if (!payload.success) {
                        reject(new Error(payload.message || 'Không kiểm tra được trạng thái đơn hàng'));
                        return;
                    }
                    resolve(payload);
                };

                xhr.onerror = () => reject(new Error('Không kết nối được máy chủ kiểm tra trạng thái đơn hàng'));
                xhr.ontimeout = () => reject(new Error('Hết thời gian chờ kiểm tra trạng thái đơn hàng'));

                const formData = new FormData();
                formData.append('action', 'get_order_status');
                formData.append('ajax', '1');
                formData.append('order_id', String(orderId));
                xhr.send(formData);
            });
        }

        function openPayOSDialog(checkoutUrl) {
            if (!window.PayOSCheckout || typeof window.PayOSCheckout.usePayOS !== 'function') {
                throw new Error('PayOS Checkout chưa sẵn sàng. Vui lòng tải lại trang.');
            }

            const normalizedUrl = normalizePayOSCheckoutUrl(checkoutUrl);
            const currentUrl = `${window.location.origin}${window.location.pathname}`;

            const { open } = window.PayOSCheckout.usePayOS({
                RETURN_URL: `${currentUrl}?payos=return`,
                ELEMENT_ID: 'payos_checkout_root',
                CHECKOUT_URL: normalizedUrl,
                onExit: () => console.log('PayOS popup closed'),
                onCancel: () => console.log('PayOS payment cancelled'),
                onSuccess: () => console.log('PayOS payment success callback'),
            });

            open();
        }

        const bankInfoCache = {};
        async function getBankInfoByBin(bin) {
            const key = String(bin || '').trim();
            if (!key) {
                return null;
            }
            if (bankInfoCache[key]) {
                return bankInfoCache[key];
            }

            try {
                const res = await fetch('https://api.vietqr.io/v2/banks');
                const json = await res.json();
                if (!json || !Array.isArray(json.data)) {
                    return null;
                }
                const found = json.data.find((b) => String(b.bin || '') === key) || null;
                bankInfoCache[key] = found;
                return found;
            } catch (e) {
                return null;
            }
        }

        function recalculateSelected() {
            let subtotal = 0;
            let count = 0;
            const selectAllCb = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.item-checkbox');

            let allChecked = true;
            let noneChecked = true;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    const id = cb.value;
                    const price_el = document.getElementById('price-' + id);
                    const qty_el = document.getElementById('qty-' + id);
                    if (price_el && qty_el) {
                        const price = parseFloat(price_el.dataset.price || '0');
                        const qty = parseInt(qty_el.innerText || '0', 10);
                        subtotal += price * qty;
                        count += qty;
                        noneChecked = false;
                    }
                } else {
                    allChecked = false;
                }
            });

            if (selectAllCb) {
                selectAllCb.checked = allChecked;
                selectAllCb.indeterminate = (!allChecked && !noneChecked);
            }

            // Update Summary UI
            const summaryTotal = document.getElementById('summary-total');
            const summarySubtotal = document.getElementById('summary-subtotal');
            const summaryCount = document.getElementById('summary-count');
            const summaryShipping = document.getElementById('summary-shipping');
            const summaryDiscountRow = document.getElementById('summary-discount-row');
            const summaryDiscount = document.getElementById('summary-discount');

            const shippingFee = calcShippingFee(subtotal);

            // Calculate Discount dynamically
            let discountAmount = 0;
            if (appliedCoupon) {
                // Re-calculate if subtotal changed (important for percentage)
                if (appliedCoupon.type === 'fixed') {
                    discountAmount = parseFloat(appliedCoupon.value);
                } else {
                    discountAmount = subtotal * (appliedCoupon.value / 100);
                    // Use a max discount if defined? We'll rely on the API for now, 
                    // or just keep the amount fixed once applied if we don't want to re-validate.
                    // Actually, let's just use the amount from the API but update if it's percentage.
                    // For simplicity, we'll use the ratio.
                    if (appliedCoupon.amount > 0 && appliedCoupon.type === 'percentage') {
                        // Re-calc based on new subtotal
                        // This might get complex if we don't have constraints here.
                        // Let's just stick to the amount returned by the API for now, 
                        // but hide if subtotal is too low?
                        // Better: just use the initial amount but cap at subtotal.
                        discountAmount = Math.min(appliedCoupon.amount, subtotal);
                    } else {
                        discountAmount = Math.min(appliedCoupon.amount, subtotal);
                    }
                }
            }

            const grandTotal = subtotal + shippingFee - discountAmount;

            if (summaryTotal) summaryTotal.innerText = formatVnd(grandTotal);
            if (summarySubtotal) summarySubtotal.innerText = formatVnd(subtotal);
            if (summaryCount) summaryCount.innerText = count + ' món được chọn';

            if (summaryDiscountRow && summaryDiscount) {
                if (discountAmount > 0) {
                    summaryDiscountRow.classList.remove('hidden');
                    summaryDiscount.innerText = '-' + formatVnd(discountAmount);
                } else {
                    summaryDiscountRow.classList.add('hidden');
                }
            }

            if (summaryShipping) {
                if (!isRoomDeliveryEnabled()) {
                    summaryShipping.innerText = '0đ (tự đến lấy)';
                    summaryShipping.classList.remove('text-green-600');
                    summaryShipping.classList.add('text-gray-800');
                } else if (shippingFee === 0) {
                    summaryShipping.innerText = 'Free ship';
                    summaryShipping.classList.remove('text-gray-800');
                    summaryShipping.classList.add('text-green-600');
                } else {
                    summaryShipping.innerText = formatVnd(shippingFee);
                    summaryShipping.classList.remove('text-green-600');
                    summaryShipping.classList.add('text-gray-800');
                }
            }

            // Disable Pay button if none selected
            const payBtn = document.querySelector('.pay-btn');
            if (payBtn) {
                if (noneChecked) {
                    payBtn.disabled = true;
                    payBtn.style.opacity = '0.5';
                    payBtn.style.cursor = 'not-allowed';
                } else {
                    payBtn.disabled = false;
                    payBtn.style.opacity = '1';
                    payBtn.style.cursor = 'pointer';
                }
            }
        }

        // -------------------- Payment Modal --------------------
        async function showPaymentModal() {
            try {
                console.log('showPaymentModal triggered');
                const selectedCheckboxes = Array.from(document.querySelectorAll('.item-checkbox:checked'));
                if (selectedCheckboxes.length === 0) {
                    showToast('Vui lòng chọn ít nhất một món để thanh toán', 'error');
                    return;
                }

                let tableNumber = '';
                let deliveryAddress = '';
                let customerPhone = '';
                let deliveryRequested = false;
                let deliveryDisplay = '';

                if (currentOrderMode === 'dine_in') {
                    const tableInput = document.getElementById('order-table-input');
                    tableNumber = tableInput ? tableInput.value.trim() : '';
                    if (!tableNumber) tableNumber = 'Bàn tự chọn';
                    deliveryDisplay = '🍽️ Tại bàn: ' + tableNumber;
                } else if (currentOrderMode === 'delivery') {
                    deliveryRequested = true;
                    const addrInput = document.getElementById('delivery-address-input');
                    const phoneInput = document.getElementById('delivery-phone-input');
                    deliveryAddress = addrInput ? addrInput.value.trim() : '';
                    customerPhone = phoneInput ? phoneInput.value.trim() : '';
                    if (!deliveryAddress) {
                        showToast('Vui lòng nhập địa chỉ nhận món', 'error');
                        if (addrInput) addrInput.focus();
                        return;
                    }
                    if (!customerPhone) {
                        showToast('Vui lòng nhập số điện thoại nhận hàng', 'error');
                        if (phoneInput) phoneInput.focus();
                        return;
                    }
                    deliveryDisplay = '🛵 Giao đến: ' + deliveryAddress;
                } else {
                    const phoneInput = document.getElementById('takeaway-phone-input');
                    customerPhone = phoneInput ? phoneInput.value.trim() : '';
                    deliveryDisplay = '🛍️ Mang về (Tự đến lấy)';
                }

                const orderNoteInput = document.getElementById('order-note');
                const orderNote = orderNoteInput ? orderNoteInput.value.trim() : '';
                const selectedCartItemIds = selectedCheckboxes.map(cb => cb.value);

                // Calculate totals visually first
                const subtotalRaw = getSubtotalFromSelected();
                const shippingFeeRaw = calcShippingFee(subtotalRaw);
                const totalRawVal = subtotalRaw + shippingFeeRaw;

                const subtotalStr = formatVnd(subtotalRaw);
                const shippingStr = deliveryRequested ? (shippingFeeRaw === 0 ? 'Miễn phí' : formatVnd(shippingFeeRaw)) : '0đ';
                const totalStr = formatVnd(totalRawVal);

                let itemsHtml = '';
                selectedCheckboxes.forEach(cb => {
                    const id = cb.value;
                    const itemEl = document.getElementById('cart-item-' + id);
                    const title = itemEl ? (itemEl.querySelector('h3') ? itemEl.querySelector('h3').innerText : 'Món ăn') : 'Món ăn';
                    const qtyEl = document.getElementById('qty-' + id);
                    const qty = qtyEl ? qtyEl.innerText : '1';
                    itemsHtml += `
                        <div style="display:flex; flex-direction:column; gap:2px; padding:8px 0; border-bottom:1px solid #e5e7eb;">
                            <span style="color:#1f2937; font-weight:700; font-size:0.82rem; line-height:1.35;">${escapeHtml(title)}</span>
                            <span style="color:#6b7280; font-weight:600; font-size:0.78rem;">Số lượng: x${qty}</span>
                        </div>
                    `;
                });

                // Step 1: Confirmation Modal with Payment Selection
                const result = await Swal.fire({
                    title: `<div style="font-family:'Quicksand',sans-serif;font-weight:800;color:#1c1917;font-size:1.25rem;">Xác nhận đặt món</div>`,
                    html: `
                    <div style="font-family:'Quicksand',sans-serif; text-align:left;">
                        <div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:12px;font-size:0.78rem;font-weight:700;color:#9a3412;">
                            <span style="padding:4px 10px;border-radius:999px;background:#ffedd5;border:1px solid #fdba74;">1. Kiểm tra món</span>
                            <span style="color:#ea580c;">•</span>
                            <span style="padding:4px 10px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;">2. Chọn thanh toán</span>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:12px; margin-bottom:12px;">
                            <div style="font-weight:800; font-size:0.8rem; color:#475569; margin-bottom:8px;">Món đã chọn</div>
                            <div style="max-height:110px; overflow-y:auto; padding-right:4px;">${itemsHtml}</div>
                        </div>
                        
                        <div style="background:#fff; border-radius:18px; border:1px solid #fed7aa; padding:12px; margin-bottom:12px;">
                            <div style="font-weight:800; color:#9a3412; margin-bottom:8px; font-size:0.85rem;">Phương thức thanh toán</div>
                            <label style="display:flex;align-items:flex-start;gap:8px;padding:10px;border:1px solid #fed7aa;border-radius:12px;margin-bottom:8px;cursor:pointer;background:#fffaf0;">
                                <input type="radio" name="payment-method" value="transfer" checked style="margin-top:2px;">
                                <span>
                                    <span style="display:block;font-weight:800;color:#374151;">Chuyển khoản QR / PayOS</span>
                                    <span style="display:block;font-size:0.75rem;color:#6b7280;">Mở app Ngân hàng quét mã thanh toán ngay.</span>
                                </span>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:8px;padding:10px;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer;background:#ffffff;">
                                <input type="radio" name="payment-method" value="direct" style="margin-top:2px;">
                                <span>
                                    <span style="display:block;font-weight:800;color:#374151;">${currentOrderMode === 'dine_in' ? 'Thanh toán tại bàn / tại quầy' : 'Tiền mặt khi nhận hàng'}</span>
                                    <span style="display:block;font-size:0.75rem;color:#6b7280;">Nhân viên sẽ hỗ trợ thanh toán trực tiếp.</span>
                                </span>
                            </label>
                        </div>

                        <div style="background:#fff7ed; border-radius:18px; border:1px solid #fdba74; padding:14px;">
                            <div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px dashed #fed7aa; font-size:0.85rem;">
                                <span style="color:#6b7280; font-weight:600;">Tạm tính</span>
                                <span style="font-weight:800; color:#1f2937;">${subtotalStr}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #fed7aa; font-size:0.85rem;">
                                <span style="color:#6b7280; font-weight:600;">Phí giao</span>
                                <span style="font-weight:800; color:${shippingFeeRaw === 0 ? '#059669' : '#1f2937'};">${shippingStr}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #fed7aa; font-size:0.85rem;">
                                <span style="color:#6b7280; font-weight:600;">Hình thức</span>
                                <span style="font-weight:800; color:#1c1917;">${escapeHtml(deliveryDisplay)}</span>
                            </div>
                            ${orderNote ? `
                            <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #fed7aa; font-size:0.85rem;">
                                <span style="color:#6b7280; font-weight:600;">Ghi chú</span>
                                <span style="font-weight:800; color:#1c1917; text-align:right; max-width:60%; word-break:break-word;">${escapeHtml(orderNote)}</span>
                            </div>
                            ` : ''}
                            <div style="display:flex; justify-content:space-between; padding:8px 0; font-size:0.95rem;">
                                <span style="color:#6b7280; font-weight:700;">Tổng thanh toán</span>
                                <span style="font-weight:800; color:#ea580c; font-size:1.15rem;">${totalStr}</span>
                            </div>
                        </div>
                    </div>
                `,
                    showCancelButton: true,
                    confirmButtonColor: '#ea580c',
                    cancelButtonColor: '#e5e7eb',
                    confirmButtonText: 'Xác nhận đặt ngay 🚀',
                    cancelButtonText: 'Quay lại',
                    customClass: { popup: 'rounded-[1.5rem] shadow-2xl' },
                    width: '680px',
                    preConfirm: () => {
                        const selectedpm = document.querySelector('input[name="payment-method"]:checked');
                        if (!selectedpm) {
                            Swal.showValidationMessage('Vui lòng chọn phương thức thanh toán');
                            return false;
                        }
                        return selectedpm.value;
                    },
                    didOpen: () => {
                        if (window.lucide) lucide.createIcons();
                    }
                });

                if (!result.isConfirmed) return;

                const paymentMethod = result.value;

                // Step 2: Show loading and call prepare_order
                Swal.fire({
                    html: `<div style="font-family:'Quicksand',sans-serif;text-align:center;padding:20px 0;">Đang tạo đơn hàng...</div>`,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    customClass: { popup: 'rounded-[1.5rem]' },
                    width: '320px',
                });

                let preparedOrder = null;
                try {
                    const prepareFd = new FormData();
                    prepareFd.append('action', 'prepare_order');
                    prepareFd.append('ajax', '1');
                    prepareFd.append('selected_ids', JSON.stringify(selectedCartItemIds));
                    prepareFd.append('order_type', currentOrderMode);
                    prepareFd.append('table_number', tableNumber);
                    prepareFd.append('delivery_requested', deliveryRequested ? '1' : '0');
                    prepareFd.append('delivery_room', deliveryAddress);
                    prepareFd.append('customer_phone', customerPhone);
                    prepareFd.append('note', orderNote);
                    prepareFd.append('payment_method', paymentMethod);
                    if (appliedCoupon && appliedCoupon.id) {
                        prepareFd.append('coupon_id', appliedCoupon.id);
                    }
                    const prepareRes = await fetch('order.php', { method: 'POST', body: prepareFd });
                    preparedOrder = await prepareRes.json();
                } catch (e) {
                    console.error('Prepare Order Error:', e);
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Lỗi kết nối', 
                        text: 'Không thể kết nối máy chủ để tạo đơn: ' + e.message, 
                        confirmButtonColor: '#ea580c' 
                    });
                    return;
                }

                if (!preparedOrder || !preparedOrder.success) {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: preparedOrder?.message || 'Không tạo được đơn hàng', confirmButtonColor: '#ea580c' });
                    return;
                }

                const orderId = preparedOrder.order_id;
                const orderCode = preparedOrder.order_code;
                // Use server's grand total for payment as source of truth
                const totalRaw = Number(preparedOrder.total_amount || 0);

                let submitPaymentLinkId = '';
                if (paymentMethod === 'transfer') {
                    let loadingShown = false;
                    Swal.fire({
                        html: `<div style="font-family:'Quicksand',sans-serif;text-align:center;padding:20px 0;">
                            <div style="font-weight:700;color:#1f1a12;">Đang tạo liên kết thanh toán...</div>
                            <div style="font-size:0.8rem;color:#6b7280;margin-top:6px;">Thời gian chờ tối đa khoảng 25 giây</div>
                           </div>`,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        customClass: { popup: 'rounded-[1.5rem]' },
                        width: '340px',
                    });
                    loadingShown = true;

                    try {
                        const payosData = await createPayOSLink(orderId, 25000);
                        const checkoutUrl = String(payosData?.checkoutUrl || '');
                        if (!checkoutUrl) {
                            throw new Error('Không nhận được liên kết thanh toán hợp lệ từ PayOS');
                        }
                        const qrCode = String(payosData?.qrCode || '');
                        const qrPayload = qrCode || checkoutUrl;
                        const bin = String(payosData?.bin || '');
                        const paymentLinkId = String(payosData?.paymentLinkId || '');
                        submitPaymentLinkId = paymentLinkId;
                        const expiredAt = Number(payosData?.expiredAt || 0);
                        const quicklink = String(payosData?.quicklink || '');
                        const accountNumber = String(payosData?.accountNumber || 'Đang cập nhật từ PayOS');
                        const accountName = String(payosData?.accountName || 'Đang cập nhật từ PayOS');
                        const transferNote = String(payosData?.description || `Thanh toan ${orderCode}`);
                        const transferAmount = formatVnd(totalRaw);
                        const transferAmountRaw = String(Math.max(0, Math.round(totalRaw)));
                        const bankInfo = await getBankInfoByBin(bin);
                        const bankName = String(bankInfo?.name || bankInfo?.shortName || 'Ngân hàng theo PayOS');
                        const bankLogo = String(bankInfo?.logo || '');

                        let qrImageUrl = '';
                        if (/^https?:\/\//i.test(quicklink)) {
                            qrImageUrl = quicklink;
                        } else if (/^https?:\/\//i.test(qrCode)) {
                            qrImageUrl = qrCode;
                        } else if (bin && accountNumber && /^\d+$/.test(transferAmountRaw)) {
                            qrImageUrl = `https://img.vietqr.io/image/${encodeURIComponent(bin)}-${encodeURIComponent(accountNumber)}-vietqr_pro.jpg?addInfo=${encodeURIComponent(transferNote)}&amount=${encodeURIComponent(transferAmountRaw)}`;
                        } else {
                            qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrPayload)}`;
                        }

                        if (loadingShown) {
                            Swal.close();
                            loadingShown = false;
                        }

                        let popupOpened = true;
                        try {
                            openPayOSDialog(checkoutUrl);
                        } catch (popupErr) {
                            popupOpened = false;
                        }

                        let timeLeftTimer = null;
                        let pollStatusTimer = null;
                        let pollBusy = false;
                        let paymentDetected = false;
                        const isMobile = window.matchMedia('(max-width: 768px)').matches;
                        const confirmResult = await Swal.fire({
                            title: 'Thanh toán chuyển khoản',
                            html: `
                            <div style="text-align:left;font-size:0.92rem;line-height:1.5;">
                                <div id="payos-info-grid" style="display:grid;grid-template-columns:250px 1fr;gap:14px;align-items:start;">
                                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:8px;">
                                        <img src="${escapeHtml(qrImageUrl)}" alt="PayOS QR" style="width:100%;aspect-ratio:1/1;object-fit:contain;border-radius:10px;background:#fff;" onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<div style=\'height:230px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:0.8rem;\'>Không thể hiển thị QR</div>');" />
                                    </div>
                                    <div style="background:#fffaf0;border:1px solid #fde68a;border-radius:12px;padding:10px;">
                                        <div style="display:flex;align-items:center;gap:8px;padding:4px 0 8px;border-bottom:1px dashed #fcd34d;">
                                            ${bankLogo ? `<img src="${escapeHtml(bankLogo)}" alt="Bank logo" style="width:24px;height:24px;border-radius:999px;object-fit:contain;background:#fff;">` : ''}
                                            <span style="color:#6b7280;">Ngân hàng</span>
                                            <b style="margin-left:auto;text-align:right;">${escapeHtml(bankName)}</b>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #fcd34d;"><span style="color:#6b7280;">Mã đơn</span><b>${escapeHtml(orderCode)}</b></div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px dashed #fcd34d;gap:8px;"><span style="color:#6b7280;">Số tài khoản</span><b style="margin-left:auto;">${escapeHtml(accountNumber)}</b><button id="copy-account-number" data-value="${escapeHtml(accountNumber)}" style="border:none;background:#dcfce7;color:#374151;border-radius:8px;padding:4px 8px;font-size:0.76rem;font-weight:700;cursor:pointer;">Sao chép</button></div>
                                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #fcd34d;"><span style="color:#6b7280;">Chủ tài khoản</span><b>${escapeHtml(accountName)}</b></div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px dashed #fcd34d;gap:8px;"><span style="color:#6b7280;">Số tiền</span><b style="color:#b45309;margin-left:auto;">${escapeHtml(transferAmount)}</b><button id="copy-transfer-amount" data-value="${escapeHtml(transferAmountRaw)}" style="border:none;background:#dcfce7;color:#374151;border-radius:8px;padding:4px 8px;font-size:0.76rem;font-weight:700;cursor:pointer;">Sao chép</button></div>
                                        <div style="padding:6px 0 2px;border-bottom:1px dashed #fcd34d;">
                                            <div style="color:#6b7280;margin-bottom:2px;">Nội dung chuyển khoản</div>
                                            <div style="display:flex;align-items:flex-start;gap:8px;">
                                                <b style="word-break:break-word;">${escapeHtml(transferNote)}</b>
                                                <button id="copy-transfer-note" data-value="${escapeHtml(transferNote)}" style="border:none;background:#dcfce7;color:#374151;border-radius:8px;padding:4px 8px;font-size:0.76rem;font-weight:700;cursor:pointer;flex-shrink:0;margin-bottom:2px;">Sao chép</button>
                                            </div>
                                        </div>
                                        <div style="padding-top:8px;color:#374151;"><span style="color:#6b7280;">Lưu ý:</span> Nhập chính xác <b>${escapeHtml(transferAmount)}</b> khi chuyển khoản.</div>
                                    </div>
                                </div>
                                <div style="margin-top:8px;color:#374151;">Thời gian còn lại: <b id="payos-time-left">--:--</b></div>
                                <div style="margin-top:6px;color:#374151;">Trạng thái thanh toán: <b id="payos-live-status">Đang chờ xác nhận</b></div>
                                </div>
                            </div>
                        `,
                            showCancelButton: true,
                            showDenyButton: true,
                            confirmButtonText: 'Tôi đã thanh toán',
                            denyButtonText: 'Mở tab mới',
                            cancelButtonText: 'Hủy',
                            confirmButtonColor: '#d97706',
                            denyButtonColor: '#2563eb',
                            cancelButtonColor: '#9ca3af',
                            width: isMobile ? '95vw' : '820px',
                            didOpen: () => {
                                const popup = Swal.getPopup();
                                if (popup) {
                                    popup.style.maxWidth = isMobile ? '95vw' : '820px';
                                }

                                const grid = document.getElementById('payos-info-grid');
                                if (grid && isMobile) {
                                    grid.style.gridTemplateColumns = '1fr';
                                }

                                const actions = Swal.getActions();
                                if (actions && isMobile) {
                                    actions.style.display = 'grid';
                                    actions.style.gridTemplateColumns = '1fr';
                                    actions.style.width = '100%';
                                    actions.style.marginTop = '14px';
                                    actions.style.paddingLeft = '8px';
                                    actions.style.paddingRight = '8px';
                                    actions.style.boxSizing = 'border-box';
                                    actions.style.gap = '8px';
                                    const buttons = actions.querySelectorAll('button');
                                    buttons.forEach((btn) => {
                                        btn.style.width = '100%';
                                        btn.style.margin = '0';
                                    });
                                }

                                const timeEl = document.getElementById('payos-time-left');
                                if (timeEl && expiredAt > 0) {
                                    const updateTimeLeft = () => {
                                        const now = Math.floor(Date.now() / 1000);
                                        const remain = Math.max(0, expiredAt - now);
                                        const mm = String(Math.floor(remain / 60)).padStart(2, '0');
                                        const ss = String(remain % 60).padStart(2, '0');
                                        timeEl.textContent = `${mm}:${ss}`;
                                    };
                                    updateTimeLeft();
                                    timeLeftTimer = setInterval(updateTimeLeft, 1000);
                                }

                                const bindCopy = (id, successMessage) => {
                                    const btn = document.getElementById(id);
                                    if (!btn) return;
                                    btn.addEventListener('click', async () => {
                                        const value = btn.getAttribute('data-value') || '';
                                        try {
                                            await navigator.clipboard.writeText(value);
                                            showToast(successMessage);
                                        } catch (e) {
                                            showToast('Không thể sao chép', 'error');
                                        }
                                    });
                                };
                                bindCopy('copy-account-number', 'Đã sao chép số tài khoản');
                                bindCopy('copy-transfer-amount', 'Đã sao chép số tiền');
                                bindCopy('copy-transfer-note', 'Đã sao chép nội dung chuyển khoản');

                                const statusEl = document.getElementById('payos-live-status');
                                const updateStatusText = (text, color = '#374151') => {
                                    if (!statusEl) return;
                                    statusEl.textContent = text;
                                    statusEl.style.color = color;
                                };

                                const stopStatusPolling = () => {
                                    if (pollStatusTimer) {
                                        clearInterval(pollStatusTimer);
                                        pollStatusTimer = null;
                                    }
                                };

                                const pollPaymentStatus = async () => {
                                    if (pollBusy || paymentDetected) {
                                        return;
                                    }

                                    pollBusy = true;
                                    try {
                                        const statusData = await checkOrderStatus(orderId, 12000);
                                        const orderStatus = String(statusData?.status || '').toLowerCase();

                                        if (statusData?.paid) {
                                            paymentDetected = true;
                                            updateStatusText('Đã thanh toán thành công', '#059669');
                                            stopStatusPolling();
                                            showToast('✨ Hệ thống đã xác nhận thanh toán thành công!');
                                            const confirmButton = Swal.getConfirmButton();
                                            if (confirmButton) {
                                                confirmButton.click();
                                            }
                                            return;
                                        }

                                        if (orderStatus === 'cancelled') {
                                            updateStatusText('Đơn đã bị hủy', '#dc2626');
                                            stopStatusPolling();
                                        } else if (orderStatus === 'paid') {
                                            updateStatusText('Đã thanh toán thành công', '#059669');
                                            stopStatusPolling();
                                        } else if (orderStatus === 'pending') {
                                            updateStatusText('Hệ thống đang xác nhận thanh toán', '#b45309');
                                        } else {
                                            updateStatusText('Đang chờ cập nhật trạng thái đơn', '#374151');
                                        }
                                    } catch (pollErr) {
                                        // Keep polling even if one request fails intermittently.
                                    } finally {
                                        pollBusy = false;
                                    }
                                };

                                pollPaymentStatus();
                                pollStatusTimer = setInterval(pollPaymentStatus, 4000);
                            },
                            willClose: () => {
                                if (timeLeftTimer) {
                                    clearInterval(timeLeftTimer);
                                    timeLeftTimer = null;
                                }
                                if (pollStatusTimer) {
                                    clearInterval(pollStatusTimer);
                                    pollStatusTimer = null;
                                }
                            }
                        });

                        if (confirmResult.isDenied) {
                            window.open(checkoutUrl, '_blank');
                            return;
                        }

                        if (!confirmResult.isConfirmed) {
                            try {
                                const payosCancelFd = new FormData();
                                payosCancelFd.append('order_id', String(orderId));
                                payosCancelFd.append('payment_link_id', paymentLinkId);
                                payosCancelFd.append('reason', 'Khach huy thanh toan');
                                await fetch('src/api/payos_cancel_payment.php', { method: 'POST', body: payosCancelFd });

                                const cancelFd = new FormData();
                                cancelFd.append('action', 'cancel_prepared_order');
                                cancelFd.append('ajax', '1');
                                cancelFd.append('order_id', String(orderId));
                                await fetch('order.php', { method: 'POST', body: cancelFd });
                            } catch (cancelErr) {
                                // ignore cancellation errors
                            }
                            showToast('Đã hủy thanh toán');
                            return;
                        }
                    } catch (e) {
                        if (loadingShown) {
                            Swal.close();
                            loadingShown = false;
                        }
                        // CRITICAL: Cleanup the orphaned order if payment link creation fails
                        try {
                            const failCleanupFd = new FormData();
                            failCleanupFd.append('action', 'cancel_prepared_order');
                            failCleanupFd.append('ajax', '1');
                            failCleanupFd.append('order_id', String(orderId));
                            await fetch('order.php', { method: 'POST', body: failCleanupFd });
                        } catch(cleanupErr) {
                            console.error('Cleanup failed after PayOS failure', cleanupErr);
                        }

                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Lỗi thanh toán ❌', 
                            text: 'Gặp sự cố khi kết nối cổng thanh toán. Đơn hàng của bạn chưa được lưu để tránh sai sót. Vui lòng thử lại!', 
                            confirmButtonColor: '#d97706' 
                        });
                        return;
                    }
                } else {
                    const directResult = await Swal.fire({
                        title: 'Thanh toán trực tiếp',
                        html: `
                        <div style="font-family:'Quicksand',sans-serif;text-align:left;line-height:1.5;">
                            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px;margin-bottom:10px;">
                                <div style="font-weight:800;color:#9a3412;margin-bottom:6px;">Bạn đã chọn trả tiền trực tiếp</div>
                                <div style="font-size:0.85rem;color:#7c2d12;">Vui lòng thanh toán tại quầy khi nhận món. Đơn sẽ được gửi cho nhà hàng ngay sau khi bạn xác nhận.</div>
                            </div>
                            <div style="font-size:0.83rem;color:#6b7280;">Mã đơn: <b style="color:#92400e;">${orderCode}</b></div>
                        </div>
                    `,
                        showCancelButton: true,
                        confirmButtonText: 'Xác nhận gửi đơn',
                        cancelButtonText: 'Quay lại',
                        confirmButtonColor: '#d97706',
                        width: '480px',
                        customClass: { popup: 'rounded-[1.5rem] shadow-2xl' }
                    });
                    if (!directResult.isConfirmed) {
                        return;
                    }
                }

                Swal.fire({
                    html: `<div style="font-family:'Quicksand',sans-serif;text-align:center;padding:20px 0;">Đang gửi đơn hàng...</div>`,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    customClass: { popup: 'rounded-[1.5rem]' },
                    width: '320px',
                });

                try {
                    const submitFd = new FormData();
                    submitFd.append('action', 'submit_order');
                    submitFd.append('ajax', '1');
                    submitFd.append('order_id', String(orderId));
                    submitFd.append('payment_mode', paymentMethod === 'transfer' ? 'transfer' : 'direct');
                    if (submitPaymentLinkId) {
                        submitFd.append('payment_link_id', submitPaymentLinkId);
                    }
                    const submitRes = await fetch('order.php', { method: 'POST', body: submitFd });
                    const submitData = await submitRes.json();

                    if (submitData.success) {
                        let finalStatus = String(submitData.new_status || '').toLowerCase();
                        if (paymentMethod === 'transfer') {
                            try {
                                const latestOrderStatus = await checkOrderStatus(orderId, 6000);
                                if (latestOrderStatus?.success) {
                                    finalStatus = String(latestOrderStatus.status || finalStatus).toLowerCase();
                                }
                            } catch (statusErr) {
                                // Fallback to submit response when status polling fails.
                            }
                        }

                        const isPaidOrder = finalStatus === 'paid';
                        const isTransferOrder = paymentMethod === 'transfer';
                        Swal.fire({
                            html: `<div style="text-align:center;padding:12px 4px 8px;font-family:'Quicksand',sans-serif;">
                                <div style="width:78px;height:78px;border-radius:22px;background:linear-gradient(145deg,#f59e0b,#d97706);box-shadow:0 10px 24px rgba(217,119,6,0.34);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:#fff;">${isPaidOrder ? '✓' : (isTransferOrder ? '⌛' : '🚀')}</div>
                                <div style="font-size:1.38rem;font-weight:800;color:#1f1a12;margin-bottom:8px;">${isPaidOrder ? 'Thanh toán thành công! ✨' : (isTransferOrder ? 'Đang chờ xác nhận... ⏳' : 'Đặt hàng thành công! 🚀')}</div>
                                <div style="font-size:0.9rem;color:#5b4a34;margin-bottom:12px;line-height:1.5;">${isPaidOrder ? 'Tuyệt vời! Đơn hàng của bạn đã được thanh toán và đang chờ Nhà hàng chuẩn bị.' : (isTransferOrder ? 'Hệ thống đã ghi nhận yêu cầu. Đơn hàng sẽ tự động cập nhật ngay khi giao dịch hoàn tất.' : 'Nhà hàng đã nhận được đơn của bạn và sẽ sớm xử lý. Chúc bạn ngon miệng!')}</div>
                                <div style="background:#fff7ed;border-radius:16px;padding:12px 18px;border:1px solid #fed7aa;display:inline-block;margin-bottom:10px;box-shadow:0 3px 10px rgba(217,119,6,0.12);">
                                    <div style="font-size:0.72rem;color:#9a3412;font-weight:800;text-transform:uppercase;letter-spacing:1px;">Mã đơn: ${orderCode}</div>
                                </div>
                            </div>`,
                            showConfirmButton: true,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d97706',
                            customClass: { popup: 'rounded-[1.35rem] shadow-2xl' },
                            width: '420px',
                        }).then(() => { window.location.href = 'my_orders.php'; });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Rất tiếc... 😥', text: submitData.message || 'Không thể gửi đơn hàng lúc này', confirmButtonColor: '#d97706' });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Lỗi kết nối 📡', text: 'Không thể kết nối tới máy chủ. Vui lòng thử lại sau!', confirmButtonColor: '#d97706' });
                }
            } catch (err) {
                console.error('showPaymentModal critical error:', err);
                Swal.fire({ icon: 'error', title: 'Lỗi ứng dụng', text: err.message, confirmButtonColor: '#d97706' });
            }
        }


        // -------------------- Cart AJAX --------------------
        const qtySyncTimers = {};
        const qtyRequestSeq = {};
        const lastSyncedQty = {};

        function applyQtyUI(cartItemId, quantity) {
            const qtyEl = document.getElementById('qty-' + cartItemId);
            if (qtyEl) {
                qtyEl.innerText = quantity;
            }

            const priceEl = document.getElementById('price-' + cartItemId);
            const sub = document.getElementById('subtotal-' + cartItemId);
            if (priceEl && sub) {
                const price = parseFloat(priceEl.dataset.price || '0');
                sub.innerText = new Intl.NumberFormat('vi-VN').format(price * quantity) + 'đ';
            }

            recalculateSelected();
        }

        function queueQtySync(cartItemId, quantity) {
            if (qtySyncTimers[cartItemId]) {
                clearTimeout(qtySyncTimers[cartItemId]);
            }

            const seq = (qtyRequestSeq[cartItemId] || 0) + 1;
            qtyRequestSeq[cartItemId] = seq;

            qtySyncTimers[cartItemId] = setTimeout(() => {
                updateCart('update', cartItemId, quantity, { silent: true, optimistic: true, expectedSeq: seq });
            }, 180);
        }

        async function updateCart(action, cartItemId, quantity = null, options = {}) {
            const { silent = false, optimistic = false, expectedSeq = null } = options;
            const fd = new FormData();
            fd.append('action', action);
            fd.append('cart_item_id', cartItemId);
            if (quantity !== null) fd.append('quantity', quantity);
            fd.append('ajax', '1');

            try {
                const res = await fetch('order.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (action === 'update' && expectedSeq !== null && expectedSeq !== qtyRequestSeq[cartItemId]) {
                    return;
                }

                if (data.success) {
                    if (action === 'remove' || quantity === 0) {
                        if (qtySyncTimers[cartItemId]) {
                            clearTimeout(qtySyncTimers[cartItemId]);
                            delete qtySyncTimers[cartItemId];
                        }
                        delete lastSyncedQty[cartItemId];
                        delete qtyRequestSeq[cartItemId];

                        const el = document.getElementById('cart-item-' + cartItemId);
                        if (el) {
                            el.style.transition = 'all 0.35s ease';
                            el.style.opacity = '0';
                            el.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                el.remove();
                                if (data.cart_count === 0) window.location.reload();
                                recalculateSelected();
                            }, 360);
                        }
                        if (!silent) showToast('🗑️ Đã xóa món khỏi giỏ hàng');
                    } else if (action === 'update') {
                        lastSyncedQty[productId] = quantity;
                        if (!optimistic) {
                            applyQtyUI(productId, quantity);
                        }
                        if (!silent) showToast('Đã cập nhật số lượng');
                    }

                    // Update summary display if needed (though recalculateSelected handles it)
                    // document.getElementById('summary-count').innerText = data.cart_count + ' món';
                }
            } catch (err) {
                console.error('Update Cart Error:', err);
                if (action === 'update' && optimistic) {
                    const rollbackQty = lastSyncedQty[productId];
                    if (typeof rollbackQty === 'number' && rollbackQty > 0) {
                        applyQtyUI(productId, rollbackQty);
                    }
                }
                showToast('Có lỗi xảy ra: ' + err.message, 'error');
            }
        }

        function askDelete(id) {
            Swal.fire({
                title: '<div style="font-family:\'Quicksand\',sans-serif;font-size:1.9rem;font-weight:800;color:#2d1f0e;">Bỏ món này đi?</div>',
                html: `
                    <div style="text-align:center;font-family:'Quicksand',sans-serif;color:#6b7280;font-size:1.05rem;line-height:1.6;">
                        Bạn chắc chắn muốn xóa món khỏi giỏ hàng không?
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'Xóa món',
                cancelButtonText: 'Giữ lại',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-[1.6rem] shadow-2xl',
                    confirmButton: 'font-bold px-6 py-2.5 rounded-xl',
                    cancelButton: 'font-bold px-6 py-2.5 rounded-xl'
                }
            }).then((result) => { if (result.isConfirmed) updateCart('remove', id); });
        }

        function clearCart() {
            Swal.fire({
                title: '<div style="font-family:\'Quicksand\',sans-serif;font-size:1.7rem;font-weight:800;color:#2d1f0e;">Xóa toàn bộ giỏ hàng?</div>',
                html: `
                    <div style="text-align:center;font-family:'Quicksand',sans-serif;color:#6b7280;font-size:1rem;line-height:1.6;">
                        Hành động này sẽ xóa tất cả món đã chọn trong giỏ.
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'Xóa tất cả',
                cancelButtonText: 'Giữ lại',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-[1.6rem] shadow-2xl',
                    confirmButton: 'font-bold px-6 py-2.5 rounded-xl',
                    cancelButton: 'font-bold px-6 py-2.5 rounded-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'clear'); fd.append('ajax', '1');
                    fetch('order.php', { method: 'POST', body: fd }).then(() => window.location.reload());
                }
            });
        }

        function updateQty(id, change) {
            const qtyEl = document.getElementById('qty-' + id);
            let current = parseInt(qtyEl.innerText);
            let newQty = current + change;
            if (newQty < 1) { askDelete(id); return; }
            applyQtyUI(id, newQty);
            queueQtySync(id, newQty);
        }

        function removeItem(id) { askDelete(id); }

        // --- Add to Cart for Suggestions ---
        async function addToCart(productId, title, price, image) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('title', title);
            formData.append('price', price);
            formData.append('image', image);
            formData.append('ajax', '1');

            try {
                const response = await fetch('src/api/add_to_order.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showToast(' Đã thêm món vào giỏ hàng');
                    setTimeout(() => window.location.reload(), 800);
                }
            } catch (err) { console.error(err); }
        }

        window.addEventListener('load', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            recalculateSelected(); // Initial calculation
        });
    </script>
    <div id="payos_checkout_root"></div>
    <?php include 'src/includes/food_detail_modal.php'; ?>
    <?php include 'src/includes/order_tracking_widget.php'; ?>
    <?php include 'src/includes/table_service_widget.php'; ?>
    <?php include 'src/includes/floating_reservation_btn.php'; ?>
</body>

</html>
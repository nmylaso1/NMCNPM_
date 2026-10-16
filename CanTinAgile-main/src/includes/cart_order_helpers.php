<?php



function renderStars(float $rating): string {
    $rating = max(0.0, min(5.0, $rating));
    $display = number_format($rating, 1);
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $html .= '<i data-lucide="star" class="w-3 h-3 fill-current"></i>';
        } else if ($i == ceil($rating) && ($rating - floor($rating)) > 0) {
            $html .= '<i data-lucide="star-half" class="w-3 h-3 fill-current"></i>';
        } else {
            $html .= '<i data-lucide="star" class="w-3 h-3 text-gray-200"></i>';
        }
    }
    $html .= '<span class="outer-rating-num text-[10px] font-bold text-[#a39a85] ml-1">' . $display . '</span>';
    return $html;
}



function getActiveShippingPolicy(?mysqli $conn = null): array
{
    $defaults = ['threshold_1' => 100000.0, 'fee_1' => 10000.0];
    if (!$conn) { return $defaults; }
    $defaults = [
        'threshold_1' => 100000.0,
        'fee_1' => 10000.0,
    ];

    $res = $conn->query("SELECT threshold_1, fee_1
                        FROM shipping_policies
                        WHERE is_active = 1
                        ORDER BY id ASC
                        LIMIT 1");
    if (!$res || !$res->num_rows) {
        return $defaults;
    }

    $row = $res->fetch_assoc();
    $t1 = (float)($row['threshold_1'] ?? $defaults['threshold_1']);

    return [
        'threshold_1' => $t1,
        'fee_1' => max(0, (float)($row['fee_1'] ?? $defaults['fee_1'])),
    ];
}

function ensureSessionUser(?mysqli $conn = null): int
{
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        return (int)$_SESSION['user_id'];
    }
    if (!$conn) {
        return 0;
    }
    $guest_username = 'guest_' . bin2hex(random_bytes(6));
    $guest_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $table = $_SESSION['table_number'] ?? null;
    $guest_name = $table ? ('Khách ' . $table) : 'Khách vãng lai';

    $stmt = $conn->prepare("INSERT INTO accounts (username, password, full_name, role) VALUES (?, ?, ?, 'guest')");
    if ($stmt) {
        $stmt->bind_param("sss", $guest_username, $guest_pass, $guest_name);
        if ($stmt->execute()) {
            $new_id = (int)$stmt->insert_id;
            $_SESSION['user_id'] = $new_id;
            $_SESSION['role'] = 'guest';
            $_SESSION['full_name'] = $guest_name;
            $_SESSION['username'] = $guest_username;
            $stmt->close();
            return $new_id;
        }
        $stmt->close();
    }
    return 0;
}

function getOrCreateCartId(?mysqli $conn = null, int $userId = 0): int
{
    if (!$conn) { return 0; }
    $userId = (int) $userId;
    if ($userId <= 0) {
        return 0;
    }

    // Kiểm tra xem đã có giỏ hàng chưa (tránh INSERT IGNORE trên mỗi request)
    $res = $conn->query("SELECT id FROM carts WHERE user_id = $userId LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int) ($row['id'] ?? 0);
    }

    $conn->query("INSERT IGNORE INTO carts (user_id, status) VALUES ($userId, 'active')");
    $res = $conn->query("SELECT id FROM carts WHERE user_id = $userId LIMIT 1");
    if (!$res || !$res->num_rows) {
        return 0;
    }

    $row = $res->fetch_assoc();
    return (int) ($row['id'] ?? 0);
}

function syncSessionCartFromDb(?mysqli $conn = null, int $userId = 0): array
{
    if (!$conn) { return $_SESSION['cart'] ?? []; }
    $cart = [];
    $cartId = getOrCreateCartId($conn, $userId);
    if ($cartId <= 0) {
        $_SESSION['cart'] = [];
        return [];
    }

    $sql = "
        SELECT ci.id AS cart_item_id, ci.product_id, ci.quantity, ci.unit_price, ci.is_selected_for_checkout, ci.product_name,
               p.title AS base_title, p.image
        FROM cart_items ci
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = $cartId
        ORDER BY ci.id ASC
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cart_item_id = (int) ($row['cart_item_id'] ?? 0);
            $pid = (int) ($row['product_id'] ?? 0);
            if ($cart_item_id <= 0) {
                continue;
            }
            // Use saved product_name if it exists, fallback to base product title
            $display_title = !empty($row['product_name']) ? $row['product_name'] : $row['base_title'];
            
            $cart[$cart_item_id] = [
                'cart_item_id' => $cart_item_id,
                'id' => $pid,
                'title' => (string) $display_title,
                'price' => (float) ($row['unit_price'] ?? 0),
                'image' => (string) ($row['image'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'is_selected_for_checkout' => (int) ($row['is_selected_for_checkout'] ?? 1)
            ];
        }
    }

    $_SESSION['cart'] = $cart;
    return $cart;
}

function getCartCountFromDb(?mysqli $conn = null, int $userId = 0): int
{
    if (!$conn || $userId <= 0) { return 0; }
    $userId = (int) $userId;

    // Tối ưu 1 câu query duy nhất gom carts + cart_items, giảm 2 lượt round-trip tới Cloud DB
    $res = $conn->query("SELECT COALESCE(SUM(ci.quantity), 0) AS total_items 
                         FROM carts c 
                         JOIN cart_items ci ON c.id = ci.cart_id 
                         WHERE c.user_id = $userId");
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['total_items'] ?? 0);
}

function logCartAction(?mysqli $conn = null, int $cartId = 0, int $userId = 0, int $productId = 0, string $actionType = '', int $oldQty = 0, int $newQty = 0): void
{
    if (!$conn) { return; }
    $cartId = (int) $cartId;
    $userId = (int) $userId;
    $productId = (int) $productId;
    $oldQty = (int) $oldQty;
    $newQty = (int) $newQty;
    $actionTypeEscaped = $conn->real_escape_string($actionType);

    $conn->query("INSERT INTO cart_item_logs (cart_id, user_id, product_id, action_type, old_quantity, new_quantity)
                  VALUES ($cartId, $userId, $productId, '$actionTypeEscaped', $oldQty, $newQty)");
}

function getCanteenSettings(?mysqli $conn = null): array {
    if (!$conn) { return []; }
    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM canteen_settings");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $settings;
}

function getActiveAnnouncements(?mysqli $conn = null): array {
    if (!$conn) { return []; }
    $list = [];
    $res = $conn->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $list[] = $r;
        }
    }
    return $list;
}

/**
 * Ghi nhật ký thao tác hệ thống (Audit / Action Log)
 * Theo dõi hành động của Nhân viên & Admin (ẩn/hiện/sửa món, duyệt đơn, xếp thực đơn...)
 */
function log_system_action(?mysqli $conn, ?int $user_id, string $action_type, string $target_title, string $description = ''): void {
    if (!$conn) return;

    static $table_checked = false;
    if (!$table_checked) {
        $conn->query("CREATE TABLE IF NOT EXISTS system_action_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            target_title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_time (created_at),
            INDEX idx_action_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $table_checked = true;
    }

    $uid = $user_id ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $conn->prepare("INSERT INTO system_action_logs (user_id, action_type, target_title, description, ip_address) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('issss', $uid, $action_type, $target_title, $description, $ip);
        $stmt->execute();
    }
}


<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'src/includes/config.php';

// Giả lập thời gian load (API processing) - Đã xóa sleep(1) để hết lag
// sleep(1);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

// Giải phóng khóa Session sớm để ko chặn các request khác cùng lúc
session_write_close();

$time = isset($_GET['time']) ? $_GET['time'] : date('H:i');
$budget = isset($_GET['budget']) ? floatval($_GET['budget']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 4;

$response = [
    'success' => false,
    'user_id' => $user_id,
    'recommendations' => []
];

// Lấy tất cả sản phẩm (chỉ lấy món đang hiển thị)
$products = [];
$sql = "SELECT id as dish_id, title as name, price, image, description FROM products WHERE is_hidden = 0";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['score'] = 0;
        $row['reasons'] = [];
        $products[$row['dish_id']] = $row;
    }
}

if(empty($products)) {
    $response['message'] = "Không có sản phẩm nào trong DB.";
    echo json_encode($response);
    exit;
}

// 1. TÍNH ĐIỂM XU HƯỚNG (Trend - Top bán chạy)
$top_sellers = [];
$checkOrderItems = $conn->query("SHOW TABLES LIKE 'order_items'");
if ($checkOrderItems && $checkOrderItems->num_rows > 0) {
    $trend_sql = "SELECT product_id, COUNT(product_id) as total_sold FROM order_items GROUP BY product_id ORDER BY total_sold DESC LIMIT 5";
    $trend_res = $conn->query($trend_sql);
    if ($trend_res && $trend_res->num_rows > 0) {
        while($row = $trend_res->fetch_assoc()) {
            $top_sellers[] = $row['product_id'];
        }
    }
}

// 2. TÍNH ĐIỂM LỊCH SỬ MUA (Purchase History)
$history = [];
if ($user_id > 0 && $checkOrderItems && $checkOrderItems->num_rows > 0) {
    $hist_sql = "SELECT product_id, COUNT(product_id) as times_bought 
                 FROM order_items oi 
                 JOIN orders o ON o.id = oi.order_id 
                 WHERE o.user_id = $user_id 
                 GROUP BY product_id";
    $hist_res = $conn->query($hist_sql);
    if ($hist_res && $hist_res->num_rows > 0) {
        while($row = $hist_res->fetch_assoc()) {
            $history[$row['product_id']] = $row['times_bought'];
        }
    }
}

// 3. TÍNH ĐIỂM THỜI ĐIỂM (Time-based & Daily Menu)
$hour = intval(explode(':', $time)[0]);
$current_meal = 'Trưa'; // Default
if ($hour >= 5 && $hour <= 10) $current_meal = 'Sáng';
if ($hour >= 11 && $hour <= 14) $current_meal = 'Trưa';
if ($hour >= 15 && $hour <= 22) $current_meal = 'Tối';

$daily_menu_items = [];
$menu_sql = "SELECT product_id FROM daily_menu WHERE serving_date = CURDATE() AND meal_type = '$current_meal'";
$menu_res = $conn->query($menu_sql);
if ($menu_res && $menu_res->num_rows > 0) {
    while($row = $menu_res->fetch_assoc()) {
        $daily_menu_items[] = $row['product_id'];
    }
}

// CHẤM ĐIỂM TỪNG MÓN
foreach ($products as $id => &$p) {
    $title = $p['name'];
    
    // Áp dụng Rule 1: Lịch sử mua (+30đ cho mỗi lần mua)
    if (isset($history[$id])) {
        $p['score'] += ($history[$id] * 30);
        $p['reasons'][] = "Món quen thuộc bạn hay đặt gần đây";
    }
    
    // Áp dụng Rule 5: Xu hướng (+20đ)
    if (in_array($id, $top_sellers)) {
        $p['score'] += 20;
        $p['reasons'][] = "Món Best-seller đang bán cực chạy";
    }
    
    // Áp dụng Rule 3: Thời điểm / Daily Menu (+25đ)
    if (in_array($id, $daily_menu_items)) {
        $p['score'] += 25;
        $p['reasons'][] = "Nằm trong thực đơn nổi bật buổi $current_meal";
    } else {
        // Fallback theo tên món
        if ($current_meal == 'Sáng' && (stripos($title, 'bún') !== false || stripos($title, 'phở') !== false || stripos($title, 'bánh') !== false || stripos($title, 'trứng') !== false)) {
            $p['score'] += 15;
            $p['reasons'][] = "Gợi ý chuẩn bài cho bữa Sáng nhẹ nhàng";
        }
        if (($current_meal == 'Trưa' || $current_meal == 'Tối') && stripos($title, 'cơm') !== false) {
            $p['score'] += 15;
            $p['reasons'][] = "Bữa $current_meal cần ăn cơm cho chắc bụng";
        }
    }
    
    // Áp dụng Rule 6: Chi tiêu (Budget)
    if ($budget > 0) {
        if ($p['price'] <= $budget) {
            $p['score'] += 10;
            $p['reasons'][] = "Rất vừa túi tiền của bạn (dưới " . number_format($budget, 0, ',', '.') . "đ)";
        } else {
            // Trừ điểm mạnh nếu vượt quá ngân sách
            $p['score'] -= 100;
        }
    }
    
    // Random nhẹ (+ 0-5đ random) để menu linh động
    $p['score'] += rand(0, 5);
    
    // Chọn lý do hiển thị
    if (empty($p['reasons'])) {
        $p['reason'] = "Món ăn đặc sắc bạn có thể sẽ thích hôm nay!";
    } else {
        $p['reason'] = $p['reasons'][0]; // Lấy lý do có trọng số cao nhất (được add đầu tiên)
    }
}

// Lọc CỨNG món vượt ngân sách (không xuất hiện dù điểm cao)
if ($budget > 0) {
    $products = array_filter($products, function($p) use ($budget) {
        return (float)$p['price'] <= $budget;
    });
}

// Sắp xếp giảm dần theo điểm
usort($products, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// Lấy top N
$top_recommendations = array_slice(array_values($products), 0, $limit);

$response['success'] = true;
$response['recommendations'] = $top_recommendations;

echo json_encode($response);
$conn->close();
?>

<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Giải phóng khóa Session vì ở dưới chỉ đọc từ DB, ko lưu gì vào SESSION nữa
session_write_close();

// Favorite Ids for the current user
$favoriteIds = [];
if ($user_id > 0) {
    $favRes = $conn->query("SELECT product_id FROM favorites WHERE user_id = $user_id");
    if ($favRes) {
        while ($frow = $favRes->fetch_assoc()) {
            $favoriteIds[] = (int)$frow['product_id'];
        }
    }
}

// Build Query
$where = "WHERE p.is_hidden = 0";
if ($category_id > 0) {
    $where .= " AND p.category_id = " . (int)$category_id;
}
if (!empty($search)) {
    $safeSearch = $conn->real_escape_string($search);
    $where .= " AND (p.title LIKE '%$safeSearch%' OR p.description LIKE '%$safeSearch%')";
}

$orderBy = "p.created_at DESC";
if ($sort === 'price_low') $orderBy = "p.price ASC";
if ($sort === 'price_high') $orderBy = "p.price DESC";

$sql = "SELECT p.*, cp.name AS category_name FROM products p LEFT JOIN categories_products cp ON cp.id = p.category_id $where ORDER BY $orderBy";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-12">';
    while ($p = $res->fetch_assoc()) {
        $isFav = in_array((int)$p['id'], $favoriteIds);
        $titleEsc = addslashes($p['title']);
        $imgEsc = addslashes($p['image']);
        $price = number_format($p['price'], 0, ',', '.');
        $desc = htmlspecialchars($p['description'] ?: 'Hương vị thơm ngon, chuẩn phong cách hiện đại...');
        $jsonStr = htmlspecialchars(json_encode($p, JSON_HEX_APOS), ENT_QUOTES, 'UTF-8');
        
        $favClass = $isFav ? 'bg-red-50 text-red-500' : 'text-gray-300';
        $favIconClass = $isFav ? 'fill-current' : '';
        
        echo <<<HTML
        <article class="product-card bg-white rounded-[2rem] p-5 shadow-sm border border-orange-100 flex flex-col relative group">
            <div class="relative -mt-12 mb-4">
                <div class="food-img-wrapper cursor-pointer" onclick='openFoodDetailModal({$jsonStr})'>
                    <img src="{$p['image']}" alt="{$p['title']}">
                </div>
            </div>
            <div class="flex-1 text-center space-y-2">
                <h3 class="text-base font-bold text-[#2d261a] line-clamp-1 group-hover:text-amber-600 transition cursor-pointer" onclick='openFoodDetailModal({$jsonStr})'>
                    {$p['title']}
                </h3>
                <p class="text-[11px] text-gray-400 font-medium line-clamp-2 leading-relaxed h-8">
                    {$desc}
                </p>
            </div>
            <div class="mt-4 pt-4 border-t border-dashed border-gray-100 flex items-center justify-between">
                <span class="text-lg font-bold text-amber-600">{$price}đ</span>
                <div class="flex gap-2">
                    <button onclick="toggleFavorite({$p['id']}, this)" 
                            class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 transition-all hover:bg-red-50 hover:text-red-500 {$favClass}">
                        <i data-lucide="heart" class="w-4 h-4 {$favIconClass}"></i>
                    </button>
                    <button onclick="event.stopPropagation(); addToCart({$p['id']}, '{$titleEsc}', {$p['price']}, '{$imgEsc}', this, true)" 
                            class="w-9 h-9 bg-amber-100 text-amber-700 rounded-xl hover:bg-amber-200 transition-all flex items-center justify-center" title="Mua nhanh">
                        <i data-lucide="zap" class="w-4 h-4 fill-current"></i>
                    </button>
                    <button onclick="event.stopPropagation(); addToCart({$p['id']}, '{$titleEsc}', {$p['price']}, '{$imgEsc}', this)" 
                            class="w-9 h-9 bg-amber-600 text-white rounded-xl shadow-lg shadow-amber-600/20 hover:bg-amber-700 transition-all flex items-center justify-center">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </article>
HTML;
    }
    echo '</div>';
} else {
    echo <<<HTML
    <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="search-x" class="w-10 h-10 text-gray-300"></i>
        </div>
        <h3 class="text-xl font-bold text-[#2d261a]">Không tìm thấy món ăn</h3>
        <p class="text-gray-400 text-sm mt-1">Vui lòng thử tìm kiếm với từ khóa khác hoặc chọn danh mục khác.</p>
        <button onclick="resetFilters()" class="mt-6 px-6 py-2.5 bg-amber-100 text-amber-700 font-bold rounded-full hover:bg-amber-200 transition">Xem tất cả</button>
    </div>
HTML;
}
?>

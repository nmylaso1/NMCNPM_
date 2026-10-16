<?php
session_start();
require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

// Bắt tham số mã bàn nếu quét QR (vd: menu.php?table=05)
if (isset($_GET['table']) && trim($_GET['table']) !== '') {
    $tNum = trim($_GET['table']);
    $_SESSION['table_number'] = str_starts_with(mb_strtolower($tNum), 'bàn') ? $tNum : ('Bàn ' . $tNum);
    $_SESSION['order_type'] = 'dine_in';
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && isset($conn)) {
    $user_id = ensureSessionUser($conn);
}

// Giải phóng SESSION để các request khác ko bị treo
session_write_close();
if ($user_id > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, $user_id);
} else {
    $cart_count = 0;
}

if (isset($conn)) {
    $canteenSettings = getCanteenSettings($conn);
} else {
    $canteenSettings = [];
}


// Categories
$categories = [];
$catRes = $conn ? $conn->query("SELECT * FROM categories_products ORDER BY id ASC") : null;
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}
$totalCats = count($categories);

// Stats
$productCountRes = $conn ? $conn->query("SELECT COUNT(*) as c FROM products") : null;
$totalProducts = $productCountRes ? (int)($productCountRes->fetch_assoc()['c'] ?? 0) : 0;

// Current Filtering
$activeCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Favorite Ids
$favoriteIds = [];
if ($user_id > 0) {
    $favRes = $conn->query("SELECT product_id FROM favorites WHERE user_id = $user_id");
    if ($favRes) {
        while ($frow = $favRes->fetch_assoc()) {
            $favoriteIds[] = (int)$frow['product_id'];
        }
    }
}

// Fetch products based on filters
$where = "WHERE 1=1";
if ($activeCategory > 0) {
    $where .= " AND p.category_id = " . (int)$activeCategory;
}
if (!empty($searchQuery)) {
    $safeSearch = $conn->real_escape_string($searchQuery);
    $where .= " AND (p.title LIKE '%$safeSearch%' OR p.description LIKE '%$safeSearch%')";
}

$orderBy = "p.created_at DESC";
if ($sortBy === 'price_low') $orderBy = "p.price ASC";
if ($sortBy === 'price_high') $orderBy = "p.price DESC";

$sqlProducts = "SELECT p.*, cp.name AS category_name FROM products p LEFT JOIN categories_products cp ON cp.id = p.category_id $where ORDER BY $orderBy";
$productsRes = $conn->query($sqlProducts);

// Page Agent setup
$pageAgentQuery = '';
if (defined('PAGE_AGENT_MODEL')) {
    $pageAgentQuery = http_build_query([
        'model' => PAGE_AGENT_MODEL,
        'baseURL' => PAGE_AGENT_BASE_URL,
        'apiKey' => PAGE_AGENT_API_KEY,
        'lang' => PAGE_AGENT_LANG,
    ]);
}
$pageAgentScriptSrc = (defined('PAGE_AGENT_SCRIPT_URL') && !empty(PAGE_AGENT_SCRIPT_URL)) ? PAGE_AGENT_SCRIPT_URL . '?' . $pageAgentQuery : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thực đơn - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'HDPTTM Canteen'); ?></title>
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
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap&subset=vietnamese" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <?php if (!empty($pageAgentScriptSrc)): ?>
    <style>
        #page-agent-runtime_agent-panel:not([data-pa-ui-enhanced="1"]) { display: none !important; }
    </style>
    <script>
        window.__PAGE_AGENT_SCRIPT_SRC__ = <?php echo json_encode($pageAgentScriptSrc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <!-- Alibaba Page Agent -->
    <script src="<?php echo htmlspecialchars($pageAgentScriptSrc, ENT_QUOTES, 'UTF-8'); ?>" crossorigin="true" defer></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #faf7f2; }
        .hero-img-stack .img-main { width: 320px; height: 320px; }
        .hero-img-stack .img-sub { width: 120px; height: 120px; border: 6px solid #fff; }
        
        .category-pill.active {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border-color: #ea580c;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.28);
        }
        .category-pill.active:hover {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            color: white;
            border-color: #c2410c;
        }
        
        .product-card { transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -15px rgba(234, 88, 12, 0.18); }
        .food-img-wrapper { 
            position: relative; padding-top: 100%; border-radius: 50%; overflow: hidden; background: #fff;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.05);
        }
        .food-img-wrapper img { position: absolute; top:0; left:0; width:100%; height:100%; object-fit: cover; }
        
        /* Skeleton Animation */
        .skeleton {
            background: linear-gradient(90deg, #f5f2eb 25%, #ede8de 50%, #f5f2eb 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Custom sort select */
        #menu-sort {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23ea580c' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            cursor: pointer;
        }
        #menu-sort option { font-weight: 700; }
        #menu-sort:focus { border: 2px solid #ea580c !important; outline: none; }

        /* Custom Toast Styles */
        .toast-container.bottom-center {
            position: fixed; bottom: 40px; left: 50%; transform: translateX(-50%);
            z-index: 9999; display: flex; flex-direction: column-reverse; gap: 10px; align-items: center;
        }
        .toast {
            background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); border-radius: 18px;
            padding: 14px 22px; box-shadow: 0 15px 35px -5px rgba(234, 88, 12, 0.18);
            display: flex; align-items: center; gap: 12px; min-width: 250px; border-bottom: 4px solid #ea580c;
            animation: toast-in-bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes toast-in-bottom { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes toast-out-bottom { to { transform: translateY(100px); opacity: 0; } }
    </style>
</head>
<body class="min-h-screen">

    <?php if (!empty($_SESSION['table_number'])): ?>
    <!-- AT-TABLE TOP BANNER -->
    <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white text-xs font-bold py-2.5 px-4 text-center flex items-center justify-center gap-2 shadow-sm relative z-50">
        <i data-lucide="utensils" class="w-4 h-4 text-emerald-200"></i>
        <span>Quý khách đang đặt món tại <strong><?php echo htmlspecialchars($_SESSION['table_number']); ?></strong> (Ăn tại quán)</span>
        <a href="order.php" class="bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded text-[11px] ml-2 transition">Xem đơn bàn này</a>
    </div>
    <?php endif; ?>

    <!-- NAVBAR -->
    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1">
                        <?php 
                        $cName = $canteenSettings['canteen_name'] ?? 'Restaurant Gourmet';
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
                    <a href="dashboard.php" class="navbar-item"><i data-lucide="house" class="w-4 h-4"></i>Trang chủ</a>
                    <a href="menu.php" class="navbar-item navbar-item-active"><i data-lucide="utensils-crossed" class="w-4 h-4"></i>Thực đơn</a>
                    <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest')): ?>
                    <a href="profile.php" class="navbar-item"><i data-lucide="user-circle" class="w-4 h-4"></i>Hồ sơ</a>
                    <?php else: ?>
                    <a href="login.php" class="navbar-item"><i data-lucide="log-in" class="w-4 h-4"></i>Đăng nhập</a>
                    <?php endif; ?>
                    <a href="order.php" class="navbar-item relative pr-8">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>Giỏ hàng
                        <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#ea580c] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-orange-200"><?php echo $cart_count; ?></span>
                    </a>
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                    <a href="src/admin/index.php" class="navbar-item" style="background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border-radius:12px;padding:6px 14px;">
                        <i data-lucide="<?php echo $_SESSION['role'] === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                        <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Quản lý'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HERO SECTION -->
    <header class="bg-[#fefce8] pt-24 pb-24 relative overflow-hidden -mt-[88px]">
        <div class="h-[88px]"></div> <!-- Spacer to allow background to bleed under navbar -->
        <!-- Decorative blobs -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-amber-100 rounded-full blur-3xl opacity-60 -mr-20 -mt-20"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-green-50 rounded-full blur-3xl opacity-60 -ml-20 -mb-20"></div>

        <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 items-center gap-12 relative z-10">
            <div class="space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-white rounded-full border border-amber-200 text-amber-600 text-[10px] font-extrabold uppercase tracking-widest">
                    <i data-lucide="flame" class="w-3 h-3"></i> Hôm nay phục vụ
                </div>
                <h1 class="text-5xl md:text-7xl font-bold text-[#2d261a] leading-tight">
                    Thực đơn<br><span class="text-amber-600 italic">căn tin</span> ✨
                </h1>
                <p class="text-gray-600 max-w-sm leading-relaxed text-sm font-medium">
                    Chọn món ngon, đặt nhanh trong vài giây. Thực đơn cập nhật mỗi ngày, đảm bảo tươi ngon và đủ dinh dưỡng cho dân IT & Kinh tế!
                </p>
                <div class="flex gap-8 pt-4">
                    <div>
                        <span class="block text-3xl font-bold text-[#2d261a]"><?php echo $totalProducts; ?>+</span>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Món ăn</span>
                    </div>
                    <div class="border-l border-gray-200 pl-8">
                        <span class="block text-3xl font-bold text-[#2d261a]"><?php echo $totalCats; ?>+</span>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Danh mục</span>
                    </div>
                </div>
            </div>

            <div class="relative flex justify-center items-center">
                <!-- Stacked circular images -->
                <div class="relative hero-img-stack">
                    <div class="img-main rounded-full overflow-hidden border-8 border-white shadow-2xl relative z-10 bg-white">
                        <img src="assets/img/hero/bbq.png" alt="BBQ" class="w-full h-full object-cover">
                    </div>
                    <div class="img-sub rounded-full overflow-hidden absolute -top-8 -right-8 z-20 shadow-xl bg-white">
                        <img src="assets/img/hero/salad.png" alt="Salad" class="w-full h-full object-cover">
                    </div>
                    <div class="img-sub rounded-full overflow-hidden absolute -bottom-6 -left-10 z-20 shadow-xl bg-white animate-bounce" style="animation-duration: 4s;">
                        <img src="assets/img/hero/dimsum.png" alt="Dimsum" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- SEARCH & FILTERS -->
    <div class="max-w-6xl mx-auto px-6 -mt-10 relative z-20">
        <div class="bg-white rounded-[2rem] p-4 shadow-xl shadow-amber-900/5 border border-amber-100 flex flex-col md:flex-row gap-4 items-center">
            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="menu-search" placeholder="Tìm kiếm món ăn..." 
                       value="<?php echo htmlspecialchars($searchQuery); ?>"
                       class="w-full pl-12 pr-4 py-3.5 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-amber-200 transition font-medium">
            </div>
            <div class="relative w-full md:w-56" id="custom-sort-wrapper">
                <button type="button" id="sort-dropdown-btn" 
                        class="w-full px-5 py-3.5 bg-gray-50 rounded-2xl outline-none border border-transparent focus:border-amber-200 transition font-bold text-sm text-gray-700 flex items-center justify-between group">
                    <span id="sort-current-label"><?php 
                        $labels = ['newest' => 'Mới nhất', 'price_low' => 'Giá thấp -> cao', 'price_high' => 'Giá cao -> thấp'];
                        echo $labels[$sortBy] ?? 'Mới nhất';
                    ?></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 group-hover:text-amber-600 transition-transform duration-300"></i>
                </button>
                
                <!-- Custom Dropdown Menu -->
                <div id="sort-options" class="absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-amber-100 rounded-2xl shadow-2xl shadow-amber-900/10 overflow-hidden opacity-0 pointer-events-none translate-y-2 transition-all duration-300 z-50">
                    <div class="p-1.5">
                        <button type="button" data-sort="newest" data-label="Mới nhất" class="sort-option w-full text-left px-4 py-2.5 rounded-xl text-sm font-bold text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition">
                            Mới nhất
                        </button>
                        <button type="button" data-sort="price_low" data-label="Giá thấp -> cao" class="sort-option w-full text-left px-4 py-2.5 rounded-xl text-sm font-bold text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition">
                            Giá thấp -> cao
                        </button>
                        <button type="button" data-sort="price_high" data-label="Giá cao -> thấp" class="sort-option w-full text-left px-4 py-2.5 rounded-xl text-sm font-bold text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition">
                            Giá cao -> thấp
                        </button>
                    </div>
                </div>
                <input type="hidden" id="menu-sort" value="<?php echo htmlspecialchars($sortBy); ?>">
            </div>
        </div>

        <!-- Category Horizontal List -->
        <div class="flex gap-3 overflow-x-auto py-6 no-scrollbar mt-2">
            <button onclick="setCategory(0)" 
               class="category-pill whitespace-nowrap px-6 py-2.5 rounded-full border border-gray-200 bg-white font-bold text-sm hover:border-amber-400 hover:text-amber-600 transition <?php echo $activeCategory == 0 ? 'active' : ''; ?>"
               data-category="0">
                Tất cả
            </button>
            <?php foreach ($categories as $cat): ?>
                <button onclick="setCategory(<?php echo $cat['id']; ?>)" 
                   class="category-pill whitespace-nowrap px-6 py-2.5 rounded-full border border-gray-200 bg-white font-bold text-sm hover:border-amber-400 hover:text-amber-600 transition <?php echo $activeCategory == $cat['id'] ? 'active' : ''; ?>"
                   data-category="<?php echo $cat['id']; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PRODUCT LIST -->
    <main class="max-w-6xl mx-auto px-6 py-12" id="product-grid-container">
        <?php if ($productsRes && $productsRes->num_rows > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-12">
                <?php while ($p = $productsRes->fetch_assoc()): ?>
                    <article class="product-card bg-white rounded-[2rem] p-5 shadow-sm border border-orange-100 flex flex-col relative group">
                        <!-- Image overlap -->
                        <div class="relative -mt-12 mb-4">
                            <div class="food-img-wrapper cursor-pointer" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
                            </div>
                        </div>

                        <div class="flex-1 text-center space-y-2">
                            <h3 class="text-base font-bold text-[#2d261a] line-clamp-1 group-hover:text-amber-600 transition cursor-pointer" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                <?php echo htmlspecialchars($p['title']); ?>
                            </h3>
                            <p class="text-[11px] text-gray-400 font-medium line-clamp-2 leading-relaxed h-8">
                                <?php echo htmlspecialchars($p['description'] ?: 'Hương vị thơm ngon, chuẩn phong cách hiện đại...'); ?>
                            </p>
                        </div>

                        <div class="mt-4 pt-4 border-t border-dashed border-gray-100 flex items-center justify-between">
                            <span class="text-lg font-bold text-amber-600"><?php echo number_format($p['price'], 0, ',', '.'); ?>đ</span>
                            <div class="flex gap-2">
                                <?php $isFav = in_array((int)$p['id'], $favoriteIds); ?>
                                <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $p['id']; ?>, this)" 
                                        class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 transition-all hover:bg-red-50 hover:text-red-500 <?php echo $isFav ? 'bg-red-50 text-red-500' : 'text-gray-300'; ?>">
                                    <i data-lucide="heart" class="w-4 h-4 <?php echo $isFav ? 'fill-current' : ''; ?>"></i>
                                </button>
                                <button onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this, true)" 
                                        class="w-9 h-9 bg-amber-100 text-amber-700 rounded-xl hover:bg-amber-200 transition-all flex items-center justify-center" title="Mua nhanh">
                                    <i data-lucide="zap" class="w-4 h-4 fill-current"></i>
                                </button>
                                <button onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)" 
                                        class="w-9 h-9 bg-amber-600 text-white rounded-xl shadow-lg shadow-amber-600/20 hover:bg-amber-700 transition-all flex items-center justify-center">
                                    <i data-lucide="plus" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="search-x" class="w-10 h-10 text-gray-300"></i>
                </div>
                <h3 class="text-xl font-bold text-[#2d261a]">Không tìm thấy món ăn</h3>
                <p class="text-gray-400 text-sm mt-1">Vui lòng thử tìm kiếm với từ khóa khác hoặc chọn danh mục khác.</p>
                <button onclick="resetFilters()" class="mt-6 px-6 py-2.5 bg-amber-100 text-amber-700 font-bold rounded-full hover:bg-amber-200 transition">Xem tất cả</button>
            </div>
        <?php endif; ?>
    </main>

    <script>
        window.addEventListener('load', function() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });

        // UI Elements & State
        const searchInput = document.getElementById('menu-search');
        const sortInput = document.getElementById('menu-sort');
        const sortBtn = document.getElementById('sort-dropdown-btn');
        const sortOptions = document.getElementById('sort-options');
        const sortLabel = document.getElementById('sort-current-label');
        const productGridContainer = document.getElementById('product-grid-container');
        
        let currentCategory = <?php echo $activeCategory; ?>;
        let searchTimeout = null;

        function renderSkeletons() {
            const skeletonHtml = `
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-12 animate-pulse">
                    ${Array(8).fill(0).map(() => `
                        <div class="bg-white rounded-[2rem] p-5 border border-gray-100 flex flex-col relative">
                            <div class="relative -mt-12 mb-4">
                                <div class="relative pt-[100%] rounded-full skeleton opacity-40"></div>
                            </div>
                            <div class="flex-1 space-y-3">
                                <div class="h-4 skeleton rounded-full w-3/4 mx-auto"></div>
                                <div class="h-3 skeleton rounded-full w-1/2 mx-auto"></div>
                            </div>
                            <div class="mt-6 pt-4 border-t border-dashed border-gray-100 flex items-center justify-between">
                                <div class="h-6 skeleton rounded-full w-1/3"></div>
                                <div class="flex gap-2">
                                    <div class="w-9 h-9 skeleton rounded-xl"></div>
                                    <div class="w-9 h-9 skeleton rounded-xl"></div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            productGridContainer.innerHTML = skeletonHtml;
        }

        async function fetchProducts() {
            const searchVal = searchInput.value;
            const sortVal = sortInput.value;
            
            renderSkeletons();
            
            const params = new URLSearchParams({
                category: currentCategory,
                search: searchVal,
                sort: sortVal
            });

            try {
                const res = await fetch('src/api/get_menu_products.php?' + params.toString());
                if (!res.ok) throw new Error('Network response was not ok');
                const html = await res.text();
                
                productGridContainer.innerHTML = html;
                productGridContainer.style.opacity = '1';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                
                // Update URL history
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('category', currentCategory);
                newUrl.searchParams.set('search', searchVal);
                newUrl.searchParams.set('sort', sortVal);
                window.history.pushState({}, '', newUrl);
                
            } catch (e) { 
                console.error('Error fetching products:', e);
                productGridContainer.style.opacity = '1';
            }
        }

        function setCategory(catId) {
            currentCategory = catId;
            document.querySelectorAll('.category-pill').forEach(btn => {
                btn.classList.toggle('active', parseInt(btn.dataset.category) === catId);
            });
            fetchProducts();
        }

        function resetFilters() {
            searchInput.value = '';
            sortInput.value = 'newest';
            sortLabel.textContent = 'Mới nhất';
            setCategory(0);
        }

        // Dropdown Logic
        function toggleSortMenu(show) {
            const icon = sortBtn.querySelector('[data-lucide="chevron-down"]') || sortBtn.querySelector('svg');
            if (show) {
                sortOptions.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
                if (icon) icon.classList.add('rotate-180');
            } else {
                sortOptions.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                if (icon) icon.classList.remove('rotate-180');
            }
        }

        // Unified click listener for dropdown
        document.addEventListener('click', (e) => {
            const isClickInsideBtn = sortBtn.contains(e.target);
            const isClickInsideOptions = sortOptions.contains(e.target);
            const isOpen = !sortOptions.classList.contains('opacity-0');

            if (isClickInsideBtn) {
                toggleSortMenu(!isOpen);
            } else if (isClickInsideOptions) {
                const opt = e.target.closest('.sort-option');
                if (opt) {
                    sortInput.value = opt.dataset.sort;
                    sortLabel.textContent = opt.dataset.label;
                    toggleSortMenu(false);
                    fetchProducts();
                }
            } else {
                if (isOpen) toggleSortMenu(false);
            }
        });

        // Debounced search
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(fetchProducts, 400);
        });


        // Cart Actions (Reusing existing logic)
        async function addToCart(productId, title, price, image, btn, isBuyNow = false) {
            if (!btn) return;
            btn.disabled = true;
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin h-3.5 w-3.5 ' + (isBuyNow ? 'text-amber-700' : 'text-white') + '" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            
            const fd = new FormData();
            fd.append('product_id', productId);
            fd.append('title', title);
            fd.append('price', price);
            fd.append('image', image);

            try {
                const res = await fetch('src/api/add_to_order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('cart-count-badge').innerText = data.cart_count;
                    if (isBuyNow) {
                        window.location.href = 'order.php';
                    } else {
                        showToast(data.message);
                    }
                } else {
                    showToast(data.message || 'Lỗi khi thêm vào giỏ hàng');
                    if (data.message && data.message.includes('đăng nhập')) {
                        setTimeout(() => { window.location.href = 'login.php'; }, 1500);
                    }
                }
            } catch (err) { 
                console.error(err);
                showToast('Lỗi kết nối máy chủ');
            }
            finally {
                btn.disabled = false;
                btn.innerHTML = originalIcon;
            }
        }

        async function toggleFavorite(productId, btn) {
            const fd = new FormData();
            fd.append('product_id', productId);
            try {
                const res = await fetch('src/api/toggle_favorite.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const icon = btn.querySelector('[data-lucide="heart"]') || btn.querySelector('svg');
                    if (data.action === 'added') {
                        btn.classList.add('bg-red-50', 'text-red-500');
                        btn.classList.remove('text-gray-300');
                        if (icon) icon.classList.add('fill-current');
                    } else {
                        btn.classList.remove('bg-red-50', 'text-red-500');
                        btn.classList.add('text-gray-300');
                        if (icon) icon.classList.remove('fill-current');
                    }
                    showToast(data.message);
                }
            } catch (e) { console.error(e); }
        }

        function showToast(msg) {
            const container = document.querySelector('.toast-container') || (() => {
                const c = document.createElement('div');
                c.className = 'toast-container bottom-center';
                document.body.appendChild(c);
                return c;
            })();
            const t = document.createElement('div');
            t.className = 'toast';
            t.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-amber-600"></i><span class="text-sm font-bold text-gray-700">${msg}</span>`;
            container.appendChild(t);
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                t.style.animation = 'toast-out-bottom 0.4s ease-in forwards';
                setTimeout(() => t.remove(), 400);
            }, 3000);
        }
    </script>
    <script src="assets/js/page-agent-ui.js?v=<?php echo rawurlencode((string) (@filemtime(__DIR__ . '/assets/js/page-agent-ui.js') ?: time())); ?>"></script>
    <?php include 'src/includes/food_detail_modal.php'; ?>
    <?php include 'src/includes/order_tracking_widget.php'; ?>
</body>
</html>

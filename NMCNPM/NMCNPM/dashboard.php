<?php
session_start();
require_once __DIR__ . '/src/includes/config.php';
require_once __DIR__ . '/src/includes/cart_order_helpers.php';

// Bắt tham số bàn nếu quét QR (vd: dashboard.php?table=05)
if (isset($_GET['table']) && trim($_GET['table']) !== '') {
    $tNum = trim($_GET['table']);
    $_SESSION['table_number'] = str_starts_with(mb_strtolower($tNum), 'bàn') ? $tNum : ('Bàn ' . $tNum);
    $_SESSION['order_type'] = 'dine_in';
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && isset($conn)) {
    $user_id = ensureSessionUser($conn);
}

$full_name = $_SESSION['full_name'] ?? 'Quý khách';
$role = $_SESSION['role'] ?? 'guest';

// === LOGIC START: CHỈ HIỂN THỊ BANNER KHI MỚI ĐĂNG NHẬP ===
$show_banners_on_login = false;
if (!isset($_SESSION['has_seen_banner'])) {
    $show_banners_on_login = true;
    $_SESSION['has_seen_banner'] = true;
}
// =============================================================

// === BƯỚC 1: LẤY TẤT CẢ BANNER TỪ DATABASE ===
$current_time = date('Y-m-d H:i:s');
$sql_promo = "SELECT * FROM announcements 
              WHERE is_active = 1 
              AND (start_time IS NULL OR start_time <= '$current_time') 
              AND (end_time IS NULL OR end_time >= '$current_time')
              ORDER BY display_order ASC, created_at DESC";
$result_promo = $conn ? $conn->query($sql_promo) : null;
$promoBanners = [];
if ($result_promo && $result_promo->num_rows > 0) {
    while ($row = $result_promo->fetch_assoc()) {
        $promoBanners[] = $row;
    }
}

// Fetch system settings
$canteenSettings = [];
if (isset($conn)) {
    $canteenSettings = getCanteenSettings($conn);
}

// Tính số lượng món ăn trong giỏ hàng
$cart_count = 0;
$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Giải phóng session lock vì ở dưới chỉ đọc dữ liệu
session_write_close();
if ($user_id > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, $user_id);
}

// Lấy danh sách danh mục ẩm thực cho trang chủ
$homeCategories = [];
if (isset($conn)) {
    $catSql = "
        SELECT cp.*, 
               COUNT(p.id) AS product_count,
               (SELECT image FROM products WHERE category_id = cp.id AND is_hidden = 0 AND image != '' ORDER BY id DESC LIMIT 1) AS sample_image
        FROM categories_products cp
        LEFT JOIN products p ON p.category_id = cp.id AND p.is_hidden = 0
        GROUP BY cp.id
        ORDER BY cp.id ASC
    ";
    $catRes = $conn->query($catSql);
    if ($catRes) {
        while ($crow = $catRes->fetch_assoc()) {
            $homeCategories[] = $crow;
        }
    }
}

// Lấy danh sách món yêu thích của user
$favoriteIds = [];
if ($user_id > 0 && isset($conn)) {
    $favRes = $conn->query("SELECT product_id FROM favorites WHERE user_id = $user_id");
    if ($favRes) {
        while ($frow = $favRes->fetch_assoc()) {
            $favoriteIds[] = (int) $frow['product_id'];
        }
    }
}

// Lấy danh sách món mới nhất
$latestProducts = [];
// Lấy danh sách món nổi bật
$featuredDishes = [];
// Lấy danh sách món bán chạy
$bestSellerDishes = [];

if (isset($conn) && $conn) {
    // 1. Món mới
    $resLatest = $conn->query("SELECT * FROM products WHERE is_hidden = 0 ORDER BY id DESC LIMIT 4");
    if ($resLatest) {
        while ($row = $resLatest->fetch_assoc()) {
            $latestProducts[] = $row;
        }
    }

    // 2. Món nổi bật (ưu tiên is_highlight = 1, nếu thiếu bù bằng rating cao)
    $resFeat = $conn->query("SELECT * FROM products WHERE is_hidden = 0 AND is_highlight = 1 ORDER BY id DESC LIMIT 4");
    if ($resFeat) {
        while ($row = $resFeat->fetch_assoc()) {
            $featuredDishes[] = $row;
        }
    }
    if (count($featuredDishes) < 4) {
        $needed = 4 - count($featuredDishes);
        $excludeIds = !empty($featuredDishes) ? implode(',', array_column($featuredDishes, 'id')) : '0';
        $resMore = $conn->query("SELECT * FROM products WHERE is_hidden = 0 AND id NOT IN ($excludeIds) ORDER BY rating DESC, id DESC LIMIT $needed");
        if ($resMore) {
            while ($row = $resMore->fetch_assoc()) {
                $featuredDishes[] = $row;
            }
        }
    }

    // 3. Món bán chạy
    $resBest = $conn->query("SELECT * FROM products WHERE is_hidden = 0 ORDER BY sales_count DESC, id DESC LIMIT 4");
    if ($resBest && $resBest->num_rows > 0) {
        while ($row = $resBest->fetch_assoc()) {
            $bestSellerDishes[] = $row;
        }
    }
    if (empty($bestSellerDishes)) {
        $bestSellerDishes = $latestProducts;
    }
}

// Tìm 1 món nổi bật cho Banner (Hero Section)
$heroItem = null;
if (!empty($featuredDishes)) {
    $heroItem = $featuredDishes[0];
} elseif (!empty($latestProducts)) {
    $heroItem = $latestProducts[0];
}

if (!$heroItem) {
    $heroItem = [
        'id' => 0,
        'title' => 'Món Ngon',
        'subtitle' => 'Sườn Nướng Mật Ong',
        'description' => 'Sự kết hợp hoàn hảo giữa sườn nướng sốt mật ong thơm lừng và cơm mềm dẻo. Hương vị đậm đà, chuẩn vị nhà hàng!',
        'image' => 'assets/img/hero/bbq.png',
        'price' => 45000
    ];
} else {
    $heroItem['subtitle'] = $heroItem['title'];
    $heroItem['title'] = 'Món Nổi Bật';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhà Hàng Gourmet - Đặt Món & Không Gian Ẩm Thực</title>
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
    <link
        href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap&subset=vietnamese"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <?php
    $open_time_str = $canteenSettings['open_time'] ?? '06:30:00';
    $close_time_str = $canteenSettings['close_time'] ?? '20:00:00';
    $current_time = time();
    $open_time = strtotime($open_time_str);
    $close_time = strtotime($close_time_str);
    $is_canteen_open = ($current_time >= $open_time && $current_time <= $close_time);
    $formatted_canteen_hours = date('H:i', $open_time) . ' - ' . date('H:i', $close_time);
    ?>
    <script>
        window.IS_CANTEEN_OPEN = <?php echo $is_canteen_open ? 'true' : 'false'; ?>;
        window.CANTEEN_HOURS = "<?php echo htmlspecialchars($formatted_canteen_hours); ?>";

        document.addEventListener('DOMContentLoaded', () => {
            if (!window.IS_CANTEEN_OPEN) {
                const orderBtns = document.querySelectorAll('button[onclick*="addToCart"], button[onclick*="scrollIntoView"]');
                orderBtns.forEach(btn => {
                    btn.classList.add('opacity-50', 'grayscale', 'cursor-not-allowed', 'pointer-events-none');
                    btn.classList.remove('hover:bg-amber-200', 'active:scale-90', 'hover:bg-amber-700', 'hover:bg-violet-200');
                    btn.title = 'Ngoài giờ phục vụ (' + window.CANTEEN_HOURS + ')';
                });
            }
        });
    </script>
    <style>
        body {
            font-family: 'Quicksand', -apple-system, sans-serif;
            background-color: #faf7f2;
            color: #1c1917;
        }

        h1,
        h2,
        h3,
        .font-serif {
            font-family: 'Quicksand', sans-serif;
            font-weight: 700;
        }

        svg {
            max-width: 100%;
            vertical-align: middle;
        }

        .w-3\.5 {
            width: 0.875rem !important;
            height: 0.875rem !important;
        }

        .w-4 {
            width: 1rem !important;
            height: 1rem !important;
        }

        .w-5 {
            width: 1.25rem !important;
            height: 1.25rem !important;
        }

        .blob-1 {
            border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
        }

        .blob-2 {
            border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        }

        .food-card {
            background: #ffffff;
            border: 1px solid rgba(234, 88, 12, 0.12);
            border-radius: 1.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(234, 88, 12, 0.05);
        }

        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(234, 88, 12, 0.12);
            border-color: rgba(234, 88, 12, 0.25);
        }

        .toast-container.top-right {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast-container.bottom-center {
            position: fixed;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column-reverse;
            gap: 10px;
            pointer-events: none;
            align-items: center;
        }

        .toast {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 14px 22px;
            box-shadow: 0 15px 35px -5px rgba(234, 88, 12, 0.18);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 250px;
            pointer-events: auto;
            border-left: 4px solid #ea580c;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast-container.top-right .toast {
            transform: translateX(120%);
            animation: toast-in-right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .toast-container.bottom-center .toast {
            transform: translateY(120%);
            animation: toast-in-bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            border-left: none;
            border-bottom: 4px solid #ea580c;
        }

        .toast.success {
            border-color: #ea580c;
        }

        .toast.error {
            border-color: #ef4444;
        }

        @keyframes toast-in-right {
            to {
                transform: translateX(0);
            }
        }

        @keyframes toast-in-bottom {
            to {
                transform: translateY(0);
            }
        }

        @keyframes toast-out-right {
            to {
                transform: translateX(120%);
                opacity: 0;
            }
        }

        @keyframes toast-out-bottom {
            to {
                transform: translateY(120%);
                opacity: 0;
            }
        }

        .food-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%;
            /* 1:1 Aspect Ratio */
            border-radius: 50%;
            overflow: hidden;
            background-color: #faf7f2;
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

        .add-btn {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border-radius: 9999px;
            padding: 0.65rem 1.6rem;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.28);
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(234, 88, 12, 0.38);
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-15px) rotate(3deg);
            }

            100% {
                transform: translateY(0px) rotate(0deg);
            }
        }

        /* Tabs styling */
        .curated-tab-btn.active {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);
            border-color: transparent;
        }
    </style>
</head>

<body class="relative min-h-screen flex flex-col">

    <div
        class="absolute top-0 right-0 w-[550px] h-[550px] bg-gradient-to-br from-orange-200/35 to-amber-100/25 blob-1 -z-10 -translate-y-1/4 translate-x-1/4 blur-2xl">
    </div>
    <div
        class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-gradient-to-tr from-amber-100/30 to-orange-100/25 blob-2 -z-10 translate-y-1/4 -translate-x-1/4 blur-2xl">
    </div>

    <?php if (!empty($_SESSION['table_number'])): ?>
        <!-- AT-TABLE TOP BANNER -->
        <div
            class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white text-xs font-bold py-2.5 px-4 text-center flex items-center justify-center gap-2 shadow-sm relative z-50">
            <i data-lucide="utensils" class="w-4 h-4 text-emerald-200"></i>
            <span>Quý khách đang đặt món tại <strong><?php echo htmlspecialchars($_SESSION['table_number']); ?></strong> (Ăn
                tại quán)</span>
            <a href="order.php"
                class="bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded text-[11px] ml-2 transition">Xem đơn bàn
                này</a>
        </div>
    <?php endif; ?>

    <!-- STICKY NAVBAR -->
    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo"
                        class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
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
                    <!-- Thanh tìm kiếm gọn gàng -->
                    <form action="menu.php" method="GET"
                        class="hidden lg:flex items-center bg-orange-50/70 border border-orange-200/60 rounded-full px-2.5 py-1 mr-1">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-orange-600"></i>
                        <input type="text" name="search" placeholder="Tìm món..."
                            class="bg-transparent border-none outline-none text-xs px-2 text-[#1c1917] w-24 focus:w-36 transition-all placeholder-stone-400 font-medium">
                    </form>

                    <a href="dashboard.php" class="navbar-item navbar-item-active">
                        <i data-lucide="house" class="w-4 h-4"></i>
                        Trang chủ
                    </a>
                    <a href="menu.php" class="navbar-item">
                        <i data-lucide="utensils-crossed" class="w-4 h-4"></i>
                        Thực đơn
                    </a>
                    <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest')): ?>
                        <a href="profile.php" class="navbar-item">
                            <i data-lucide="user-circle" class="w-4 h-4"></i>
                            Hồ sơ
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="navbar-item">
                            <i data-lucide="log-in" class="w-4 h-4"></i>
                            Đăng nhập
                        </a>
                    <?php endif; ?>
                    <a href="order.php" class="navbar-item relative pr-7">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <span id="cart-count-badge"
                            class="absolute -top-1 -right-1 bg-white text-[#ea580c] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-orange-200"><?php echo $cart_count; ?></span>
                    </a>

                    <!-- Nút Giới Thiệu -->
                    <a href="about.php"
                        class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0"
                        title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>

                    <?php if (in_array(($role ?? ''), ['admin', 'staff'])): ?>
                        <a href="src/admin/index.php" class="navbar-item"
                            style="background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border-radius:12px;padding:6px 12px;margin-left:2px;">
                            <i data-lucide="<?php echo ($role ?? '') === 'admin' ? 'shield-check' : 'briefcase'; ?>"
                                class="w-4 h-4"></i>
                            <?php echo ($role ?? '') === 'admin' ? 'Admin' : 'Quản lý'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTAINER -->
    <div class="max-w-6xl mx-auto px-6 pt-0 pb-12 relative z-10 flex-grow w-full space-y-16">

        <!-- 1. HERO SECTION -->
        <div class="mt-2 md:mt-3 flex flex-col md:flex-row items-center justify-between gap-12">
            <div class="flex-1 space-y-5">
                <!-- Giờ mở cửa pill -->
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-orange-50 border border-orange-200/70 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                    <span class="text-[11px] font-semibold text-stone-600">
                        Mở cửa <?php echo date('H:i', strtotime($canteenSettings['open_time'] ?? '06:30')); ?> – <?php echo date('H:i', strtotime($canteenSettings['close_time'] ?? '21:00')); ?>
                    </span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold leading-tight text-[#1c1917] mt-3">
                    <?php echo htmlspecialchars($heroItem['title']); ?><br>
                    <span class="text-[#ea580c] italic"><?php echo htmlspecialchars($heroItem['subtitle']); ?></span>
                </h1>
                <p class="text-[#6b6256] max-w-sm leading-relaxed text-sm mt-1">
                    <?php echo htmlspecialchars(!empty($heroItem['description']) ? $heroItem['description'] : 'Nguyên liệu tươi chọn lọc, bếp làm mới từng ngày. Hương vị quen mà vẫn có gì đó khác.'); ?>
                </p>
                <div class="pt-4 flex flex-wrap items-center gap-2.5">
                    <button onclick="document.getElementById('thucdon-section').scrollIntoView({ behavior: 'smooth' });"
                        class="add-btn inline-flex items-center gap-2 active:scale-95 text-sm cursor-pointer">
                        Xem thực đơn <i data-lucide="arrow-down" class="w-4 h-4"></i>
                    </button>

                    <a href="reservation.php"
                        class="inline-flex items-center gap-2 font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-4 py-2.5 rounded-full text-sm transition-all active:scale-95">
                        <i data-lucide="calendar" class="w-4 h-4 text-amber-600"></i> Đặt bàn
                    </a>

                    <button onclick="pickRandomDish()"
                        class="bg-white text-stone-600 border border-stone-200 hover:bg-stone-50 font-semibold px-4 py-2.5 flex items-center gap-1.5 text-sm cursor-pointer transition-all rounded-full">
                        🎲 Hôm nay ăn gì?
                    </button>

                    <?php if ($heroItem['id'] > 0): ?>
                        <button
                            onclick="addToCart(<?php echo $heroItem['id']; ?>, '<?php echo addslashes($heroItem['subtitle']); ?>', <?php echo $heroItem['price']; ?>, '<?php echo addslashes($heroItem['image']); ?>', this)"
                            class="inline-flex items-center gap-1.5 font-semibold text-[#ea580c] bg-orange-50 px-4 py-2.5 rounded-full hover:bg-orange-100 transition-colors active:scale-95 text-sm cursor-pointer border border-orange-100">
                            <i data-lucide="plus" class="w-4 h-4"></i> Thêm vào giỏ
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 relative w-full flex justify-center mt-6 md:mt-0">
                <div class="relative w-full max-w-sm md:max-w-md mx-auto drop-shadow-2xl">
                    <div
                        class="w-full aspect-square rounded-full overflow-hidden bg-gradient-to-br from-orange-100 to-amber-50 p-4 border-4 border-white shadow-xl">
                        <img src="<?php echo htmlspecialchars($heroItem['image']); ?>"
                            alt="<?php echo htmlspecialchars($heroItem['subtitle']); ?>"
                            onerror="this.onerror=null; this.src='assets/img/hero/bbq.png';"
                            class="w-full h-full object-cover rounded-full shadow-inner transform hover:scale-105 transition duration-700">
                    </div>
                    <?php if (isset($heroItem['price'])): ?>
                        <div class="absolute -bottom-2 -left-2 bg-white rounded-full border-4 border-[#faf7f2] shadow-xl px-4 py-2 animate-float"
                            style="animation-delay: 1s;">
                            <span class="font-sans font-bold text-[#ea580c] text-lg">
                                <?php echo number_format((float) $heroItem['price'], 0, ',', '.'); ?>đ
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. KHÁM PHÁ DANH MỤC ẨM THỰC (Categories Showcase) -->
        <div id="danhmuc-section">
            <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a]">
                        Danh mục món ăn
                    </h2>
                    <p class="text-xs md:text-sm text-[#7b6f5b] mt-1">
                        Chọn danh mục để xem các món trong thực đơn
                    </p>
                </div>
                <a href="menu.php"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-amber-200 text-[#d97706] text-xs font-bold hover:bg-[#d97706] hover:text-white hover:border-[#d97706] transition-all shadow-sm group">
                    <span>Xem toàn bộ menu</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>

            <?php
            $catMeta = [
                'mon-khai-vi' => [
                    'icon' => 'sparkles',
                    'desc' => 'Salad tươi ngon, súp nóng hổi đánh thức vị giác',
                    'badge' => 'Khai vị',
                    'bg_gradient' => 'from-emerald-500/10 via-teal-500/5 to-white',
                    'accent_color' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                    'default_img' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&auto=format&fit=crop&q=80'
                ],
                'mon-chinh' => [
                    'icon' => 'utensils-crossed',
                    'desc' => 'Món ngon đậm vị, cơm, bún, mì & hải sản chế biến công phu',
                    'badge' => 'Món chính',
                    'bg_gradient' => 'from-amber-500/10 via-orange-500/5 to-white',
                    'accent_color' => 'text-amber-700 bg-amber-50 border-amber-200',
                    'default_img' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80'
                ],
                'mon-lau-nuong' => [
                    'icon' => 'flame',
                    'desc' => 'Lẩu nghi ngút khói & đồ nướng sốt đặc biệt sum vầy',
                    'badge' => 'Lẩu & Nướng',
                    'bg_gradient' => 'from-rose-500/10 via-red-500/5 to-white',
                    'accent_color' => 'text-rose-700 bg-rose-50 border-rose-200',
                    'default_img' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&auto=format&fit=crop&q=80'
                ],
                'mon-trang-mieng' => [
                    'icon' => 'cake',
                    'desc' => 'Bánh ngọt, kem mát lạnh & chè thanh mát',
                    'badge' => 'Tráng miệng',
                    'bg_gradient' => 'from-pink-500/10 via-purple-500/5 to-white',
                    'accent_color' => 'text-pink-700 bg-pink-50 border-pink-200',
                    'default_img' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80'
                ],
                'thuc-uong' => [
                    'icon' => 'glass-water',
                    'desc' => 'Nước ép nguyên chất, mocktail, trà hoa quả & vang hảo hạng',
                    'badge' => 'Thức uống',
                    'bg_gradient' => 'from-sky-500/10 via-blue-500/5 to-white',
                    'accent_color' => 'text-sky-700 bg-sky-50 border-sky-200',
                    'default_img' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80'
                ],
                'set-menu-combo' => [
                    'icon' => 'crown',
                    'desc' => 'Thực đơn trọn gói thiết kế riêng cho nhóm bạn & gia đình',
                    'badge' => 'Set Menu',
                    'bg_gradient' => 'from-amber-600/10 via-yellow-500/5 to-white',
                    'accent_color' => 'text-amber-800 bg-amber-50 border-amber-300',
                    'default_img' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&auto=format&fit=crop&q=80'
                ],
            ];
            ?>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <?php foreach ($homeCategories as $c):
                    $slug = $c['slug'] ?? '';
                    $meta = $catMeta[$slug] ?? [
                        'icon' => 'utensils',
                        'desc' => 'Món ngon mỗi ngày',
                        'badge' => 'Danh mục',
                        'bg_gradient' => 'from-amber-500/10 via-orange-500/5 to-white',
                        'accent_color' => 'text-amber-700 bg-amber-50 border-amber-200',
                        'default_img' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80'
                    ];
                    $thumb = !empty($c['sample_image']) ? $c['sample_image'] : $meta['default_img'];
                    ?>
                    <a href="menu.php?category=<?php echo $c['id']; ?>"
                        class="group relative overflow-hidden rounded-2xl bg-white border border-amber-100 hover:border-orange-300 p-3 shadow-xs hover:shadow-md transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between text-center">
                        <div>
                            <div class="relative w-full aspect-square rounded-xl overflow-hidden mb-2.5 bg-orange-50">
                                <img src="<?php echo htmlspecialchars($thumb); ?>"
                                    alt="<?php echo htmlspecialchars($c['name']); ?>"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                    onerror="this.onerror=null; this.src='<?php echo $meta['default_img']; ?>';">
                                <div class="absolute inset-0 bg-black/15 group-hover:bg-black/5 transition-colors"></div>
                                <div
                                    class="absolute top-2 right-2 w-7 h-7 rounded-lg bg-white/90 backdrop-blur-xs flex items-center justify-center text-orange-600 shadow-xs">
                                    <i data-lucide="<?php echo $meta['icon']; ?>" class="w-3.5 h-3.5"></i>
                                </div>
                            </div>

                            <h3
                                class="font-bold text-xs md:text-sm text-[#2d261a] group-hover:text-[#d97706] transition-colors truncate">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </h3>
                        </div>
                        <span class="text-[10px] text-gray-500 font-semibold mt-1 block">
                            <?php echo (int) $c['product_count']; ?> món
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. THỰC ĐƠN TUYỂN CHỌN (TÍCH HỢP TABS ĐỂ GỌN GÀNG, KHÔNG LẶP) -->
        <div id="thucdon-section" class="scroll-mt-24">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a]">
                        Gọi món
                    </h2>
                </div>

                <!-- Tabs navigation -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                    <button onclick="switchCuratedTab('ai')" id="tab-btn-ai"
                        class="curated-tab-btn active px-3.5 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-white text-gray-700 hover:border-orange-300 transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Gợi ý cho bạn
                    </button>
                    <button onclick="switchCuratedTab('featured')" id="tab-btn-featured"
                        class="curated-tab-btn px-3.5 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-white text-gray-700 hover:border-orange-300 transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="star" class="w-3.5 h-3.5"></i> Món nổi bật
                    </button>
                    <button onclick="switchCuratedTab('bestseller')" id="tab-btn-bestseller"
                        class="curated-tab-btn px-3.5 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-white text-gray-700 hover:border-orange-300 transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="flame" class="w-3.5 h-3.5"></i> Bán chạy
                    </button>
                    <button onclick="switchCuratedTab('latest')" id="tab-btn-latest"
                        class="curated-tab-btn px-3.5 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-white text-gray-700 hover:border-orange-300 transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i> Món mới
                    </button>
                </div>
            </div>

            <!-- TAB 1: AI RECOMMENDATION -->
            <div id="tab-pane-ai" class="curated-pane block">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] text-gray-400">Lọc theo giá:</span>
                        <?php foreach ([0, 20000, 30000, 50000] as $b): ?>
                            <button onclick="setBudget(<?php echo $b; ?>)" data-budget="<?php echo $b; ?>"
                                class="budget-btn px-3 py-1 rounded-full text-xs font-bold border transition
                            <?php echo $b === 0 ? 'bg-orange-600 text-white border-orange-600' : 'bg-white text-gray-600 border-gray-200 hover:border-orange-400'; ?>">
                                <?php echo $b === 0 ? 'Tất cả' : 'Dưới ' . number_format($b, 0, ',', '.') . 'đ'; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="loadRecommendations()" id="ai-refresh-btn"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-50 border border-orange-200 text-orange-600 font-bold text-xs hover:bg-orange-100 transition active:scale-95">
                        <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Gợi ý lại
                    </button>
                </div>

                <div id="ai-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-10">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div
                            class="ai-skeleton bg-white rounded-3xl p-5 shadow-sm border border-orange-100 animate-pulse text-center">
                            <div class="w-3/4 mx-auto aspect-square rounded-full bg-gray-200 mb-4 -mt-12"></div>
                            <div class="h-4 bg-gray-200 rounded-full w-3/4 mx-auto mb-2"></div>
                            <div class="h-3 bg-gray-200 rounded-full w-1/2 mx-auto mb-4"></div>
                            <div class="h-8 bg-gray-100 rounded-xl w-full"></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- TAB 2: FEATURED DISHES -->
            <div id="tab-pane-featured" class="curated-pane hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-10">
                    <?php foreach ($featuredDishes as $p): ?>
                        <div class="food-card p-5 relative group flex flex-col justify-between cursor-pointer"
                            onclick="openFoodDetailModal(<?php echo htmlspecialchars(json_encode(['id' => $p['id'], 'title' => $p['title'], 'price' => (float) $p['price'], 'image' => $p['image'], 'description' => $p['description'] ?? '']), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div
                                class="absolute -top-3 -right-2 z-20 bg-amber-500 text-white text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md">
                                ⭐ Nổi bật
                            </div>
                            <div>
                                <div
                                    class="food-img-wrapper z-10 w-4/5 mx-auto -mt-14 group-hover:-mt-16 transition-all duration-300">
                                    <img src="<?php echo htmlspecialchars($p['image']); ?>"
                                        alt="<?php echo htmlspecialchars($p['title']); ?>"
                                        onerror="this.src='assets/img/hero/bbq.png'">
                                </div>
                                <div class="mt-4 text-center">
                                    <h3 class="font-bold text-base text-[#2d261a] leading-snug mb-1 line-clamp-1">
                                        <?php echo htmlspecialchars($p['title']); ?>
                                    </h3>
                                    <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 mt-1">
                                        <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Hương vị thơm ngon đậm đà, chuẩn công thức nhà hàng.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 flex items-center justify-between border-t border-orange-100">
                                <span class="font-sans font-bold text-[#ea580c] text-base">
                                    <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <?php $isFav = in_array((int) $p['id'], $favoriteIds); ?>
                                    <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $p['id']; ?>, this)"
                                        class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all active:scale-90 <?php echo $isFav ? 'bg-red-50 text-red-500' : 'bg-white text-gray-300 hover:text-red-400'; ?>"
                                        data-product-id="<?php echo $p['id']; ?>">
                                        <i data-lucide="heart" class="w-4 h-4 fill-current"></i>
                                    </button>
                                    <button
                                        onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)"
                                        class="bg-[#ea580c] text-white p-2 rounded-xl shadow-md hover:bg-orange-700 active:scale-95 transition-all">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB 3: BEST SELLERS -->
            <div id="tab-pane-bestseller" class="curated-pane hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-10">
                    <?php foreach ($bestSellerDishes as $p): ?>
                        <div class="food-card p-5 relative group flex flex-col justify-between cursor-pointer"
                            onclick="openFoodDetailModal(<?php echo htmlspecialchars(json_encode(['id' => $p['id'], 'title' => $p['title'], 'price' => (float) $p['price'], 'image' => $p['image'], 'description' => $p['description'] ?? '']), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div
                                class="absolute -top-3 -right-2 z-20 bg-rose-500 text-white text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md flex items-center gap-1">
                                <i data-lucide="flame" class="w-3 h-3"></i> Bán chạy
                            </div>
                            <div>
                                <div
                                    class="food-img-wrapper z-10 w-4/5 mx-auto -mt-14 group-hover:-mt-16 transition-all duration-300">
                                    <img src="<?php echo htmlspecialchars($p['image']); ?>"
                                        alt="<?php echo htmlspecialchars($p['title']); ?>"
                                        onerror="this.src='assets/img/hero/bbq.png'">
                                </div>
                                <div class="mt-4 text-center">
                                    <h3 class="font-bold text-base text-[#2d261a] leading-snug mb-1 line-clamp-1">
                                        <?php echo htmlspecialchars($p['title']); ?>
                                    </h3>
                                    <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 mt-1">
                                        <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Món ăn yêu thích được lựa chọn nhiều nhất trong tuần.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 flex items-center justify-between border-t border-orange-100">
                                <span class="font-sans font-bold text-[#ea580c] text-base">
                                    <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <?php $isFav = in_array((int) $p['id'], $favoriteIds); ?>
                                    <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $p['id']; ?>, this)"
                                        class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all active:scale-90 <?php echo $isFav ? 'bg-red-50 text-red-500' : 'bg-white text-gray-300 hover:text-red-400'; ?>"
                                        data-product-id="<?php echo $p['id']; ?>">
                                        <i data-lucide="heart" class="w-4 h-4 fill-current"></i>
                                    </button>
                                    <button
                                        onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)"
                                        class="bg-[#ea580c] text-white p-2 rounded-xl shadow-md hover:bg-orange-700 active:scale-95 transition-all">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB 4: LATEST PRODUCTS -->
            <div id="tab-pane-latest" class="curated-pane hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-10">
                    <?php foreach ($latestProducts as $p): ?>
                        <div class="food-card p-5 relative group flex flex-col justify-between cursor-pointer"
                            onclick="openFoodDetailModal(<?php echo htmlspecialchars(json_encode(['id' => $p['id'], 'title' => $p['title'], 'price' => (float) $p['price'], 'image' => $p['image'], 'description' => $p['description'] ?? '']), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div
                                class="absolute -top-3 -right-2 z-20 bg-emerald-600 text-white text-[10px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md">
                                Mới
                            </div>
                            <div>
                                <div
                                    class="food-img-wrapper z-10 w-4/5 mx-auto -mt-14 group-hover:-mt-16 transition-all duration-300">
                                    <img src="<?php echo htmlspecialchars($p['image']); ?>"
                                        alt="<?php echo htmlspecialchars($p['title']); ?>"
                                        onerror="this.src='assets/img/hero/bbq.png'">
                                </div>
                                <div class="mt-4 text-center">
                                    <h3 class="font-bold text-base text-[#2d261a] leading-snug mb-1 line-clamp-1">
                                        <?php echo htmlspecialchars($p['title']); ?>
                                    </h3>
                                    <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 mt-1">
                                        <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Công thức mới được ra mắt tuần này.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 flex items-center justify-between border-t border-orange-100">
                                <span class="font-sans font-bold text-[#ea580c] text-base">
                                    <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <?php $isFav = in_array((int) $p['id'], $favoriteIds); ?>
                                    <button onclick="event.stopPropagation(); toggleFavorite(<?php echo $p['id']; ?>, this)"
                                        class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all active:scale-90 <?php echo $isFav ? 'bg-red-50 text-red-500' : 'bg-white text-gray-300 hover:text-red-400'; ?>"
                                        data-product-id="<?php echo $p['id']; ?>">
                                        <i data-lucide="heart" class="w-4 h-4 fill-current"></i>
                                    </button>
                                    <button
                                        onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)"
                                        class="bg-[#ea580c] text-white p-2 rounded-xl shadow-md hover:bg-orange-700 active:scale-95 transition-all">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 4. CAM KẾT CHẤT LƯỢNG & TRẢI NGHIỆM (Service Highlights) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
            <div
                class="bg-white rounded-3xl p-6 border border-orange-100/80 shadow-xs flex items-start gap-4 hover:shadow-md transition">
                <div
                    class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="leaf" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-[#1c1917] mb-1">Nguyên liệu tươi sạch</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Thực phẩm nhập tươi mỗi ngày, được sơ chế kỹ lưỡng và đảm bảo an toàn vệ sinh.
                    </p>
                </div>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-orange-100/80 shadow-xs flex items-start gap-4 hover:shadow-md transition">
                <div
                    class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="chef-hat" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-[#1c1917] mb-1">Hương vị đặc trưng</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Nêm nếm vừa vặn theo khẩu vị quen thuộc, giữ trọn độ tươi ngọt tự nhiên của từng món.
                    </p>
                </div>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-orange-100/80 shadow-xs flex items-start gap-4 hover:shadow-md transition">
                <div
                    class="w-12 h-12 rounded-xl bg-orange-50 text-orange-600 border border-orange-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="zap" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-[#1c1917] mb-1">Phục vụ nhanh chóng</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Lên món nhanh, hỗ trợ gọi món tại bàn mượt mà, giúp bữa ăn của bạn luôn trọn vẹn.
                    </p>
                </div>
            </div>
        </div>

        <!-- 5. KHÔNG GIAN & ĐẶT BÀN TIỆC (Inline Reservation Banner) -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#3b2b1a] via-[#4d3620] to-[#2e1d0f] text-white p-8 md:p-12 shadow-xl border border-amber-900/30">
            <div
                class="absolute -right-10 -bottom-10 w-72 h-72 bg-orange-500/10 rounded-full blur-3xl pointer-events-none">
            </div>

            <div class="relative z-10 max-w-2xl">
                <h2 class="text-2xl md:text-4xl font-serif font-bold text-white mb-3">
                    Đặt bàn — chúng tôi lo phần còn lại
                </h2>
                <p class="text-sm text-amber-100/80 mb-6 leading-relaxed">
                    Cho bữa cơm gia đình, buổi gặp bạn bè hay tiếp đãi đối tác. Chọn bàn trước để chúng tôi chuẩn bị tốt nhất.
                </p>

                <!-- Fast booking form linking to reservation.php -->
                <form action="reservation.php" method="GET"
                    class="bg-white/10 backdrop-blur-md p-3 rounded-2xl border border-white/20 flex flex-col sm:flex-row items-center gap-3">
                    <div class="flex-1 w-full flex items-center gap-2 bg-white/15 rounded-xl px-3 py-2 text-xs">
                        <i data-lucide="calendar" class="w-4 h-4 text-amber-300"></i>
                        <input type="date" name="reservation_date" value="<?php echo date('Y-m-d'); ?>"
                            min="<?php echo date('Y-m-d'); ?>"
                            class="bg-transparent text-white outline-none w-full text-xs cursor-pointer">
                    </div>
                    <div class="flex-1 w-full flex items-center gap-2 bg-white/15 rounded-xl px-3 py-2 text-xs">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-300"></i>
                        <select name="reservation_time"
                            class="bg-transparent text-white outline-none w-full text-xs cursor-pointer">
                            <option value="11:30" class="text-gray-900">11:30 (Trưa)</option>
                            <option value="12:00" class="text-gray-900">12:00 (Trưa)</option>
                            <option value="18:00" class="text-gray-900" selected>18:00 (Tối)</option>
                            <option value="19:00" class="text-gray-900">19:00 (Tối)</option>
                            <option value="20:00" class="text-gray-900">20:00 (Tối)</option>
                        </select>
                    </div>
                    <div class="flex-1 w-full flex items-center gap-2 bg-white/15 rounded-xl px-3 py-2 text-xs">
                        <i data-lucide="users" class="w-4 h-4 text-amber-300"></i>
                        <select name="guest_count"
                            class="bg-transparent text-white outline-none w-full text-xs cursor-pointer">
                            <option value="2" class="text-gray-900">2 Khách</option>
                            <option value="4" class="text-gray-900" selected>4 Khách</option>
                            <option value="6" class="text-gray-900">6 Khách</option>
                            <option value="8" class="text-gray-900">Tiệc (8+)</option>
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full sm:w-auto whitespace-nowrap bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold px-5 py-2.5 rounded-xl text-xs transition active:scale-95 shadow-lg flex items-center justify-center gap-1.5">
                        <span>Tiếp tục</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- 6. ĐÁNH GIÁ TỪ THỰC KHÁCH (Customer Testimonials) -->
        <div>
            <div class="text-center max-w-xl mx-auto mb-8">
                <h2 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a]">
                    Thực khách nói gì
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-3">
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                        </div>
                        <p class="text-xs text-gray-600 leading-relaxed italic mb-4">
                            "Món sườn nướng mật ong ướp rất vừa vị, cơm dẻo nóng hổi. Đặt món quét mã tại bàn cũng tiện, không phải chờ lâu."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                        <div
                            class="w-9 h-9 rounded-full bg-orange-100 text-orange-700 font-bold flex items-center justify-center text-xs">
                            HN
                        </div>
                        <div>
                            <div class="font-bold text-xs text-[#2d261a]">Hoàng Nam</div>
                            <div class="text-[10px] text-gray-400">Khách hàng thân thiết</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-3">
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                        </div>
                        <p class="text-xs text-gray-600 leading-relaxed italic mb-4">
                            "Không gian rất thoáng mát và lịch sự. Đồ ăn ra luôn nóng hổi, nhân viên hỗ trợ nhiệt tình.
                            Đặt bàn trước cho gia đình vào cuối tuần rất yên tâm."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                        <div
                            class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 font-bold flex items-center justify-center text-xs">
                            ML
                        </div>
                        <div>
                            <div class="font-bold text-xs text-[#2d261a]">Mai Linh</div>
                            <div class="text-[10px] text-gray-400">Dân văn phòng</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 border border-orange-100 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-3">
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                        </div>
                        <p class="text-xs text-gray-600 leading-relaxed italic mb-4">
                            "Đồ ăn ra nhanh, nóng sốt, giá cả hợp lý cho sinh viên tụi mình. Menu nhiều món đổi bữa cả tuần không lo bị ngán."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                        <div
                            class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-xs">
                            ĐA
                        </div>
                        <div>
                            <div class="font-bold text-xs text-[#2d261a]">Đức Anh</div>
                            <div class="text-[10px] text-gray-400">Sinh viên</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <footer class="bg-[#423826] text-[#d4cbb8] py-12 border-t-[6px] border-[#d97706] mt-10">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10">
            <div class="space-y-4">
                <div class="flex items-center gap-3 text-2xl font-serif font-bold text-[#d97706] tracking-wide mb-4">
                    <img src="assets/img/hero/logo.png" alt="Logo"
                        class="w-12 h-12 rounded-full object-contain shadow-sm">
                    <div>
                        <?php
                        $cName = $canteenSettings['canteen_name'] ?? 'CănTin';
                        $parts = explode(' ', $cName, 2);
                        if (count($parts) > 1) {
                            echo htmlspecialchars($parts[0]) . ' <span class="text-white">' . htmlspecialchars($parts[1]) . '</span>';
                        } else {
                            echo htmlspecialchars($cName);
                        }
                        ?>
                    </div>
                </div>
                <p class="text-xs leading-relaxed opacity-80 max-w-xs">
                    Phục vụ các món ăn ngon mỗi ngày với nguyên liệu tươi sạch, giá cả hợp lý và không gian thân thiện.
                </p>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Liên kết</h4>
                <ul class="space-y-3 text-sm opacity-80">
                    <li><a href="dashboard.php" class="hover:text-amber-500 transition">Trang chủ</a></li>
                    <li><a href="menu.php" class="hover:text-amber-500 transition">Thực đơn</a></li>
                    <li><a href="reservation.php" class="hover:text-amber-500 transition">Đặt bàn trước</a></li>
                    <li><a href="about.php" class="hover:text-amber-500 transition">Giới thiệu</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Hỗ trợ</h4>
                <ul class="space-y-3 text-sm opacity-80">
                    <li><a href="about.php" class="hover:text-amber-500 transition">Chính sách phục vụ</a></li>
                    <li><a href="about.php" class="hover:text-amber-500 transition">Tiêu chuẩn an toàn</a></li>
                    <li><a href="about.php" class="hover:text-amber-500 transition">Phản hồi khách hàng</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Kết nối</h4>
                <p class="text-sm opacity-80 mb-3">
                    <?php echo htmlspecialchars($canteenSettings['canteen_address'] ?? 'Tầng trệt tòa B, Khuôn viên trường ĐH'); ?>
                </p>
                <p class="text-sm text-amber-500 font-bold mb-1">Hotline:
                    <?php echo htmlspecialchars($canteenSettings['canteen_hotline'] ?? '0987.654.321'); ?></p>
                <div class="flex gap-3 mt-3">
                    <div
                        class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#d97706] hover:text-white transition cursor-pointer">
                        <i data-lucide="facebook" class="w-4 h-4"></i>
                    </div>
                    <div
                        class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#d97706] hover:text-white transition cursor-pointer">
                        <i data-lucide="instagram" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-12 text-center text-xs opacity-50 border-t border-white/10 pt-6 font-medium">
            &copy; 2026 Nhà Hàng Gourmet. Mọi quyền được bảo lưu.
        </div>
    </footer>

    <!-- MODAL RANDOM DISH -->
    <div id="random-dish-modal"
        class="hidden fixed inset-0 z-[10000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity duration-300 opacity-0">
        <div id="random-dish-content"
            class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-orange-100 transform scale-95 transition-all duration-300 relative text-center">
            <button onclick="closeRandomModal()"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div id="random-dish-body" class="flex flex-col items-center">
                <!-- Nội dung được nạp qua JS -->
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // === CHUYỂN ĐỔI TAB THỰC ĐƠN TUYỂN CHỌN ===
        function switchCuratedTab(tabName) {
            // Update button styles
            document.querySelectorAll('.curated-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if (activeBtn) activeBtn.classList.add('active');

            // Toggle panes
            document.querySelectorAll('.curated-pane').forEach(pane => {
                pane.classList.add('hidden');
                pane.classList.remove('block');
            });
            const targetPane = document.getElementById('tab-pane-' + tabName);
            if (targetPane) {
                targetPane.classList.remove('hidden');
                targetPane.classList.add('block');
            }
            if (tabName === 'ai') {
                loadRecommendations();
            }
            lucide.createIcons();
        }

        // === AI RECOMMENDATION LOGIC ===
        let currentBudget = 0;

        function setBudget(budget) {
            currentBudget = budget;
            document.querySelectorAll('.budget-btn').forEach(btn => {
                const isActive = parseInt(btn.dataset.budget) === budget;
                btn.className = btn.className.replace(/bg-orange-600 text-white border-orange-600|bg-white text-gray-600 border-gray-200/g, '');
                if (isActive) {
                    btn.classList.add('bg-orange-600', 'text-white', 'border-orange-600');
                    btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
                } else {
                    btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
                    btn.classList.remove('bg-orange-600', 'text-white', 'border-orange-600');
                }
            });
            loadRecommendations();
        }

        async function loadRecommendations() {
            const container = document.getElementById('ai-cards');
            const btn = document.getElementById('ai-refresh-btn');
            if (!container) return;

            container.innerHTML = `${Array(4).fill(`
                <div class="ai-skeleton bg-white rounded-3xl p-5 shadow-sm border border-orange-100 animate-pulse text-center">
                    <div class="w-3/4 mx-auto aspect-square rounded-full bg-gray-200 mb-4 -mt-12"></div>
                    <div class="h-4 bg-gray-200 rounded-full w-3/4 mx-auto mb-2"></div>
                    <div class="h-3 bg-gray-200 rounded-full w-1/2 mx-auto mb-4"></div>
                    <div class="h-8 bg-gray-100 rounded-xl w-full"></div>
                </div>`).join('')}`;

            if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }

            try {
                const now = new Date();
                const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                const params = new URLSearchParams({ time: timeStr, budget: currentBudget, limit: 4 });
                const res = await fetch('src/api/get_recommendations.php?' + params.toString());
                const data = await res.json();

                if (data.success && data.recommendations.length > 0) {
                    container.innerHTML = data.recommendations.map(item => `
                        <div class="food-card p-5 relative group flex flex-col justify-between cursor-pointer" onclick="openFoodDetailModal(${JSON.stringify({
                        id: item.dish_id, title: item.name, price: parseFloat(item.price),
                        image: item.image, description: item.description || ""
                    }).replace(/"/g, '&quot;')})">
                            <div class="absolute -top-3 -right-2 z-20 bg-gradient-to-r from-orange-500 to-amber-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow flex items-center gap-1">
                                ✨ Gợi ý
                            </div>
                            <div>
                                <div class="food-img-wrapper z-10 w-4/5 mx-auto -mt-14 group-hover:-mt-16 transition-all duration-300">
                                    <img src="${item.image}" alt="${item.name}" onerror="this.src='assets/img/hero/bbq.png'">
                                </div>
                                <div class="mt-4 text-center">
                                    <h3 class="font-bold text-base text-[#2d261a] leading-snug mb-1 line-clamp-1">${item.name}</h3>
                                    <p class="text-[10px] text-orange-600 font-semibold line-clamp-2 mb-2 h-7">${item.reason || 'Món ăn phù hợp với khung giờ và khẩu vị của bạn'}</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 flex items-center justify-between border-t border-orange-100">
                                <span class="font-sans font-bold text-[#ea580c] text-base">${Number(item.price).toLocaleString('vi-VN')}đ</span>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="event.stopPropagation(); addToCart(${item.dish_id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, '${item.image.replace(/'/g, "\\'")}', this, true)"
                                        class="bg-orange-100 text-orange-700 p-2 rounded-xl hover:bg-orange-200 active:scale-90 transition flex items-center justify-center" title="Mua ngay">
                                        <i data-lucide="zap" class="w-4 h-4 fill-current"></i>
                                    </button>
                                    <button onclick="event.stopPropagation(); addToCart(${item.dish_id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, '${item.image.replace(/'/g, "\\'")}', this)"
                                        class="bg-[#ea580c] text-white p-2 rounded-xl hover:bg-orange-700 active:scale-90 transition flex items-center justify-center">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    lucide.createIcons();
                } else {
                    container.innerHTML = `<div class="col-span-full text-center py-8 text-gray-400 text-sm">Chưa có gợi ý phù hợp cho ngân sách này. Hãy thử chọn ngân sách khác!</div>`;
                }
            } catch (e) {
                console.error('AI Recommendation error:', e);
                container.innerHTML = `<div class="col-span-full text-center py-8 text-gray-400 text-sm">Không thể tải gợi ý lúc này.</div>`;
            }

            if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }
        }

        // === TOGGLE FAVORITE ===
        async function toggleFavorite(productId, btn) {
            const fd = new FormData();
            fd.append('product_id', productId);

            try {
                const res = await fetch('src/api/toggle_favorite.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    if (data.action === 'added') {
                        btn.classList.remove('bg-white', 'text-gray-300');
                        btn.classList.add('bg-red-50', 'text-red-500');
                    } else {
                        btn.classList.remove('bg-red-50', 'text-red-500');
                        btn.classList.add('bg-white', 'text-gray-300');
                    }
                    showToast(data.message, 'success', 'bottom-center');
                } else {
                    showToast(data.message || 'Lỗi', 'error');
                }
            } catch (e) {
                console.error(e);
            }
        }

        // === ADD TO CART ===
        async function addToCart(productId, title, price, image, btn, isBuyNow = false) {
            if (!window.IS_CANTEEN_OPEN) {
                showToast('Ngoài giờ phục vụ (' + window.CANTEEN_HOURS + ')', 'error', 'bottom-center');
                return;
            }
            if (!btn) return;
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('title', title);
            formData.append('price', price);
            formData.append('image', image);
            formData.append('quantity', 1);

            try {
                const response = await fetch('src/api/add_to_order.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const badge = document.getElementById('cart-count-badge');
                    if (badge) badge.innerText = data.cart_count;

                    if (isBuyNow) {
                        window.location.href = 'order.php';
                    } else {
                        showToast(data.message || 'Đã thêm vào giỏ hàng!', 'success', 'bottom-center');
                    }
                } else {
                    showToast(data.message || 'Lỗi thêm vào giỏ hàng', 'error');
                }
            } catch (err) {
                console.error('Error adding to cart:', err);
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
                btn.innerHTML = originalContent;
            }
        }

        // === TOAST NOTIFICATION ===
        function showToast(message, type = 'success', position = 'top-right') {
            let containerId = 'toastContainer-' + position;
            let container = document.getElementById(containerId);
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'toast-container fixed z-[9999] flex gap-2.5 pointer-events-none ' + position;
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            toast.innerHTML = `
                <i data-lucide="${icon}" class="w-5 h-5 ${type === 'success' ? 'text-[#ea580c]' : 'text-red-500'}"></i>
                <span class="text-sm font-bold text-[#423826]">${message}</span>
            `;
            container.appendChild(toast);
            lucide.createIcons();

            setTimeout(() => {
                toast.style.animation = (position === 'top-right' ? 'toast-out-right' : 'toast-out-bottom') + ' 0.4s ease-in forwards';
                setTimeout(() => toast.remove(), 400);
            }, 2500);
        }

        // === RANDOM DISH MODAL LOGIC ===
        function closeRandomModal() {
            const modal = document.getElementById('random-dish-modal');
            const content = document.getElementById('random-dish-content');
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        async function pickRandomDish() {
            const modal = document.getElementById('random-dish-modal');
            const content = document.getElementById('random-dish-content');
            const body = document.getElementById('random-dish-body');

            body.innerHTML = `
                <div class="w-14 h-14 border-4 border-orange-200 border-t-orange-600 rounded-full animate-spin my-6"></div>
                <p class="text-gray-400 text-sm">Đang chọn món... 🎲</p>
            `;

            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);

            try {
                await new Promise(r => setTimeout(r, 500));
                let dish = null;

                // Thử lấy từ api_random.php
                try {
                    const response = await fetch('api_random.php');
                    const result = await response.json();
                    if (result.success && result.data) dish = result.data;
                } catch (e) { }

                // Fallback nếu không có api_random
                if (!dish) {
                    const fallbackRes = await fetch('src/api/get_recommendations.php?limit=8');
                    const fallbackData = await fallbackRes.json();
                    if (fallbackData.success && fallbackData.recommendations.length > 0) {
                        const randomItem = fallbackData.recommendations[Math.floor(Math.random() * fallbackData.recommendations.length)];
                        dish = {
                            id: randomItem.dish_id,
                            title: randomItem.name,
                            price: randomItem.price,
                            image: randomItem.image,
                            description: randomItem.reason || ''
                        };
                    }
                }

                if (dish) {
                    const price = parseFloat(dish.price).toLocaleString('vi-VN');
                    body.innerHTML = `
                        <div class="relative w-36 h-36 rounded-full border-4 border-orange-200 shadow-lg overflow-hidden mb-4">
                            <img src="${dish.image}" alt="${dish.title}" onerror="this.src='assets/img/hero/bbq.png'" class="w-full h-full object-cover rounded-full">
                        </div>
                        <h3 class="font-serif font-bold text-xl text-center text-gray-900 mb-1 leading-snug">${dish.title}</h3>
                        <p class="text-xs text-gray-400 text-center mb-4">Hôm nay thử món này xem sao 🎲</p>
                        
                        <div class="w-full bg-orange-50/70 rounded-2xl p-3 border border-orange-100 flex items-center justify-between">
                            <span class="text-lg font-bold text-orange-600">${price}đ</span>
                            <div class="flex gap-1.5">
                                <button onclick="closeRandomModal(); openFoodDetailModal({id: ${dish.id}, title: '${dish.title.replace(/'/g, "\\'")}', price: ${dish.price}, image: '${dish.image.replace(/'/g, "\\'")}', description: '${(dish.description || '').replace(/'/g, "\\'")}'})" class="bg-white text-orange-700 hover:bg-orange-100 border border-orange-200 px-3 py-1.5 rounded-xl text-xs font-bold transition">Chi tiết</button>
                                <button onclick="addToCart(${dish.id}, '${dish.title.replace(/'/g, "\\'")}', ${dish.price}, '${dish.image.replace(/'/g, "\\'")}', this)" class="bg-[#ea580c] hover:bg-orange-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold shadow-sm active:scale-95 transition flex items-center gap-1">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Thêm
                                </button>
                            </div>
                        </div>
                    `;
                    lucide.createIcons();
                } else {
                    body.innerHTML = `<p class="text-red-500 text-center my-6 text-sm">Không thể chọn món lúc này, vui lòng thử lại!</p>`;
                }
            } catch (err) {
                console.error(err);
                body.innerHTML = `<p class="text-red-500 text-center my-6 text-sm">Lỗi tải dữ liệu!</p>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadRecommendations();
            lucide.createIcons();
        });
    </script>

    <?php include __DIR__ . '/src/includes/food_detail_modal.php'; ?>

    <?php if ($show_banners_on_login && !empty($promoBanners)): ?>
        <!-- PROMO POPUP -->
        <div id="promo-popup" style="display: none;"
            class="fixed inset-0 z-[99999] bg-black/70 backdrop-blur-md items-center justify-center p-4 transition-opacity duration-200">
            <div class="relative max-w-lg w-full bg-white rounded-3xl overflow-hidden shadow-2xl transform scale-95 opacity-0 transition-all duration-300 ease-out flex flex-col max-h-[85vh]"
                id="promo-content">
                <button onclick="closePromo()"
                    class="absolute top-4 right-4 bg-black/60 text-white rounded-full p-2 hover:bg-black/80 transition-all z-30 shadow-xl border border-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>

                <button id="promo-prev-btn" onclick="prevBanner()"
                    class="absolute left-3 top-[40%] bg-black/40 text-white rounded-full p-2 hover:bg-black/70 transition-all z-30 hidden border border-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button id="promo-next-btn" onclick="nextBanner()"
                    class="absolute right-3 top-[40%] bg-black/40 text-white rounded-full p-2 hover:bg-black/70 transition-all z-30 hidden border border-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>

                <div id="promo-image-container"
                    class="w-full flex-shrink relative flex items-center justify-center bg-slate-900/5 overflow-hidden hidden"
                    style="min-height: 200px;">
                    <img id="promo-image" src="" alt="Promo Banner"
                        class="w-full max-h-[60vh] object-contain transition-opacity duration-300">
                </div>

                <div id="promo-text-container"
                    class="p-6 text-center bg-gradient-to-b from-white to-amber-50 shrink-0 hidden relative flex flex-col items-center">
                    <h3 id="promo-title" class="text-xl md:text-2xl font-black text-amber-600 mb-2 tracking-wide hidden">
                    </h3>
                    <p id="promo-desc" class="text-sm text-slate-600 leading-relaxed hidden px-4"></p>
                    <div id="promo-indicators" class="flex items-center justify-center gap-1.5 mt-4 mb-2 hidden"></div>
                    <button
                        onclick="closePromo(); document.getElementById('thucdon-section').scrollIntoView({ behavior: 'smooth' });"
                        class="mt-4 px-6 py-2.5 bg-amber-500 text-white font-bold rounded-full shadow-lg hover:bg-amber-600 transition active:scale-95 text-xs uppercase tracking-wider">
                        Khám phá ngay
                    </button>
                </div>
            </div>
        </div>

        <script>
            let bannerQueue = <?php echo json_encode($promoBanners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            let currentBannerIndex = 0;
            let bannerInterval;

            document.addEventListener('DOMContentLoaded', function () {
                if (bannerQueue.length > 0) {
                    renderBanner(currentBannerIndex);

                    const indicatorsDiv = document.getElementById('promo-indicators');
                    if (bannerQueue.length > 1) {
                        indicatorsDiv.classList.remove('hidden');
                        for (let i = 0; i < bannerQueue.length; i++) {
                            const dot = document.createElement('div');
                            dot.className = `w-2 h-2 rounded-full cursor-pointer transition-all duration-300 ${i === 0 ? 'bg-amber-500 w-4' : 'bg-slate-300 hover:bg-amber-300'}`;
                            dot.onclick = () => {
                                currentBannerIndex = i;
                                renderBanner(currentBannerIndex);
                                resetBannerInterval();
                            };
                            indicatorsDiv.appendChild(dot);
                        }
                        document.getElementById('promo-prev-btn').classList.remove('hidden');
                        document.getElementById('promo-next-btn').classList.remove('hidden');
                        startBannerInterval();
                    }

                    const popup = document.getElementById('promo-popup');
                    const content = document.getElementById('promo-content');
                    popup.style.display = 'flex';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            content.classList.remove('scale-95', 'opacity-0');
                            content.classList.add('scale-100', 'opacity-100');
                        });
                    });
                }
            });

            function startBannerInterval() {
                if (bannerQueue.length > 1) {
                    bannerInterval = setInterval(() => { nextBanner(false); }, 3500);
                }
            }

            function resetBannerInterval() {
                clearInterval(bannerInterval);
                startBannerInterval();
            }

            function renderBanner(index) {
                if (index < 0 || index >= bannerQueue.length) return;
                const banner = bannerQueue[index];
                const imgContainer = document.getElementById('promo-image-container');
                const imgEl = document.getElementById('promo-image');
                const textContainer = document.getElementById('promo-text-container');
                const titleEl = document.getElementById('promo-title');
                const descEl = document.getElementById('promo-desc');

                imgEl.style.opacity = '0';
                if (banner.image_url && banner.image_url.trim() !== '') {
                    imgEl.src = banner.image_url;
                    imgContainer.classList.remove('hidden');
                    imgEl.onload = () => imgEl.style.opacity = '1';
                    imgEl.onerror = () => imgEl.style.opacity = '1';
                    if (imgEl.complete) imgEl.style.opacity = '1';
                } else {
                    imgContainer.classList.add('hidden');
                }

                let hasText = false;
                if (banner.title && banner.title.trim() !== '') {
                    titleEl.innerText = banner.title;
                    titleEl.classList.remove('hidden');
                    hasText = true;
                } else {
                    titleEl.classList.add('hidden');
                }

                if (banner.content && banner.content.trim() !== '') {
                    descEl.innerText = banner.content;
                    descEl.classList.remove('hidden');
                    hasText = true;
                } else {
                    descEl.classList.add('hidden');
                }

                if (hasText || bannerQueue.length > 1) {
                    textContainer.classList.remove('hidden');
                } else {
                    textContainer.classList.add('hidden');
                }

                const indicators = document.getElementById('promo-indicators').children;
                for (let i = 0; i < indicators.length; i++) {
                    indicators[i].className = (i === index) ? 'w-4 h-2 rounded-full cursor-pointer transition-all duration-300 bg-amber-500' : 'w-2 h-2 rounded-full cursor-pointer transition-all duration-300 bg-slate-300 hover:bg-amber-300';
                }
            }

            function nextBanner(manual = true) {
                currentBannerIndex = (currentBannerIndex + 1) % bannerQueue.length;
                renderBanner(currentBannerIndex);
                if (manual) resetBannerInterval();
            }

            function prevBanner() {
                currentBannerIndex = (currentBannerIndex - 1 + bannerQueue.length) % bannerQueue.length;
                renderBanner(currentBannerIndex);
                resetBannerInterval();
            }

            function closePromo() {
                clearInterval(bannerInterval);
                const popup = document.getElementById('promo-popup');
                const content = document.getElementById('promo-content');
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
                setTimeout(() => { popup.style.display = 'none'; }, 200);
            }

            document.getElementById('promo-popup').addEventListener('click', function (e) {
                if (e.target === this) closePromo();
            });
        </script>
    <?php endif; ?>

    <?php include __DIR__ . '/src/includes/order_tracking_widget.php'; ?>
    <?php include __DIR__ . '/src/includes/table_service_widget.php'; ?>
    <?php include __DIR__ . '/src/includes/floating_reservation_btn.php'; ?>
</body>

</html>
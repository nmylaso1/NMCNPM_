<?php
session_start();
include 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

// Bắt tham số bàn nếu quét QR (vd: dashboard.php?table=05)
if (isset($_GET['table']) && trim($_GET['table']) !== '') {
    $tNum = trim($_GET['table']);
    $_SESSION['table_number'] = str_starts_with(mb_strtolower($tNum), 'bàn') ? $tNum : ('Bàn ' . $tNum);
    $_SESSION['order_type'] = 'dine_in';
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
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
    while($row = $result_promo->fetch_assoc()) {
        $promoBanners[] = $row;
    }
}
// ==============================================

// Fetch system settings
$canteenSettings = [];

if (isset($conn)) {
    $canteenSettings = getCanteenSettings($conn);
}



// Lấy thông tin page agent
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

// Lấy thực đơn hôm nay theo từng bữa
$mealOrder = ['Sáng', 'Trưa', 'Tối'];
$menuByMeal = [
    'Sáng' => [],
    'Trưa' => [],
    'Tối' => [],
];

// Tính số lượng món ăn trong giỏ hàng
$cart_count = 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Giải phóng session lock vì ở dưới chỉ đọc dữ liệu
session_write_close();
if ($user_id > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, $user_id);
}

if (isset($conn)) {
    $checkDailyMenuTable = $conn->query("SHOW TABLES LIKE 'daily_menu'");
    if ($checkDailyMenuTable && $checkDailyMenuTable->num_rows > 0) {
        $sqlDailyMenu = "
            SELECT p.id, p.title, p.description, p.price, p.image, p.created_at, dm.meal_type, dm.is_highlight
            FROM daily_menu dm
            INNER JOIN products p ON p.id = dm.product_id
            WHERE dm.serving_date = CURDATE()
            ORDER BY FIELD(dm.meal_type, 'Sáng', 'Trưa', 'Tối'), dm.is_highlight DESC, dm.id ASC, p.id DESC
        ";
        $dailyMenuResult = $conn->query($sqlDailyMenu);
        if ($dailyMenuResult) {
            while ($row = $dailyMenuResult->fetch_assoc()) {
                $meal = $row['meal_type'] ?? '';
                if (!isset($menuByMeal[$meal])) {
                    continue;
                }
                $row['is_highlight'] = (int) ($row['is_highlight'] ?? 0);
                $menuByMeal[$meal][] = $row;
            }
        }
    }
}

$todayMenuCount = 0;
foreach ($mealOrder as $mealLabel) {
    $todayMenuCount += count($menuByMeal[$mealLabel]);
}
$hasAnyMenu = $todayMenuCount > 0;

// Lấy danh sách món yêu thích của user
$favoriteIds = [];
if ($user_id > 0) {
    $favRes = ($conn && $user_id > 0) ? $conn->query("SELECT product_id FROM favorites WHERE user_id = $user_id") : null;
    if ($favRes) {
        while ($frow = $favRes->fetch_assoc()) {
            $favoriteIds[] = (int)$frow['product_id'];
        }
    }
}

// Thêm lấy danh sách 4 món ăn mới nhất (grid từ pjAgile)
$latestProducts = [];
if (isset($conn)) {
    $check_products = $conn ? $conn->query("SHOW TABLES LIKE 'products'") : null;
    if ($check_products && $check_products->num_rows > 0) {
        // Safe migration: ensure is_hidden column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'is_hidden'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
        }
        $resLatest = $conn->query("SELECT * FROM products WHERE is_hidden = 0 ORDER BY id DESC LIMIT 4");
        if ($resLatest) {
            while ($row = $resLatest->fetch_assoc()) {
                $latestProducts[] = $row;
            }
        }
    }
}
// Tìm 1 món nổi bật cho Banner (Hero Section)
$heroItem = null;
foreach ($mealOrder as $mealLabel) {
    if (!empty($menuByMeal[$mealLabel])) {
        foreach ($menuByMeal[$mealLabel] as $item) {
            if (!empty($item['is_highlight'])) {
                $heroItem = $item;
                break 2;
            }
        }
        if (!$heroItem && count($menuByMeal[$mealLabel]) > 0) {
            $heroItem = $menuByMeal[$mealLabel][0];
        }
    }
}
if (!$heroItem) {
    // Fallback mặc định nếu chưa có món nào
    $heroItem = [
        'id' => 0,
        'title' => 'Cơm tấm',
        'subtitle' => 'Sườn Nướng',
        'description' => 'Sự kết hợp hoàn hảo giữa sườn nướng sốt mật ong thơm lừng và cơm tấm mềm dẻo. Hương vị đậm đà, chuẩn vị nhà hàng!',
        'image' => 'assets/img/hero/bbq.png',
        'price' => 45000
    ];
} else {
    $heroItem['subtitle'] = $heroItem['title'];
    $heroItem['title'] = 'Món Ngon';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhà Hàng Gourmet - Đặt Món</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <?php if ($pageAgentScriptSrc): ?>
    <script>
        window.__PAGE_AGENT_SCRIPT_SRC__ = <?php echo json_encode($pageAgentScriptSrc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="<?php echo htmlspecialchars($pageAgentScriptSrc, ENT_QUOTES, 'UTF-8'); ?>" crossorigin="true" defer></script>
    <?php endif; ?>
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
        body { font-family: 'Quicksand', -apple-system, sans-serif; background-color: #faf7f2; color: #1c1917; }
        h1, h2, h3, .font-serif { font-family: 'Quicksand', sans-serif; font-weight: 700; }
        
        svg { max-width: 100%; vertical-align: middle; }
        .w-3\.5 { width: 0.875rem !important; height: 0.875rem !important; }
        .w-4 { width: 1rem !important; height: 1rem !important; }
        .w-5 { width: 1.25rem !important; height: 1.25rem !important; }
        
        .blob-1 { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        .blob-2 { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; }
        
        .food-card {
            background: #ffffff;
            border: 1px solid rgba(234, 88, 12, 0.12);
            border-radius: 2rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(234, 88, 12, 0.05);
        }
        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(234, 88, 12, 0.12);
            border-color: rgba(234, 88, 12, 0.25);
        }

        .toast-container.top-right { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast-container.bottom-center { position: fixed; bottom: 40px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column-reverse; gap: 10px; pointer-events: none; align-items: center; }
        .toast { background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); border-radius: 18px; padding: 14px 22px; box-shadow: 0 15px 35px -5px rgba(234, 88, 12, 0.18); display: flex; align-items: center; gap: 12px; min-width: 250px; pointer-events: auto; border-left: 4px solid #ea580c; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .toast-container.top-right .toast { transform: translateX(120%); animation: toast-in-right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .toast-container.bottom-center .toast { transform: translateY(120%); animation: toast-in-bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; border-left: none; border-bottom: 4px solid #ea580c; }
        .toast.success { border-color: #ea580c; }
        .toast.error { border-color: #ef4444; }
        @keyframes toast-in-right { to { transform: translateX(0); } }
        @keyframes toast-in-bottom { to { transform: translateY(0); } }
        @keyframes toast-out-right { to { transform: translateX(120%); opacity: 0; } }
        @keyframes toast-out-bottom { to { transform: translateY(120%); opacity: 0; } }
        .food-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%; /* 1:1 Aspect Ratio */
            border-radius: 50%;
            overflow: hidden;
            background-color: #faf7f2;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .food-img-wrapper img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;
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

        /* Suggestion Styling */
        .premium-glow {
            box-shadow: 0 0 20px rgba(234, 88, 12, 0.25);
            border: 1px solid rgba(234, 88, 12, 0.2);
            background: linear-gradient(145deg, rgba(255,255,255,0.95), rgba(255,247,237,0.9));
        }
        .premium-badge {
            background: linear-gradient(90deg, #ea580c, #c2410c);
            color: transparent;
            -webkit-background-clip: text;
        }
        .shimmer {
            background: #f6f7f8;
            background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-repeat: no-repeat;
            background-size: 800px 100%; 
            animation-duration: 1.5s;
            animation-fill-mode: forwards; 
            animation-iteration-count: infinite;
            animation-name: placeholderShimmer;
            animation-timing-function: linear;
        }
        @keyframes placeholderShimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; } 
        }
        
        .animate-float { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-15px) rotate(3deg); } 100% { transform: translateY(0px) rotate(0deg); } }
    </style>
</head>
<body class="relative min-h-screen flex flex-col">

    <div class="absolute top-0 right-0 w-[550px] h-[550px] bg-gradient-to-br from-orange-200/35 to-amber-100/25 blob-1 -z-10 -translate-y-1/4 translate-x-1/4 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-gradient-to-tr from-amber-100/30 to-orange-100/25 blob-2 -z-10 translate-y-1/4 -translate-x-1/4 blur-2xl"></div>

    <?php if (!empty($_SESSION['table_number'])): ?>
    <!-- AT-TABLE TOP BANNER -->
    <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white text-xs font-bold py-2.5 px-4 text-center flex items-center justify-center gap-2 shadow-sm relative z-50">
        <i data-lucide="utensils" class="w-4 h-4 text-emerald-200"></i>
        <span>Quý khách đang đặt món tại <strong><?php echo htmlspecialchars($_SESSION['table_number']); ?></strong> (Ăn tại quán)</span>
        <a href="order.php" class="bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded text-[11px] ml-2 transition">Xem đơn bàn này</a>
    </div>
    <?php endif; ?>

    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1">
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
                    <!-- Thanh tìm kiếm -->
                    <form action="menu.php" method="GET" class="hidden md:flex items-center bg-orange-50/60 border border-orange-200/60 rounded-full px-3 py-1.5 mr-2">
                        <i data-lucide="search" class="w-4 h-4 text-orange-600"></i>
                        <input type="text" name="search" placeholder="Tìm món ngon..." class="bg-transparent border-none outline-none text-sm px-2 text-[#1c1917] w-32 focus:w-48 transition-all placeholder-stone-400 font-medium">
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
                    <a href="order.php" class="navbar-item relative pr-8">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#ea580c] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-orange-200"><?php echo $cart_count; ?></span>
                    </a>
                    <?php if (in_array(($role ?? ''), ['admin', 'staff'])): ?>
                    <a href="src/admin/index.php" class="navbar-item" style="background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border-radius:12px;padding:6px 14px;">
                        <i data-lucide="<?php echo ($role ?? '') === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                        <?php echo ($role ?? '') === 'admin' ? 'Admin' : 'Quản lý'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-6 pt-0 pb-4 relative z-10 flex-grow w-full">

        <div class="mt-2 md:mt-3 flex flex-col md:flex-row items-center justify-between gap-12">
            <div class="flex-1 space-y-6">
                <div class="inline-flex items-center gap-2.5 px-4 py-1.5 bg-orange-500/10 border border-orange-500/20 rounded-full mb-3 shadow-sm">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-orange-600"></i>
                    <span class="text-[10px] font-extrabold uppercase tracking-[0.15em] text-orange-900/80">
                        Giờ mở cửa: <?php echo date('H:i', strtotime($canteenSettings['open_time'] ?? '06:30')); ?> - <?php echo date('H:i', strtotime($canteenSettings['close_time'] ?? '21:00')); ?>
                    </span>
                </div>
                <span class="text-sm font-bold tracking-[0.2em] uppercase text-orange-600/80 block">Món Nổi Bật Hôm Nay</span>
                <h1 class="text-5xl md:text-7xl font-serif font-bold leading-tight text-[#1c1917]">
                    <?php echo htmlspecialchars($heroItem['title']); ?><br>
                    <span class="italic text-[#ea580c]"><?php echo htmlspecialchars($heroItem['subtitle']); ?></span>
                </h1>
                <p class="text-[#57534e] max-w-sm leading-relaxed text-sm h-16 line-clamp-3">
                    <?php echo htmlspecialchars(!empty($heroItem['description']) ? $heroItem['description'] : 'Hương vị tuyệt hảo, nguyên liệu tươi sạch, được chế biến tận tâm từ những đầu bếp hàng đầu.'); ?>
                </p>
                <div class="pt-4 flex flex-wrap items-center gap-4">
                    <button onclick="document.getElementById('thucdon-section').scrollIntoView({ behavior: 'smooth' });" class="add-btn inline-flex items-center gap-2 shadow-xl shadow-orange-900/10 active:scale-95 text-sm md:text-base cursor-pointer">
                        Xem thực đơn <i data-lucide="arrow-down" class="w-4 h-4"></i>
                    </button>
                    
                    <button onclick="pickRandomDish()" class="bg-white text-orange-600 border border-orange-200 hover:bg-orange-50 font-bold px-4 py-2 flex items-center gap-2 shadow-lg shadow-orange-900/5 active:scale-95 text-sm md:text-base cursor-pointer transition-all duration-300" style="border-radius: 9999px;">
                        🎲 Hôm nay ăn gì?
                    </button>
                    
                    <?php if ($heroItem['id'] > 0): ?>
                    <button onclick="addToCart(<?php echo $heroItem['id']; ?>, '<?php echo addslashes($heroItem['subtitle']); ?>', <?php echo $heroItem['price']; ?>, '<?php echo addslashes($heroItem['image']); ?>', this)" class="inline-flex items-center gap-2 font-bold text-[#ea580c] bg-orange-50 px-5 py-2.5 rounded-full hover:bg-orange-100 transition-colors active:scale-95 text-sm md:text-base cursor-pointer border border-orange-200">
                        <i data-lucide="plus" class="w-4 h-4"></i> Thêm vào giỏ
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 relative w-full flex justify-center mt-10 md:mt-0">
                <div class="absolute top-10 left-5 md:left-10 text-xl text-orange-400 animate-pulse">✨</div>
                <div class="absolute bottom-20 -right-5 md:right-5 text-2xl text-emerald-600 rotate-45 opacity-60">🌿</div>
                
                <div class="relative w-full max-w-md mx-auto drop-shadow-2xl">
                    <div class="w-full aspect-square rounded-full overflow-hidden bg-gradient-to-br from-orange-100 to-amber-50 p-4 border-4 border-white shadow-xl">
                        <img src="<?php echo htmlspecialchars($heroItem['image']); ?>" alt="<?php echo htmlspecialchars($heroItem['subtitle']); ?>" onerror="this.onerror=null; this.src='assets/img/hero/bbq.png';" class="w-full h-full object-cover rounded-full shadow-inner transform hover:scale-105 transition duration-700">
                    </div>
                    <?php if (isset($heroItem['price'])): ?>
                    <!-- Price Tag floating -->
                    <div class="absolute -bottom-2 -left-2 bg-white rounded-full border-4 border-[#faf7f2] shadow-xl px-4 py-2 animate-float" style="animation-delay: 1s;">
                        <span class="font-sans font-bold text-[#ea580c] text-lg">
                            <?php echo number_format((float) $heroItem['price'], 0, ',', '.'); ?>đ
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="ai-recommend-section" class="mt-14">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center shadow-lg" style="width:40px;height:40px;min-width:40px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" style="width:20px;height:20px;min-width:20px;max-width:20px;" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/><circle cx="18" cy="6" r="3"/><path d="M18 3v3l2 1"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-[#2d261a]">Gợi Ý Cho Bạn</h2>
                        <p class="text-[11px] text-gray-400 font-medium">Dựa trên lịch sử & xu hướng hôm nay</p>
                    </div>
                </div>
                <button onclick="loadRecommendations()" id="ai-refresh-btn"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-violet-50 border border-violet-200 text-violet-600 font-bold text-xs hover:bg-violet-100 transition active:scale-95">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Gợi ý lại
                </button>
            </div>

            <div class="flex items-center gap-3 mb-5 flex-wrap">
                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Ngân sách:</span>
                <?php foreach ([0, 20000, 30000, 50000] as $b): ?>
                <button onclick="setBudget(<?php echo $b; ?>)" data-budget="<?php echo $b; ?>"
                    class="budget-btn px-3 py-1.5 rounded-full text-xs font-bold border transition
                    <?php echo $b === 0 ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-gray-600 border-gray-200 hover:border-violet-400'; ?>">
                    <?php echo $b === 0 ? 'Tất cả' : 'Dưới ' . number_format($b, 0, ',', '.') . 'đ'; ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div id="ai-cards" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="ai-skeleton bg-white/60 rounded-2xl p-4 animate-pulse">
                    <div class="w-3/4 mx-auto aspect-square rounded-full bg-gray-200 mb-3"></div>
                    <div class="h-3 bg-gray-200 rounded-full w-3/4 mx-auto mb-2"></div>
                    <div class="h-3 bg-gray-200 rounded-full w-1/2 mx-auto"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div id="thucdon-section" class="mt-16 pt-12 mb-14">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-8">
                <div class="flex items-center gap-3">
                    <span style="font-size: 24px;">🍲</span>
                    <h2 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a]">
                        Thực đơn hôm nay
                    </h2>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/70 border border-white/70 text-[11px] font-semibold uppercase tracking-wider text-[#8e836e]">
                    <i data-lucide="utensils-crossed" class="w-3.5 h-3.5 text-[#d97706]"></i>
                    <?php echo $todayMenuCount; ?> món trong ngày
                </div>
            </div>

            <?php if (!$hasAnyMenu): ?>
                <div class="relative overflow-hidden bg-white/70 border border-white/70 rounded-3xl backdrop-blur-sm p-8 md:p-10">
                    <div class="absolute -top-10 -right-8 w-36 h-36 rounded-full bg-amber-100/70 blur-2xl"></div>
                    <div class="absolute -bottom-10 -left-8 w-36 h-36 rounded-full bg-green-100/60 blur-2xl"></div>

                    <div class="relative z-10 text-center max-w-2xl mx-auto">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm mb-4">
                            <i data-lucide="chef-hat" class="w-8 h-8"></i>
                        </div>

                        <h3 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a] mb-2">
                            Hôm nay chưa có thực đơn
                        </h3>

                        <p class="text-[#7b6f5b] leading-relaxed mb-6">
                            <?php if (in_array($role, ['admin', 'staff'])): ?>
                                Hiện chưa có món cho các bữa Sáng, Trưa, Tối. Vui lòng cập nhật thực đơn ngày để hệ thống hiển thị món ăn.
                            <?php else: ?>
                                Hiện chưa có món cho các bữa Sáng, Trưa, Tối. Quán đang cập nhật thực đơn hôm nay, bạn vui lòng quay lại sau nhé.
                            <?php endif; ?>
                        </p>

                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <?php if (in_array($role, ['admin', 'staff'])): ?>
                                <a href="src/admin/manage_daily_menu.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#d97706] text-white font-semibold hover:bg-amber-700 transition-all shadow-md shadow-amber-900/10">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                    Cập nhật thực đơn ngay
                                </a>
                            <?php else: ?>
                                <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#d97706] text-white font-semibold hover:bg-amber-700 transition-all shadow-md shadow-amber-900/10">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                    Tải lại thực đơn
                                </button>
                            <?php endif; ?>

                            <a href="#" onclick="window.scrollTo({ top: 0, behavior: 'smooth' }); return false;" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white text-[#d97706] border border-amber-200 font-semibold hover:bg-amber-50 transition-all">
                                <i data-lucide="arrow-up" class="w-4 h-4"></i>
                                Về đầu trang
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                    <?php foreach ($mealOrder as $mealIndex => $mealLabel): ?>
                        <?php $mealItems = $menuByMeal[$mealLabel]; ?>
                        <section class="bg-white/50 border border-white/70 rounded-2xl p-4 backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="inline-flex items-center gap-2 text-lg font-bold text-[#2d261a]">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#d97706]"></span>
                                    <?php echo htmlspecialchars($mealLabel); ?>
                                </h3>
                                <span class="text-[11px] font-semibold text-[#a39a85] uppercase tracking-wider">
                                    <?php echo count($mealItems); ?> món
                                </span>
                            </div>

                            <?php if (empty($mealItems)): ?>
                                <div class="text-xs text-[#7b6f5b] italic bg-white/55 rounded-xl border border-white/60 p-4">
                                    Chưa có món cho bữa <?php echo htmlspecialchars($mealLabel); ?>.
                                </div>
                            <?php else: ?>
                                <?php
                                    $featuredItem = $mealItems[0];
                                    foreach ($mealItems as $candidateItem) {
                                        if (!empty($candidateItem['is_highlight'])) {
                                            $featuredItem = $candidateItem;
                                            break;
                                        }
                                    }
                                ?>
                                <div class="meal-preview-card">
                                    <a href="food_detail.php?id=<?php echo $featuredItem['id']; ?>" class="block mt-14">
                                    <article class="food-card p-4 relative group">
                                        <?php if (!empty($featuredItem['is_highlight'])): ?>
                                        <div class="absolute -top-2 -right-2 z-20 bg-[#d97706] text-white text-[9px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md transform rotate-12">
                                            Gợi ý
                                        </div>
                                        <?php endif; ?>

                                        <div class="relative">
                                            <div class="food-img-wrapper z-10 w-[70%] mx-auto -mt-6 group-hover:-mt-8 transition-all duration-300 cursor-pointer" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($featuredItem, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                                <img src="<?php echo htmlspecialchars($featuredItem['image']); ?>" alt="<?php echo htmlspecialchars($featuredItem['title']); ?>">
                                            </div>
                                        </div>

                                        <div class="mt-4 text-center">
                                            <h3 class="font-serif font-bold text-base text-[#2d261a] leading-snug mb-1 cursor-pointer hover:text-[#d97706] transition-colors" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($featuredItem, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                                <?php echo htmlspecialchars($featuredItem['title']); ?>
                                            </h3>

                                            <div class="flex justify-center gap-0.5 mt-1.5 mb-2.5 text-amber-500 outer-stars-<?php echo $featuredItem['id']; ?>">
                                                <?php echo renderStars(empty($featuredItem['reviews_count']) ? 0 : (float)($featuredItem['rating'] ?? 4.5)); ?>
                                            </div>

                                            <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 h-8">
                                                <?php echo htmlspecialchars(!empty($featuredItem['description']) ? $featuredItem['description'] : 'Hương vị đặc biệt, dinh dưỡng và chuẩn vị tươi ngon.'); ?>
                                            </p>
                                        </div>

                                        <div class="mt-4 flex items-center justify-between bg-white/60 rounded-xl p-2 pl-3 backdrop-blur-sm shadow-sm border border-white/40">
                                            <span class="font-sans font-bold text-[#d97706] text-base">
                                                <?php echo number_format((float) $featuredItem['price'], 0, ',', '.'); ?>đ
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <?php $isFav = in_array((int)$featuredItem['id'], $favoriteIds); ?>
                                                <button onclick="event.preventDefault(); toggleFavorite(<?php echo $featuredItem['id']; ?>, this)" 
                                                        class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all duration-300 active:scale-90 <?php echo $isFav ? 'bg-red-50 text-red-500' : 'bg-white text-gray-200 hover:text-red-300'; ?>"
                                                        data-product-id="<?php echo $featuredItem['id']; ?>">
                                                    <i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i>
                                                </button>
                                                <button onclick="event.stopPropagation(); addToCart(<?php echo $featuredItem['id']; ?>, '<?php echo addslashes($featuredItem['title']); ?>', <?php echo $featuredItem['price']; ?>, '<?php echo addslashes($featuredItem['image']); ?>', this, true)" class="bg-amber-100 text-amber-700 p-1.5 rounded-lg shadow-sm hover:bg-amber-200 active:scale-90 transition-all flex items-center justify-center min-w-[32px] min-h-[32px]" title="Mua nhanh">
                                                    <i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i>
                                                </button>
                                                <button onclick="event.stopPropagation(); addToCart(<?php echo $featuredItem['id']; ?>, '<?php echo addslashes($featuredItem['title']); ?>', <?php echo $featuredItem['price']; ?>, '<?php echo addslashes($featuredItem['image']); ?>', this)" class="bg-[#d97706] text-white p-1.5 rounded-lg shadow-md hover:bg-amber-700 active:scale-90 transition-all flex items-center justify-center min-w-[32px] min-h-[32px]" aria-label="Thêm món <?php echo htmlspecialchars($featuredItem['title']); ?>">
                                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                    </a>
                                </div>

                                <?php if (count($mealItems) > 1): ?>
                                    <div class="mt-3">
                                        <button
                                            type="button"
                                            class="meal-modal-open-btn w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-amber-200 bg-amber-50/70 text-[11px] font-semibold uppercase tracking-wider text-amber-700 hover:bg-amber-100 transition-colors"
                                            data-target="meal-modal-<?php echo $mealIndex; ?>"
                                        >
                                            <i data-lucide="list" class="w-3.5 h-3.5"></i>
                                            <span>Xem tất cả (<?php echo count($mealItems); ?> món)</span>
                                        </button>
                                    </div>

                                    <div id="meal-modal-<?php echo $mealIndex; ?>" class="meal-modal hidden" role="dialog" aria-modal="true" aria-label="Danh sách món bữa <?php echo htmlspecialchars($mealLabel); ?>" style="position: fixed; inset: 0; z-index: 9999;">
                                        <div class="meal-modal-backdrop absolute inset-0 bg-[#2d261a]/45 backdrop-blur-[2px]"></div>
                                        <div class="relative min-h-screen flex items-center justify-center p-3 md:p-5">
                                            <div class="bg-[#fcfbf8] w-full max-w-screen-xl max-h-[92vh] rounded-3xl shadow-2xl border border-amber-100 overflow-hidden">
                                                <div class="flex items-center justify-between px-5 py-4 border-b border-amber-100 bg-white/80">
                                                    <h4 class="text-lg md:text-xl font-bold text-[#2d261a] inline-flex items-center gap-2">
                                                        <span class="w-2.5 h-2.5 rounded-full bg-[#d97706]"></span>
                                                        <?php echo htmlspecialchars($mealLabel); ?> - <?php echo count($mealItems); ?> món
                                                    </h4>
                                                    <button type="button" class="meal-modal-close w-9 h-9 rounded-full bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100 inline-flex items-center justify-center" aria-label="Đóng popup">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                </div>

                                                <div class="p-4 md:p-5 overflow-y-auto max-h-[calc(92vh-78px)]">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-5 gap-y-9 pt-12">
                                                        <?php foreach ($mealItems as $index => $p): ?>
                                                            <a href="food_detail.php?id=<?php echo $p['id']; ?>" class="block h-full mt-10">
                                                            <article class="food-card p-4 relative group h-full flex flex-col">
                                                                <?php if (!empty($p['is_highlight'])): ?>
                                                                <div class="absolute -top-2 -right-2 z-20 bg-[#d97706] text-white text-[9px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-full shadow-md transform rotate-12">
                                                                    Gợi ý
                                                                </div>
                                                                <?php endif; ?>

                                                                <div class="relative">
                                                                    <div class="food-img-wrapper z-10 w-[70%] mx-auto -mt-6 group-hover:-mt-8 transition-all duration-300 cursor-pointer" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
                                                                    </div>
                                                                </div>

                                                                <div class="mt-4 text-center">
                                                                    <h3 class="font-serif font-bold text-base text-[#2d261a] leading-snug mb-1 cursor-pointer hover:text-[#d97706] transition-colors" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                                                        <?php echo htmlspecialchars($p['title']); ?>
                                                                    </h3>

                                                                    <div class="flex justify-center gap-0.5 mt-1.5 mb-2.5 text-amber-500 outer-stars-<?php echo $p['id']; ?>">
                                                                        <?php echo renderStars(empty($p['reviews_count']) ? 0 : (float)($p['rating'] ?? 4.5)); ?>
                                                                    </div>

                                                                    <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 h-8">
                                                                        <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Hương vị đặc biệt, dinh dưỡng và chuẩn vị tươi ngon.'); ?>
                                                                    </p>
                                                                </div>

                                                                <div class="mt-auto pt-4 flex items-center justify-between bg-white/60 rounded-xl p-2 pl-3 backdrop-blur-sm shadow-sm border border-white/40">
                                                                    <span class="font-sans font-bold text-[#d97706] text-base">
                                                                        <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                                                                    </span>
                                                                    <div class="flex items-center gap-2">
                                                                        <?php $isFavModal = in_array((int)$p['id'], $favoriteIds); ?>
                                                                        <button onclick="event.preventDefault(); toggleFavorite(<?php echo $p['id']; ?>, this)" 
                                                                                class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all duration-300 active:scale-90 <?php echo $isFavModal ? 'bg-red-50 text-red-500' : 'bg-white text-gray-200 hover:text-red-300'; ?>"
                                                                                data-product-id="<?php echo $p['id']; ?>">
                                                                            <i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i>
                                                                        </button>
                                                                        <button onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this, true)" class="bg-amber-100 text-amber-700 p-1.5 rounded-lg shadow-sm hover:bg-amber-200 active:scale-90 transition-all flex items-center justify-center min-w-[32px] min-h-[32px]" title="Mua nhanh">
                                                                            <i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i>
                                                                        </button>
                                                                        <button onclick="event.stopPropagation(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)" class="bg-[#d97706] text-white p-1.5 rounded-lg shadow-md hover:bg-amber-700 active:scale-90 transition-all flex items-center justify-center min-w-[32px] min-h-[32px]" aria-label="Thêm món <?php echo htmlspecialchars($p['title']); ?>">
                                                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </article>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <button onclick="window.location.href='menu.php'" class="md:hidden mt-8 w-full flex items-center justify-center gap-2 py-3 rounded-full border-2 border-[#d97706] text-[#d97706] font-bold uppercase tracking-widest text-xs hover:bg-[#d97706] hover:text-white transition cursor-pointer">
                Xem toàn bộ thực đơn <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- MÓN MỚI CẬP NHẬT (Lưới sản phẩm) -->
        <div class="mt-8 mb-20">
            <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4 mb-14">
                <div class="flex items-center gap-3">
                    <span class="text-3xl text-[#d97706] rotate-12">🌿</span>
                    <h2 class="text-2xl md:text-3xl font-serif font-bold text-[#2d261a]">
                        Món mới cập nhật
                    </h2>
                </div>
                <button onclick="window.location.href='menu.php'" class="flex items-center gap-1 text-sm font-semibold text-[#a39a85] hover:text-[#d97706] transition-colors uppercase tracking-wider">
                    Xem tất cả <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-16">
                <?php if (empty($latestProducts)): ?>
                    <div class="col-span-full text-center py-10">
                        <p class="text-gray-500 italic">Hiện tại chưa có món ăn nào trong thực đơn. Admin hãy thêm món ăn mới nhé!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($latestProducts as $index => $p): ?>
                        <a href="food_detail.php?id=<?php echo $p['id']; ?>" class="block h-full mt-16">
                        <div class="food-card p-5 relative group h-full flex flex-col">
                            <!-- Popular tag (show on first item) -->
                            <?php if ($index === 0): ?>
                            <div class="absolute -top-3 -right-3 z-20 bg-[#d97706] text-white text-[10px] uppercase font-bold tracking-wider px-3 py-1.5 rounded-full shadow-md transform rotate-12">
                                Nổi bật
                            </div>
                            <?php endif; ?>

                            <!-- Food Image -->
                            <div class="food-img-wrapper z-10 w-4/5 mx-auto -mt-16 group-hover:-mt-20 transition-all duration-300">
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?q=80&w=800&auto=format&fit=crop'">
                            </div>

                            <!-- Content -->
                            <div class="mt-6 text-center">
                                <h3 class="font-serif font-bold text-lg text-[#2d261a] leading-snug mb-1">
                                    <?php echo htmlspecialchars($p['title']); ?>
                                </h3>
                                
                                <!-- Fake Rating -->
                                <div class="flex justify-center gap-0.5 mt-2 mb-3 text-amber-500">
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    <i data-lucide="star-half" class="w-3.5 h-3.5 fill-current"></i>
                                </div>
                                
                                <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 h-8">
                                    <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Hương vị đặc biệt, dinh dưỡng và chuẩn vị tươi ngon.'); ?>
                                </p>
                            </div>

                            <!-- Footer: Price & Add button -->
                            <div class="mt-auto pt-6 flex items-center justify-between bg-white/60 rounded-[1.2rem] p-2 pl-4 backdrop-blur-sm shadow-sm border border-white/40">
                                <span class="font-sans font-bold text-[#d97706] text-lg">
                                    <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                                </span>
                                <div class="flex items-center gap-2">
                                    <?php $isFavLatest = in_array((int)$p['id'], $favoriteIds); ?>
                                    <button onclick="event.preventDefault(); toggleFavorite(<?php echo $p['id']; ?>, this)" 
                                            class="fav-btn w-8 h-8 flex items-center justify-center rounded-lg border border-amber-100 transition-all duration-300 active:scale-90 <?php echo $isFavLatest ? 'bg-red-50 text-red-500' : 'bg-white text-gray-200 hover:text-red-300'; ?>"
                                            data-product-id="<?php echo $p['id']; ?>">
                                        <i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i>
                                    </button>
                                    <button onclick="event.preventDefault(); addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)" class="bg-[#d97706] text-white p-2 rounded-xl shadow-md hover:bg-amber-700 active:scale-95 transition-all">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <footer class="bg-[#423826] text-[#d4cbb8] py-12 border-t-[6px] border-[#d97706] mt-20">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10">
            <div class="space-y-4">
                <div class="flex items-center gap-3 text-2xl font-serif font-bold text-[#d97706] tracking-wide mb-4">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="w-12 h-12 rounded-full object-contain shadow-sm">
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
                    Kết tinh dinh dưỡng cho dân IT và Kinh tế. Phục vụ bữa ăn ngon nhất mọi lúc, thao tác tiện lợi nhanh chóng.
                </p>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Liên kết</h4>
                <ul class="space-y-3 text-sm opacity-80">
                    <li><a href="dashboard.php" class="hover:text-amber-500 transition">Trang chủ</a></li>
                    <li><a href="menu.php" class="hover:text-amber-500 transition">Thực đơn</a></li>
                    <li><a href="info.php#khuyen-mai" class="hover:text-amber-500 transition">Khuyến mãi</a></li>
                    <li><a href="info.php#tuyen-dung" class="hover:text-amber-500 transition">Tuyển dụng</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Hỗ trợ</h4>
                <ul class="space-y-3 text-sm opacity-80">
                    <li><a href="info.php#chinh-sach-giao-hang" class="hover:text-amber-500 transition">Chính sách giao hàng</a></li>
                    <li><a href="info.php#dieu-khoan-su-dung" class="hover:text-amber-500 transition">Điều khoản sử dụng</a></li>
                    <li><a href="info.php#phan-hoi" class="hover:text-amber-500 transition">Phản hồi & Khách báo</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-serif font-bold text-white mb-4 uppercase tracking-wider text-sm">Kết nối</h4>
                <p class="text-sm opacity-80 mb-3"><?php echo htmlspecialchars($canteenSettings['canteen_address'] ?? 'Tầng trệt tòa B, Khuôn viên trường ĐH'); ?></p>
                <p class="text-sm text-amber-500 font-bold mb-1">Hotline: <?php echo htmlspecialchars($canteenSettings['canteen_hotline'] ?? '0987.654.321'); ?></p>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#d97706] hover:text-white transition cursor-pointer">
                        <i data-lucide="facebook" class="w-4 h-4"></i>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#d97706] hover:text-white transition cursor-pointer">
                        <i data-lucide="instagram" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-12 text-center text-xs opacity-50 border-t border-white/10 pt-6 font-medium">
            &copy; 2026 HDPTTM Canteen. Hệ thống đặt món ăn nhanh chóng cho sinh viên.
        </div>
    </footer>

    <script>
        // === AI RECOMMENDATION LOGIC ===
        let currentBudget = 0;

        function setBudget(budget) {
            currentBudget = budget;
            document.querySelectorAll('.budget-btn').forEach(btn => {
                const isActive = parseInt(btn.dataset.budget) === budget;
                btn.className = btn.className.replace(/bg-violet-600 text-white border-violet-600|bg-white text-gray-600 border-gray-200/g, '');
                if (isActive) {
                    btn.classList.add('bg-violet-600', 'text-white', 'border-violet-600');
                    btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
                } else {
                    btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
                    btn.classList.remove('bg-violet-600', 'text-white', 'border-violet-600');
                }
            });
            loadRecommendations();
        }

        async function loadRecommendations() {
            const container = document.getElementById('ai-cards');
            const btn = document.getElementById('ai-refresh-btn');
            
            container.innerHTML = `${Array(4).fill(`
                <div class="ai-skeleton bg-white/60 rounded-2xl p-4 animate-pulse">
                    <div class="w-3/4 mx-auto aspect-square rounded-full bg-gray-200 mb-3"></div>
                    <div class="h-3 bg-gray-200 rounded-full w-3/4 mx-auto mb-2"></div>
                    <div class="h-3 bg-gray-200 rounded-full w-1/2 mx-auto"></div>
                </div>`).join('')}`;
            
            if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }

            try {
                const now = new Date();
                const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                const params = new URLSearchParams({ time: timeStr, budget: currentBudget, limit: 4 });
                const res = await fetch('get_recommendations.php?' + params.toString());
                const data = await res.json();

                if (data.success && data.recommendations.length > 0) {
                    container.innerHTML = data.recommendations.map(item => `
                        <div class="food-card p-4 relative group cursor-pointer" onclick="openFoodDetailModal(${JSON.stringify({
                            id: item.dish_id, title: item.name, price: parseFloat(item.price),
                            image: item.image, description: item.description || ""
                        }).replace(/"/g, '&quot;')})">
                            <div class="absolute -top-2 -right-2 z-10 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-[9px] font-bold px-2 py-0.5 rounded-full shadow">
                                ✨
                            </div>
                            <div class="relative -mt-5 mb-3">
                                <div class="food-img-wrapper w-[70%] mx-auto group-hover:-translate-y-1 transition-transform duration-300">
                                    <img src="${item.image}" alt="${item.name}" onerror="this.src='assets/img/hero/bbq.png'">
                                </div>
                            </div>
                            <div class="text-center">
                                <h3 class="font-bold text-sm text-[#2d261a] line-clamp-1 mb-1">${item.name}</h3>
                                <p class="text-[10px] text-violet-500 font-semibold line-clamp-2 mb-2 h-7">${item.reason}</p>
                            </div>
                            <div class="flex items-center justify-between bg-white/60 rounded-xl p-1.5 pl-3 border border-white/40">
                                <span class="font-bold text-[#d97706] text-sm">${Number(item.price).toLocaleString('vi-VN')}đ</span>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="event.stopPropagation(); addToCart(${item.dish_id}, '${item.name.replace(/'/g,"\\'")}', ${item.price}, '${item.image.replace(/'/g,"\\'")}', this, true)"
                                        class="bg-violet-100 text-violet-700 p-1.5 rounded-lg hover:bg-violet-200 active:scale-90 transition flex items-center justify-center" title="Mua nhanh">
                                        <i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i>
                                    </button>
                                    <button onclick="event.stopPropagation(); addToCart(${item.dish_id}, '${item.name.replace(/'/g,"\\'")}', ${item.price}, '${item.image.replace(/'/g,"\\'")}', this)"
                                        class="bg-[#d97706] text-white p-1.5 rounded-lg hover:bg-amber-700 active:scale-90 transition flex items-center justify-center">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    lucide.createIcons();
                } else {
                    container.innerHTML = `<div class="col-span-4 text-center py-8 text-gray-400 text-sm">Chưa có gợi ý phù hợp. Hãy thử chọn ngân sách khác!</div>`;
                }
            } catch (e) {
                console.error('AI Recommendation error:', e);
                container.innerHTML = `<div class="col-span-4 text-center py-8 text-gray-400 text-sm">Không thể tải gợi ý lúc này.</div>`;
            }

            if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }
        }

        document.addEventListener('DOMContentLoaded', () => loadRecommendations());

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
                        btn.classList.remove('bg-white', 'text-gray-200');
                        btn.classList.add('bg-red-50', 'text-red-500');
                    } else {
                        btn.classList.remove('bg-red-50', 'text-red-500');
                        btn.classList.add('bg-white', 'text-gray-200');
                    }
                    showToast(data.message, 'success', 'bottom-center');
                } else {
                    showToast(data.message || 'Lỗi', 'error');
                }
            } catch (e) {
                console.error(e);
            }
        }

        lucide.createIcons();

        (function () {
            var wrap = document.getElementById('profileMenuWrap');
            var trigger = document.getElementById('profileMenuTrigger');
            var dropdown = document.getElementById('profileMenuDropdown');
            var chevron = document.getElementById('profileMenuChevron');

            if (!wrap || !trigger || !dropdown) {
                return;
            }

            function setOpen(open) {
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                dropdown.classList.toggle('opacity-0', !open);
                dropdown.classList.toggle('invisible', !open);
                dropdown.classList.toggle('pointer-events-none', !open);
                dropdown.classList.toggle('opacity-100', open);
                dropdown.classList.toggle('visible', open);
                dropdown.classList.toggle('translate-y-2', !open);
                dropdown.classList.toggle('translate-y-0', open);
                if (chevron) {
                    chevron.classList.toggle('rotate-180', open);
                }
            }

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var isOpen = trigger.getAttribute('aria-expanded') === 'true';
                setOpen(!isOpen);
            });

            document.addEventListener('click', function (event) {
                if (!wrap.contains(event.target)) {
                    setOpen(false);
                }
            });
        })();

        (function () {
            var openButtons = document.querySelectorAll('.meal-modal-open-btn');
            if (!openButtons.length) {
                return;
            }

            var modalNodes = document.querySelectorAll('.meal-modal');
            modalNodes.forEach(function (modal) {
                document.body.appendChild(modal);
            });

            function closeModal(modal) {
                if (!modal) {
                    return;
                }
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            function openModal(modal) {
                if (!modal) {
                    return;
                }
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            openButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var targetId = button.getAttribute('data-target');
                    if (!targetId) {
                        return;
                    }
                    var modal = document.getElementById(targetId);
                    openModal(modal);
                });
            });

            var modals = document.querySelectorAll('.meal-modal');
            modals.forEach(function (modal) {
                var backdrop = modal.querySelector('.meal-modal-backdrop');
                var closeButton = modal.querySelector('.meal-modal-close');

                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        closeModal(modal);
                    });
                }

                if (closeButton) {
                    closeButton.addEventListener('click', function () {
                        closeModal(modal);
                    });
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }
                var activeModal = document.querySelector('.meal-modal:not(.hidden)');
                if (activeModal) {
                    closeModal(activeModal);
                }
            });
        })();

        async function addToCart(productId, title, price, image, btn, isBuyNow = false) {
            if (!window.IS_CANTEEN_OPEN) {
                showToast('Ngoài giờ phục vụ (' + window.CANTEEN_HOURS + ')', 'error', 'bottom-center');
                return;
            }
            if (!btn) return;
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin h-3.5 w-3.5 ' + (isBuyNow ? 'text-amber-700' : 'text-white') + '" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

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
                <i data-lucide="${icon}" class="w-5 h-5 ${type === 'success' ? 'text-[#d97706]' : 'text-red-500'}"></i>
                <span class="text-sm font-bold text-[#423826]">${message}</span>
            `;
            container.appendChild(toast);
            lucide.createIcons();
            
            setTimeout(() => {
                toast.style.animation = (position === 'top-right' ? 'toast-out-right' : 'toast-out-bottom') + ' 0.4s ease-in forwards';
                setTimeout(() => toast.remove(), 400);
            }, 2500);
        }

        // --- HỆ THỐNG GỢI Ý MÓN ĂN (RECOMMENDATION) ---
        async function fetchRecommendations() {
            const suggestSection = document.getElementById('recommend-section');
            const recLoading = document.getElementById('recommend-loading');
            const recResults = document.getElementById('recommend-results');

            if (!suggestSection || !recLoading || !recResults) return;

            // Hiển thị khung và khung chớp tắt loading
            suggestSection.classList.remove('hidden');
            let skeletons = '';
            for(let i=0; i<4; i++) {
                skeletons += `
                <div class="food-card p-4 relative premium-glow h-[240px] mt-10">
                    <div class="shimmer mx-auto w-3/4 pt-[75%] rounded-full mb-4 -mt-12 bg-[#fcfbf8]"></div>
                    <div class="shimmer w-3/4 h-5 mx-auto rounded mb-3"></div>
                    <div class="flex justify-center mb-3"><div class="shimmer w-1/2 h-4 rounded"></div></div>
                    <div class="shimmer w-full h-10 rounded mt-4"></div>
                </div>`;
            }
            recLoading.innerHTML = skeletons;

            try {
                // Gọi tới Recommendation API (Truyền thêm thời gian thực của trình duyệt)
                const currentTime = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                const response = await fetch('get_recommendations.php?time=' + currentTime);
                const result = await response.json();
                
                if (result.success && result.recommendations && result.recommendations.length > 0) {
                    let html = '';
                    result.recommendations.forEach(item => {
                        const price = parseFloat(item.price).toLocaleString('vi-VN');
                        // Tạo khối thẻ (card) đẹp mắt cho từng món ăn Suggest
                        html += `
                        <article class="food-card p-4 relative group premium-glow transition-transform duration-500 ease-in-out mt-10">
                            <div class="absolute -top-2 -left-2 z-20 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[9px] uppercase font-bold tracking-wider px-2.5 py-1 rounded-md shadow-lg transform -rotate-12 flex items-center gap-1 border border-orange-300">
                                <i data-lucide="award" class="w-3 h-3 fill-white"></i> Đề Xuất
                            </div>
                            
                            <a href="food_detail.php?id=${item.dish_id}" class="block group-hover:scale-105 transition-transform duration-300">
                                <div class="food-img-wrapper z-10 w-3/4 mx-auto -mt-12 group-hover:-mt-14 transition-all duration-300">
                                    <img src="${item.image}" alt="${item.name}">
                                </div>
                                <div class="text-center mt-4 h-[60px]">
                                    <h3 class="font-serif font-bold text-sm text-[#2d261a] truncate px-1 hover:text-amber-600 transition-colors">${item.name}</h3>
                                    <div class="text-[10px] text-amber-600/90 font-medium italic mt-1 line-clamp-2 px-1 text-center leading-tight">
                                        "${item.reason}"
                                    </div>
                                </div>
                            </a>
                            <div class="mt-4 flex items-center justify-between bg-white/70 rounded-xl p-2 pl-3 backdrop-blur-sm shadow flex-row">
                                <span class="font-sans font-bold text-[#d97706] text-sm">${price}đ</span>
                                <button onclick="addToCart(${item.dish_id}, '${item.name.replace(/'/g,"\\'")}', ${item.price}, '${item.image.replace(/'/g,"\\'")}', this)" class="bg-gradient-to-br from-amber-500 to-amber-600 text-white p-2 rounded-lg shadow hover:from-amber-600 hover:to-orange-700 active:scale-95 transition-all">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
</article>`;
                    });
                    
                    recResults.innerHTML = html;
                    recLoading.classList.add('hidden');
                    recResults.classList.remove('hidden');
                    lucide.createIcons(); // render icon
                } else {
                    suggestSection.classList.add('hidden');
                }
            } catch (error) {
                console.error("Lỗi khi load Recommendations:", error);
                suggestSection.classList.add('hidden');
            }
        }

        // Tự động kích hoạt khi trang web nạp xong cấu trúc DOM
        document.addEventListener('DOMContentLoaded', fetchRecommendations);

        // --- HỆ THỐNG GỢI Ý NGẪU NHIÊN (RANDOM DISH) ---
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
                <div class="w-16 h-16 border-4 border-amber-200 border-t-amber-600 rounded-full animate-spin my-8"></div>
                <p class="text-gray-500 italic text-sm">Đang lật tẩy thực đơn... 🎲</p>
            `;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);

            try {
                await new Promise(r => setTimeout(r, 600)); // Hồi hộp tí

                const response = await fetch('api_random.php');
                const result = await response.json();

                if (result.success && result.data) {
                    const dish = result.data;
                    const price = parseFloat(dish.price).toLocaleString('vi-VN');
                    body.innerHTML = `
                        <div class="relative w-40 h-40 rounded-full border-4 border-amber-300 shadow-xl overflow-hidden mb-5 animate-[bounce_1s_ease-in-out]">
                            <img src="${dish.image}" alt="${dish.title}" class="w-full h-full object-cover rounded-full">
                        </div>
                        <h3 class="font-serif font-bold text-2xl text-center text-gray-900 mb-1">${dish.title}</h3>
                        <div class="flex items-center justify-center gap-1 text-amber-400 mb-3">
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                            <i data-lucide="star" class="w-4 h-4 fill-current"></i>
                        </div>
                        <p class="text-sm text-gray-500 text-center italic mb-5 line-clamp-2 px-4 shadow-sm border-l-2 border-r-2 border-amber-200">
                            "Hôm nay hãy tự thưởng cho mình món này xem sao nhé!"
                        </p>
                        
                        <div class="w-full bg-white rounded-2xl p-4 shadow-inner border border-gray-100 flex items-center justify-between">
                            <span class="text-xl font-bold text-amber-600">${price} ₫</span>
                            <div class="flex gap-2">
                                <a href="food_detail.php?id=${dish.id}" class="bg-amber-100 text-amber-700 hover:bg-amber-200 px-3 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center justify-center">Chi tiết</a>
                                <button onclick="addToCart(${dish.id}, '${dish.title.replace(/'/g,"\\'")}', ${dish.price}, '${dish.image.replace(/'/g,"\\'")}', this)" class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white px-3 py-2 rounded-xl text-sm font-semibold shadow-md active:scale-95 transition-all">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Thêm
                                </button>
                            </div>
                        </div>
                    `;
                    lucide.createIcons();
                } else {
                    body.innerHTML = `<p class="text-red-500 text-center my-6">Không tìm thấy món nào :(</p>`;
                }
            } catch (err) {
                console.error(err);
                body.innerHTML = `<p class="text-red-500 text-center my-6">Lỗi tải dữ liệu!</p>`;
            }
        }
    </script>
    <script src="assets/js/page-agent-ui.js?v=<?php echo rawurlencode((string) (@filemtime(__DIR__ . '/assets/js/page-agent-ui.js') ?: time())); ?>"></script>
    <?php include 'src/includes/food_detail_modal.php'; ?>

    <?php if ($show_banners_on_login && !empty($promoBanners)): ?>
    <div id="promo-popup" style="display: none;" class="fixed inset-0 z-[99999] bg-black/70 backdrop-blur-md items-center justify-center p-4 transition-opacity duration-200">
        
        <div class="relative max-w-lg w-full bg-white dark:bg-slate-800 rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform scale-95 opacity-0 transition-all duration-300 ease-out flex flex-col max-h-[85vh]" id="promo-content">
            
            <!-- Close Button -->
            <button onclick="closePromo()" class="absolute top-4 right-4 bg-black/60 text-white rounded-full p-2.5 hover:bg-black/90 hover:scale-110 transition-all z-30 shadow-xl border border-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>

            <!-- Navigation Buttons -->
            <button id="promo-prev-btn" onclick="prevBanner()" class="absolute left-3 top-[40%] bg-black/40 text-white rounded-full p-2.5 hover:bg-black/70 transition-all z-30 hidden hover:scale-110 border border-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button id="promo-next-btn" onclick="nextBanner()" class="absolute right-3 top-[40%] bg-black/40 text-white rounded-full p-2.5 hover:bg-black/70 transition-all z-30 hidden hover:scale-110 border border-white/20">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>

            <div id="promo-image-container" class="w-full flex-shrink relative flex items-center justify-center bg-slate-900/5 overflow-hidden hidden" style="min-height: 200px;">
                <img id="promo-image" src="" alt="Promo Banner" class="w-full max-h-[60vh] object-contain transition-opacity duration-300">
            </div>

            <div id="promo-text-container" class="p-6 text-center bg-gradient-to-b from-white to-amber-50 dark:from-slate-800 dark:to-slate-900 shrink-0 hidden relative flex flex-col items-center">
                <h3 id="promo-title" class="text-xl md:text-2xl font-black text-amber-600 mb-2 tracking-wide hidden"></h3>
                <p id="promo-desc" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed hidden px-4"></p>
                
                <!-- Pagination Indicators -->
                <div id="promo-indicators" class="flex items-center justify-center gap-1.5 mt-4 mb-2 hidden"></div>

                <button onclick="closePromo(); document.getElementById('thucdon-section').scrollIntoView({ behavior: 'smooth' });" class="mt-4 px-6 py-2.5 bg-amber-500 text-white font-bold rounded-full shadow-lg hover:bg-amber-600 transition active:scale-95 text-sm uppercase tracking-wider">
                    Khám phá ngay
                </button>
            </div>
        </div>
    </div>
    
    <script>
    let bannerQueue = <?php echo json_encode($promoBanners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let currentBannerIndex = 0;
    let bannerInterval;

    document.addEventListener('DOMContentLoaded', function() {
        if (bannerQueue.length > 0) {
            renderBanner(currentBannerIndex);
            
            // Build indicators if multiple banners
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
                
                // Show Prev/Next
                document.getElementById('promo-prev-btn').classList.remove('hidden');
                document.getElementById('promo-next-btn').classList.remove('hidden');

                // Khởi động trượt tự động
                startBannerInterval();
            }

            // Show Popup
            const popup = document.getElementById('promo-popup');
            const content = document.getElementById('promo-content');
            popup.style.display = 'flex';
            // Use requestAnimationFrame to let the browser register display:flex before transitioning
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
            bannerInterval = setInterval(() => {
                nextBanner(false);
            }, 3500); // 3.5s
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
            imgEl.src = banner.image_url.startsWith('http') ? banner.image_url : banner.image_url;
            imgContainer.classList.remove('hidden');
            imgEl.onload = () => imgEl.style.opacity = '1';
            imgEl.onerror = () => imgEl.style.opacity = '1';
            // Fallback for cached images that might not fire onload
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
            if (i === index) {
                indicators[i].className = 'w-4 h-2 rounded-full cursor-pointer transition-all duration-300 bg-amber-500';
            } else {
                indicators[i].className = 'w-2 h-2 rounded-full cursor-pointer transition-all duration-300 bg-slate-300 hover:bg-amber-300';
            }
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

    document.getElementById('promo-popup').addEventListener('click', function(e) {
        if (e.target === this) closePromo();
    });
    </script>
    <?php endif; ?>
    <?php include 'src/includes/order_tracking_widget.php'; ?>
</body>
</html>

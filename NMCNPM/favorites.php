<?php
session_start();
// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$full_name = $_SESSION['full_name'] ?? 'Người dùng';
$role = $_SESSION['role'] ?? 'user';
$user_id = (int)$_SESSION['user_id'];
include 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

$canteenSettings = getCanteenSettings($conn);


// Tính số lượng món ăn trong giỏ hàng
syncSessionCartFromDb($conn, $user_id);
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int)($item['quantity'] ?? 0);
    }
}


// Lấy danh sách món yêu thích
$favorites = [];
$favQuery = "
    SELECT p.*, cp.name AS category_name
    FROM favorites f
    JOIN products p ON p.id = f.product_id
    LEFT JOIN categories_products cp ON cp.id = p.category_id
    WHERE f.user_id = $user_id
    ORDER BY f.created_at DESC
";
$favRes = $conn->query($favQuery);
if ($favRes) {
    while ($row = $favRes->fetch_assoc()) {
        $favorites[] = $row;
    }
}

// Lấy danh sách IDs để đánh dấu tim (tất nhiên ở trang này là tất cả)
$favoriteIds = array_column($favorites, 'id');


?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Món yêu thích - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet'); ?></title>
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
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>

    <style>
        * { font-family: 'Quicksand', sans-serif; }
        body { 
            background: #fdfaf6; 
            color: #423826; 
            min-height: 100vh; 
        }
        .food-card {
            background: linear-gradient(165deg, #fffaf0 0%, #fff1dd 100%);
            border: 1px solid rgba(217, 119, 6, 0.16);
            border-radius: 2rem;
            transition: all 0.3s ease;
        }
        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.05);
        }

        /* Custom Toast Styles */
        .toast-container.top-right {
            top: 20px;
            right: 20px;
            flex-direction: column;
        }
        .toast-container.bottom-center {
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            flex-direction: column-reverse;
            align-items: center;
        }
        .toast {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 14px 22px;
            box-shadow: 0 15px 35px -5px rgba(217, 119, 6, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 250px;
            pointer-events: auto;
            border-left: 4px solid #d97706;
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
            border-bottom: 4px solid #d97706;
        }
        .toast.success { border-color: #d97706; }
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
            background-color: #fcfbf8;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .food-img-wrapper img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;
        }
    </style>
</head>
<body class="flex flex-col">

    <!-- NAVBAR -->
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
                    <a href="order.php" class="navbar-item relative pr-7">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <?php if ($cart_count > 0): ?>
                        <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#d97706] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-amber-200"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Nút Giới Thiệu (Icon tròn góc riêng) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>

                    <?php if (in_array(($role ?? ''), ['admin', 'staff'])): ?>
                    <a href="src/admin/index.php" class="navbar-item" style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:12px;padding:6px 14px;">
                        <i data-lucide="<?php echo ($role ?? '') === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                        <?php echo ($role ?? '') === 'admin' ? 'Admin' : 'Quản lý'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 py-8 relative z-10 w-full flex-grow">
        <!-- Header -->
        <div class="flex items-center justify-between mb-16">
            <div class="flex items-center gap-4">
                <a href="profile.php" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-amber-600 shadow-sm border border-amber-100 hover:bg-amber-50">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-[#2d261a]">Món ăn yêu thích</h1>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-0.5">Nơi lưu giữ những hương vị bạn say mê nhất</p>
                </div>
            </div>
        </div>

        <?php if (empty($favorites)): ?>
            <div class="bg-white rounded-[2rem] p-16 text-center shadow-sm border border-gray-100 max-w-2xl mx-auto">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="heart-off" class="w-10 h-10 text-amber-300"></i>
                </div>
                <h3 class="text-xl font-bold text-[#2d261a] mb-2">Chưa có món yêu thích</h3>
                <p class="text-gray-400 font-medium mb-8">Hãy ghé qua thực đơn và thả tim cho những món bạn thích nhé!</p>
                <a href="dashboard.php" class="inline-flex items-center gap-2 bg-amber-500 text-white px-8 py-3 rounded-xl font-bold hover:bg-amber-600 transition shadow-lg shadow-amber-200 active:scale-95">
                    <i data-lucide="utensils" class="w-5 h-5"></i>
                    Khám phá thực đơn
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12">
                <?php foreach ($favorites as $p): ?>
                    <article class="food-card p-6 relative group" id="fav-card-<?php echo $p['id']; ?>">
                        <div class="relative">
                            <div class="food-img-wrapper z-10 w-3/4 mx-auto -mt-12 group-hover:-mt-14 transition-all duration-300 cursor-pointer" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES); ?>)'>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <h3 class="font-serif font-bold text-base text-[#2d261a] leading-snug mb-1 cursor-pointer hover:text-[#d97706] transition-colors" onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES); ?>)'>
                                <?php echo htmlspecialchars($p['title']); ?>
                            </h3>

                            <div class="flex justify-center gap-0.5 mt-1.5 mb-2.5 text-amber-500 outer-stars-<?php echo $p['id']; ?>">
                                <?php echo renderStars(empty($p['reviews_count']) ? 0 : (float)($p['rating'] ?? 4.5)); ?>
                            </div>

                            <p class="text-[11px] text-[#6e6350] line-clamp-2 px-1 h-8">
                                <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Hương vị đặc biệt, dinh dưỡng và chuẩn vị tươi ngon.'); ?>
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between bg-white/80 rounded-2xl p-2 pl-4 backdrop-blur-md shadow-sm border border-amber-100/50">
                            <span class="font-sans font-bold text-[#d97706] text-base">
                                <?php echo number_format((float) $p['price'], 0, ',', '.'); ?>đ
                            </span>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleFavorite(<?php echo $p['id']; ?>, this)" 
                                        class="fav-btn w-9 h-9 flex items-center justify-center rounded-xl bg-white border border-gray-100 transition-all hover:bg-red-50 hover:text-red-500 active:scale-90 <?php echo in_array($p['id'], $favoriteIds) ? 'text-red-500' : 'text-gray-300'; ?>"
                                        data-product-id="<?php echo $p['id']; ?>">
                                    <i data-lucide="heart" class="w-4 h-4 <?php echo in_array($p['id'], $favoriteIds) ? 'fill-current' : ''; ?>"></i>
                                </button>
                                <button onclick="buyNow(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)" 
                                        class="w-9 h-9 bg-[#fff9db] text-[#854d0e] rounded-xl border border-[#fef08a] hover:bg-[#fef08a] active:scale-90 transition-all flex items-center justify-center shadow-sm"
                                        aria-label="Đặt ngay" title="Đặt ngay">
                                    <i data-lucide="zap" class="w-4 h-4 fill-current"></i>
                                </button>
                                <button onclick="addToCart(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['image']); ?>', this)" 
                                        class="w-9 h-9 bg-[#d97706] text-white rounded-xl shadow-md shadow-amber-200 hover:bg-amber-700 active:scale-90 transition-all flex items-center justify-center" 
                                        aria-label="Thêm vào giỏ">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer removed as per user request -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        async function toggleFavorite(productId, btn) {
            const fd = new FormData();
            fd.append('product_id', productId);
            
            try {
                const res = await fetch('src/api/toggle_favorite.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    if (data.action === 'added') {
                        btn.classList.add('bg-white', 'text-red-500');
                        btn.classList.remove('text-gray-300');
                        const icon = btn.querySelector('[data-lucide="heart"]') || btn.querySelector('svg');
                        if (icon) icon.classList.add('fill-current');
                    } else if (data.action === 'removed') {
                        // Nếu đang ở trang favorites, xóa card luôn cho mượt
                        const card = document.getElementById('fav-card-' + productId);
                        if (card) {
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';
                            setTimeout(() => {
                                card.remove();
                                if (document.querySelectorAll('.food-card').length === 0) {
                                    location.reload(); // Hiện state trống
                                }
                            }, 300);
                        } else {
                            btn.classList.remove('text-red-500');
                            btn.classList.add('bg-white', 'text-gray-300');
                            const icon = btn.querySelector('[data-lucide="heart"]') || btn.querySelector('svg');
                            if (icon) icon.classList.remove('fill-current');
                        }
                    }
                    
                    // Custom toast - always bottom-center for favorite actions
                    showToast(data.message, 'success', 'bottom-center');
                }
            } catch (e) { console.error(e); }
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

        async function buyNow(id, title, price, image, btn) {
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('product_id', id);
            fd.append('title', title);
            fd.append('price', price);
            fd.append('image', image);
            fd.append('quantity', 1);

            btn.disabled = true;
            const originalInner = btn.innerHTML;
            btn.innerHTML = '<span class="w-4 h-4 border-2 border-amber-600 border-t-transparent rounded-full animate-spin"></span>';

            try {
                const res = await fetch('src/api/add_to_order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'order.php';
                } else {
                    showToast(data.message || 'Có lỗi xảy ra!', 'error', 'bottom-center');
                    btn.disabled = false;
                    btn.innerHTML = originalInner;
                    lucide.createIcons();
                }
            } catch (e) {
                console.error(e);
                btn.disabled = false;
                btn.innerHTML = originalInner;
                lucide.createIcons();
            }
        }

        async function addToCart(id, title, price, image, btn) {
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('product_id', id);
            fd.append('title', title);
            fd.append('price', price);
            fd.append('image', image);
            fd.append('quantity', 1);

            btn.disabled = true;
            const originalInner = btn.innerHTML;
            btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>';

            try {
                const res = await fetch('src/api/add_to_order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success', 'bottom-center');
                    
                    // Update badge ID
                    const badge = document.getElementById('cart-count-badge');
                    if (badge) {
                         badge.textContent = data.cart_count;
                    } else {
                         // Fallback reload if badge not found
                         setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    showToast(data.message || 'Lỗi khi thêm vào giỏ hàng', 'error', 'bottom-center');
                }
            } catch (e) { 
                console.error(e);
                showToast('Lỗi kết nối máy chủ', 'error', 'bottom-center');
            }
            finally {
                btn.disabled = false;
                btn.innerHTML = originalInner;
            }
        }
    </script>
    <?php include 'src/includes/food_detail_modal.php'; ?>
    <?php include 'src/includes/floating_reservation_btn.php'; ?>
    <?php include 'src/includes/order_tracking_widget.php'; ?>
</body>
</html>

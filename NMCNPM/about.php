<?php
session_start();
require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? '';
$canteenSettings = getCanteenSettings($conn);
$cName = $canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet';

// Giỏ hàng
if ($user_id > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, $user_id);
} else {
    $cart_count = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Về Chúng Tôi - <?= htmlspecialchars($cName) ?></title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Quicksand', 'sans-serif'],
                        serif: ['Playfair Display', 'serif']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#faf8f5] text-[#2d261a] min-h-screen flex flex-col font-sans">
    
    <!-- NAVBAR (Đồng bộ chuẩn giao diện hệ thống) -->
    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1 whitespace-nowrap flex-shrink-0">
                        <?php 
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
                        <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#ea580c] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-orange-200"><?php echo $cart_count; ?></span>
                    </a>

                    <!-- Nút Giới Thiệu (Icon nhỏ góc riêng, đang active) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-600 text-white shadow-md shadow-orange-600/30 border border-orange-500 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>

                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                    <a href="src/admin/index.php" class="navbar-item" style="background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border-radius:12px;padding:6px 12px;margin-left:2px;">
                        <i data-lucide="<?php echo $_SESSION['role'] === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                        <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Quản lý'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-12 space-y-16">
        
        <!-- Hero Story -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="space-y-6">
                <span class="px-3.5 py-1.5 rounded-full bg-amber-100 text-amber-800 text-xs font-black uppercase tracking-wider">
                    Câu Chuyện Ẩm Thực
                </span>
                <h2 class="text-3xl sm:text-4xl font-black font-serif text-[#2d261a] leading-tight">
                    Nâng Tầm Hương Vị Truyền Thống Trong Không Gian Hiện Đại
                </h2>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Khởi nguồn từ niềm đam mê đem đến những bữa ăn tinh tế, <strong><?= htmlspecialchars($cName) ?></strong> kết hợp hài hòa giữa tinh hoa ẩm thực truyền thống Việt Nam và nghệ thuật trình bày ẩm thực hiện đại.
                </p>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Mỗi món ăn là một bản giao hưởng của nguyên liệu hữu cơ tươi ngon được tuyển chọn hằng ngày, kỹ nghệ chế biến điêu luyện của đầu bếp và sự phục vụ tận tâm từ trái tim.
                </p>

                <div class="grid grid-cols-3 gap-4 pt-4 border-t border-amber-100">
                    <div>
                        <p class="text-2xl font-black text-amber-600 font-serif">100%</p>
                        <p class="text-xs font-bold text-slate-500 mt-1">Nguyên liệu tươi mới</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-amber-600 font-serif">15+</p>
                        <p class="text-xs font-bold text-slate-500 mt-1">Năm kinh nghiệm bếp trưởng</p>
                    </div>
                    <div>
                        <p class="text-2xl font-black text-amber-600 font-serif">4.9 ★</p>
                        <p class="text-xs font-bold text-slate-500 mt-1">Thực khách hài lòng</p>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="rounded-3xl overflow-hidden shadow-2xl border-4 border-white aspect-[4/3] bg-slate-100">
                    <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?q=80&w=1200" alt="Không gian nhà hàng" class="w-full h-full object-cover">
                </div>
                <div class="absolute -bottom-6 -left-6 bg-white p-5 rounded-2xl shadow-xl border border-amber-100 max-w-xs hidden sm:block">
                    <p class="text-xs font-black text-amber-600 uppercase tracking-widest mb-1">Không gian ấm cúng</p>
                    <p class="text-xs text-slate-600 font-medium">Sức chứa lên tới 200 thực khách, hỗ trợ phòng VIP và tiệc sinh nhật.</p>
                </div>
            </div>
        </section>

        <!-- 3 Core Pillars -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-3xl border border-amber-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-6">
                    <i data-lucide="chef-hat" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold font-serif mb-2">Đội Ngũ Bếp Trưởng</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Được dẫn dắt bởi những đầu bếp giàu kinh nghiệm, mang đến hương vị chuẩn mực và sự đổi mới sáng tạo không ngừng trong từng thực đơn.
                </p>
            </div>

            <div class="bg-white p-8 rounded-3xl border border-amber-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-6">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold font-serif mb-2">An Toàn & Tươi Sạch</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    100% nguồn rau củ, thịt cá đều có nguồn gốc kiểm định rõ ràng, đạt chuẩn an toàn vệ sinh thực phẩm quốc gia.
                </p>
            </div>

            <div class="bg-white p-8 rounded-3xl border border-amber-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-6">
                    <i data-lucide="sparkles" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold font-serif mb-2">Dịch Vụ Tận Tâm</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Từ việc gọi món bằng mã QR tại bàn cho đến phục vụ trực tiếp, chúng tôi luôn lắng nghe và chăm sóc từng yêu cầu nhỏ nhất của bạn.
                </p>
            </div>
        </section>

        <!-- Information & Contact Bar -->
        <section class="bg-gradient-to-r from-slate-900 to-[#1e293b] text-white rounded-3xl p-8 sm:p-12 shadow-xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h4 class="text-amber-400 text-xs font-black uppercase tracking-widest mb-2">Địa Chỉ Nhà Hàng</h4>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($canteenSettings['canteen_address'] ?? 'Số 88 Phố Ẩm Thực, Quận 1, TP. Hồ Chí Minh') ?></p>
                </div>
                <div>
                    <h4 class="text-amber-400 text-xs font-black uppercase tracking-widest mb-2">Thời Gian Hoạt Động</h4>
                    <p class="text-sm font-semibold">Ca Trưa: 10:30 - 14:00</p>
                    <p class="text-sm font-semibold">Ca Tối: 17:00 - 22:00</p>
                </div>
                <div>
                    <h4 class="text-amber-400 text-xs font-black uppercase tracking-widest mb-2">Hotline Đặt Bàn & Tiệc</h4>
                    <p class="text-lg font-black text-amber-400 font-mono"><?= htmlspecialchars($canteenSettings['canteen_hotline'] ?? '0909 123 456') ?></p>
                    <a href="reservation.php" class="inline-block mt-3 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-all">
                        Đặt bàn trực tuyến →
                    </a>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-amber-100 py-8 text-center text-xs text-slate-400">
        <p>© 2026 <?= htmlspecialchars($cName) ?>. Nền tảng ẩm thực và đặt bàn nhà hàng chuyên nghiệp.</p>
    </footer>

    <script>lucide.createIcons();</script>
    <?php include 'src/includes/floating_reservation_btn.php'; ?>
</body>
</html>

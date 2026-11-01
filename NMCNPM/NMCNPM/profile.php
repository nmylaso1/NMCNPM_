<?php
session_start();
require_once __DIR__ . '/src/includes/config.php';
require_once __DIR__ . '/src/includes/cart_order_helpers.php';

// Check access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$canteenSettings = getCanteenSettings($conn);


// Fetch fresh user data from DB
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email, role, created_at FROM accounts WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$cart_count = 0;
if ($userId > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, (int)$userId);
}

// =========================================================================
// LẤY 5 LẦN ĐĂNG NHẬP GẦN NHẤT CỦA USER NÀY ĐỂ HIỂN THỊ
// =========================================================================
$login_history = [];
$histRes = $conn->query("SELECT * FROM login_history WHERE user_id = $userId ORDER BY login_time DESC LIMIT 5");
if ($histRes) {
    while ($row = $histRes->fetch_assoc()) {
        $login_history[] = $row;
    }
}
// =========================================================================


?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Thực Khách - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet'); ?></title>
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #f3f4f6; color: #423826; }
        h1, h2, h3, .font-serif { font-family: 'Quicksand', sans-serif; font-weight: 700; }
        .blob-bg { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        
        /* Hiệu ứng chuyển Tab */
        .tab-content { display: none; opacity: 0; transition: opacity 0.3s ease; }
        .tab-content.active { display: block; opacity: 1; }
    </style>
</head>
<body class="min-h-screen relative overflow-x-hidden flex flex-col">
    <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-[#e4ead3]/80 blob-bg -z-10 -translate-y-1/4 translate-x-1/4"></div>

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
                    <a href="profile.php" class="navbar-item navbar-item-active">
                        <i data-lucide="user-circle" class="w-4 h-4"></i>
                        Hồ sơ
                    </a>
                    <a href="order.php" class="navbar-item relative pr-7">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#d97706] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-amber-200"><?php echo $cart_count; ?></span>
                    </a>

                    <!-- Nút Giới Thiệu (Icon tròn góc riêng) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>

                    <?php if (in_array(($user['role'] ?? ''), ['admin', 'staff'])): ?>
                    <a href="src/admin/index.php" class="navbar-item" style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:12px;padding:6px 14px;">
                        <i data-lucide="<?php echo ($user['role'] ?? '') === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                        <?php echo ($user['role'] ?? '') === 'admin' ? 'Admin' : 'Quản lý'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-8 relative z-10 text-center md:text-left flex-grow w-full">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 border-b border-stone-200/70 pb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-100/80 rounded-full border border-amber-200 text-amber-800 text-xs font-bold uppercase tracking-wider mb-2">
                    <i data-lucide="crown" class="w-3.5 h-3.5 text-amber-600"></i> Thành Viên Gourmet
                </div>
                <h1 class="text-3xl md:text-4xl font-serif font-bold text-[#2d261a]">
                    Hồ Sơ <span class="text-amber-600 italic">Thực Khách</span>
                </h1>
                <p class="text-xs md:text-sm text-stone-500 mt-1 font-medium">Quản lý thông tin tài khoản & đặc quyền ẩm thực của bạn</p>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="my_orders.php" class="px-4 py-2 rounded-xl bg-white hover:bg-stone-50 text-stone-700 text-xs font-bold border border-stone-200 shadow-sm flex items-center gap-1.5 transition active:scale-95">
                    <i data-lucide="history" class="w-3.5 h-3.5 text-amber-600"></i> Lịch sử đơn
                </a>
                <a href="reservation.php" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-md shadow-amber-600/20 flex items-center gap-1.5 transition active:scale-95">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Đặt bàn trước
                </a>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            <div class="bg-white/80 backdrop-blur-md rounded-[2rem] shadow-xl shadow-amber-900/5 p-8 w-full lg:w-2/3 border border-white">
                <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shadow-inner">
                            <i data-lucide="user" class="w-8 h-8"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-[#2d261a] font-serif">
                                Chào, <?php echo htmlspecialchars($user['full_name'] ?: $user['email']); ?>!
                            </h2>
                            <p class="text-sm text-gray-500">Quản lý thông tin tài khoản của bạn</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-6 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                    <button onclick="switchTab('info')" id="tab-btn-info" class="flex-1 py-3 px-4 rounded-xl text-sm font-bold bg-[#d97706] text-white transition-all">
                        Thông tin
                    </button>
                    <button onclick="switchTab('history')" id="tab-btn-history" class="flex-1 py-3 px-4 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-50 transition-all">
                        Lịch sử hoạt động
                    </button>
                </div>

                <div id="tab-info" class="tab-content active space-y-6">
                    <div class="group">
                        <p class="text-[10px] font-bold text-[#d97706] uppercase tracking-widest mb-1.5 opacity-80 group-hover:opacity-100 transition-opacity">Họ và tên</p>
                        <p class="text-lg text-[#423826] font-medium bg-gray-50/50 p-3 rounded-xl border border-gray-100"><?php echo htmlspecialchars($user['full_name'] ?: 'Chưa cập nhật'); ?></p>
                    </div>

                    <div class="group">
                        <p class="text-[10px] font-bold text-[#d97706] uppercase tracking-widest mb-1.5 opacity-80 group-hover:opacity-100 transition-opacity">Email đăng nhập</p>
                        <p class="text-lg text-[#423826] font-medium bg-gray-50/50 p-3 rounded-xl border border-gray-100"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="group">
                            <p class="text-[10px] font-bold text-[#d97706] uppercase tracking-widest mb-1.5 opacity-80 group-hover:opacity-100 transition-opacity">Loại tài khoản</p>
                            <div class="bg-gray-50/50 p-3 rounded-xl border border-gray-100 flex items-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?php echo ($user['role'] ?? '') === 'admin' ? 'bg-[#d97706]' : (($user['role'] ?? '') === 'staff' ? 'bg-sky-600' : 'bg-slate-600'); ?> text-white shadow-sm capitalize">
                                    <i data-lucide="<?php echo ($user['role'] ?? '') === 'admin' ? 'shield' : (($user['role'] ?? '') === 'staff' ? 'briefcase' : 'user'); ?>" class="w-3 h-3"></i> 
                                    <?php 
                                        $displayRole = match($user['role'] ?? '') {
                                            'admin' => 'Admin (Quản trị)',
                                            'staff' => 'Nhân viên',
                                            'user'  => 'Thực khách thân thiết',
                                            default => $user['role'] ?? 'Người dùng'
                                        };
                                        echo htmlspecialchars($displayRole); 
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="group">
                            <p class="text-[10px] font-bold text-[#d97706] uppercase tracking-widest mb-1.5 opacity-80 group-hover:opacity-100 transition-opacity">Tham gia từ</p>
                            <p class="text-lg text-[#423826] font-medium bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                                <?php 
                                if (!empty($user['created_at'])) {
                                    $date = new DateTime($user['created_at']);
                                    echo $date->format('d/m/Y'); 
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 mt-8 pt-4 border-t border-gray-100">
                        <button onclick="openPasswordModal()" class="flex-1 py-3.5 bg-white text-[#d97706] font-bold rounded-full border border-amber-200 hover:bg-amber-50 hover:border-amber-400 transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 text-sm uppercase tracking-wider">
                            <i data-lucide="key" class="w-4 h-4"></i> Đổi mật khẩu
                        </button>
                        <a href="logout.php" class="flex-1 py-3.5 bg-red-50 text-red-600 font-bold rounded-full border border-red-100 hover:bg-red-500 hover:text-white transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 text-sm uppercase tracking-wider">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Đăng xuất
                        </a>
                    </div>
                </div>

                <div id="tab-history" class="tab-content space-y-4">
                    <p class="text-xs font-bold text-gray-500 mb-4 px-2"> Hiển thị tối đa 5 lần hoạt động gần nhất của bạn:</p>

                    <?php if (empty($login_history)): ?>
                        <div class="text-center py-8 text-gray-500 italic bg-gray-50 rounded-2xl border border-gray-100">
                            Chưa có dữ liệu hoạt động nào được ghi nhận.
                        </div>
                    <?php else: ?>
                        <?php foreach ($login_history as $index => $hist): ?>
                            <?php 
                                // Kiểm tra xem có đang là phiên online không
                                $logout = $hist['logout_time'];
                                $isOnline = (is_null($logout) || $logout === '' || strpos($logout, '0000-00-00') !== false || strpos($logout, '1970-01-01') !== false);
                            ?>
                            <div class="flex items-start gap-4 p-4 rounded-2xl <?php echo $index === 0 ? 'bg-amber-50/50 border border-amber-200 shadow-sm' : 'bg-gray-50 border border-gray-100'; ?>">
                                <div class="hidden sm:flex w-10 h-10 rounded-full <?php echo $isOnline ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-200 text-gray-500'; ?> items-center justify-center shrink-0">
                                    <i data-lucide="<?php echo $isOnline ? 'zap' : 'log-in'; ?>" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                        <h4 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                            Đăng nhập thành công
                                            <?php if($isOnline): ?>
                                                <span class="px-2 py-0.5 text-[10px] bg-emerald-100 text-emerald-700 rounded-full uppercase tracking-wider font-black flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Phiên hiện tại
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <span class="text-[11px] font-bold text-gray-500 bg-white px-2 py-1 rounded-md border border-gray-200 shadow-sm">
                                            <?php echo date('H:i - d/m/Y', strtotime($hist['login_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-5 text-[12px] text-gray-500 mt-2">
                                        <span class="flex items-center gap-1.5"><i data-lucide="globe" class="w-3.5 h-3.5 opacity-70"></i> <?php echo htmlspecialchars($hist['ip_address'] ?? 'Không rõ'); ?></span>
                                        <span class="flex items-center gap-1.5 text-[#d97706] font-semibold">
                                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                            <?php 
                                                if ($isOnline) {
                                                    echo "Đang online...";
                                                } else {
                                                    $mins = (int)$hist['duration_minutes'];
                                                    if ($mins < 60) echo "Thoát sau: {$mins} phút";
                                                    else echo "Thoát sau: " . floor($mins/60) . " giờ " . ($mins%60) . " phút";
                                                }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <div class="w-full lg:w-1/3 space-y-4">
                <a href="my_orders.php" class="bg-white/60 backdrop-blur-sm p-6 rounded-[2rem] border border-white/60 text-center hover:bg-white transition shadow-sm hover:shadow-md cursor-pointer group block">
                    <div class="w-14 h-14 mx-auto rounded-full bg-orange-100 flex items-center justify-center text-orange-600 mb-3 group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-bold tracking-wide text-gray-700">Đơn hàng của tôi</p>
                </a>
                
                <a href="payment_history.php" class="bg-white/60 backdrop-blur-sm p-6 rounded-[2rem] border border-white/60 text-center hover:bg-white transition shadow-sm hover:shadow-md cursor-pointer group block">
                    <div class="w-14 h-14 mx-auto rounded-full bg-amber-100 flex items-center justify-center text-amber-600 mb-3 group-hover:scale-110 transition-transform">
                        <i data-lucide="receipt-text" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-bold tracking-wide text-gray-700">Lịch sử thanh toán</p>
                </a>
                
                <a href="favorites.php" class="bg-white/60 backdrop-blur-sm p-6 rounded-[2rem] border border-white/60 text-center hover:bg-white transition shadow-sm hover:shadow-md cursor-pointer group block">
                    <div class="w-14 h-14 mx-auto rounded-full bg-green-100 flex items-center justify-center text-green-600 mb-3 group-hover:scale-110 transition-transform">
                        <i data-lucide="heart" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-bold tracking-wide text-gray-700">Món ăn yêu thích</p>
                </a>
                
            </div>
        </div>
    </div>

    <div id="passwordModal" class="fixed inset-0 z-[100] hidden opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closePasswordModal()"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm transform scale-95 transition-transform duration-300" id="passwordModalContent">
            <div class="bg-white rounded-3xl p-8 shadow-2xl mx-4 relative overflow-hidden">
                <button type="button" onclick="closePasswordModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
                <div class="text-center mb-6">
                    <div class="w-14 h-14 bg-amber-100 text-[#d97706] rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="shield-check" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-bold text-[#423826] font-serif">Đổi mật khẩu</h3>
                </div>
                
                <form id="changePasswordForm" class="space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-[#d97706] uppercase tracking-wider ml-1">Mật khẩu cũ</label>
                        <div class="relative">
                            <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="old_password" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none transition text-sm text-[#423826] font-medium placeholder:font-normal placeholder:text-gray-400" placeholder="••••••••">
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-[#d97706] uppercase tracking-wider ml-1">Mật khẩu mới</label>
                        <div class="relative">
                            <i data-lucide="key" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="new_password" required minlength="6" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none transition text-sm text-[#423826] font-medium placeholder:font-normal placeholder:text-gray-400" placeholder="••••••••">
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-[#d97706] uppercase tracking-wider ml-1">Xác nhận mật khẩu</label>
                        <div class="relative">
                            <i data-lucide="check-circle" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="password" name="confirm_password" required minlength="6" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none transition text-sm text-[#423826] font-medium placeholder:font-normal placeholder:text-gray-400" placeholder="••••••••">
                        </div>
                    </div>
                    
                    <div id="pwdMsg" class="hidden text-[11px] font-semibold text-center mt-2 px-2 py-1.5 rounded-lg border"></div>
                    
                    <button type="submit" id="pwdSubmit" class="w-full mt-2 py-3.5 bg-gradient-to-r from-amber-500 to-[#d97706] hover:from-amber-600 hover:to-amber-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-amber-500/30 active:scale-95 flex items-center justify-center gap-2">
                        <span id="pwdBtnText">Lưu lại ngay</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Script chuyển đổi giữa 2 Tab
        function switchTab(tabId) {
            // 1. Ẩn tất cả Tab
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // 2. Hiện Tab được chọn
            document.getElementById('tab-' + tabId).classList.add('active');

            // 3. Đổi màu nút bấm
            const btns = ['tab-btn-info', 'tab-btn-history'];
            btns.forEach(id => {
                const btn = document.getElementById(id);
                btn.classList.remove('bg-[#d97706]', 'text-white');
                btn.classList.add('text-gray-500', 'bg-transparent');
            });

            const activeBtn = document.getElementById('tab-btn-' + tabId);
            activeBtn.classList.remove('text-gray-500', 'bg-transparent');
            activeBtn.classList.add('bg-[#d97706]', 'text-white');
        }

        // Modal Logic
        const modal = document.getElementById('passwordModal');
        const modalContent = document.getElementById('passwordModalContent');
        const pwdForm = document.getElementById('changePasswordForm');
        const pwdMsg = document.getElementById('pwdMsg');
        const pwdSubmit = document.getElementById('pwdSubmit');
        const pwdBtnText = document.getElementById('pwdBtnText');

        function openPasswordModal() {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closePasswordModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                pwdForm.reset();
                pwdMsg.classList.add('hidden');
            }, 300);
        }

        // Handle AJAX Submit
        pwdForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            pwdSubmit.disabled = true;
            pwdBtnText.textContent = 'Đang kiểm tra...';
            pwdMsg.classList.add('hidden');
            pwdMsg.className = 'text-[11px] font-semibold text-center mt-2 px-2 py-1.5 rounded-lg border'; 
            
            const formData = new FormData(pwdForm);

            try {
                const response = await fetch('src/api/change_password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                pwdMsg.classList.remove('hidden');
                if (data.success) {
                    pwdMsg.classList.add('text-green-700', 'bg-green-50', 'border-green-200');
                    pwdMsg.innerHTML = `<i data-lucide="check-circle-2" class="w-3 h-3 inline mr-1 -mt-0.5"></i> ${data.message}`;
                    lucide.createIcons();
                    
                    setTimeout(() => {
                        closePasswordModal();
                    }, 1500);
                } else {
                    pwdMsg.classList.add('text-red-700', 'bg-red-50', 'border-red-200');
                    pwdMsg.innerHTML = `<i data-lucide="alert-triangle" class="w-3 h-3 inline mr-1 -mt-0.5"></i> ${data.message}`;
                    lucide.createIcons();
                }
            } catch (err) {
                pwdMsg.classList.remove('hidden');
                pwdMsg.classList.add('text-red-700', 'bg-red-50', 'border-red-200');
                pwdMsg.innerText = 'Lỗi kết nối máy chủ!';
            } finally {
                pwdSubmit.disabled = false;
                pwdBtnText.textContent = 'Lưu lại ngay';
            }
        });
    </script>
    <?php include __DIR__ . '/src/includes/floating_reservation_btn.php'; ?>
    <?php include __DIR__ . '/src/includes/order_tracking_widget.php'; ?>
</body>
</html>
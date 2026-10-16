<?php
session_start();
require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$canteenSettings = getCanteenSettings($conn);
$cName = $canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet';

// Giỏ hàng
if ($user_id > 0 && isset($conn)) {
    $cart_count = getCartCountFromDb($conn, $user_id);
} else {
    $cart_count = 0;
}

// Lấy danh sách đặt bàn của user này (nếu đã đăng nhập)
$my_reservations = [];
if ($user_id > 0 && isset($conn)) {
    $res = $conn->query("SELECT * FROM reservations WHERE user_id = $user_id ORDER BY reservation_date DESC, reservation_time DESC LIMIT 10");
    if ($res) {
        while ($r = $res->fetch_assoc()) $my_reservations[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Bàn Trước - <?= htmlspecialchars($cName) ?></title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

                    <!-- Nút Giới Thiệu (Icon nhỏ góc riêng) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
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

    <!-- Main Content -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-10">
        
        <!-- Hero Header -->
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="px-4 py-1.5 rounded-full bg-amber-100 text-amber-800 text-xs font-black uppercase tracking-widest inline-block mb-3">
                ✦ Trải nghiệm ẩm thực đẳng cấp ✦
            </span>
            <h2 class="text-3xl sm:text-4xl font-black font-serif text-[#2d261a] mb-4">
                Đặt Bàn Trước Cùng Người Thân
            </h2>
            <p class="text-sm sm:text-base text-slate-500 font-medium">
                Hãy để chúng tôi chuẩn bị cho bạn một không gian hoàn hảo, từng chi tiết từ bàn tiệc đến hương vị món ăn đều được chăm chút tỉ mỉ.
            </p>
        </div>

        <!-- Reservation Card Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Form (7 cols) -->
            <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-10 border border-amber-100/80 shadow-xl shadow-amber-900/5">
                <form id="reservationForm" onsubmit="handleReservationSubmit(event)" class="space-y-6">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- Date -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <i data-lucide="calendar" class="w-4 h-4 text-amber-600"></i> Ngày dùng bữa
                            </label>
                            <input type="date" name="reservation_date" id="res_date" required 
                                   min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"
                                   class="w-full px-4 py-3 rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm font-bold text-slate-800 outline-none transition-all">
                        </div>

                        <!-- Time -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i> Khung giờ
                            </label>
                            <select name="reservation_time" id="res_time" required
                                    class="w-full px-4 py-3 rounded-2xl border border-amber-200 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm font-bold text-slate-800 outline-none transition-all bg-white">
                                <optgroup label="Ca Trưa (11:00 - 14:00)">
                                    <option value="11:00">11:00</option>
                                    <option value="11:30">11:30</option>
                                    <option value="12:00" selected>12:00</option>
                                    <option value="12:30">12:30</option>
                                    <option value="13:00">13:00</option>
                                </optgroup>
                                <optgroup label="Ca Tối (17:30 - 21:30)">
                                    <option value="17:30">17:30</option>
                                    <option value="18:00">18:00</option>
                                    <option value="18:30">18:30</option>
                                    <option value="19:00">19:00</option>
                                    <option value="19:30">19:30</option>
                                    <option value="20:00">20:00</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- Guest Count -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i data-lucide="users" class="w-4 h-4 text-amber-600"></i> Số lượng khách
                        </label>
                        <div class="grid grid-cols-5 gap-2">
                            <?php foreach ([1, 2, 4, 6, 8] as $num): ?>
                                <button type="button" onclick="setGuestCount(<?= $num ?>)" 
                                        class="guest-btn py-2.5 rounded-xl border border-amber-200 text-xs font-bold transition-all text-slate-700 hover:border-amber-500 <?= $num === 2 ? 'bg-amber-600 text-white border-amber-600 shadow-md shadow-amber-600/20' : 'bg-slate-50' ?>" 
                                        data-val="<?= $num ?>">
                                    <?= $num ?> người
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-xs text-slate-500 font-medium">Hoặc nhập số lượng lớn:</span>
                            <input type="number" name="guest_count" id="guest_count_input" value="2" min="1" max="100" 
                                   class="w-24 px-3 py-1.5 rounded-xl border border-amber-200 text-xs font-bold text-center outline-none focus:border-amber-500">
                        </div>
                    </div>

                    <!-- Seating Area -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-4 h-4 text-amber-600"></i> Khu vực dùng bữa mong muốn
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative flex flex-col p-4 border-2 border-amber-500 bg-amber-50/40 rounded-2xl cursor-pointer hover:bg-amber-50 transition-all area-option">
                                <input type="radio" name="seating_area" value="standard" checked class="hidden">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">🏛️</span>
                                    <span class="text-xs font-black text-slate-800">Sảnh Tiệc Chính</span>
                                </div>
                                <span class="text-[11px] text-slate-500">Ấm cúng, thoáng mát và âm nhạc nhẹ</span>
                            </label>

                            <label class="relative flex flex-col p-4 border-2 border-slate-200 bg-white rounded-2xl cursor-pointer hover:border-amber-300 transition-all area-option">
                                <input type="radio" name="seating_area" value="vip" class="hidden">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">👑</span>
                                    <span class="text-xs font-black text-slate-800">Phòng VIP Riêng</span>
                                </div>
                                <span class="text-[11px] text-slate-500">Kín đáo, tiếp khách & gia đình</span>
                            </label>

                            <label class="relative flex flex-col p-4 border-2 border-slate-200 bg-white rounded-2xl cursor-pointer hover:border-amber-300 transition-all area-option">
                                <input type="radio" name="seating_area" value="window" class="hidden">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">🪟</span>
                                    <span class="text-xs font-black text-slate-800">Bàn Cửa Sổ</span>
                                </div>
                                <span class="text-[11px] text-slate-500">View đẹp lãng mạn cho cặp đôi</span>
                            </label>

                            <label class="relative flex flex-col p-4 border-2 border-slate-200 bg-white rounded-2xl cursor-pointer hover:border-amber-300 transition-all area-option">
                                <input type="radio" name="seating_area" value="outdoor" class="hidden">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">🌿</span>
                                    <span class="text-xs font-black text-slate-800">Sân Vườn Ngoài Trời</span>
                                </div>
                                <span class="text-[11px] text-slate-500">Gần gũi thiên nhiên, không khí mát</span>
                            </label>
                        </div>
                    </div>

                    <!-- Customer Contact Info -->
                    <div class="border-t border-amber-100 pt-6 space-y-4">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-400">Thông tin liên hệ của bạn</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Họ và tên *</label>
                                <input type="text" name="customer_name" required value="<?= htmlspecialchars($full_name) ?>" placeholder="Nguyễn Văn A" 
                                       class="w-full px-4 py-3 rounded-2xl border border-amber-200 text-sm font-semibold outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Số điện thoại *</label>
                                <input type="tel" name="phone" required placeholder="0909 123 456" 
                                       class="w-full px-4 py-3 rounded-2xl border border-amber-200 text-sm font-semibold outline-none focus:border-amber-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Email (nhận xác nhận)</label>
                            <input type="email" name="email" placeholder="email@example.com" 
                                   class="w-full px-4 py-3 rounded-2xl border border-amber-200 text-sm font-semibold outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Yêu cầu đặc biệt (tùy chọn)</label>
                            <textarea name="note" rows="2" placeholder="Ví dụ: Trang trí sinh nhật, dị ứng đồ biển, cần ghế em bé..." 
                                      class="w-full px-4 py-3 rounded-2xl border border-amber-200 text-sm font-medium outline-none focus:border-amber-500 resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" 
                            class="w-full py-4 rounded-2xl bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white font-extrabold text-sm shadow-xl shadow-amber-600/25 transition-all transform hover:-translate-y-0.5 active:scale-[0.99] flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span>Xác nhận Đặt Bàn Ngay</span>
                    </button>
                </form>
            </div>

            <!-- Right Info & My Reservations (5 cols) -->
            <div class="lg:col-span-5 space-y-6">
                <!-- Restaurant Policy Card -->
                <div class="bg-gradient-to-br from-slate-900 to-[#1e293b] text-white rounded-3xl p-8 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 opacity-10 text-amber-400">
                        <i data-lucide="utensils" class="w-48 h-48"></i>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest text-amber-400 mb-2 block">Dịch vụ chu đáo</span>
                    <h3 class="text-xl font-bold font-serif mb-4">Cam Kết Phục Vụ</h3>
                    <ul class="space-y-3.5 text-xs text-slate-300 font-medium">
                        <li class="flex items-start gap-2.5">
                            <i data-lucide="clock" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                            <span>Giữ bàn miễn phí tối đa <strong>15 phút</strong> so với giờ hẹn.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <i data-lucide="heart" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                            <span>Miễn phí trang trí tiệc sinh nhật cơ bản khi đặt từ <strong>6 người</strong>.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <i data-lucide="phone-call" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                            <span>Hotline hỗ trợ tiệc riêng / đặt cọc: <strong><?= htmlspecialchars($canteenSettings['canteen_hotline'] ?? '0909 123 456') ?></strong></span>
                        </li>
                    </ul>
                </div>

                <!-- My Reservations (If logged in) -->
                <?php if ($user_id > 0): ?>
                    <div class="bg-white rounded-3xl p-6 border border-amber-100 shadow-sm">
                        <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-amber-600"></i>
                            Lịch sử đặt bàn của bạn
                        </h4>
                        <?php if (count($my_reservations) === 0): ?>
                            <p class="text-xs text-slate-400 text-center py-6">Bạn chưa có lịch đặt bàn nào.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($my_reservations as $m): 
                                    $stBadge = [
                                        'pending' => ['bg' => 'bg-amber-100 text-amber-800', 'text' => 'Chờ duyệt'],
                                        'confirmed' => ['bg' => 'bg-blue-100 text-blue-800', 'text' => 'Đã xác nhận'],
                                        'completed' => ['bg' => 'bg-emerald-100 text-emerald-800', 'text' => 'Hoàn tất'],
                                        'cancelled' => ['bg' => 'bg-rose-100 text-rose-800', 'text' => 'Đã hủy']
                                    ][$m['status']] ?? ['bg' => 'bg-gray-100 text-gray-800', 'text' => $m['status']];
                                ?>
                                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs">
                                        <div>
                                            <p class="font-extrabold text-slate-800">
                                                <?= date('H:i', strtotime($m['reservation_time'])) ?> - <?= date('d/m/Y', strtotime($m['reservation_date'])) ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500 font-medium">Mã: <?= htmlspecialchars($m['reservation_code']) ?> (<?= (int)$m['guest_count'] ?> người)</p>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-xl font-bold <?= $stBadge['bg'] ?>">
                                            <?= $stBadge['text'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-amber-100 py-8 mt-16 text-center text-xs text-slate-400">
        <p>© 2026 <?= htmlspecialchars($cName) ?>. Nền tảng ẩm thực và đặt bàn nhà hàng chuyên nghiệp.</p>
    </footer>

    <script>
        lucide.createIcons();

        function setGuestCount(val) {
            document.getElementById('guest_count_input').value = val;
            document.querySelectorAll('.guest-btn').forEach(b => {
                if (parseInt(b.getAttribute('data-val')) === val) {
                    b.className = 'guest-btn py-2.5 rounded-xl border border-amber-600 bg-amber-600 text-white text-xs font-bold transition-all shadow-md shadow-amber-600/20';
                } else {
                    b.className = 'guest-btn py-2.5 rounded-xl border border-amber-200 text-xs font-bold transition-all text-slate-700 bg-slate-50 hover:border-amber-500';
                }
            });
        }

        // Handle Area Card selection styling
        document.querySelectorAll('.area-option').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.area-option').forEach(c => {
                    c.classList.remove('border-amber-500', 'bg-amber-50/40');
                    c.classList.add('border-slate-200', 'bg-white');
                });
                card.classList.add('border-amber-500', 'bg-amber-50/40');
                card.classList.remove('border-slate-200', 'bg-white');
            });
        });

        async function handleReservationSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin">🌀</span> Đang gửi yêu cầu...';

            const form = document.getElementById('reservationForm');
            const fd = new FormData(form);

            try {
                const res = await fetch('src/api/create_reservation.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đặt bàn thành công!',
                        html: `<p class="text-sm text-slate-600 mb-2">Mã đặt bàn của bạn là: <strong class="text-amber-600 font-mono text-base">${data.reservation_code}</strong></p><p class="text-xs text-slate-500">${data.message}</p>`,
                        confirmButtonText: 'Đã hiểu',
                        confirmButtonColor: '#d97706',
                        customClass: { popup: 'rounded-3xl' }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Thông báo',
                        text: data.message || 'Không thể đặt bàn!',
                        confirmButtonColor: '#d97706',
                        customClass: { popup: 'rounded-3xl' }
                    });
                }
            } catch (err) {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Có lỗi kết nối. Vui lòng thử lại!' });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    </script>
</body>
</html>

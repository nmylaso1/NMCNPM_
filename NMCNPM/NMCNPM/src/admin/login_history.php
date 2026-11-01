<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php'); exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';

// Tự động khởi tạo bảng nếu chưa có
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

// NÚT XÓA LỊCH SỬ NẾU ADMIN CẦN DỌN DẸP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_history'])) {
    $conn->query("TRUNCATE TABLE system_action_logs");
    header("Location: login_history.php?msg=cleared");
    exit();
}

// BƠM DỮ LIỆU MẪU BAN ĐẦU NẾU BẢNG ĐANG TRỐNG ĐỂ ADMIN XEM ĐƯỢC NGAY
$countCheck = $conn->query("SELECT COUNT(*) FROM system_action_logs")->fetch_row()[0] ?? 0;
if ($countCheck == 0) {
    $adminId = (int)$_SESSION['user_id'];
    // Tìm thử 1 id nhân viên hoặc tạo log mẫu
    $staffUser = $conn->query("SELECT id FROM accounts WHERE role='staff' LIMIT 1")->fetch_assoc();
    $staffId = $staffUser ? (int)$staffUser['id'] : $adminId;

    $sampleLogs = [
        [$staffId, 'food_toggle', 'Cơm Gà Hải Nam', 'Đã đổi trạng thái sang: Ẩn khỏi menu món \'Cơm Gà Hải Nam\'', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-15 minutes'))],
        [$staffId, 'order_cooking', 'DH-8492', 'Chuyển đơn DH-8492 sang Đang nấu', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-35 minutes'))],
        [$staffId, 'order_paid', 'DH-8490', 'Hoàn tất & xác nhận thanh toán đơn DH-8490 (75.000đ)', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-1 hour'))],
        [$adminId, 'menu_add', 'Bún Bò Huế', 'Thêm \'Bún Bò Huế\' vào thực đơn bữa Trưa (' . date('Y-m-d') . ')', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-2 hours'))],
        [$adminId, 'food_edit', 'Trà Đào Cam Sả', 'Cập nhật món ăn: Trà Đào Cam Sả (Giá: 25.000đ)', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-3 hours'))],
        [$adminId, 'shipping_edit', 'Giao Hàng Tiêu Chuẩn', 'Cập nhật chính sách phí ship: \'Giao Hàng Tiêu Chuẩn\' (Phí: 12.000đ)', '127.0.0.1', date('Y-m-d H:i:s', strtotime('-5 hours'))]
    ];
    foreach ($sampleLogs as $sl) {
        $stmtInit = $conn->prepare("INSERT INTO system_action_logs (user_id, action_type, target_title, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmtInit) {
            $stmtInit->bind_param('isssss', $sl[0], $sl[1], $sl[2], $sl[3], $sl[4], $sl[5]);
            $stmtInit->execute();
        }
    }
}

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'login_history';
$page_title   = 'Lịch sử thao tác Hệ thống';

// BỘ LỌC VÀ TÌM KIẾM
$searchKeyword = trim($_GET['search'] ?? '');
$filterRole    = trim($_GET['role'] ?? 'all');
$filterGroup   = trim($_GET['group'] ?? 'all');

$whereClause = "1=1";
if ($searchKeyword !== '') {
    $kw = $conn->real_escape_string($searchKeyword);
    $whereClause .= " AND (l.target_title LIKE '%$kw%' OR l.description LIKE '%$kw%' OR a.full_name LIKE '%$kw%' OR a.username LIKE '%$kw%')";
}
if ($filterRole !== 'all') {
    $r = $conn->real_escape_string($filterRole);
    $whereClause .= " AND a.role = '$r'";
}
if ($filterGroup !== 'all') {
    switch ($filterGroup) {
        case 'food':
            $whereClause .= " AND l.action_type LIKE 'food_%'";
            break;
        case 'order':
            $whereClause .= " AND l.action_type LIKE 'order_%'";
            break;
        case 'menu':
            $whereClause .= " AND l.action_type LIKE 'menu_%'";
            break;
        case 'category':
            $whereClause .= " AND l.action_type LIKE 'category_%'";
            break;
        case 'user':
            $whereClause .= " AND l.action_type LIKE 'user_%'";
            break;
        case 'shipping':
            $whereClause .= " AND l.action_type LIKE 'shipping_%'";
            break;
    }
}

// Thống kê nhanh
$statTotalToday = (int)($conn->query("SELECT COUNT(*) FROM system_action_logs WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0);
$statStaffTotal = (int)($conn->query("SELECT COUNT(*) FROM system_action_logs l JOIN accounts a ON l.user_id = a.id WHERE a.role = 'staff'")->fetch_row()[0] ?? 0);
$statFoodChanges = (int)($conn->query("SELECT COUNT(*) FROM system_action_logs WHERE action_type LIKE 'food_%'")->fetch_row()[0] ?? 0);
$statOrderProcess = (int)($conn->query("SELECT COUNT(*) FROM system_action_logs WHERE action_type LIKE 'order_%'")->fetch_row()[0] ?? 0);

// Danh sách log
$action_list = [];
$sql = "SELECT l.*, a.username, a.email, a.full_name, a.role 
        FROM system_action_logs l 
        LEFT JOIN accounts a ON l.user_id = a.id 
        WHERE $whereClause 
        ORDER BY l.created_at DESC 
        LIMIT 200";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $action_list[] = $row;
    }
}

include __DIR__ . '/admin_layout_open.php';
?>

<!-- Header -->
<div class="mb-7 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl md:text-2xl font-black text-white flex items-center gap-2.5">
            <i data-lucide="activity" class="w-6 h-6 text-amber-500"></i>
            Lịch sử thao tác Hệ thống
        </h2>
        <p class="text-xs text-slate-400 mt-1">Giám sát mọi tác động của Nhân viên & Quản trị viên (ẩn/hiện/sửa món, duyệt đơn, xếp thực đơn, phân quyền)</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="POST" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA SẠCH toàn bộ lịch sử thao tác không?');">
            <input type="hidden" name="clear_history" value="1">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-500/15 border border-red-500/30 text-red-400 hover:bg-red-500/30 hover:text-white rounded-xl transition text-xs font-bold shadow-lg">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Dọn sạch nhật ký
            </button>
        </form>
    </div>
</div>

<!-- Stats Overview Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-7">
    <div class="stat-card p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
            <i data-lucide="calendar" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-xs font-bold text-slate-400">Thao tác hôm nay</div>
            <div class="text-xl font-black text-white"><?php echo number_format($statTotalToday); ?></div>
        </div>
    </div>

    <div class="stat-card p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
            <i data-lucide="user-check" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-xs font-bold text-slate-400">Nhân viên thao tác</div>
            <div class="text-xl font-black text-sky-400"><?php echo number_format($statStaffTotal); ?></div>
        </div>
    </div>

    <div class="stat-card p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
            <i data-lucide="utensils" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-xs font-bold text-slate-400">Tác động Món ăn</div>
            <div class="text-xl font-black text-white"><?php echo number_format($statFoodChanges); ?></div>
        </div>
    </div>

    <div class="stat-card p-4 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center shrink-0">
            <i data-lucide="shopping-bag" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-xs font-bold text-slate-400">Xử lý Đơn hàng</div>
            <div class="text-xl font-black text-white"><?php echo number_format($statOrderProcess); ?></div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="content-card p-4 mb-6 rounded-2xl border border-white/10">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>" 
                   placeholder="Tìm theo món, mã đơn, người thực hiện..." 
                   class="w-full pl-9 pr-4 py-2 text-xs rounded-xl bg-slate-800/80 border border-white/10 text-white placeholder-slate-500 outline-none focus:border-amber-500">
        </div>

        <select name="role" class="px-3 py-2 text-xs rounded-xl bg-slate-800/80 border border-white/10 text-slate-200 outline-none">
            <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>Tất cả vai trò</option>
            <option value="staff" <?php echo $filterRole === 'staff' ? 'selected' : ''; ?>>Nhân viên</option>
            <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin (Quản trị)</option>
        </select>

        <select name="group" class="px-3 py-2 text-xs rounded-xl bg-slate-800/80 border border-white/10 text-slate-200 outline-none">
            <option value="all" <?php echo $filterGroup === 'all' ? 'selected' : ''; ?>>Tất cả loại thao tác</option>
            <option value="food" <?php echo $filterGroup === 'food' ? 'selected' : ''; ?>>Món ăn (Ẩn/Hiện/Sửa/Thêm)</option>
            <option value="order" <?php echo $filterGroup === 'order' ? 'selected' : ''; ?>>Đơn hàng (Duyệt/Nấu/Giao)</option>
            <option value="menu" <?php echo $filterGroup === 'menu' ? 'selected' : ''; ?>>Thực đơn theo ngày</option>
            <option value="category" <?php echo $filterGroup === 'category' ? 'selected' : ''; ?>>Danh mục món</option>
            <option value="user" <?php echo $filterGroup === 'user' ? 'selected' : ''; ?>>Tài khoản & Phân quyền</option>
            <option value="shipping" <?php echo $filterGroup === 'shipping' ? 'selected' : ''; ?>>Phí giao hàng</option>
        </select>

        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition shadow">
            Lọc kết quả
        </button>

        <?php if ($searchKeyword !== '' || $filterRole !== 'all' || $filterGroup !== 'all'): ?>
            <a href="login_history.php" class="px-3 py-2 bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-xs font-semibold rounded-xl transition">
                Đặt lại
            </a>
        <?php endif; ?>

        <div class="ml-auto text-xs text-slate-400 font-medium">
            Hiển thị <span class="font-bold text-white"><?php echo count($action_list); ?></span> bản ghi
        </div>
    </form>
</div>

<!-- Action Log Table -->
<div class="content-card rounded-2xl border border-white/10 overflow-hidden shadow-2xl">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white/5 text-[10px] uppercase tracking-wider text-slate-400 font-extrabold border-b border-white/5">
                    <th class="px-5 py-3.5">Người thực hiện</th>
                    <th class="px-5 py-3.5">Loại thao tác</th>
                    <th class="px-5 py-3.5">Đối tượng tác động</th>
                    <th class="px-5 py-3.5">Nội dung chi tiết</th>
                    <th class="px-5 py-3.5">IP</th>
                    <th class="px-5 py-3.5 text-right">Thời gian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-slate-300">
                <?php if (empty($action_list)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-500 italic">
                        Không tìm thấy thao tác nào phù hợp với bộ lọc.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($action_list as $act): 
                        // Cấu hình nhãn và màu sắc hành động
                        $badgeConfig = match($act['action_type']) {
                            'food_toggle'      => ['label' => 'Ẩn/Hiện món',   'class' => 'bg-purple-500/15 text-purple-400 border-purple-500/30', 'icon' => 'eye'],
                            'food_add'         => ['label' => 'Thêm món',      'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30', 'icon' => 'plus-circle'],
                            'food_edit'        => ['label' => 'Sửa món',       'class' => 'bg-amber-500/15 text-amber-400 border-amber-500/30', 'icon' => 'edit-3'],
                            'food_delete'      => ['label' => 'Xóa món',       'class' => 'bg-red-500/15 text-red-400 border-red-500/30', 'icon' => 'trash-2'],
                            'order_cooking'    => ['label' => 'Đang nấu đơn',  'class' => 'bg-orange-500/15 text-orange-400 border-orange-500/30', 'icon' => 'flame'],
                            'order_ready'      => ['label' => 'Báo xong đơn',  'class' => 'bg-teal-500/15 text-teal-400 border-teal-500/30', 'icon' => 'check'],
                            'order_paid'       => ['label' => 'Hoàn tất đơn',  'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30', 'icon' => 'check-circle-2'],
                            'order_reject'     => ['label' => 'Hủy đơn',       'class' => 'bg-red-500/15 text-red-400 border-red-500/30', 'icon' => 'x-circle'],
                            'order_note'       => ['label' => 'Ghi chú đơn',   'class' => 'bg-blue-500/15 text-blue-400 border-blue-500/30', 'icon' => 'file-text'],
                            'menu_add'         => ['label' => 'Thêm TĐ ngày',  'class' => 'bg-sky-500/15 text-sky-400 border-sky-500/30', 'icon' => 'calendar-plus'],
                            'menu_delete'      => ['label' => 'Xóa TĐ ngày',   'class' => 'bg-rose-500/15 text-rose-400 border-rose-500/30', 'icon' => 'calendar-x'],
                            'category_add'     => ['label' => 'Thêm danh mục', 'class' => 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30', 'icon' => 'tag'],
                            'category_edit'    => ['label' => 'Sửa danh mục',  'class' => 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30', 'icon' => 'edit'],
                            'category_delete'  => ['label' => 'Xóa danh mục',  'class' => 'bg-red-500/15 text-red-400 border-red-500/30', 'icon' => 'trash'],
                            'shipping_add', 'shipping_edit', 'shipping_active'
                                               => ['label' => 'Phí ship',      'class' => 'bg-blue-500/15 text-blue-400 border-blue-500/30', 'icon' => 'truck'],
                            'user_role'        => ['label' => 'Phân quyền',    'class' => 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30', 'icon' => 'shield'],
                            'user_lock'        => ['label' => 'Khóa/Mở TK',    'class' => 'bg-red-500/15 text-red-400 border-red-500/30', 'icon' => 'lock'],
                            'user_reset_pass'  => ['label' => 'Reset mật khẩu','class' => 'bg-slate-500/15 text-slate-300 border-slate-500/30', 'icon' => 'key'],
                            default            => ['label' => 'Thao tác khác', 'class' => 'bg-slate-700/50 text-slate-300 border-white/10', 'icon' => 'activity']
                        };

                        $isStaffUser = ($act['role'] === 'staff');
                        $isAdminUser = ($act['role'] === 'admin');
                    ?>
                    <tr class="hover:bg-white/[0.03] transition-colors">
                        <!-- Người thực hiện -->
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full <?php echo $isAdminUser ? 'bg-amber-500/20 text-amber-400' : ($isStaffUser ? 'bg-sky-500/20 text-sky-400' : 'bg-slate-700 text-slate-300'); ?> flex items-center justify-center shrink-0 border border-white/5">
                                    <i data-lucide="<?php echo $isAdminUser ? 'crown' : ($isStaffUser ? 'user-check' : 'user'); ?>" class="w-4 h-4"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-white flex items-center gap-1.5 truncate">
                                        <?php echo htmlspecialchars($act['full_name'] ?: ($act['username'] ?? 'Hệ thống')); ?>
                                        <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full border <?php echo $isAdminUser ? 'bg-amber-500/15 text-amber-400 border-amber-500/30' : ($isStaffUser ? 'bg-sky-500/15 text-sky-400 border-sky-500/30' : 'bg-slate-700 text-slate-400 border-slate-600'); ?>">
                                            <?php echo $isAdminUser ? 'Admin' : ($isStaffUser ? 'Nhân viên' : 'User'); ?>
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        @<?php echo htmlspecialchars($act['username'] ?: 'system'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Loại thao tác -->
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border <?php echo $badgeConfig['class']; ?>">
                                <i data-lucide="<?php echo $badgeConfig['icon']; ?>" class="w-3.5 h-3.5"></i>
                                <?php echo $badgeConfig['label']; ?>
                            </span>
                        </td>

                        <!-- Đối tượng tác động -->
                        <td class="px-5 py-3.5 font-bold text-white">
                            <?php echo htmlspecialchars($act['target_title']); ?>
                        </td>

                        <!-- Nội dung chi tiết -->
                        <td class="px-5 py-3.5 text-slate-300 font-medium">
                            <?php echo htmlspecialchars($act['description']); ?>
                        </td>

                        <!-- IP -->
                        <td class="px-5 py-3.5 text-slate-400 font-mono text-[11px]">
                            <?php echo htmlspecialchars($act['ip_address'] ?: '127.0.0.1'); ?>
                        </td>

                        <!-- Thời gian -->
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <div class="font-bold text-slate-200"><?php echo date('H:i · d/m/Y', strtotime($act['created_at'])); ?></div>
                            <div class="text-[10px] text-slate-500">
                                <?php 
                                    $diff = time() - strtotime($act['created_at']);
                                    if ($diff < 60) echo "Vừa xong";
                                    elseif ($diff < 3600) echo floor($diff/60) . " phút trước";
                                    elseif ($diff < 86400) echo floor($diff/3600) . " giờ trước";
                                    else echo floor($diff/86400) . " ngày trước";
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/admin_layout_close.php'; ?>
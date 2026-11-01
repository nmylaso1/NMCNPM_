<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'reservations';
$page_title   = 'Quản lý Đặt bàn & Gọi phục vụ';

$msg = '';
$msgType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Đổi trạng thái đặt bàn
    if ($action === 'update_reservation_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
        if ($id > 0 && in_array($status, $allowed)) {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $id);
            if ($stmt->execute()) {
                $msg = 'Đã cập nhật trạng thái đặt bàn #' . $id . ' sang ' . strtoupper($status);
                $msgType = 'success';
            }
            $stmt->close();
        }
    }

    // Hoàn tất yêu cầu gọi phục vụ
    if ($action === 'resolve_service_request') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        if ($req_id > 0) {
            $conn->query("UPDATE service_requests SET status = 'completed' WHERE id = $req_id");
            $msg = 'Đã xử lý yêu cầu phục vụ #' . $req_id;
            $msgType = 'success';
        }
    }
}

// Lấy danh sách yêu cầu gọi phục vụ chưa xử lý
$service_requests = [];
$srv_res = $conn->query("SELECT * FROM service_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
if ($srv_res) {
    while ($r = $srv_res->fetch_assoc()) $service_requests[] = $r;
}

// Filter đặt bàn
$tab = $_GET['tab'] ?? 'all';
$filterDate = $_GET['date'] ?? '';

$where = [];
if ($tab !== 'all' && in_array($tab, ['pending', 'confirmed', 'cancelled', 'completed'])) {
    $where[] = "status = '" . $conn->real_escape_string($tab) . "'";
}
if (!empty($filterDate)) {
    $where[] = "reservation_date = '" . $conn->real_escape_string($filterDate) . "'";
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$reservations = [];
$res_query = $conn->query("SELECT * FROM reservations $whereSql ORDER BY reservation_date DESC, reservation_time DESC LIMIT 100");
if ($res_query) {
    while ($r = $res_query->fetch_assoc()) $reservations[] = $r;
}

// Đếm thống kê
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$c_res = $conn->query("SELECT status, COUNT(*) as c FROM reservations GROUP BY status");
if ($c_res) {
    while ($r = $c_res->fetch_assoc()) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']] = (int)$r['c'];
        }
        $counts['all'] += (int)$r['c'];
    }
}

include __DIR__ . '/admin_layout_open.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 dark:text-white flex items-center gap-2.5">
                <i data-lucide="calendar-check" class="w-7 h-7 text-amber-500"></i>
                Quản lý Đặt bàn & Yêu cầu phục vụ
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Tiếp nhận yêu cầu đặt tiệc, bàn ăn và chuông gọi nhân viên tại bàn</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl flex items-center gap-3 <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
            <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 flex-shrink-0"></i>
            <span class="text-sm font-semibold"><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- KHU VỰC 1: YÊU CẦU GỌI PHỤC VỤ TẠI BÀN (REAL-TIME ALERTS) -->
    <?php if (count($service_requests) > 0): ?>
        <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-red-500/10 border-2 border-amber-400/40 rounded-3xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                    <h2 class="text-base font-extrabold text-amber-900 dark:text-amber-400">Yêu cầu từ khách tại bàn đang chờ xử lý (<?= count($service_requests) ?>)</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($service_requests as $srv): 
                    $typeLabels = [
                        'staff' => ['icon' => 'user-check', 'text' => 'Gọi nhân viên', 'color' => 'bg-amber-100 text-amber-800'],
                        'water' => ['icon' => 'droplets', 'text' => 'Xin nước / Đá', 'color' => 'bg-blue-100 text-blue-800'],
                        'cutlery' => ['icon' => 'utensils', 'text' => 'Bát đũa / Khăn', 'color' => 'bg-purple-100 text-purple-800'],
                        'bill' => ['icon' => 'receipt', 'text' => 'Thanh toán / In bill', 'color' => 'bg-emerald-100 text-emerald-800'],
                    ];
                    $tInfo = $typeLabels[$srv['request_type']] ?? ['icon' => 'bell', 'text' => $srv['request_type'], 'color' => 'bg-gray-100 text-gray-800'];
                ?>
                    <div class="bg-white dark:bg-slate-800 border border-amber-200 dark:border-slate-700 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="px-2.5 py-1 rounded-xl text-xs font-black <?= $tInfo['color'] ?> flex items-center gap-1">
                                <i data-lucide="<?= $tInfo['icon'] ?>" class="w-3.5 h-3.5"></i>
                                <?= $tInfo['text'] ?>
                            </span>
                            <div>
                                <p class="text-sm font-extrabold text-slate-800 dark:text-white"><?= htmlspecialchars($srv['table_number']) ?></p>
                                <p class="text-[11px] text-slate-400"><?= date('H:i - d/m', strtotime($srv['created_at'])) ?></p>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="resolve_service_request">
                            <input type="hidden" name="request_id" value="<?= $srv['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm">
                                Đã phục vụ
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- KHU VỰC 2: DANH SÁCH ĐẶT BÀN TRƯỚC -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
        <!-- Tabs & Search -->
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap gap-2">
                <?php 
                $tabs = [
                    'all' => 'Tất cả (' . $counts['all'] . ')',
                    'pending' => 'Chờ duyệt (' . $counts['pending'] . ')',
                    'confirmed' => 'Đã xác nhận (' . $counts['confirmed'] . ')',
                    'completed' => 'Hoàn thành (' . $counts['completed'] . ')',
                    'cancelled' => 'Đã hủy (' . $counts['cancelled'] . ')'
                ];
                foreach ($tabs as $k => $label): 
                    $isActive = ($tab === $k);
                ?>
                    <a href="?tab=<?= $k ?>&date=<?= urlencode($filterDate) ?>" 
                       class="px-4 py-2 rounded-2xl text-xs font-bold transition-all <?= $isActive ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Date Filter -->
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" 
                       class="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none focus:border-amber-500">
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold">Lọc ngày</button>
                <?php if ($filterDate): ?>
                    <a href="?tab=<?= htmlspecialchars($tab) ?>" class="text-xs text-rose-500 font-bold hover:underline">Xóa lọc</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-[11px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-4 px-6">Mã đặt bàn</th>
                        <th class="py-4 px-6">Khách hàng</th>
                        <th class="py-4 px-6">Thời gian đến</th>
                        <th class="py-4 px-6">Số khách</th>
                        <th class="py-4 px-6">Khu vực bàn</th>
                        <th class="py-4 px-6">Trạng thái</th>
                        <th class="py-4 px-6 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php if (count($reservations) === 0): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                Chưa có đơn đặt bàn nào theo bộ lọc này.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($reservations as $r): 
                        $areaNames = [
                            'standard' => 'Sảnh chung',
                            'vip' => 'Phòng VIP',
                            'outdoor' => 'Sân vườn',
                            'window' => 'Gần cửa sổ'
                        ];
                        $areaName = $areaNames[$r['seating_area']] ?? $r['seating_area'];

                        $statusBadge = [
                            'pending' => ['bg' => 'bg-amber-100 text-amber-800', 'text' => 'Chờ duyệt'],
                            'confirmed' => ['bg' => 'bg-blue-100 text-blue-800', 'text' => 'Đã xác nhận'],
                            'completed' => ['bg' => 'bg-emerald-100 text-emerald-800', 'text' => 'Hoàn tất'],
                            'cancelled' => ['bg' => 'bg-rose-100 text-rose-800', 'text' => 'Đã hủy'],
                        ];
                        $st = $statusBadge[$r['status']] ?? ['bg' => 'bg-gray-100 text-gray-800', 'text' => $r['status']];
                    ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="py-4 px-6 font-mono font-bold text-amber-600 dark:text-amber-400">
                                <?= htmlspecialchars($r['reservation_code']) ?>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($r['customer_name']) ?></p>
                                <p class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                    <i data-lucide="phone" class="w-3 h-3"></i> <?= htmlspecialchars($r['phone']) ?>
                                    <?php if ($r['email']): ?>
                                        <span class="mx-1">•</span> <?= htmlspecialchars($r['email']) ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($r['note']): ?>
                                    <p class="text-xs text-amber-700 bg-amber-50 dark:bg-amber-950/40 p-1.5 rounded-lg mt-1 italic">
                                        "<?= htmlspecialchars($r['note']) ?>"
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-extrabold text-slate-700 dark:text-slate-200">
                                    <?= date('H:i', strtotime($r['reservation_time'])) ?>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <?= date('d/m/Y', strtotime($r['reservation_date'])) ?>
                                </p>
                            </td>
                            <td class="py-4 px-6 font-bold text-slate-700 dark:text-slate-200">
                                <?= (int)$r['guest_count'] ?> người
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    <?= htmlspecialchars($areaName) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $st['bg'] ?>">
                                    <?= $st['text'] ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <form method="POST" class="inline-flex gap-1.5">
                                    <input type="hidden" name="action" value="update_reservation_status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">

                                    <?php if ($r['status'] === 'pending'): ?>
                                        <button type="submit" name="status" value="confirmed" 
                                                title="Xác nhận đặt bàn"
                                                class="px-2.5 py-1.5 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white text-xs font-bold transition-all">
                                            Duyệt
                                        </button>
                                        <button type="submit" name="status" value="cancelled" 
                                                title="Hủy đặt bàn"
                                                class="px-2.5 py-1.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white text-xs font-bold transition-all">
                                            Hủy
                                        </button>
                                    <?php elseif ($r['status'] === 'confirmed'): ?>
                                        <button type="submit" name="status" value="completed" 
                                                title="Hoàn tất khi khách đã đến ăn"
                                                class="px-2.5 py-1.5 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white text-xs font-bold transition-all">
                                            Hoàn tất
                                        </button>
                                        <button type="submit" name="status" value="cancelled" 
                                                title="Hủy đặt bàn"
                                                class="px-2.5 py-1.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white text-xs font-bold transition-all">
                                            Hủy
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin_layout_close.php'; ?>

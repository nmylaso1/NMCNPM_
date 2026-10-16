<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
$admin_name  = $_SESSION['full_name'] ?? (($_SESSION['role'] ?? '') === 'staff' ? 'Nhân viên' : 'Admin');

// Giải phóng session để tránh treo các trang khác khi load số liệu thống kê
session_write_close();
$current_page = 'index';
$page_title   = 'Tổng quan';

require_once '../includes/config.php';

$safe = fn($q) => ($r = $conn->query($q)) ? (float)$r->fetch_row()[0] : 0;

// =========================================================================
// 1. XỬ LÝ LỌC THỜI GIAN CHO BIỂU ĐỒ (Hôm nay, 7 ngày, Tháng này)
// =========================================================================
$period = $_GET['period'] ?? 'week';
$days_to_subtract = 6; 
$period_sql = "";
$chart_title = "";

switch ($period) {
    case 'today':
        $period_sql = "DATE(created_at) = CURDATE()";
        $chart_title = "Hôm nay (Theo giờ)";
        break;
    case 'month':
        $period_sql = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
        $days_to_subtract = (int)date('j') - 1;
        $chart_title = "Tháng này";
        break;
    case 'week':
    default:
        $period_sql = "created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
        $days_to_subtract = 6;
        $chart_title = "7 ngày qua";
        break;
}


// ─── Stats - Gộp các query lại để tối ưu tốc độ load ──
// 1. Products stats
$p_stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_hidden=0 THEN 1 ELSE 0 END) as visible, SUM(CASE WHEN is_hidden=1 THEN 1 ELSE 0 END) as hidden FROM products")->fetch_assoc();
$stat_foods_total   = (int)($p_stats['total'] ?? 0);
$stat_foods_visible = (int)($p_stats['visible'] ?? 0);
$stat_foods_hidden  = (int)($p_stats['hidden'] ?? 0);

// 2. Users stats
$u_stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admins FROM accounts")->fetch_assoc();
$stat_users         = (int)($u_stats['total'] ?? 0);
$stat_admins        = (int)($u_stats['admins'] ?? 0);

// 3. Orders stats (Tính tổng thể, không theo bộ lọc thời gian)
$o_stats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) as today, SUM(CASE WHEN status='pending' OR status='processing' THEN 1 ELSE 0 END) as pending FROM orders WHERE status != 'cancelled'")->fetch_assoc();
$stat_orders_total  = (int)($o_stats['total'] ?? 0);
$stat_orders_today  = (int)($o_stats['today'] ?? 0);
$stat_orders_pending = (int)($o_stats['pending'] ?? 0);

// Revenue (Tính tổng thể, không theo bộ lọc thời gian)
$rev_res = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled', 'rejected')");
$stat_revenue_total = $rev_res ? (float)$rev_res->fetch_row()[0] : 0;
$rev_today_res = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled', 'rejected') AND DATE(created_at)=CURDATE()");
$stat_revenue_today = $rev_today_res ? (float)$rev_today_res->fetch_row()[0] : 0;

// Today menu count
$sm_res = $conn->query("SELECT COUNT(*) FROM daily_menu WHERE serving_date=CURDATE()");
$stat_menu_today = $sm_res ? (int)$sm_res->fetch_row()[0] : 0;


// =========================================================================
// LẤY DỮ LIỆU BIỂU ĐỒ DOANH THU (CHẠY THEO BỘ LỌC THỜI GIAN)
// =========================================================================
$chart_dates = [];
$chart_pending = [];
$chart_completed = [];
$chart_rejected = [];

if ($period === 'today') {
    for ($i = 0; $i <= 23; $i++) {
        $h = sprintf("%02d", $i);
        $chart_dates[$h] = $h . ':00';
        $chart_pending[$h] = 0;
        $chart_completed[$h] = 0;
        $chart_rejected[$h] = 0;
    }
    $chart_sql = "
        SELECT 
            LPAD(HOUR(created_at), 2, '0') as dt,
            SUM(CASE WHEN status IN ('pending', 'processing') THEN total_amount ELSE 0 END) as p_total,
            SUM(CASE WHEN status IN ('delivered', 'paid') THEN total_amount ELSE 0 END) as c_total,
            SUM(CASE WHEN status IN ('rejected', 'cancelled') THEN total_amount ELSE 0 END) as r_total
        FROM orders 
        WHERE $period_sql
        GROUP BY HOUR(created_at)
    ";
} else {
    for ($i = $days_to_subtract; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_dates[$d] = date('d/m', strtotime("-$i days"));
        $chart_pending[$d] = 0;
        $chart_completed[$d] = 0;
        $chart_rejected[$d] = 0;
    }
    $chart_sql = "
        SELECT 
            DATE(created_at) as dt,
            SUM(CASE WHEN status IN ('pending', 'processing') THEN total_amount ELSE 0 END) as p_total,
            SUM(CASE WHEN status IN ('delivered', 'paid') THEN total_amount ELSE 0 END) as c_total,
            SUM(CASE WHEN status IN ('rejected', 'cancelled') THEN total_amount ELSE 0 END) as r_total
        FROM orders 
        WHERE $period_sql
        GROUP BY DATE(created_at)
    ";
}

$chart_res = $conn->query($chart_sql);
if ($chart_res) {
    while ($row = $chart_res->fetch_assoc()) {
        $d = $row['dt'];
        if (isset($chart_pending[$d])) {
            $chart_pending[$d] = (float)$row['p_total'];
            $chart_completed[$d] = (float)$row['c_total'];
            $chart_rejected[$d] = (float)$row['r_total'];
        }
    }
}

$labels_json = json_encode(array_values($chart_dates));
$data_pending_json = json_encode(array_values($chart_pending));
$data_completed_json = json_encode(array_values($chart_completed));
$data_rejected_json = json_encode(array_values($chart_rejected));


// Top 5 selling foods (Chạy theo bộ lọc)
$top_foods = [];
$food_period_sql = str_replace('created_at', 'o.created_at', $period_sql);
$top_res = $conn->query("SELECT oi.product_name, SUM(oi.quantity) AS total 
                         FROM order_items oi JOIN orders o ON oi.order_id = o.id 
                         WHERE $food_period_sql 
                         GROUP BY oi.product_name ORDER BY total DESC LIMIT 5");
if ($top_res) while ($r = $top_res->fetch_assoc()) $top_foods[] = $r;

// Recent orders
$recent_orders = [];
$ro_res = $conn->query("SELECT o.order_code, o.total_amount, o.status, o.created_at, a.full_name FROM orders o JOIN accounts a ON a.id=o.user_id ORDER BY o.created_at DESC LIMIT 6");
if ($ro_res) while ($r = $ro_res->fetch_assoc()) $recent_orders[] = $r;

include 'admin_layout_open.php';
?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
    <?php
    $cards = [
        ['label'=>'Tổng món ăn',     'value'=>$stat_foods_total,    'sub'=>"$stat_foods_visible hiện · $stat_foods_hidden ẩn",   'icon'=>'utensils',        'color'=>'from-amber-500 to-amber-700'],
        ['label'=>'Hôm nay thực đơn','value'=>$stat_menu_today,     'sub'=>'Số món trong thực đơn ngày',                         'icon'=>'calendar-days',   'color'=>'from-sky-500 to-sky-700'],
        ['label'=>'Đơn hàng hôm nay','value'=>$stat_orders_today,   'sub'=>"$stat_orders_pending đơn đang chờ",                  'icon'=>'shopping-bag',    'color'=>'from-violet-500 to-violet-700'],
        ['label'=>'Doanh thu hôm nay','value'=>number_format($stat_revenue_today,0,',','.').'đ', 'sub'=>'Tất cả đơn hợp lệ', 'icon'=>'trending-up', 'color'=>'from-emerald-500 to-emerald-700'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?php echo $c['color']; ?> flex items-center justify-center shadow-lg">
                <i data-lucide="<?php echo $c['icon']; ?>" class="w-4.5 h-4.5 text-white"></i>
            </div>
        </div>
        <div class="text-2xl font-extrabold text-white mb-0.5"><?php echo $c['value']; ?></div>
        <div class="text-xs font-bold text-slate-400 mb-1"><?php echo $c['label']; ?></div>
        <div class="text-[10px] text-slate-600 font-semibold"><?php echo $c['sub']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-7">
    <div class="lg:col-span-2 content-card p-5 flex flex-col">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 gap-4 border-b border-slate-700/50 pb-4">
            
            <div class="flex flex-col gap-2">
                <h3 class="font-extrabold text-white text-sm">📈 Doanh thu <?php echo $chart_title; ?></h3>
                <div class="flex gap-1">
                    <a href="?period=today" class="px-2 py-1 rounded text-[10px] font-bold transition <?php echo $period==='today'?'bg-blue-600 text-white':'bg-slate-800 text-slate-400 hover:text-white'; ?>">Hôm nay</a>
                    <a href="?period=week" class="px-2 py-1 rounded text-[10px] font-bold transition <?php echo $period==='week'?'bg-blue-600 text-white':'bg-slate-800 text-slate-400 hover:text-white'; ?>">7 ngày</a>
                    <a href="?period=month" class="px-2 py-1 rounded text-[10px] font-bold transition <?php echo $period==='month'?'bg-blue-600 text-white':'bg-slate-800 text-slate-400 hover:text-white'; ?>">Tháng này</a>
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5" id="chartFilters">
                <button onclick="filterChartLine('all', this)" class="filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-slate-700 text-white border border-slate-600 transition-colors">Tất cả</button>
                <button onclick="filterChartLine(0, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 hover:bg-amber-500/20 border border-amber-500/30 transition-colors">Đang chờ</button>
                <button onclick="filterChartLine(1, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors">Hoàn thành</button>
                <button onclick="filterChartLine(2, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/30 transition-colors">Đã hủy</button>
            </div>
        </div>

        <div class="relative w-full flex-1 min-h-[220px]">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <div class="content-card p-5">
        <h3 class="font-extrabold text-white text-sm mb-4">🔥 Top bán chạy (<?php echo $period==='today'?'Hôm nay':($period==='month'?'Tháng này':'7 ngày'); ?>)</h3>
        <?php if (empty($top_foods)): ?>
        <p class="text-slate-500 text-xs italic text-center py-8">Chưa có dữ liệu.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($top_foods as $i => $f): ?>
            <div class="flex items-center gap-3">
                <span class="text-xs font-black rounded-full w-5 h-5 flex items-center justify-center <?php echo $i===0?'bg-amber-500 text-white':($i===1?'bg-slate-600 text-white':($i===2?'bg-orange-700/70 text-white':'bg-slate-800 text-slate-400')); ?>"><?php echo $i+1; ?></span>
                <span class="flex-1 text-xs font-semibold text-slate-300 truncate"><?php echo htmlspecialchars($f['product_name'] ?? 'Không rõ'); ?></span>
                <span class="text-xs font-black text-amber-400"><?php echo $f['total']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<h3 class="text-sm font-extrabold text-slate-500 uppercase tracking-wider mb-4">🗂 Danh sách module</h3>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-8">
<?php
$modules = [
    ['icon'=>'utensils',        'label'=>'Món ăn',        'desc'=>"$stat_foods_total món",       'href'=>'manage_foods.php',       'color'=>'from-amber-500 to-amber-700', 'badge'=>null],
    ['icon'=>'tags',            'label'=>'Danh mục',      'desc'=>'Phân loại món',                'href'=>'manage_categories.php',  'color'=>'from-orange-500 to-orange-700'],
    ['icon'=>'calendar-days',   'label'=>'Thực đơn ngày', 'desc'=>"Hôm nay: $stat_menu_today",   'href'=>'manage_daily_menu.php',  'color'=>'from-sky-500 to-sky-700'],
    ['icon'=>'shopping-bag',    'label'=>'Đơn hàng',      'desc'=>"$stat_orders_pending chờ",    'href'=>'manage_orders.php',      'color'=>'from-violet-500 to-violet-700', 'badge'=>$stat_orders_pending],
    ['icon'=>'ticket',          'label'=>'Mã giảm giá',   'desc'=>'Voucher khuyến mãi',           'href'=>'manage_coupons.php',     'color'=>'from-indigo-500 to-indigo-700'],
    ['icon'=>'truck',           'label'=>'Phí giao hàng', 'desc'=>'Cấu hình vận chuyển',          'href'=>'manage_shipping.php',    'color'=>'from-blue-500 to-blue-700'],
    ['icon'=>'star',            'label'=>'Đánh giá',      'desc'=>'Phản hồi từ khách',            'href'=>'manage_reviews.php',     'color'=>'from-yellow-500 to-yellow-700'],
    ['icon'=>'users',           'label'=>'Tài khoản',     'desc'=>"$stat_users tài khoản",       'href'=>'manage_users.php',       'color'=>'from-pink-500 to-pink-700'],
    ['icon'=>'activity',        'label'=>'Lịch sử thao tác','desc'=>'Nhật ký tác động hệ thống',  'href'=>'login_history.php',      'color'=>'from-emerald-500 to-emerald-700'],
    ['icon'=>'bar-chart-2',     'label'=>'Thống kê',      'desc'=>'Doanh thu & xu hướng',        'href'=>'stats.php',              'color'=>'from-teal-500 to-teal-700'],
    ['icon'=>'history',         'label'=>'Lịch sử thanh toán','desc'=>'Lịch sử giao dịch',       'href'=>'manage_payments.php',    'color'=>'from-cyan-500 to-cyan-700'],
    ['icon'=>'bell',            'label'=>'Thông báo',     'desc'=>'Gửi thông báo đến user',      'href'=>'notifications.php',      'color'=>'from-rose-500 to-rose-700'],
    ['icon'=>'settings',        'label'=>'Cài đặt',       'desc'=>'Thông tin hệ thống',          'href'=>'settings.php',           'color'=>'from-slate-500 to-slate-700'],
];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    $modules = array_values(array_filter($modules, function($m) {
        return !in_array($m['href'], ['manage_coupons.php', 'manage_users.php', 'login_history.php']);
    }));
}
foreach ($modules as $m): ?>
<a href="<?php echo $m['href']; ?>" class="content-card p-4 flex flex-col gap-2 hover:border-amber-500/30 transition group relative">
    <?php if (!empty($m['badge']) && $m['badge'] > 0): ?>
    <span class="absolute top-3 right-3 bg-red-500 text-white text-[9px] font-black w-4.5 h-4.5 rounded-full flex items-center justify-center"><?php echo $m['badge']; ?></span>
    <?php endif; ?>
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?php echo $m['color']; ?> flex items-center justify-center shadow mb-1 group-hover:scale-110 transition-transform">
        <i data-lucide="<?php echo $m['icon']; ?>" class="w-5 h-5 text-white"></i>
    </div>
    <span class="text-sm font-extrabold text-white"><?php echo $m['label']; ?></span>
    <span class="text-[11px] text-slate-500 font-semibold"><?php echo $m['desc']; ?></span>
</a>
<?php endforeach; ?>
</div>

<div class="content-card p-5">
    <div class="flex items-center justify-between mb-5">
        <h3 class="font-extrabold text-white text-sm">🛒 Đơn hàng gần đây</h3>
        <a href="manage_orders.php" class="text-xs text-amber-400 hover:text-amber-300 font-bold flex items-center gap-1">
            Xem tất cả <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
    </div>
    <?php if (empty($recent_orders)): ?>
    <p class="text-slate-500 text-xs italic text-center py-8">Chưa có đơn hàng nào.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-white/5 text-slate-500 text-left">
                    <th class="pb-3 font-bold uppercase tracking-wider">Mã đơn</th>
                    <th class="pb-3 font-bold uppercase tracking-wider">Khách</th>
                    <th class="pb-3 font-bold uppercase tracking-wider">Tổng</th>
                    <th class="pb-3 font-bold uppercase tracking-wider">Trạng thái</th>
                    <th class="pb-3 font-bold uppercase tracking-wider">Thời gian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php foreach ($recent_orders as $o):
                    $sClass = match($o['status']) {
                        'paid', 'delivered' => 'bg-emerald-500/15 text-emerald-400',
                        'pending', 'processing' => 'bg-amber-500/15 text-amber-400',
                        default => 'bg-red-500/15 text-red-400',
                    };
                    $sTxt = match($o['status']) {
                        'paid' => 'Đã thanh toán', 
                        'delivered' => 'Đã giao',
                        'pending' => 'Chờ xác nhận',
                        'processing' => 'Đang xử lý',
                        'rejected', 'cancelled' => 'Đã hủy', 
                        default => $o['status']
                    };
                ?>
                <tr class="hover:bg-white/3 transition">
                    <td class="py-2.5 font-mono font-bold text-amber-400"><?php echo htmlspecialchars($o['order_code']); ?></td>
                    <td class="py-2.5 font-semibold text-slate-300 truncate max-w-[120px]"><?php echo htmlspecialchars($o['full_name']); ?></td>
                    <td class="py-2.5 font-bold text-white"><?php echo number_format($o['total_amount'],0,',','.'); ?>đ</td>
                    <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $sClass; ?>"><?php echo $sTxt; ?></span></td>
                    <td class="py-2.5 text-slate-500"><?php echo date('H:i d/m', strtotime($o['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
let globalRevChart = null;

function filterChartLine(datasetIndex, btnElement) {
    if (!globalRevChart) return;
    
    // Cập nhật giao diện nút bấm
    const btns = document.querySelectorAll('#chartFilters .filter-btn');
    btns.forEach(b => {
        if(b.innerText === 'Tất cả') {
            b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors';
        } else {
            if (b.innerText === 'Đang chờ') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 hover:bg-amber-500/20 border border-amber-500/30 transition-colors';
            if (b.innerText === 'Hoàn thành') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors';
            if (b.innerText === 'Đã hủy') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/30 transition-colors';
        }
    });

    // Sáng nút vừa bấm
    if (datasetIndex === 'all') {
        btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-slate-700 text-white border border-slate-600 transition-colors';
    } else {
        if (datasetIndex === 0) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500 text-white border border-amber-600 transition-colors';
        if (datasetIndex === 1) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500 text-white border border-emerald-600 transition-colors';
        if (datasetIndex === 2) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-red-500 text-white border border-red-600 transition-colors';
    }

    // Điều khiển biểu đồ
    if (datasetIndex === 'all') {
        for(let i = 0; i < 3; i++) globalRevChart.setDatasetVisibility(i, true);
    } else {
        for(let i = 0; i < 3; i++) globalRevChart.setDatasetVisibility(i, i === datasetIndex);
    }
    globalRevChart.update();
}

window.addEventListener('load', function() {
    if (typeof Chart === 'undefined') return;

    const colorPending = { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.15)' }; 
    const colorCompleted = { border: '#10b981', bg: 'rgba(16, 185, 129, 0.15)' }; 
    const colorRejected = { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.15)' }; 

    const revCtx = document.getElementById('revenueChart');
    if (revCtx) {
        globalRevChart = new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?php echo $labels_json; ?>,
                datasets: [
                    {
                        label: 'Đang chờ (VNĐ)',
                        data: <?php echo $data_pending_json; ?>,
                        borderColor: colorPending.border,
                        backgroundColor: colorPending.bg,
                        borderWidth: 2.5,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        tension: 0.4, 
                        fill: true
                    },
                    {
                        label: 'Hoàn thành (VNĐ)',
                        data: <?php echo $data_completed_json; ?>,
                        borderColor: colorCompleted.border,
                        backgroundColor: colorCompleted.bg,
                        borderWidth: 2.5,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        tension: 0.4, 
                        fill: true
                    },
                    {
                        label: 'Hủy/Từ chối (VNĐ)',
                        data: <?php echo $data_rejected_json; ?>,
                        borderColor: colorRejected.border,
                        backgroundColor: colorRejected.bg,
                        borderWidth: 2.5,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        tension: 0.4, 
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false }, 
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + 'đ';
                                }
                                return label;
                            }
                        }
                    }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                scales: {
                    x: { 
                        ticks: { color: '#475569', font: { size: 10, family: 'Quicksand' } }, 
                        grid: { color: 'rgba(255,255,255,0.03)' } 
                    },
                    y: { 
                        ticks: { color: '#475569', font: { size: 10 }, callback: v => v >= 1000 ? (v/1000)+'k' : v }, 
                        grid: { color: 'rgba(255,255,255,0.03)' } 
                    }
                }
            }
        });
    }
});
</script>

<?php include 'admin_layout_close.php'; ?>
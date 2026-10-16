<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'stats';
$page_title   = 'Thống kê & Báo cáo';

// Giải phóng session để tránh treo các trang khác khi load số liệu thống kê
session_write_close();

require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

$safe = fn($q) => ($r = $conn->query($q)) ? (float)$r->fetch_row()[0] : 0;

// =========================================================================
// 1. XỬ LÝ LỌC THỜI GIAN (Hôm nay, 7 ngày, Tháng này)
// =========================================================================
$period = $_GET['period'] ?? 'week';

// Cấu hình số ngày lùi lại cho biểu đồ
$days_to_subtract = 6; 
$period_sql = "";

switch ($period) {
    case 'today':
        $period_sql = "DATE(created_at) = CURDATE()";
        break;
    case 'month':
        $period_sql = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
        $days_to_subtract = (int)date('j') - 1; // Số ngày từ mùng 1 đến hôm nay
        break;
    case 'week':
    default:
        $period_sql = "created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
        $days_to_subtract = 6;
        break;
}

// Tính các thẻ thống kê nhanh
$rev_total   = $safe("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled', 'rejected') AND $period_sql");
$order_count = (int)$safe("SELECT COUNT(*) FROM orders WHERE $period_sql");
$paid_count  = (int)$safe("SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'delivered') AND $period_sql");
$cancel_count= (int)$safe("SELECT COUNT(*) FROM orders WHERE status IN ('rejected', 'cancelled') AND $period_sql");


// =========================================================================
// 2. BIỂU ĐỒ DOANH THU (Chia theo GIỜ nếu là Hôm nay, theo NGÀY nếu khác)
// =========================================================================
$chart_dates = [];
$chart_pending = [];
$chart_completed = [];
$chart_rejected = [];

if ($period === 'today') {
    // CHẾ ĐỘ HIỂN THỊ THEO GIỜ (00:00 -> 23:00)
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
    // CHẾ ĐỘ HIỂN THỊ THEO NGÀY
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


// =========================================================================
// 3. TOP MÓN ĂN & PHÂN BỐ TRẠNG THÁI
// =========================================================================
$top_foods = [];
$food_period_sql = str_replace('created_at', 'o.created_at', $period_sql);

$tr = $conn->query("SELECT oi.product_name, SUM(oi.quantity) AS total, SUM(oi.unit_price*oi.quantity) AS revenue 
                    FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE $food_period_sql
                    GROUP BY oi.product_name ORDER BY total DESC LIMIT 10");
if ($tr) while ($r = $tr->fetch_assoc()) $top_foods[] = $r;

$status_dist = [
    'pending' => (int)$safe("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'processing') AND $period_sql"),
    'completed' => (int)$safe("SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'delivered') AND $period_sql"),
    'cancelled' => (int)$safe("SELECT COUNT(*) FROM orders WHERE status IN ('rejected', 'cancelled') AND $period_sql"),
];

include 'admin_layout_open.php';
?>

<div class="flex gap-2 mb-6 flex-wrap">
    <?php foreach (['today'=>'Hôm nay', 'week'=>'7 ngày qua', 'month'=>'Tháng này'] as $v=>$l): ?>
    <a href="?period=<?php echo $v; ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition <?php echo $period===$v?'bg-amber-600 text-white shadow-lg shadow-amber-500/30':'bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700'; ?>">
        <?php echo $l; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
    <?php $stats_rows = [
        ['Doanh thu', number_format($rev_total,0,',','.').'đ', 'trending-up',   'from-amber-500 to-amber-700'],
        ['Tổng đơn',  $order_count,                             'shopping-bag',  'from-violet-500 to-violet-700'],
        ['Đã hoàn thành',$paid_count,                           'check-circle',  'from-emerald-500 to-emerald-700'],
        ['Bị hủy/từ chối', $cancel_count,                       'x-circle',       'from-red-500 to-red-700'],
    ];
    foreach ($stats_rows as $c): ?>
    <div class="stat-card">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br <?php echo $c[3]; ?> flex items-center justify-center mb-4">
            <i data-lucide="<?php echo $c[2]; ?>" class="w-4 h-4 text-white"></i>
        </div>
        <div class="text-xl font-extrabold text-white"><?php echo $c[1]; ?></div>
        <div class="text-xs text-slate-500 font-semibold mt-1"><?php echo $c[0]; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-7">
    <div class="lg:col-span-2 content-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 gap-3">
            <div>
                <h3 class="font-extrabold text-white text-sm">📈 Doanh thu <?php echo $period==='today'?'Hôm nay (Theo giờ)':($period==='month'?'Tháng này':'7 ngày qua'); ?></h3>
            </div>
            <div class="flex flex-wrap gap-1.5" id="chartFilters">
                <button onclick="filterChartLine('all', this)" class="filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-slate-700 text-white border border-slate-600 transition-colors">Tất cả</button>
                <button onclick="filterChartLine(0, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 hover:bg-amber-500/20 border border-amber-500/30 transition-colors">Đang chờ</button>
                <button onclick="filterChartLine(1, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors">Hoàn thành</button>
                <button onclick="filterChartLine(2, this)" class="filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/30 transition-colors">Đã hủy</button>
            </div>
        </div>
        <div class="relative w-full h-64"><canvas id="revChart"></canvas></div>
    </div>

    <div class="content-card p-5 flex flex-col">
        <h3 class="font-extrabold text-white text-sm mb-2">📊 Tỷ lệ trạng thái</h3>
        <p class="text-[10px] text-slate-500 mb-4 text-center">Tổng hợp đơn hàng theo tỷ lệ</p>
        <div class="relative w-full h-48 flex-1 cursor-pointer"><canvas id="statusChart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="content-card p-5 lg:col-span-2">
        <h3 class="font-extrabold text-white text-sm mb-5">🏆 Top 10 Món bán chạy (<?php echo $period==='today'?'Hôm nay':($period==='month'?'Tháng này':'7 ngày qua'); ?>)</h3>
        <?php if (empty($top_foods)): ?>
        <p class="text-slate-500 text-xs italic text-center py-8">Chưa có dữ liệu.</p>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <?php foreach ($top_foods as $i=>$f): $pct = $top_foods[0]['total']>0 ? round($f['total']/$top_foods[0]['total']*100) : 0; ?>
            <div>
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="font-semibold text-slate-300 truncate"><?php echo ($i+1).'. '.htmlspecialchars($f['product_name'] ?? 'Không rõ'); ?></span>
                    <span class="text-amber-400 font-bold ml-2 shrink-0"><?php echo $f['total']; ?> suất</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-700" style="width:<?php echo $pct; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Biến toàn cục để lưu trữ biểu đồ Line, giúp hàm ngoài có thể gọi được
let globalRevChart = null;

// Hàm lọc hiển thị đường dây (Gọi khi bấm 4 nút nhỏ)
function filterChartLine(datasetIndex, btnElement) {
    if (!globalRevChart) return;
    
    // Cập nhật giao diện nút bấm (highlight nút được chọn)
    const btns = document.querySelectorAll('#chartFilters .filter-btn');
    btns.forEach(b => {
        // Reset nút "Tất cả"
        if(b.innerText === 'Tất cả') {
            b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors';
        } else {
            // Lấy lại class gốc cho các nút màu
            if (b.innerText === 'Đang chờ') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 hover:bg-amber-500/20 border border-amber-500/30 transition-colors';
            if (b.innerText === 'Hoàn thành') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors';
            if (b.innerText === 'Đã hủy') b.className = 'filter-btn px-2.5 py-1 rounded text-[10px] font-bold bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/30 transition-colors';
        }
    });

    // Thêm class Active cho nút vừa bấm
    if (datasetIndex === 'all') {
        btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-slate-700 text-white border border-slate-600 transition-colors';
    } else {
        // Tùy màu mà đổi class active (làm đậm lên)
        if (datasetIndex === 0) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-amber-500 text-white border border-amber-600 transition-colors';
        if (datasetIndex === 1) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-emerald-500 text-white border border-emerald-600 transition-colors';
        if (datasetIndex === 2) btnElement.className = 'filter-btn active px-2.5 py-1 rounded text-[10px] font-bold bg-red-500 text-white border border-red-600 transition-colors';
    }

    // Logic điều khiển biểu đồ
    if (datasetIndex === 'all') {
        // Hiện tất cả
        for(let i = 0; i < 3; i++) {
            globalRevChart.setDatasetVisibility(i, true);
        }
    } else {
        // Chỉ hiện cái được chọn, ẩn 2 cái kia
        for(let i = 0; i < 3; i++) {
            globalRevChart.setDatasetVisibility(i, i === datasetIndex);
        }
    }
    globalRevChart.update();
}


window.addEventListener('load', function() {
    if (typeof Chart === 'undefined') return;

    // Cấu hình màu sắc (Vàng, Xanh, Đỏ)
    const colorPending = { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.15)' }; 
    const colorCompleted = { border: '#10b981', bg: 'rgba(16, 185, 129, 0.15)' }; 
    const colorRejected = { border: '#ef4444', bg: 'rgba(239, 68, 68, 0.15)' }; 

    // ===============================================
    // 1. BIỂU ĐỒ ĐƯỜNG CÓ ĐỘ CONG HÌNH SIN (TENSION)
    // ===============================================
    const revCtx = document.getElementById('revChart');
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

    // ===============================================
    // 2. BIỂU ĐỒ TRÒN (Chỉ để hiển thị tổng quan)
    // ===============================================
    const statusData = <?php echo json_encode(array_values($status_dist)); ?>;
    const statusCtx = document.getElementById('statusChart');
    
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Đang chờ', 'Đã hoàn thành', 'Bị hủy'],
            datasets: [{
                data: statusData,
                backgroundColor: [colorPending.border, colorCompleted.border, colorRejected.border],
                borderColor: [colorPending.border, colorCompleted.border, colorRejected.border],
                borderWidth: 1.5,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true, 
            maintainAspectRatio: false, 
            cutout: '70%',
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { color: '#94a3b8', font: { family: 'Quicksand', weight: '700', size: 11 }, padding: 15 },
                } 
            }
        }
    });
});
</script>

<?php include 'admin_layout_close.php'; ?>
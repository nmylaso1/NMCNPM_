<?php
session_start();

// Admin và nhân viên
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Xử lý bộ lọc
$status_filter = $_GET['status'] ?? '';
$search_query  = $_GET['search'] ?? '';
$start_date    = $_GET['start_date'] ?? '';
$end_date      = $_GET['end_date'] ?? '';

// Xây dựng câu truy vấn SQL
$where_clauses = ["1=1"];
if ($status_filter) {
    $where_clauses[] = "o.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_query) {
    $s = $conn->real_escape_string($search_query);
    $where_clauses[] = "(o.order_code LIKE '%$s%' OR a.full_name LIKE '%$s%' OR a.username LIKE '%$s%')";
}
if ($start_date) {
    $where_clauses[] = "o.created_at >= '" . $conn->real_escape_string($start_date) . " 00:00:00'";
}
if ($end_date) {
    $where_clauses[] = "o.created_at <= '" . $conn->real_escape_string($end_date) . " 23:59:59'";
}

$where_sql = implode(" AND ", $where_clauses);

// Thống kê nhanh
$stats = [
    'total_paid' => 0,
    'total_pending' => 0,
    'total_count' => 0
];

$stats_res = $conn->query("
    SELECT 
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_sum,
        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_sum,
        COUNT(*) as total_count
    FROM orders o
    JOIN accounts a ON o.user_id = a.id
    WHERE $where_sql
");
if ($stats_res) {
    $row = $stats_res->fetch_assoc();
    $stats['total_paid'] = (float)($row['paid_sum'] ?? 0);
    $stats['total_pending'] = (float)($row['pending_sum'] ?? 0);
    $stats['total_count'] = (int)($row['total_count'] ?? 0);
}

// Lấy danh sách giao dịch
$query = "
    SELECT o.*, a.full_name, a.username as student_id
    FROM orders o
    JOIN accounts a ON o.user_id = a.id
    WHERE $where_sql
    ORDER BY o.created_at DESC
";
$orders_res = $conn->query($query);
$orders = [];
while ($row = $orders_res->fetch_assoc()) {
    $orders[] = $row;
}

$current_page = 'payments';
$page_title   = 'Lịch sử thanh toán';

include 'admin_layout_open.php';
?>

<style>
    .filter-card { background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 20px; transition: all 0.3s; }
    .table-container { background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 20px; overflow: hidden; }
    
    .status-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:99px;font-size:0.75rem;font-weight:700; }
    .status-pending { background:rgba(245,158,11,0.1); color:#f59e0b; border: 1px solid rgba(245,158,11,0.2); }
    .status-paid { background:rgba(16,185,129,0.1); color:#10b981; border: 1px solid rgba(16,185,129,0.2); }
    .status-cooking { background:rgba(59,130,246,0.1); color:#3b82f6; border: 1px solid rgba(59,130,246,0.2); }
    .status-ready { background:rgba(139,92,246,0.1); color:#8b5cf6; border: 1px solid rgba(139,92,246,0.2); }
    .status-rejected { background:rgba(239,68,68,0.1); color:#ef4444; border: 1px solid rgba(239,68,68,0.2); }
    .status-cancelled { background:rgba(100,116,139,0.1); color:#64748b; border: 1px solid rgba(100,116,139,0.2); }

    .method-badge { font-size: 0.7rem; font-weight: 800; padding: 2px 8px; border-radius: 6px; text-transform: uppercase; }
    .method-payos { background: #005baa; color: white; }
    .method-direct { background: #64748b; color: white; }

    /* Table Row Styling - Moving the "gạch" up */
    #payments-table tbody tr { 
        transition: background 0.2s; 
        border-top: 1px solid var(--card-border); 
    }
    #payments-table tbody tr:first-child { 
        border-top: none; 
    }
    #payments-table tbody tr:hover { 
        background: rgba(217, 119, 6, 0.03); 
    }
    .dark #payments-table tbody tr:hover { 
        background: rgba(255,255,255,0.02); 
    }

    .btn-action { padding: 6px; border-radius: 8px; transition: all 0.2s; color: var(--text-muted); }
    .btn-action:hover { background: var(--nav-item-hover-bg); color: #d97706; }

    /* Custom scrollbar for table */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }

    /* Unified Premium Style for COLORS only */
    .premium-input-unified {
        background-color: #f9fafb !important;
        border: 1px solid #e5e7eb !important;
        color: #374151 !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        outline: none !important;
    }
    .premium-input-unified:hover {
        background-color: #f3f4f6 !important;
        border-color: #d1d5db !important;
    }
    .premium-input-unified:focus {
        background-color: #fff !important;
        border-color: #d97706 !important;
        box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.1) !important;
    }
    
    .dark .premium-input-unified {
        background-color: rgba(30, 41, 59, 0.5) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #f3f4f6 !important;
    }
</style>

<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="stat-card flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center">
                <i data-lucide="check-circle" class="w-6 h-6 text-emerald-500"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Đã thanh toán (Thực nhận)</p>
                <div class="text-2xl font-black theme-text-main"><?php echo number_format($stats['total_paid'], 0, ',', '.'); ?>đ</div>
            </div>
        </div>
        <div class="stat-card flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center">
                <i data-lucide="clock" class="w-6 h-6 text-amber-500"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Đang chờ xử lý</p>
                <div class="text-2xl font-black theme-text-main"><?php echo number_format($stats['total_pending'], 0, ',', '.'); ?>đ</div>
            </div>
        </div>
        <div class="stat-card flex items-center gap-5">
            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 flex items-center justify-center">
                <i data-lucide="bar-chart-3" class="w-6 h-6 text-blue-500"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Tổng số giao dịch</p>
                <div class="text-2xl font-black theme-text-main"><?php echo number_format($stats['total_count'], 0, ',', '.'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card p-6 shadow-sm mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-5 items-end">
            <div class="space-y-2">
                <label class="text-[11px] font-black text-slate-500 uppercase tracking-widest ml-1">Tìm kiếm</label>
                <div class="relative group">
                    <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-amber-500 transition-colors z-10 pointer-events-none"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars((string)($search_query ?? '')); ?>" 
                           placeholder="Mã đơn / Tên / MSSV..." 
                           class="premium-input-unified w-full h-[46px] pl-10 pr-4 rounded-2xl text-sm font-medium">
                </div>
            </div>
            
            <div class="space-y-2">
                <label class="text-[11px] font-black text-slate-500 uppercase tracking-widest ml-1">Trạng thái</label>
                <div class="relative group">
                    <select name="status" class="w-full h-[46px]">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                        <option value="cooking" <?php echo $status_filter === 'cooking' ? 'selected' : ''; ?>>Đang nấu</option>
                        <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Sẵn sàng</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Đã thanh toán</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Đã từ chối</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[11px] font-black text-slate-500 uppercase tracking-widest ml-1">Từ ngày</label>
                <div class="relative group">
                    <i data-lucide="calendar" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-amber-500 transition-colors pointer-events-none z-10"></i>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars((string)($start_date ?? '')); ?>" 
                           class="premium-input-unified w-full h-[46px] pl-10 pr-4 rounded-2xl text-sm font-medium cursor-pointer">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[11px] font-black text-slate-500 uppercase tracking-widest ml-1">Đến ngày</label>
                <div class="relative group">
                    <i data-lucide="calendar" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-amber-500 transition-colors pointer-events-none z-10"></i>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars((string)($end_date ?? '')); ?>" 
                           class="premium-input-unified w-full h-[46px] pl-10 pr-4 rounded-2xl text-sm font-medium cursor-pointer">
                </div>
            </div>

            <div class="flex gap-2 h-[46px]">
                <button type="submit" class="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-bold rounded-2xl text-sm transition-all shadow-lg shadow-amber-600/25 active:scale-95 flex items-center justify-center gap-2">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    Lọc dữ liệu
                </button>
                <button type="button" onclick="exportData()" class="w-12 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl transition-all shadow-lg shadow-emerald-500/25 active:scale-95 flex items-center justify-center" title="Xuất báo cáo">
                    <i data-lucide="download" class="w-5 h-5"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-[1000px]" id="payments-table">
                <thead>
                    <tr class="bg-slate-50 dark:bg-white/5 border-b border-slate-200 dark:border-white/10">
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Mã Giao Dịch</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Người dùng</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Thời gian</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Số tiền</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Phương thức</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest">Trạng thái</th>
                        <th class="px-6 py-4 text-[11px] font-black text-slate-500 uppercase tracking-widest text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-transparent">
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                                    <i data-lucide="database-zap" class="w-8 h-8 text-slate-400"></i>
                                </div>
                                <h3 class="font-extrabold text-slate-500">Không tìm thấy giao dịch nào</h3>
                                <p class="text-xs text-slate-400">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <?php
                        $status_cls = match($order['status']) {
                            'pending' => 'status-pending',
                            'cooking' => 'status-cooking',
                            'ready' => 'status-ready',
                            'paid' => 'status-paid',
                            'rejected' => 'status-rejected',
                            'cancelled' => 'status-cancelled',
                            default => ''
                        };
                        $status_txt = match($order['status']) {
                            'pending' => '🕒 Chờ xử lý',
                            'cooking' => '🔥 Đang nấu',
                            'ready' => '🔔 Sẵn sàng',
                            'paid' => '✅ Thành công',
                            'rejected' => '❌ Đã từ chối',
                            'cancelled' => '🚫 Đã hủy',
                            default => ($order['status'] ?: 'Chưa rõ')
                        };
                        $method_cls = $order['payment_method'] === 'payos' ? 'method-payos' : 'method-direct';
                    ?>
                    <tr>
                        <td class="px-6 py-4">
                            <span class="font-bold text-amber-600"><?php echo htmlspecialchars($order['order_code']); ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-bold text-sm theme-text-main"><?php echo htmlspecialchars((string)($order['full_name'] ?? 'Khách')); ?></span>
                                <span class="text-[10px] font-semibold text-slate-500">ID: <?php echo htmlspecialchars((string)($order['student_id'] ?: 'Chưa cập nhật')); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                            <?php echo date('H:i d/m/Y', strtotime($order['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-black text-sm theme-text-main"><?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="method-badge <?php echo $method_cls; ?>">
                                <?php echo $order['payment_method']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="status-badge <?php echo $status_cls; ?>">
                                <?php echo $status_txt; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)" 
                                        class="btn-action" title="Xem chi tiết">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                                <button onclick="editNote(<?php echo $order['id']; ?>, <?php echo htmlspecialchars(json_encode($order['note'])); ?>)" 
                                        class="btn-action" title="Ghi chú">
                                    <i data-lucide="sticky-note" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Chi Tiết -->
<div id="details-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeModal('details-modal')"></div>
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] w-full max-w-xl relative shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <!-- Header -->
            <div class="bg-gradient-to-r from-amber-500 to-amber-700 px-8 py-6 text-white relative">
                <h3 class="text-xl font-black mb-1">Chi tiết giao dịch</h3>
                <p class="text-amber-100/80 text-sm font-bold opacity-80" id="modal-order-code"></p>
                <button onclick="closeModal('details-modal')" class="absolute top-6 right-8 text-white/50 hover:text-white transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="p-8 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <!-- Info Section -->
                <div class="grid grid-cols-2 gap-6 pb-6 border-b border-slate-100 dark:border-white/5">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Người thanh toán</p>
                        <p class="font-bold text-slate-700 dark:text-slate-200" id="modal-user-name"></p>
                        <p class="text-xs text-slate-500" id="modal-student-id"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Phương thức</p>
                        <p class="font-bold text-slate-700 dark:text-slate-200" id="modal-payment-method"></p>
                        <p class="text-xs text-slate-500" id="modal-payment-time"></p>
                    </div>
                </div>

                <!-- Products -->
                <div>
                    <h4 class="text-sm font-black text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                        <i data-lucide="shopping-basket" class="w-4 h-4 text-amber-500"></i>
                        Vật phẩm trong đơn
                    </h4>
                    <div id="modal-items-list" class="space-y-2">
                        <!-- Items rendered here via JS -->
                    </div>
                </div>

                <!-- System / Admin Notes -->
                <div class="bg-slate-50 dark:bg-white/5 rounded-2xl p-4 border border-slate-100 dark:border-white/5">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="info" class="w-3.5 h-3.5"></i>
                        Thông tin hệ thống / Ghi chú
                    </h4>
                    <div id="modal-notes" class="text-xs text-slate-600 dark:text-slate-400 font-medium italic"></div>
                </div>
            </div>

            <div class="px-8 py-6 bg-slate-50 dark:bg-white/5 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                 <div class="text-center md:text-left">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tổng tiền</p>
                    <p class="text-2xl font-black text-amber-600" id="modal-total-amount"></p>
                </div>
                <button onclick="closeModal('details-modal')" class="px-6 py-2.5 bg-slate-200 dark:bg-white/10 text-slate-700 dark:text-white font-bold rounded-xl text-sm hover:bg-slate-300 transition">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

// --- Functions ---

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

async function viewDetails(order) {
    // Fill basic info
    document.getElementById('modal-order-code').innerText = '#' + order.order_code;
    document.getElementById('modal-user-name').innerText = order.full_name;
    document.getElementById('modal-student-id').innerText = 'MSSV: ' + order.student_id;
    document.getElementById('modal-payment-method').innerText = order.payment_method.toUpperCase();
    document.getElementById('modal-payment-time').innerText = order.created_at;
    document.getElementById('modal-total-amount').innerText = new Intl.NumberFormat('vi-VN').format(order.total_amount) + 'đ';
    
    // Process notes (separate System vs Admin)
    let noteHtml = order.note ? order.note.replace(/\n/g, '<br>') : 'Không có ghi chú nào.';
    document.getElementById('modal-notes').innerHTML = noteHtml;

    // Fetch items via API (simulated or simplified since we don't have a fetch_items API ready)
    // For now, let's fetch using a quick inline approach or assuming we might need it.
    // To keep it simple, I'll use a fetch request to a script we know exists or I'll create a small helper.
    const itemList = document.getElementById('modal-items-list');
    itemList.innerHTML = '<div class="text-center py-4 text-slate-400 text-xs">Đang tải danh sách...</div>';
    
    openModal('details-modal');

    try {
        const res = await fetch(`../api/get_order_items.php?order_id=${order.id}`);
        const data = await res.json();
        if (data.success) {
            itemList.innerHTML = '';
            data.items.forEach(item => {
                itemList.innerHTML += `
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-black/20 border border-slate-100 dark:border-white/5">
                        <div class="flex-1">
                            <p class="text-sm font-bold theme-text-main">${item.product_name}</p>
                            <p class="text-[10px] text-slate-500 font-semibold">Giá: ${new Intl.NumberFormat('vi-VN').format(item.unit_price)}đ</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-amber-600">x${item.quantity}</p>
                            <p class="text-xs font-black theme-text-main">${new Intl.NumberFormat('vi-VN').format(item.unit_price * item.quantity)}đ</p>
                        </div>
                    </div>
                `;
            });
        } else {
            itemList.innerHTML = '<div class="text-red-500 text-xs italic">Không lấy được danh sách món.</div>';
        }
    } catch(e) {
        itemList.innerHTML = '<div class="text-red-500 text-xs italic">Lỗi kết nối.</div>';
    }
}

async function editNote(orderId, currentNote) {
    const { value: text } = await Swal.fire({
        title: '<span style="font-family:Quicksand;font-weight:800;">Ghi chú giao dịch</span>',
        input: 'textarea',
        inputLabel: 'Thêm ghi chú xử lý lỗi hoặc thông tin đối soát',
        inputValue: currentNote,
        inputPlaceholder: 'Nhập ghi chú tại đây...',
        inputAttributes: { 'aria-label': 'Ghi chú' },
        showCancelButton: true,
        confirmButtonText: 'Lưu ghi chú',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#d97706',
        customClass: {
            container: 'font-quicksand',
            popup: 'rounded-3xl',
            input: 'rounded-xl text-sm'
        }
    });

    if (text !== undefined) {
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('note', text);

        try {
            const res = await fetch('../api/update_order_note.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thành công',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-2xl' }
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message, customClass: { popup: 'rounded-2xl' } });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Vui lòng thử lại.', customClass: { popup: 'rounded-2xl' } });
        }
    }
}

function exportData() {
    const table = document.getElementById('payments-table');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip "Hành động" column
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
            // Xử lý dấu phẩy để không làm vỡ CSV
            text = '"' + text.replace(/"/g, '""') + '"';
            row.push(text);
        }
        
        csv.push(row.join(','));
    }

    const csvFile = new Blob(["\ufeff" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const downloadLink = document.createElement("a");
    const date = new Date().toISOString().slice(0,10);
    
    downloadLink.download = `LichSuThanhToan_${date}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php include 'admin_layout_close.php'; ?>

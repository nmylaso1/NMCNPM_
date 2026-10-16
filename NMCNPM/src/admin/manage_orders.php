<?php
session_start();

// Admin và nhân viên
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php');
    exit;
}
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Giải phóng khóa session sớm để không chặn các tab khác của Admin và User
session_write_close();

require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

// Lấy thông tin page agent (nếu biến chưa được định nghĩa thì gán mảng rỗng để tránh lỗi)
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



// Tab filter
$tab = $_GET['tab'] ?? 'pending';
$allowed_tabs = ['pending', 'cooking', 'paid', 'rejected'];
if (!in_array($tab, $allowed_tabs)) $tab = 'pending';

// Đếm từng loại - Gộp lại thành 1 query để tối ưu tốc độ, tránh treo server
$counts = ['pending' => 0, 'cooking' => 0, 'paid' => 0, 'rejected' => 0];
$res_counts = $conn->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status");
if ($res_counts) {
    while ($rc = $res_counts->fetch_assoc()) {
        $st = $rc['status'];
        if (isset($counts[$st])) {
            $counts[$st] = (int)$rc['c'];
        }
    }
}

// Lấy đơn hàng theo tab
$tab_escaped = $conn->real_escape_string($tab);
$orders_res = $conn->query("
    SELECT o.*, 
           COALESCE(NULLIF(o.customer_name, ''), a.full_name, 'Khách vãng lai') as display_customer_name, 
           a.email
    FROM orders o
    LEFT JOIN accounts a ON a.id = o.user_id
    WHERE o.status = '$tab_escaped'
    ORDER BY o.created_at DESC
");
$orders = [];
while ($row = $orders_res->fetch_assoc()) $orders[] = $row;

// Lấy items của từng đơn
$order_items_map = [];
if (!empty($orders)) {
    $ids = implode(',', array_column($orders, 'id'));
    $items_res = $conn->query("SELECT order_id, product_name AS title, unit_price AS price, quantity FROM order_items WHERE order_id IN ($ids)");
    while ($item = $items_res->fetch_assoc()) {
        $order_items_map[$item['order_id']][] = $item;
    }
}
$current_page = 'orders';
$page_title   = 'Quản lý Đơn hàng';

include 'admin_layout_open.php';
?>
<style>
    /* Tabs */
    .tab-btn { padding: 8px 20px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: all 0.2s; border: 1px solid var(--card-border); }
    .tab-active { background: #d97706; color: white !important; box-shadow: 0 4px 12px rgba(217,119,6,0.25); border-color: transparent; }
    .tab-inactive { background: var(--nav-item-hover-bg); color: var(--text-muted); }
    .tab-inactive:hover { background: rgba(217,119,6,0.05); color: #d97706; }

    /* Orders */
    .order-card { background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 20px; overflow: hidden; transition: all 0.2s; }
    .order-card:hover { border-color: rgba(217,119,6,0.3); transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    .dark .order-card:hover { box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }

    /* Badges */
    .status-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:99px;font-size:0.7rem;font-weight:800; text-transform:uppercase; letter-spacing:0.025em; }
    .status-pending { background:rgba(245,158,11,0.1); color:#f59e0b; border: 1px solid rgba(245,158,11,0.2); }
    .status-cooking { background:rgba(59,130,246,0.1); color:#3b82f6; border: 1px solid rgba(59,130,246,0.2); }
    .status-paid { background:rgba(16,185,129,0.1); color:#10b981; border: 1px solid rgba(16,185,129,0.2); }
    .status-rejected { background:rgba(239,68,68,0.1); color:#ef4444; border: 1px solid rgba(239,68,68,0.2); }

    /* Actions - High Contrast */
    .btn-confirm { background: linear-gradient(135deg,#10b981,#059669); color:white; padding:10px 20px; border-radius:14px; font-weight:800; font-size:0.85rem; border:none; cursor:pointer; transition:all 0.2s; display:flex;align-items:center;gap:8px; box-shadow:0 10px 15px -3px rgba(16,185,129,0.3); }
    .btn-confirm:hover { transform:translateY(-2px); box-shadow:0 15px 20px -3px rgba(16,185,129,0.5); filter:brightness(1.1); }
    
    .btn-info { background: linear-gradient(135deg,#3b82f6,#2563eb); color:white; padding:10px 20px; border-radius:14px; font-weight:800; font-size:0.85rem; border:none; cursor:pointer; transition:all 0.2s; display:flex;align-items:center;gap:8px; box-shadow:0 10px 15px -3px rgba(59,130,246,0.3); }
    .btn-info:hover { transform:translateY(-2px); box-shadow:0 15px 20px -3px rgba(59,130,246,0.5); filter:brightness(1.1); }

    .btn-purple { background: linear-gradient(135deg,#a855f7,#7e22ce); color:white; padding:10px 20px; border-radius:14px; font-weight:800; font-size:0.85rem; border:none; cursor:pointer; transition:all 0.2s; display:flex;align-items:center;gap:8px; box-shadow:0 10px 15px -3px rgba(168,85,247,0.3); }
    .btn-purple:hover { transform:translateY(-2px); box-shadow:0 15px 20px -3px rgba(168,85,247,0.5); filter:brightness(1.1); }

    .btn-reject { background: linear-gradient(135deg,#f43f5e,#e11d48); color:white; padding:10px 20px; border-radius:14px; font-weight:800; font-size:0.85rem; border:none; cursor:pointer; transition:all 0.2s; display:flex;align-items:center;gap:8px; box-shadow:0 10px 15px -3px rgba(244,63,94,0.3); }
    .btn-reject:hover { transform:translateY(-2px); box-shadow:0 15px 20px -3px rgba(244,63,94,0.5); filter:brightness(1.1); }

    .empty-box { background: var(--bg-card); border: 2px dashed var(--card-border); border-radius:24px; padding:60px 40px; text-align:center; }
</style>

    <!-- Page title -->
    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-3 items-start">
        <div>
            <h2 class="text-xl font-extrabold text-white">Xử lý Đơn hàng</h2>
            <p class="text-xs text-slate-500 mt-1">Duyệt và kiểm tra các giao dịch thanh toán từ khách hàng</p>
        </div>
        <div class="text-xs font-bold text-amber-500 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-xl shrink-0">
            👑 Admin: <?php echo htmlspecialchars($admin_name); ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-7">
        <div class="content-card p-3 text-center border-t-2 border-amber-600">
            <div class="text-xl font-black text-amber-500"><?php echo $counts['pending']; ?></div>
            <div class="text-[9px] uppercase font-bold text-slate-500 mt-1 tracking-widest">⏳ Chờ xác nhận</div>
        </div>
        <div class="content-card p-3 text-center border-t-2 border-blue-600">
            <div class="text-xl font-black text-blue-500"><?php echo $counts['cooking']; ?></div>
            <div class="text-[9px] uppercase font-bold text-slate-500 mt-1 tracking-widest">🔥 Đang nấu</div>
        </div>
        <div class="content-card p-3 text-center border-t-2 border-emerald-600">
            <div class="text-xl font-black text-emerald-500"><?php echo $counts['paid']; ?></div>
            <div class="text-[9px] uppercase font-bold text-slate-500 mt-1 tracking-widest">✅ Hoàn thành</div>
        </div>
        <div class="content-card p-3 text-center border-t-2 border-rose-600">
            <div class="text-xl font-black text-rose-500"><?php echo $counts['rejected']; ?></div>
            <div class="text-[9px] uppercase font-bold text-slate-500 mt-1 tracking-widest">❌ Từ chối</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-2 mb-6 flex-wrap">
        <a href="?tab=pending" class="tab-btn <?php echo $tab === 'pending' ? 'tab-active' : 'tab-inactive'; ?> flex items-center gap-2">
            ⏳ Chờ xác nhận
            <?php if ($counts['pending'] > 0): ?>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px]
                         <?php echo $tab==='pending'?'bg-white/30 text-white':'bg-amber-500/20 text-amber-500'; ?>">
                <?php echo $counts['pending']; ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="?tab=cooking" class="tab-btn <?php echo $tab === 'cooking' ? 'tab-active' : 'tab-inactive'; ?> flex items-center gap-2">
            🔥 Đang nấu
            <?php if ($counts['cooking'] > 0): ?>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px]
                         <?php echo $tab==='cooking'?'bg-white/30 text-white':'bg-blue-500/20 text-blue-500'; ?>">
                <?php echo $counts['cooking']; ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="?tab=paid" class="tab-btn <?php echo $tab === 'paid' ? 'tab-active' : 'tab-inactive'; ?> flex items-center gap-2">
            ✅ Hoàn thành
        </a>
        <a href="?tab=rejected" class="tab-btn <?php echo $tab === 'rejected' ? 'tab-active' : 'tab-inactive'; ?> flex items-center gap-2">
            ❌ Đã từ chối
        </a>
    </div>

    <!-- Order list -->
    <?php if (empty($orders)): ?>
    <div class="empty-box">
        <div class="text-5xl mb-4">
            <?php echo $tab==='pending'?'🎉':(in_array($tab, ['cooking','ready'])?'⏳':($tab==='paid'?'📋':'🗑️')); ?>
        </div>
        <h3 class="text-lg font-extrabold text-gray-700 mb-2">
            <?php echo $tab==='pending'?'Không có đơn nào đang chờ':(in_array($tab, ['cooking','ready'])?'Chưa có đơn hàng nào ở bước này':($tab==='paid'?'Chưa có đơn nào hoàn thành':'Chưa có đơn nào bị từ chối')); ?>
        </h3>
        <p class="text-gray-400 font-medium text-sm">Các đơn hàng sẽ xuất hiện ở đây khi có thay đổi trạng thái.</p>
    </div>

    <?php else: ?>
    <div class="space-y-4" id="orders-container">
        <?php foreach ($orders as $order): ?>
        <?php
            $items = $order_items_map[$order['id']] ?? [];
            $status_cls = match($order['status']) {
                'pending' => 'status-pending',
                'cooking' => 'status-cooking',
                'ready' => 'status-ready',
                'paid' => 'status-paid',
                'rejected' => 'status-rejected',
                default => ''
            };
            $status_txt = match($order['status']) {
                'pending' => '🕒 Chờ xác nhận',
                'cooking' => '🔥 Đang nấu',
                'paid' => '✅ Hoàn thành',
                'rejected' => '❌ Đã từ chối',
                default => $order['status']
            };
        ?>
        <div class="order-card" id="order-card-<?php echo $order['id']; ?>">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div>
                        <div class="font-extrabold text-white text-base tracking-wide">
                            <?php echo htmlspecialchars($order['order_code']); ?>
                        </div>
                        <div class="text-xs text-slate-400 font-semibold mt-0.5 flex items-center flex-wrap gap-2">
                            <span>👤 <strong class="text-slate-200"><?php echo htmlspecialchars($order['display_customer_name'] ?? 'Khách hàng'); ?></strong></span>
                            <?php if (!empty($order['customer_phone'])): ?>
                            <span class="text-amber-400 font-bold">📞 <?php echo htmlspecialchars($order['customer_phone']); ?></span>
                            <?php endif; ?>
                            <span class="text-slate-500">🕒 <?php echo date('H:i d/m/Y', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="text-[11px] font-semibold mt-1.5 flex items-center flex-wrap gap-2">
                            <?php $otype = $order['order_type'] ?? 'dine_in'; ?>
                            <?php if ($otype === 'dine_in'): ?>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold flex items-center gap-1">
                                    🍽️ Tại bàn: <?php echo htmlspecialchars(!empty($order['table_number']) ? $order['table_number'] : 'Bàn tự chọn'); ?>
                                </span>
                            <?php elseif ($otype === 'delivery'): ?>
                                <span class="px-2.5 py-0.5 rounded-full bg-orange-500/20 text-orange-300 border border-orange-500/30 font-bold flex items-center gap-1">
                                    🛵 Giao hàng: <?php echo htmlspecialchars(!empty($order['delivery_room']) ? $order['delivery_room'] : 'Giao tận nơi'); ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 font-bold flex items-center gap-1">
                                    🛍️ Mang về
                                </span>
                            <?php endif; ?>
                            <span class="text-slate-400">Phí giao: <span class="text-amber-400 font-bold"><?php echo ((float)($order['shipping_fee'] ?? 0) > 0) ? number_format((float)$order['shipping_fee'], 0, ',', '.') . 'đ' : '0đ'; ?></span></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="status-badge <?php echo $status_cls; ?>"><?php echo $status_txt; ?></span>
                    <span class="font-extrabold text-amber-600 text-lg">
                        <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ
                    </span>
                </div>
            </div>

            <!-- Notes (User & System) -->
            <?php if (!empty($order['note'])): ?>
            <div class="px-6 py-3 bg-white/5 border-b border-white/5">
                <div class="flex items-start gap-2.5">
                    <i data-lucide="sticky-note" class="w-4 h-4 text-amber-500 mt-1 shrink-0"></i>
                    <div class="text-xs">
                        <?php
                        $raw_note = (string)$order['note'];
                        $parts = explode('--- [Hệ thống] ---', $raw_note);
                        $user_note = trim($parts[0]);
                        $system_info = isset($parts[1]) ? trim($parts[1]) : '';
                        ?>
                        <?php if ($user_note): ?>
                            <div class="font-bold text-slate-300 mb-1">Ghi chú của khách:</div>
                            <div class="text-slate-400 mb-2 italic">"<?php echo nl2br(htmlspecialchars($user_note)); ?>"</div>
                        <?php endif; ?>
                        <?php if ($system_info): ?>
                            <div class="text-[10px] text-slate-500 font-mono bg-black/20 p-1.5 rounded border border-white/5">
                                ⚙️ Hệ thống: <?php echo htmlspecialchars($system_info); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Items -->
            <div class="px-6 py-4">
                <div class="text-xs font-bold text-slate-500 uppercase mb-3">Danh sách món</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($items as $item): ?>
                    <div class="flex items-center justify-between bg-white/5 rounded-xl px-4 py-2.5">
                        <span class="font-semibold text-slate-300 text-sm">
                            <?php echo htmlspecialchars($item['title']); ?>
                            <span class="text-slate-500 ml-1">x<?php echo $item['quantity']; ?></span>
                        </span>
                        <span class="font-bold text-amber-400 text-sm">
                            <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>đ
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <?php if ($order['status'] === 'pending'): ?>
            <div class="px-6 py-4 border-t border-white/5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:justify-end bg-black/20">
                <button class="btn-reject justify-center" onclick="handleOrder(<?php echo $order['id']; ?>, 'reject', '<?php echo addslashes($order['order_code']); ?>')">
                    <i data-lucide="x-circle" class="w-4 h-4"></i> Từ chối
                </button>
                <button class="btn-info justify-center" onclick="handleOrder(<?php echo $order['id']; ?>, 'cooking', '<?php echo addslashes($order['order_code']); ?>')">
                    <i data-lucide="chef-hat" class="w-4 h-4"></i> Nhận nấu
                </button>
            </div>
            <?php elseif ($order['status'] === 'cooking'): ?>
            <div class="px-6 py-4 border-t border-white/5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:justify-end bg-black/20">
                <button class="btn-confirm justify-center" onclick="handleOrder(<?php echo $order['id']; ?>, 'ready_and_confirm', '<?php echo addslashes($order['order_code']); ?>')">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Báo xong & Hoàn tất
                </button>
            </div>
            <?php elseif ($order['status'] === 'ready'): ?>
            <div class="px-6 py-4 border-t border-white/5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:justify-between bg-black/20">
                <div class="text-[10px] text-purple-400 font-bold uppercase text-center sm:text-left mb-2 sm:mb-0">Đã báo khách lấy món</div>
                <button class="btn-confirm justify-center" onclick="handleOrder(<?php echo $order['id']; ?>, 'confirm', '<?php echo addslashes($order['order_code']); ?>')">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Khách đã lấy món & Hoàn tất
                </button>
            </div>
            <?php elseif ($order['confirmed_at']): ?>
            <div class="px-6 py-3 border-t border-white/5 text-[10px] text-slate-500 font-bold uppercase tracking-wider bg-black/10">
                Xử lý lúc: <?php echo date('H:i d/m/Y', strtotime($order['confirmed_at'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
lucide.createIcons();

async function handleOrder(orderId, action, orderCode) {
    let title, text, btnColor, btnText;
    
    if (action === 'cooking') {
        title = '🔥 Bắt đầu nấu?';
        text = 'Đơn sẽ chuyển sang trạng thái đang nấu.';
        btnColor = '#3b82f6';
        btnText = 'Nhận nấu';
    } else if (action === 'ready') {
        title = '🔔 Báo món đã xong?';
        text = 'Khách sẽ nhận được thông báo món đã sẵn sàng.';
        btnColor = '#8b5cf6';
        btnText = 'Báo đã xong';
    } else if (action === 'ready_and_confirm') {
        title = '✅ Báo xong & Hoàn tất?';
        text = 'Khách sẽ được thông báo đã xong và đơn hàng chuyển sang trạng thái hoàn tất.';
        btnColor = '#10b981';
        btnText = 'Xác nhận';
    } else if (action === 'confirm') {
        title = '✅ Đã hoàn tất & Thanh toán?';
        text = 'Xác nhận khách đã nhận món và kết thúc đơn hàng.';
        btnColor = '#10b981';
        btnText = 'Hoàn tất';
    } else {
        title = '❌ Từ chối đơn?';
        text = 'Khách sẽ được thông báo đơn bị hủy.';
        btnColor = '#e11d48';
        btnText = 'Từ chối';
    }

    const result = await Swal.fire({
        title: `<span style="font-family:'Quicksand',sans-serif;font-weight:800;">${title}</span>`,
        html: `<span style="font-family:'Quicksand',sans-serif;font-size:0.95rem;color:#6b7280;">
                 Đơn hàng: <b style="color:#d97706;">${orderCode}</b><br>${text}
               </span>`,
        showCancelButton: true,
        confirmButtonColor: btnColor,
        cancelButtonColor: '#e5e7eb',
        confirmButtonText: `<span style="font-family:'Quicksand',sans-serif;font-weight:800;">${btnText}</span>`,
        cancelButtonText: `<span style="font-family:'Quicksand',sans-serif;font-weight:700;color:#374151;">Huỷ</span>`,
        customClass: { popup: 'rounded-[1.5rem]' }
    });

    if (!result.isConfirmed) return;

    try {
        let res, data;
        if (action === 'ready_and_confirm') {
            const fdReady = new FormData();
            fdReady.append('order_id', orderId);
            fdReady.append('action', 'ready');
            await fetch('../api/confirm_order.php', { method: 'POST', body: fdReady });
            
            const fdConfirm = new FormData();
            fdConfirm.append('order_id', orderId);
            fdConfirm.append('action', 'confirm');
            res = await fetch('../api/confirm_order.php', { method: 'POST', body: fdConfirm });
            data = await res.json();
            
            if (data.success) {
                data.message = 'Đã báo xong & hoàn tất đơn hàng!';
            }
        } else {
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('action', action);
            res = await fetch('../api/confirm_order.php', { method: 'POST', body: fd });
            data = await res.json();
        }

        if (data.success) {
            // Animate remove card
            const card = document.getElementById('order-card-' + orderId);
            if (card) {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(30px) scale(0.97)';
                setTimeout(() => {
                    card.remove();
                    // Check if no more cards
                    const container = document.getElementById('orders-container');
                    if (container && container.children.length === 0) {
                        container.innerHTML = `
                            <div class="empty-box" style="background:white;border-radius:24px;padding:60px 40px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.04);">
                                <div style="font-size:3rem;margin-bottom:12px;">🎉</div>
                                <h3 style="font-size:1.1rem;font-weight:800;color:#374151;margin-bottom:6px;">Không còn đơn nào đang chờ!</h3>
                                <p style="color:#9ca3af;font-size:0.875rem;font-weight:600;">Tất cả đơn đã được xử lý.</p>
                            </div>`;
                    }
                }, 420);
            }

            Swal.fire({
                toast: true, position: 'bottom-end', timer: 3000, showConfirmButton: false,
                icon: (action !== 'reject') ? 'success' : 'warning',
                title: `<span style="font-family:'Quicksand',sans-serif;font-weight:700;">${data.message}</span>`,
                background: '#fff7ed',
                color: '#7c2d12',
                iconColor: (action !== 'reject') ? '#d97706' : '#c2410c',
                timerProgressBar: true,
                customClass: { popup: 'rounded-2xl' }
            });

            // Cập nhật badge đếm trang
            setTimeout(() => location.reload(), 3200);
        } else {
            Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message, customClass: { popup: 'rounded-2xl' } });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Vui lòng thử lại.', customClass: { popup: 'rounded-2xl' } });
    }
}
</script>
<?php include 'admin_layout_close.php'; ?>

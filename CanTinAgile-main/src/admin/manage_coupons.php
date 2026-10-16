<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php'); exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}
require_once '../includes/config.php';

// TỰ ĐỘNG FIX LỖI DATABASE: Cho phép start_date và end_date được phép để trống (NULL)
$conn->query("ALTER TABLE coupons MODIFY start_date DATETIME NULL DEFAULT NULL, MODIFY end_date DATETIME NULL DEFAULT NULL");

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'coupons';
$page_title   = 'Quản lý Mã giảm giá';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code                = strtoupper(trim($_POST['code'] ?? ''));
        $discount_type       = in_array($_POST['discount_type'] ?? '', ['percentage','fixed']) ? $_POST['discount_type'] : 'percentage';
        $discount_value      = floatval($_POST['discount_value'] ?? 0);
        $min_order_value     = floatval($_POST['min_order_value'] ?? 0);
        $max_discount_amount = floatval($_POST['max_discount_amount'] ?? 0);
        $usage_limit         = intval($_POST['usage_limit'] ?? 0);
        $user_limit          = intval($_POST['user_limit'] ?? 0);
        $start_date          = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date            = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if ($code === '' || $discount_value <= 0) {
            $msg = 'Vui lòng nhập mã và mức giảm giá hợp lệ.'; $msgType = 'error';
        } else {
            // Kiểm tra trùng mã code trước khi thêm
            $check = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
            $check->bind_param('s', $code);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = 'Mã giảm giá này đã tồn tại! Vui lòng chọn mã khác.'; $msgType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code,discount_type,discount_value,min_order_value,max_discount_amount,usage_limit,user_limit,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssdddiiss', $code, $discount_type, $discount_value, $min_order_value, $max_discount_amount, $usage_limit, $user_limit, $start_date, $end_date);
                if ($stmt->execute()) {
                    $msg = 'Thêm mã giảm giá thành công!'; $msgType = 'success';
                } else {
                    $msg = 'Lỗi: ' . $stmt->error; $msgType = 'error';
                }
            }
        }
    } elseif ($action === 'edit') {
        $id                  = intval($_POST['id'] ?? 0);
        $code                = strtoupper(trim($_POST['code'] ?? ''));
        $discount_type       = in_array($_POST['discount_type'] ?? '', ['percentage','fixed']) ? $_POST['discount_type'] : 'percentage';
        $discount_value      = floatval($_POST['discount_value'] ?? 0);
        $min_order_value     = floatval($_POST['min_order_value'] ?? 0);
        $max_discount_amount = floatval($_POST['max_discount_amount'] ?? 0);
        $usage_limit         = intval($_POST['usage_limit'] ?? 0);
        $user_limit          = intval($_POST['user_limit'] ?? 0);
        $start_date          = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date            = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if ($id > 0 && $code !== '' && $discount_value > 0) {
            // Kiểm tra xem mã code mới sửa có bị trùng với một mã KHÁC đang có không
            $check = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
            $check->bind_param('si', $code, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $msg = 'Mã giảm giá này đã bị trùng với một mã khác!'; $msgType = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_value=?, max_discount_amount=?, usage_limit=?, user_limit=?, start_date=?, end_date=? WHERE id=?");
                $stmt->bind_param('ssdddiissi', $code, $discount_type, $discount_value, $min_order_value, $max_discount_amount, $usage_limit, $user_limit, $start_date, $end_date, $id);
                if ($stmt->execute()) {
                    $msg = 'Cập nhật mã giảm giá thành công!'; $msgType = 'success';
                } else {
                    $msg = 'Lỗi cập nhật: ' . $stmt->error; $msgType = 'error';
                }
            }
        } else {
            $msg = 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.'; $msgType = 'error';
        }
    } elseif ($action === 'toggle_active') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE coupons SET is_active = 1 - is_active WHERE id=?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Đã thay đổi trạng thái mã giảm giá.'; $msgType = 'success';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM coupons WHERE id=?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Đã xóa mã giảm giá.'; $msgType = 'success';
            }
        }
    }
}

// Fetch all coupons
$coupons = [];
$res = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $coupons[] = $r;

// Stats
$now        = date('Y-m-d');
$total      = count($coupons);
$active     = 0; $expired = 0; $out_of_uses = 0; $total_used = 0;
foreach ($coupons as $c) {
    $total_used += (int)$c['used_count'];
    if (!$c['is_active']) continue;
    if (($c['end_date'] && $c['end_date'] < $now)) { $expired++; continue; }
    if ($c['usage_limit'] > 0 && $c['used_count'] >= $c['usage_limit']) { $out_of_uses++; continue; }
    $active++;
}

include 'admin_layout_open.php';
?>

<style>
    /* Coupon Card Grid */
    .coupon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; }

    /* Coupon Card */
    .coupon-card {
        background: var(--bg-card);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .coupon-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.08); }
    .dark .coupon-card:hover { box-shadow: 0 16px 40px rgba(0,0,0,0.3); }
    .coupon-card.inactive { opacity: 0.55; }

    /* Coupon torn effect */
    .coupon-tear {
        position: relative;
        display: flex;
        align-items: stretch;
    }
    .coupon-left {
        flex: 1;
        padding: 20px;
        border-right: 2px dashed var(--card-border);
    }
    .coupon-right {
        width: 88px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px 12px;
        background: rgba(217, 119, 6, 0.03);
    }
    .dark .coupon-right { background: rgba(255,255,255,0.02); }

    /* Hole cutouts on dashed border */
    .coupon-tear::before,
    .coupon-tear::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        background: var(--bg-body);
        border-radius: 50%;
        left: calc(100% - 88px - 11px);
        z-index: 2;
    }
    .coupon-tear::before { top: -10px; }
    .coupon-tear::after  { bottom: -10px; }

    /* Badge styles */
    .badge-status { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 0.68rem; font-weight: 800; }
    .badge-active    { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
    .badge-inactive  { background: rgba(100,116,139,0.1); color: #64748b; border: 1px solid rgba(100,116,139,0.2); }
    .badge-expired   { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
    .badge-exhausted { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
    .badge-upcoming  { background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.2); }

    /* Progress bar */
    .usage-bar { width: 100%; height: 4px; background: rgba(0,0,0,0.08); border-radius: 99px; overflow: hidden; }
    .dark .usage-bar { background: rgba(255,255,255,0.08); }
    .usage-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #d97706, #f59e0b); transition: width 0.6s ease; }

    /* Action buttons */
    .btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; cursor: pointer; border: none; background: transparent; }
    .btn-icon-toggle-on  { background: rgba(245,158,11,0.1); color: #f59e0b; }
    .btn-icon-toggle-off { background: rgba(16,185,129,0.1); color: #10b981; }
    .btn-icon-edit   { background: rgba(59,130,246,0.1); color: #3b82f6; }
    .btn-icon-delete { background: rgba(239,68,68,0.1); color: #ef4444; }
    .btn-icon:hover { transform: scale(1.1); filter: brightness(1.1); }

    /* Modal overlay - FIXED SPECIFICITY ISSUE */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px); z-index: 60; align-items: center; justify-content: center; padding: 16px; }
    .modal-overlay:not(.hidden) { display: flex; }
    .modal-overlay.hidden { display: none !important; }
    
    .modal-box { background: var(--bg-card); border: 1px solid var(--card-border); border-radius: 28px; width: 100%; max-width: 680px; overflow: hidden; box-shadow: 0 40px 80px rgba(0,0,0,0.2); animation: modalIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.92) translateY(16px); } to { opacity: 1; transform: scale(1) translateY(0); } }

    /* Form input */
    .form-input {
        width: 100%; padding: 10px 14px; border-radius: 12px;
        background: rgba(0,0,0,0.04); border: 1.5px solid var(--card-border);
        color: var(--text-main); outline: none; font-weight: 600;
        transition: all 0.2s; font-size: 0.875rem; font-family: 'Quicksand', sans-serif;
    }
    .dark .form-input { background: rgba(255,255,255,0.05); }
    .form-input:focus { border-color: #d97706; box-shadow: 0 0 0 4px rgba(217,119,6,0.12); background: var(--bg-card); }
    
    /* Đã thêm padding-left: 36px để tránh chữ đè lên icon */
    .form-input.is-code { 
        text-transform: uppercase; 
        font-weight: 900; 
        letter-spacing: 2px; 
        font-size: 1rem; 
        color: #d97706; 
        padding-left: 36px !important; 
    }
    
    .form-label { font-size: 0.68rem; font-weight: 900; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 6px; }

    /* Tabs for filter */
    .tab-btn { padding: 7px 18px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; transition: all 0.2s; cursor: pointer; border: none; }
    .tab-btn.active { background: linear-gradient(135deg, #d97706, #b45309); color: #fff; box-shadow: 0 4px 12px rgba(217,119,6,0.3); }
    .tab-btn:not(.active) { background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--card-border); }
    .tab-btn:not(.active):hover { background: rgba(217,119,6,0.08); color: #d97706; }

    /* Empty state */
    .empty-state { padding: 60px 20px; text-align: center; }

    /* Stat pill */
    .stat-pill { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 16px; background: var(--bg-card); border: 1px solid var(--card-border); }
</style>

<?php if ($msg): ?>
<div id="flash-msg" class="mb-6 flex items-start gap-3 p-4 rounded-2xl animate-in slide-in-from-top duration-300 <?php echo $msgType==='success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success' ? 'check-circle-2' : 'alert-triangle'; ?>" class="w-5 h-5 mt-0.5 shrink-0"></i>
    <p class="text-sm font-bold"><?php echo htmlspecialchars($msg); ?></p>
    <button onclick="this.parentElement.remove()" class="ml-auto text-current opacity-50 hover:opacity-100"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-7">
    <div>
        <h2 class="text-xl font-extrabold theme-text-main flex items-center gap-2">
            <i data-lucide="ticket" class="w-6 h-6 text-amber-500"></i>
            Mã giảm giá
        </h2>
        <p class="text-xs text-slate-500 mt-1">Tạo và quản lý mã khuyến mãi cho khách hàng</p>
    </div>
    <button id="btn-open-add" onclick="openAddModal()"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-bold rounded-2xl transition shadow-lg shadow-amber-500/30 active:scale-95 text-sm">
        <i data-lucide="plus" class="w-4 h-4"></i> Tạo mã mới
    </button>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-7">
    <div class="stat-pill">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0">
            <i data-lucide="ticket" class="w-5 h-5 text-amber-500"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Tổng mã</p>
            <p class="text-xl font-black theme-text-main"><?php echo $total; ?></p>
        </div>
    </div>
    <div class="stat-pill">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Còn hiệu lực</p>
            <p class="text-xl font-black theme-text-main"><?php echo $active; ?></p>
        </div>
    </div>
    <div class="stat-pill">
        <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center shrink-0">
            <i data-lucide="clock-x" class="w-5 h-5 text-red-500"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Hết hạn / Hết lượt</p>
            <p class="text-xl font-black theme-text-main"><?php echo $expired + $out_of_uses; ?></p>
        </div>
    </div>
    <div class="stat-pill">
        <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center shrink-0">
            <i data-lucide="trending-up" class="w-5 h-5 text-blue-500"></i>
        </div>
        <div>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Tổng lượt dùng</p>
            <p class="text-xl font-black theme-text-main"><?php echo number_format($total_used); ?></p>
        </div>
    </div>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6">
    <button class="tab-btn active" onclick="filterCoupons('all', this)">
        Tất cả <span class="ml-1 text-[10px] opacity-70">(<?php echo $total; ?>)</span>
    </button>
    <button class="tab-btn" onclick="filterCoupons('active', this)">
        ✅ Hiệu lực <span class="ml-1 text-[10px] opacity-70">(<?php echo $active; ?>)</span>
    </button>
    <button class="tab-btn" onclick="filterCoupons('expired', this)">
        ⏰ Hết hạn
    </button>
    <button class="tab-btn" onclick="filterCoupons('exhausted', this)">
        🔴 Hết lượt
    </button>
    <button class="tab-btn" onclick="filterCoupons('inactive', this)">
        ⏸️ Đã tắt
    </button>
</div>

<div id="coupon-grid" class="coupon-grid">
<?php if (empty($coupons)): ?>
    <div class="col-span-full empty-state">
        <div class="w-20 h-20 rounded-full bg-amber-500/10 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="ticket-slash" class="w-10 h-10 text-amber-400"></i>
        </div>
        <h3 class="text-lg font-extrabold theme-text-main mb-1">Chưa có mã giảm giá nào</h3>
        <p class="text-sm text-slate-500">Nhấn "Tạo mã mới" để bắt đầu tạo mã khuyến mãi đầu tiên</p>
    </div>
<?php else: foreach ($coupons as $c): 
    $today      = date('Y-m-d');
    $pct        = $c['usage_limit'] > 0 ? min(100, round($c['used_count'] / $c['usage_limit'] * 100)) : 0;
    $statusKey  = 'active';
    $statusLabel = 'Còn hiệu lực';
    $statusClass = 'badge-active';

    if (!$c['is_active']) {
        $statusKey = 'inactive'; $statusLabel = '⏸ Đã tắt'; $statusClass = 'badge-inactive';
    } elseif ($c['end_date'] && $c['end_date'] < $today) {
        $statusKey = 'expired'; $statusLabel = '⏰ Hết hạn'; $statusClass = 'badge-expired';
    } elseif ($c['usage_limit'] > 0 && $c['used_count'] >= $c['usage_limit']) {
        $statusKey = 'exhausted'; $statusLabel = '🔴 Hết lượt'; $statusClass = 'badge-exhausted';
    } elseif ($c['start_date'] && $c['start_date'] > $today) {
        $statusKey = 'upcoming'; $statusLabel = '🔵 Chưa đến hạn'; $statusClass = 'badge-upcoming';
    }

    $discountText = $c['discount_type'] === 'percentage'
        ? $c['discount_value'] . '%'
        : number_format($c['discount_value'], 0, ',', '.') . 'đ';
?>
    <div class="coupon-card <?php echo !$c['is_active'] ? 'inactive' : ''; ?>" data-status="<?php echo $statusKey; ?>">
        <div class="coupon-tear">
            <div class="coupon-left">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Mã giảm giá</p>
                        <p class="text-lg font-black text-amber-500 tracking-widest cursor-pointer hover:text-amber-600 transition group/code" 
                           onclick="copyCode('<?php echo htmlspecialchars($c['code']); ?>', this)" 
                           title="Nhấn để copy">
                            <?php echo htmlspecialchars($c['code']); ?>
                            <i data-lucide="copy" class="w-3.5 h-3.5 inline-block opacity-0 group-hover/code:opacity-100 transition-opacity ml-1 -mt-1 text-amber-500"></i>
                        </p>
                    </div>
                    <span class="badge-status <?php echo $statusClass; ?> mt-1 shrink-0"><?php echo $statusLabel; ?></span>
                </div>

                <div class="flex items-center gap-2 mb-4 p-3 rounded-xl bg-amber-500/8 border border-amber-500/15">
                    <i data-lucide="<?php echo $c['discount_type'] === 'percentage' ? 'percent' : 'banknote'; ?>" class="w-5 h-5 text-amber-500 shrink-0"></i>
                    <div>
                        <p class="text-xl font-black text-amber-600 dark:text-amber-400 leading-none"><?php echo $discountText; ?></p>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">
                            <?php echo $c['discount_type'] === 'percentage' ? 'Giảm theo phần trăm' : 'Giảm số tiền cố định'; ?>
                            <?php if ($c['discount_type'] === 'percentage' && $c['max_discount_amount'] > 0): ?>
                                · Tối đa <?php echo number_format($c['max_discount_amount'], 0, ',', '.'); ?>đ
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs mb-4">
                    <div class="flex items-center gap-1.5 text-slate-500">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                        <span class="font-semibold">
                            <?php echo $c['min_order_value'] > 0 ? 'Đơn ≥ ' . number_format($c['min_order_value'], 0, ',', '.') . 'đ' : 'Không giới hạn đơn tối thiểu'; ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-500">
                        <i data-lucide="user-check" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                        <span class="font-semibold">
                            <?php echo $c['user_limit'] > 0 ? $c['user_limit'] . ' lần/người' : 'Không giới hạn/người'; ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-500 col-span-2">
                        <i data-lucide="calendar-range" class="w-3.5 h-3.5 shrink-0 text-slate-400"></i>
                        <span class="font-semibold">
                            <?php
                            $sd = $c['start_date'] ? date('d/m/Y', strtotime($c['start_date'])) : '—';
                            $ed = $c['end_date']   ? date('d/m/Y', strtotime($c['end_date']))   : '∞';
                            echo "$sd → $ed";
                            ?>
                        </span>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Lượt sử dụng</span>
                        <span class="text-[11px] font-black theme-text-main">
                            <?php echo $c['used_count']; ?><?php echo $c['usage_limit'] > 0 ? '/' . $c['usage_limit'] : ' lượt'; ?>
                        </span>
                    </div>
                    <?php if ($c['usage_limit'] > 0): ?>
                    <div class="usage-bar">
                        <div class="usage-bar-fill" style="width:<?php echo $pct; ?>%; <?php echo $pct >= 90 ? 'background: linear-gradient(90deg,#ef4444,#f87171)' : ''; ?>"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="coupon-right">
                <form method="POST">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                    <button type="submit" title="<?php echo $c['is_active'] ? 'Tắt mã này' : 'Bật mã này'; ?>"
                            class="btn-icon <?php echo $c['is_active'] ? 'btn-icon-toggle-on' : 'btn-icon-toggle-off'; ?>">
                        <i data-lucide="<?php echo $c['is_active'] ? 'toggle-right' : 'toggle-left'; ?>" class="w-4 h-4"></i>
                    </button>
                </form>

                <button onclick='openEditModal(<?php echo json_encode($c); ?>)'
                        title="Chỉnh sửa" class="btn-icon btn-icon-edit">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                </button>

                <form method="POST" onsubmit="return confirmDelete('<?php echo addslashes($c['code']); ?>')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                    <button type="submit" title="Xóa mã" class="btn-icon btn-icon-delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </form>

                <div class="mt-2 text-center">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest leading-none">Tạo lúc</p>
                    <p class="text-[10px] font-bold theme-text-main mt-0.5"><?php echo date('d/m/y', strtotime($c['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<div id="add-modal" class="modal-overlay hidden" onclick="if(event.target===this) closeAddModal()">
    <div class="modal-box max-h-[92vh] overflow-y-auto">
        <div class="px-7 py-5 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                <i data-lucide="plus-circle" class="w-5 h-5 text-amber-500"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-base font-extrabold theme-text-main">Tạo mã giảm giá mới</h3>
                <p class="text-xs text-slate-500">Thiết lập code, mức giảm và điều kiện áp dụng</p>
            </div>
            <button onclick="closeAddModal()" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-white/10 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="p-7 space-y-5">
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã giảm giá <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i data-lucide="ticket" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-amber-500 pointer-events-none"></i>
                        <input type="text" name="code" id="add-code" required placeholder="VD: SUMMER30"
                               class="form-input is-code pl-10" oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>
                <div>
                    <label class="form-label">Loại giảm giá</label>
                    <select name="discount_type" id="add-type" class="form-input" onchange="updateDiscountLabel('add')">
                        <option value="percentage">📊 Phần trăm (%)</option>
                        <option value="fixed">💵 Số tiền cố định (VNĐ)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label" id="add-value-label">Mức giảm (%) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="discount_value" required placeholder="20"
                           class="form-input">
                </div>
                <div id="add-max-discount-wrap">
                    <label class="form-label">Giảm tối đa (VNĐ) <span class="text-xs opacity-60">(0 = vô hạn)</span></label>
                    <input type="number" min="0" name="max_discount_amount" value="0" class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giá trị đơn tối thiểu (VNĐ) <span class="text-xs opacity-60">(0 = không yêu cầu)</span></label>
                    <input type="number" min="0" name="min_order_value" value="0" class="form-input">
                </div>
                <div>
                    <label class="form-label">Giới hạn tổng lượt dùng <span class="text-xs opacity-60">(0 = vô hạn)</span></label>
                    <input type="number" min="0" name="usage_limit" value="0" class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Giới hạn / mỗi khách <span class="text-xs opacity-60">(0 = vô hạn)</span></label>
                    <input type="number" min="0" name="user_limit" value="0" class="form-input">
                </div>
                <div>
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" class="form-input">
                </div>
                <div>
                    <label class="form-label">Ngày kết thúc</label>
                    <input type="date" name="end_date" class="form-input">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddModal()"
                    class="flex-1 py-3 rounded-2xl font-bold text-sm border border-slate-200 dark:border-white/10 theme-text-muted hover:bg-slate-100 dark:hover:bg-white/5 transition">
                    Hủy bỏ
                </button>
                <button type="submit"
                    class="flex-1 py-3 rounded-2xl font-bold text-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-lg shadow-amber-500/30 transition active:scale-95">
                    <i data-lucide="plus" class="w-4 h-4 inline mr-1"></i> Tạo mã giảm giá
                </button>
            </div>
        </form>
    </div>
</div>

<div id="edit-modal" class="modal-overlay hidden" onclick="if(event.target===this) closeEditModal()">
    <div class="modal-box max-h-[92vh] overflow-y-auto">
        <div class="px-7 py-5 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                <i data-lucide="pencil" class="w-5 h-5 text-blue-500"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-base font-extrabold theme-text-main">Chỉnh sửa mã <span id="edit-header-code" class="text-amber-500"></span></h3>
                <p class="text-xs text-slate-500">Cập nhật thông tin và điều kiện áp dụng</p>
            </div>
            <button onclick="closeEditModal()" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-white/10 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="p-7 space-y-5">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã giảm giá <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <i data-lucide="ticket" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-amber-500 pointer-events-none"></i>
                        <input type="text" name="code" id="edit-code" required class="form-input is-code pl-10" oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>
                <div>
                    <label class="form-label">Loại giảm giá</label>
                    <select name="discount_type" id="edit-type" class="form-input" onchange="updateDiscountLabel('edit')">
                        <option value="percentage">📊 Phần trăm (%)</option>
                        <option value="fixed">💵 Số tiền cố định (VNĐ)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label" id="edit-value-label">Mức giảm (%) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="discount_value" id="edit-value" required class="form-input">
                </div>
                <div id="edit-max-discount-wrap">
                    <label class="form-label">Giảm tối đa (VNĐ) <span class="text-xs opacity-60">(0 = vô hạn)</span></label>
                    <input type="number" min="0" name="max_discount_amount" id="edit-max-discount" class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giá trị đơn tối thiểu (VNĐ)</label>
                    <input type="number" min="0" name="min_order_value" id="edit-min-order" class="form-input">
                </div>
                <div>
                    <label class="form-label">Giới hạn tổng lượt dùng <span class="text-xs opacity-60">(0 = vô hạn)</span></label>
                    <input type="number" min="0" name="usage_limit" id="edit-usage-limit" class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Giới hạn / mỗi khách</label>
                    <input type="number" min="0" name="user_limit" id="edit-user-limit" class="form-input">
                </div>
                <div>
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" id="edit-start" class="form-input">
                </div>
                <div>
                    <label class="form-label">Ngày kết thúc</label>
                    <input type="date" name="end_date" id="edit-end" class="form-input">
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 rounded-xl bg-amber-500/6 border border-amber-500/12 text-xs font-semibold text-slate-500">
                <i data-lucide="info" class="w-4 h-4 text-amber-500 shrink-0"></i>
                Đã sử dụng: <span id="edit-used-count" class="font-black text-amber-600 dark:text-amber-400 ml-1"></span> lượt
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEditModal()"
                    class="flex-1 py-3 rounded-2xl font-bold text-sm border border-slate-200 dark:border-white/10 theme-text-muted hover:bg-slate-100 dark:hover:bg-white/5 transition">
                    Hủy bỏ
                </button>
                <button type="submit"
                    class="flex-1 py-3 rounded-2xl font-bold text-sm bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white shadow-lg shadow-blue-500/30 transition active:scale-95">
                    <i data-lucide="save" class="w-4 h-4 inline mr-1"></i> Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

// ── Copy Code ───────────────────────────────────────────────────
function copyCode(code, element) {
    navigator.clipboard.writeText(code).then(() => {
        // Đổi icon thành dấu tick để báo hiệu copy thành công
        const icon = element.querySelector('i');
        const oldIcon = icon.getAttribute('data-lucide');
        icon.setAttribute('data-lucide', 'check');
        icon.classList.remove('opacity-0', 'text-amber-500');
        icon.classList.add('opacity-100', 'text-emerald-500');
        lucide.createIcons();

        // Trả lại icon copy sau 1.5 giây
        setTimeout(() => {
            icon.setAttribute('data-lucide', oldIcon);
            icon.classList.add('opacity-0', 'text-amber-500');
            icon.classList.remove('opacity-100', 'text-emerald-500');
            lucide.createIcons();
        }, 1500);
    }).catch(err => {
        alert('Không thể copy mã: ' + err);
    });
}

// ── Filter ──────────────────────────────────────────────────────
function filterCoupons(status, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.coupon-card').forEach(card => {
        const s = card.dataset.status;
        card.style.display = (status === 'all' || s === status) ? '' : 'none';
    });

    // Show empty if none visible
    const grid = document.getElementById('coupon-grid');
    const visible = [...document.querySelectorAll('.coupon-card')].filter(c => c.style.display !== 'none');
    const emptyEl = document.getElementById('filter-empty');
    if (visible.length === 0) {
        if (!emptyEl) {
            const div = document.createElement('div');
            div.id = 'filter-empty';
            div.className = 'col-span-full empty-state';
            div.innerHTML = `<div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-white/5 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i></div>
                <h3 class="font-extrabold text-slate-500">Không tìm thấy mã nào</h3>
                <p class="text-xs text-slate-400 mt-1">Thử chọn bộ lọc khác</p>`;
            grid.appendChild(div);
            lucide.createIcons();
        }
    } else {
        emptyEl?.remove();
    }
}

// ── Add Modal ───────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('add-modal').classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('add-code').focus();
        if (typeof initPremiumInputs === 'function') initPremiumInputs();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }, 50);
}

function closeAddModal() {
    document.getElementById('add-modal').classList.add('hidden');
}

// ── Edit Modal ──────────────────────────────────────────────────
function openEditModal(coupon) {
    document.getElementById('edit-id').value          = coupon.id;
    document.getElementById('edit-code').value        = coupon.code;
    document.getElementById('edit-header-code').innerText = coupon.code;
    document.getElementById('edit-type').value        = coupon.discount_type;
    document.getElementById('edit-value').value       = coupon.discount_value;
    document.getElementById('edit-max-discount').value = coupon.max_discount_amount;
    document.getElementById('edit-min-order').value   = coupon.min_order_value;
    document.getElementById('edit-usage-limit').value  = coupon.usage_limit;
    document.getElementById('edit-user-limit').value   = coupon.user_limit;
    document.getElementById('edit-start').value        = coupon.start_date || '';
    document.getElementById('edit-end').value          = coupon.end_date || '';
    document.getElementById('edit-used-count').innerText = coupon.used_count;

    updateDiscountLabel('edit');

    document.getElementById('edit-modal').classList.remove('hidden');
    setTimeout(() => {
        if (typeof initPremiumInputs === 'function') initPremiumInputs();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }, 50);
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}

// ── Dynamic label ───────────────────────────────────────────────
function updateDiscountLabel(prefix) {
    const type = document.getElementById(prefix + '-type').value;
    const label = document.getElementById(prefix + '-value-label');
    const maxWrap = document.getElementById(prefix + '-max-discount-wrap');
    if (type === 'percentage') {
        label.innerHTML = 'Mức giảm (%) <span class="text-red-500">*</span>';
        maxWrap && (maxWrap.style.display = '');
    } else {
        label.innerHTML = 'Số tiền giảm (VNĐ) <span class="text-red-500">*</span>';
        maxWrap && (maxWrap.style.display = 'none');
    }
}

// ── Delete confirm ──────────────────────────────────────────────
function confirmDelete(code) {
    return confirm(`Bạn có chắc muốn xóa mã «${code}»?\nHành động này không thể hoàn tác.`);
}

// ── Close on Escape ─────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
    }
});
</script>

<?php include 'admin_layout_close.php'; ?>
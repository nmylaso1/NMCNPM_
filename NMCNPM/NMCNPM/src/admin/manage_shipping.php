<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php');
    exit;
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';

$admin_name = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'shipping';
$page_title = 'Quản lý Phí giao hàng';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['policy_name'] ?? '');
        $threshold = floatval($_POST['threshold_1'] ?? 100000);
        $fee = floatval($_POST['fee_1'] ?? 15000);
        if ($name === '') {
            $msg = 'Vui lòng nhập tên chính sách.';
            $msgType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO shipping_policies (policy_name, threshold_1, fee_1) VALUES (?,?,?)");
            $stmt->bind_param('sdd', $name, $threshold, $fee);
            if ($stmt->execute()) {
                $msg = 'Thêm chính sách thành công!';
                $msgType = 'success';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'shipping_add', $name, "Thêm chính sách phí ship: '$name' (Phí: " . number_format($fee, 0, ',', '.') . "đ)");
            } else {
                $msg = 'Lỗi: ' . $conn->error;
                $msgType = 'error';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['policy_name'] ?? '');
        $threshold = floatval($_POST['threshold_1'] ?? 100000);
        $fee = floatval($_POST['fee_1'] ?? 15000);
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare("UPDATE shipping_policies SET policy_name=?, threshold_1=?, fee_1=? WHERE id=?");
            $stmt->bind_param('sddi', $name, $threshold, $fee, $id);
            if ($stmt->execute()) {
                $msg = 'Cập nhật thành công!';
                $msgType = 'success';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'shipping_edit', $name, "Cập nhật chính sách phí ship: '$name' (Phí: " . number_format($fee, 0, ',', '.') . "đ)");
            } else {
                $msg = 'Lỗi: ' . $conn->error;
                $msgType = 'error';
            }
        }
    } elseif ($action === 'set_active') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pol = $conn->query("SELECT policy_name FROM shipping_policies WHERE id = $id")->fetch_assoc();
            $conn->query("UPDATE shipping_policies SET is_active = 0"); // deactivate all
            $conn->query("UPDATE shipping_policies SET is_active = 1 WHERE id = $id"); // activate one
            $msg = 'Đã kích hoạt chính sách.';
            $msgType = 'success';
            $pName = $pol['policy_name'] ?? "Chính sách #$id";
            log_system_action($conn, $_SESSION['user_id'] ?? 0, 'shipping_active', $pName, "Kích hoạt chính sách vận chuyển: '$pName'");
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM shipping_policies WHERE id=$id");
            $msg = 'Đã xóa.';
            $msgType = 'success';
        }
    }
}


$policies = [];
$res = $conn->query("SELECT * FROM shipping_policies ORDER BY is_active DESC, id ASC");
if ($res)
    while ($r = $res->fetch_assoc())
        $policies[] = $r;

include __DIR__ . '/admin_layout_open.php';
?>

<?php if ($msg): ?>
    <div
        class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
        <i data-lucide="<?php echo $msgType === 'success' ? 'check-circle' : 'alert-triangle'; ?>"
            class="w-4 h-4 mt-0.5 shrink-0"></i>
        <p class="text-sm font-semibold">
            <?php echo htmlspecialchars($msg); ?>
        </p>
    </div>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-xl font-extrabold text-white">Chính sách Phí giao hàng</h2>
        <p class="text-xs text-slate-500 mt-1">Cấu hình ngưỡng miễn phí ship và mức phí cơ bản</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-8">
    <!-- Add Form -->
    <div class="content-card p-6">
        <h3 class="font-bold text-white mb-4 flex items-center gap-2"><i data-lucide="plus-circle"
                class="w-4 h-4 text-amber-500"></i> Thêm chính sách mới</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Tên chính sách <span
                        class="text-red-500">*</span></label>
                <input type="text" name="policy_name" required placeholder="VD: Chính sách mặc định"
                    class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 outline-none focus:border-amber-500/50 font-medium">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Ngưỡng miễn phí
                        (VNĐ)</label>
                    <input type="number" min="0" name="threshold_1" value="100000"
                        class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white outline-none focus:border-amber-500/50 font-medium">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Phí ship nếu dưới
                        ngưỡng</label>
                    <input type="number" min="0" name="fee_1" value="15000"
                        class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white outline-none focus:border-amber-500/50 font-medium">
                </div>
            </div>
            <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-700 text-white font-bold rounded-xl hover:from-amber-600 hover:to-amber-800 transition shadow-lg">
                Thêm chính sách
            </button>
        </form>
    </div>

    <!-- Info -->
    <div class="content-card p-6 flex flex-col justify-center">
        <div class="text-center space-y-3">
            <div class="w-16 h-16 bg-amber-500/10 rounded-2xl flex items-center justify-center mx-auto">
                <i data-lucide="truck" class="w-8 h-8 text-amber-500"></i>
            </div>
            <h3 class="text-lg font-bold text-white">Cách hoạt động</h3>
            <p class="text-slate-400 text-sm leading-relaxed">
                Nếu tổng đơn hàng <b class="text-amber-400">dưới ngưỡng</b> → thu phí giao hàng.<br>
                Nếu tổng đơn hàng <b class="text-emerald-400">từ ngưỡng trở lên</b> → miễn phí ship.<br><br>
                Chỉ có <b>1 chính sách</b> được kích hoạt tại một thời điểm.
            </p>
        </div>
    </div>
</div>

<!-- Policies List -->
<div class="content-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-white/5 text-slate-500 text-left text-xs uppercase tracking-wider">
                    <th class="px-5 py-4">#</th>
                    <th class="px-5 py-4">Tên chính sách</th>
                    <th class="px-5 py-4">Ngưỡng miễn phí</th>
                    <th class="px-5 py-4">Phí ship</th>
                    <th class="px-5 py-4">Trạng thái</th>
                    <th class="px-5 py-4 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (empty($policies)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-slate-500 italic">Chưa có chính sách nào.</td>
                    </tr>
                <?php else:
                    foreach ($policies as $p): ?>
                        <tr class="hover:bg-white/5 transition <?php echo $p['is_active'] ? 'bg-amber-500/5' : ''; ?>">
                            <td class="px-5 py-3.5 text-slate-600 font-mono text-xs">#
                                <?php echo $p['id']; ?>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-white">
                                <?php echo htmlspecialchars($p['policy_name']); ?>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-amber-400">
                                <?php echo number_format($p['threshold_1'], 0, ',', '.'); ?>đ
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-300">
                                <?php echo number_format($p['fee_1'], 0, ',', '.'); ?>đ
                            </td>
                            <td class="px-5 py-3.5">
                                <?php if ($p['is_active']): ?>
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">✅
                                        Đang dùng</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">Không
                                        dùng</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if (!$p['is_active']): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="set_active">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button
                                                class="text-xs px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 font-semibold transition">Kích
                                                hoạt</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Xóa chính sách này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button
                                            class="text-xs px-3 py-1.5 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 font-semibold transition">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/admin_layout_close.php'; ?>
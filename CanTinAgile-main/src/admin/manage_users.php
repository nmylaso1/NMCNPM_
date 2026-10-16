<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php'); exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}
$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'users';
$page_title   = 'Quản lý Tài khoản';

require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';


// Add username column if missing
$colUser = $conn->query("SHOW COLUMNS FROM accounts LIKE 'username'");
if ($colUser && $colUser->num_rows === 0) {
    $conn->query("ALTER TABLE accounts ADD COLUMN username VARCHAR(100) UNIQUE AFTER full_name");
}

// Add is_locked column if missing
$colLock = $conn->query("SHOW COLUMNS FROM accounts LIKE 'is_locked'");
if ($colLock && $colLock->num_rows === 0) {
    $conn->query("ALTER TABLE accounts ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0");
}

$msg = ''; $msgType = '';

// ─── POST actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = intval($_POST['id'] ?? 0);

    if ($action === 'set_role' && $id > 0) {
        $role = in_array($_POST['role'] ?? '', ['admin','staff','user']) ? $_POST['role'] : 'user';
        $uTarget = $conn->query("SELECT full_name, username FROM accounts WHERE id=$id")->fetch_assoc();
        $conn->query("UPDATE accounts SET role='$role' WHERE id=$id");
        $msg = 'Đã cập nhật vai trò.'; $msgType = 'success';
        $uName = $uTarget['full_name'] ?: ($uTarget['username'] ?? "ID #$id");
        $roleVi = match($role) { 'admin' => 'Admin', 'staff' => 'Nhân viên', default => 'Người dùng' };
        log_system_action($conn, $_SESSION['user_id'] ?? 0, 'user_role', $uName, "Phân quyền tài khoản '$uName' thành: $roleVi");
    } elseif ($action === 'toggle_lock' && $id > 0) {
        $uTarget = $conn->query("SELECT full_name, username, is_locked FROM accounts WHERE id=$id")->fetch_assoc();
        $conn->query("UPDATE accounts SET is_locked = 1 - is_locked WHERE id=$id");
        $msg = 'Đã thay đổi trạng thái khóa.'; $msgType = 'success';
        if ($uTarget) {
            $uName = $uTarget['full_name'] ?: ($uTarget['username'] ?? "ID #$id");
            $stLock = ($uTarget['is_locked'] == 0) ? 'Khóa tài khoản' : 'Mở khóa tài khoản';
            log_system_action($conn, $_SESSION['user_id'] ?? 0, 'user_lock', $uName, "$stLock '$uName'");
        }
    } elseif ($action === 'reset_password' && $id > 0) {
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE accounts SET password=? WHERE id=?");
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $msg = 'Đã đặt lại mật khẩu về 123456.'; $msgType = 'success';
        $uTarget = $conn->query("SELECT full_name, username FROM accounts WHERE id=$id")->fetch_assoc();
        $uName = $uTarget['full_name'] ?: ($uTarget['username'] ?? "ID #$id");
        log_system_action($conn, $_SESSION['user_id'] ?? 0, 'user_reset_pass', $uName, "Đặt lại mật khẩu về 123456 cho '$uName'");
    } elseif ($action === 'delete' && $id > 0) {
        $uTarget = $conn->query("SELECT full_name, username FROM accounts WHERE id=$id")->fetch_assoc();
        $uName = $uTarget['full_name'] ?: ($uTarget['username'] ?? "ID #$id");
        $conn->query("DELETE FROM accounts WHERE id=$id");
        $msg = 'Đã xóa tài khoản.'; $msgType = 'success';
        log_system_action($conn, $_SESSION['user_id'] ?? 0, 'user_delete', $uName, "Xóa tài khoản '$uName'");
    }
}

// ─── Fetch users ───────────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$filter  = $_GET['role'] ?? 'all';
$where   = "1=1";
if ($search !== '') $where .= " AND (full_name LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%')";
if ($filter !== 'all') $where .= " AND role='$filter'";
$users = [];
$res = $conn->query("SELECT * FROM accounts WHERE $where ORDER BY role ASC, created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $users[] = $r;

include 'admin_layout_open.php';
?>

<!-- Status msg -->
<?php if ($msg): ?>
<div class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success'?'check-circle':'alert-triangle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="flex flex-wrap gap-3 mb-6">
    <form method="GET" class="flex gap-2 flex-wrap">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm tên, username..." class="px-4 py-2 text-sm rounded-xl bg-slate-800 border border-white/10 text-white placeholder-slate-500 outline-none focus:border-amber-500/50">
        <select name="role" class="px-4 py-2 text-sm rounded-xl bg-slate-800 border border-white/10 text-white outline-none">
            <option value="all" <?php echo $filter==='all'?'selected':''; ?>>Tất cả vai trò</option>
            <option value="admin" <?php echo $filter==='admin'?'selected':''; ?>>Admin</option>
            <option value="staff" <?php echo $filter==='staff'?'selected':''; ?>>Nhân viên</option>
            <option value="user" <?php echo $filter==='user'?'selected':''; ?>>Người dùng</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-xl transition">Lọc</button>
    </form>
    <div class="ml-auto text-xs text-slate-500 self-center"><?php echo count($users); ?> tài khoản</div>
</div>

<!-- Table -->
<div class="content-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-white/5 text-slate-500 text-left text-xs uppercase tracking-wider">
                    <th class="px-5 py-4">#</th>
                    <th class="px-5 py-4">Thông tin</th>
                    <th class="px-5 py-4">Vai trò</th>
                    <th class="px-5 py-4">Trạng thái</th>
                    <th class="px-5 py-4">Ngày tạo</th>
                    <th class="px-5 py-4 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
            <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center py-12 text-slate-500 italic">Không tìm thấy tài khoản nào.</td></tr>
            <?php else: foreach ($users as $u):
                $roleBadge = match($u['role']) {
                    'admin' => 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
                    'staff' => 'bg-sky-500/15 text-sky-400 border border-sky-500/30',
                    default => 'bg-slate-700 text-slate-300 border border-slate-600',
                };
                $roleTxt = match($u['role']) { 'admin'=>'Admin', 'staff'=>'Nhân viên', default=>'Người dùng' };
            ?>
            <tr class="hover:bg-white/3 transition <?php echo $u['is_locked']?'opacity-50':''; ?>">
                <td class="px-5 py-3.5 text-slate-600 font-mono text-xs">#<?php echo $u['id']; ?></td>
                <td class="px-5 py-3.5">
                    <p class="font-bold text-white"><?php echo htmlspecialchars($u['full_name'] ?: '—'); ?></p>
                    <p class="text-xs text-slate-500">
                        <?php if (!empty($u['username'])): ?>
                            <span class="text-amber-400 font-bold">@<?php echo htmlspecialchars($u['username']); ?></span> · 
                        <?php endif; ?>
                        <?php echo htmlspecialchars($u['email'] ?? ''); ?>
                    </p>
                </td>
                <td class="px-5 py-3.5">
                    <form method="POST" class="inline flex items-center gap-2">
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        <select name="role" onchange="this.form.submit()" class="text-xs rounded-xl px-2 py-1 bg-slate-800 border border-white/10 text-slate-200 outline-none">
                            <?php foreach (['admin'=>'Admin','staff'=>'Nhân viên','user'=>'Người dùng'] as $rv=>$rl): ?>
                            <option value="<?php echo $rv; ?>" <?php echo $u['role']===$rv?'selected':''; ?>><?php echo $rl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td class="px-5 py-3.5">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $u['is_locked']?'bg-red-500/15 text-red-400':'bg-emerald-500/15 text-emerald-400'; ?>">
                        <?php echo $u['is_locked']?'Bị khóa':'Hoạt động'; ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 text-xs text-slate-500"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                <td class="px-5 py-3.5 text-right w-48">
                    <div class="flex items-center justify-end gap-1.5 flex-nowrap whitespace-nowrap">
                        <!-- Toggle Lock -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_lock">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button class="text-[10px] px-2.5 py-1 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500/20 font-bold transition whitespace-nowrap"><?php echo $u['is_locked'] ? 'Mở khóa' : 'Khóa'; ?></button>
                        </form>
                        <!-- Reset -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button class="text-[10px] px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 font-bold transition whitespace-nowrap">Reset PW</button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" class="inline" onsubmit="return confirm('Xóa tài khoản này?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button class="text-[10px] px-2.5 py-1 rounded-lg bg-slate-500/10 text-slate-600 hover:bg-red-500/10 hover:text-red-500 font-bold transition whitespace-nowrap">Xóa</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_layout_close.php'; ?>

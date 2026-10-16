<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'categories';
$page_title   = 'Quản lý Danh mục';

$msg = ''; $msgType = '';

// ─── POST Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($slug === '') $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)));
        if ($name === '') {
            $msg = 'Vui lòng nhập tên danh mục.'; $msgType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories_products (name, slug) VALUES (?, ?)");
            $stmt->bind_param('ss', $name, $slug);
            if ($stmt->execute()) {
                $msg = 'Thêm danh mục thành công!'; $msgType = 'success';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'category_add', $name, "Thêm danh mục mới: '$name'");
            } else {
                $msg = 'Lỗi: ' . $conn->error; $msgType = 'error';
            }
        }
    } elseif ($action === 'edit') {
        $id   = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare("UPDATE categories_products SET name=?, slug=? WHERE id=?");
            $stmt->bind_param('ssi', $name, $slug, $id);
            if ($stmt->execute()) {
                $msg = 'Cập nhật thành công!'; $msgType = 'success';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'category_edit', $name, "Cập nhật danh mục #$id thành '$name'");
            } else {
                $msg = 'Lỗi: ' . $conn->error; $msgType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $cat = $conn->query("SELECT name FROM categories_products WHERE id=$id")->fetch_assoc();
            $cName = $cat['name'] ?? "Danh mục #$id";
            $conn->query("DELETE FROM categories_products WHERE id=$id");
            $msg = 'Đã xóa danh mục.'; $msgType = 'success';
            log_system_action($conn, $_SESSION['user_id'] ?? 0, 'category_delete', $cName, "Xóa danh mục: '$cName'");
        }
    }
}

// ─── Fetch ─────────────────────────────────────────────────────────────────
$categories = [];
$res = $conn->query("SELECT cp.*, (SELECT COUNT(*) FROM products WHERE category_id = cp.id) AS product_count FROM categories_products cp ORDER BY cp.id ASC");
if ($res) while ($r = $res->fetch_assoc()) $categories[] = $r;

include 'admin_layout_open.php';
?>

<!-- Status msg -->
<?php if ($msg): ?>
<div class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success'?'check-circle':'alert-triangle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-xl font-extrabold text-white">Danh mục món ăn</h2>
        <p class="text-xs text-slate-500 mt-1">Quản lý hệ thống phân loại sản phẩm</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    <!-- Add Form -->
    <div class="content-card p-6">
        <h3 class="font-bold text-white mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-amber-500"></i> Thêm danh mục mới</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Tên danh mục <span class="text-red-500">*</span></label>
                <input type="text" name="name" required placeholder="VD: Cơm - Mì - Phở" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 outline-none focus:border-amber-500/50 font-medium">
            </div>
            <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Slug (tùy chọn)</label>
                <input type="text" name="slug" placeholder="com-mi-pho" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 outline-none focus:border-amber-500/50 font-medium">
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-700 text-white font-bold rounded-xl hover:from-amber-600 hover:to-amber-800 transition shadow-lg">
                Thêm danh mục
            </button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="content-card overflow-hidden">
        <div class="px-5 py-4 border-b border-white/5">
            <h3 class="font-bold text-white flex items-center gap-2"><i data-lucide="list" class="w-4 h-4 text-amber-500"></i> Danh sách (<?php echo count($categories); ?>)</h3>
        </div>
        <?php if (empty($categories)): ?>
        <div class="p-10 text-center text-slate-500 italic">Chưa có danh mục nào.</div>
        <?php else: ?>
        <div class="divide-y divide-white/5">
            <?php foreach ($categories as $cat): ?>
            <div class="px-5 py-3.5 flex items-center justify-between hover:bg-white/5 transition" id="cat-<?php echo $cat['id']; ?>">
                <div>
                    <p class="font-bold text-white"><?php echo htmlspecialchars($cat['name']); ?></p>
                    <p class="text-[11px] text-slate-500">
                        <?php echo htmlspecialchars($cat['slug'] ?? '—'); ?> · <span class="text-amber-400"><?php echo $cat['product_count']; ?> sản phẩm</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="editCat(<?php echo htmlspecialchars(json_encode($cat, JSON_UNESCAPED_UNICODE)); ?>)" class="text-xs px-3 py-1.5 rounded-xl bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 font-semibold transition">Sửa</button>
                    <form method="POST" class="inline" onsubmit="return confirm('Xóa danh mục «<?php echo addslashes($cat['name']); ?>»?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button class="text-xs px-3 py-1.5 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 font-semibold transition">Xóa</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-cat-modal" class="fixed inset-0 z-[9999] bg-black/50 hidden items-center justify-center p-4">
    <div class="bg-[#1e293b] w-full max-w-md rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
            <h3 class="font-bold text-white">Sửa danh mục</h3>
            <button onclick="closeCatModal()" class="text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="ecat-id">
            <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Tên</label>
                <input type="text" name="name" id="ecat-name" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white outline-none focus:border-amber-500/50 font-medium">
            </div>
            <div class="space-y-1.5">
                <label class="text-[11px] font-bold text-amber-500 uppercase tracking-wider">Slug</label>
                <input type="text" name="slug" id="ecat-slug" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white outline-none focus:border-amber-500/50 font-medium">
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition">Lưu thay đổi</button>
        </form>
    </div>
</div>

<script>
function editCat(data) {
    document.getElementById('ecat-id').value = data.id;
    document.getElementById('ecat-name').value = data.name;
    document.getElementById('ecat-slug').value = data.slug || '';
    const m = document.getElementById('edit-cat-modal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function closeCatModal() {
    const m = document.getElementById('edit-cat-modal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('edit-cat-modal').addEventListener('click', function(e) { if (e.target === this) closeCatModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCatModal(); });
</script>

<?php include 'admin_layout_close.php'; ?>

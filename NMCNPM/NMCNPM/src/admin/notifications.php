<?php
// Bật hiển thị lỗi để tránh trắng trang khi có sự cố
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
require_once __DIR__ . '/../includes/config.php';

// =====================================================================
// TỰ ĐỘNG NÂNG CẤP DATABASE CỰC MẠNH (AUTO-MIGRATE)
// Quét và tự động thêm các cột còn thiếu vào bảng announcements
// =====================================================================
$columns_to_add = [
    'start_time' => 'DATETIME NULL',
    'end_time' => 'DATETIME NULL',
    'display_order' => 'INT DEFAULT 0',
    'show_title' => 'TINYINT(1) DEFAULT 1',
    'show_content' => 'TINYINT(1) DEFAULT 1',
    'show_image' => 'TINYINT(1) DEFAULT 1'
];

foreach ($columns_to_add as $col_name => $col_type) {
    try {
        // Thử select cột đó xem có tồn tại không
        $conn->query("SELECT $col_name FROM announcements LIMIT 1");
    } catch (Exception $e) {
        // Nếu lỗi (nghĩa là chưa có cột) -> Tự động thêm cột
        $conn->query("ALTER TABLE announcements ADD COLUMN $col_name $col_type");
    }
}
// =====================================================================

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'notifications';
$page_title   = 'Quản lý Thông báo / Banner';

$msg = ''; $msgType = '';

// ─── Handle Actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $show_t  = isset($_POST['show_title']) ? 1 : 0;
        $show_c  = isset($_POST['show_content']) ? 1 : 0;
        $show_i  = isset($_POST['show_image']) ? 1 : 0;
        $id      = intval($_POST['id'] ?? 0);
        $order   = intval($_POST['display_order'] ?? 0);
        
        $start_t = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_t   = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

        // Xử lý Hình ảnh (Ưu tiên file upload, nếu không có thì lấy link URL)
        $image = trim($_POST['image_url'] ?? '');
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image_file']['name']);
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename)) {
                $image = 'uploads/' . $filename;
            }
        }

        if ($title === '') {
            $msg = 'Vui lòng nhập tiêu đề.'; $msgType = 'error';
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, image_url, is_active, show_title, show_content, show_image, start_time, end_time, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssiiiissi', $title, $content, $image, $active, $show_t, $show_c, $show_i, $start_t, $end_t, $order);
                if ($stmt->execute()) { $msg = 'Thêm thông báo thành công!'; $msgType = 'success'; }
                else { $msg = 'Lỗi: ' . $conn->error; $msgType = 'error'; }
            } else {
                if ($image !== '' || !empty($_FILES['image_file']['name'])) {
                    $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, image_url=?, is_active=?, show_title=?, show_content=?, show_image=?, start_time=?, end_time=?, display_order=? WHERE id=?");
                    $stmt->bind_param('sssiiiissii', $title, $content, $image, $active, $show_t, $show_c, $show_i, $start_t, $end_t, $order, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE announcements SET title=?, content=?, is_active=?, show_title=?, show_content=?, show_image=?, start_time=?, end_time=?, display_order=? WHERE id=?");
                    $stmt->bind_param('ssiiiissii', $title, $content, $active, $show_t, $show_c, $show_i, $start_t, $end_t, $order, $id);
                }

                if ($stmt->execute()) { $msg = 'Cập nhật thành công!'; $msgType = 'success'; }
                else { $msg = 'Lỗi: ' . $conn->error; $msgType = 'error'; }
            }
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $curr = intval($_POST['current_status'] ?? 0);
        $new = $curr ? 0 : 1;
        $conn->query("UPDATE announcements SET is_active = $new WHERE id = $id");
        $msg = 'Đã thay đổi trạng thái.'; $msgType = 'success';
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $conn->query("DELETE FROM announcements WHERE id = $id");
        $msg = 'Đã xóa thông báo.'; $msgType = 'success';
    }
}

// ─── Fetch All ─────────────────────────────────────────────────────────────
$announcements = [];
// Ưu tiên sắp xếp theo thứ tự hiển thị, sau đó mới tới ngày tạo
$res = $conn->query("SELECT * FROM announcements ORDER BY display_order ASC, created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $announcements[] = $r;

$current_time = date('Y-m-d H:i:s');

include __DIR__ . '/admin_layout_open.php';
?>

<div class="mb-7 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold text-white">Quản lý Banner & Thông báo</h2>
        <p class="text-xs text-slate-500 mt-1">Hệ thống thông báo đẩy (Popup Carousel) trên trang chủ</p>
    </div>
    <button onclick="openModal('add')" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-bold rounded-xl hover:from-amber-600 hover:to-amber-700 transition shadow-lg flex items-center gap-2">
        <i data-lucide="plus-circle" class="w-4 h-4"></i> Thêm thông báo
    </button>
</div>

<?php if ($msg): ?>
<div class="mb-6 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success'?'check-circle':'alert-circle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($announcements)): ?>
        <div class="col-span-full content-card p-12 text-center text-slate-500 italic">
            Chưa có thông báo nào. Nhấn "Thêm thông báo" để bắt đầu.
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $a): 
            // Xử lý Trạng thái động dựa trên thời gian
            $statusText = 'Đang hiển thị';
            $badgeClass = 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
            $icon = 'check-circle';

            if ($a['is_active'] == 0) {
                $statusText = 'Đã ẩn';
                $badgeClass = 'bg-slate-500/20 text-slate-400 border border-slate-500/30';
                $icon = 'eye-off';
            } elseif (!empty($a['end_time']) && $current_time > $a['end_time']) {
                $statusText = 'Hết hạn';
                $badgeClass = 'bg-red-500/20 text-red-400 border border-red-500/30';
                $icon = 'clock';
            } elseif (!empty($a['start_time']) && $current_time < $a['start_time']) {
                $statusText = 'Chờ hiển thị';
                $badgeClass = 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
                $icon = 'calendar';
            }
        ?>
        <div class="content-card overflow-hidden flex flex-col group h-full relative">
            <div class="absolute top-2 left-2 z-10 bg-black/60 text-white text-[10px] font-bold px-2 py-1 rounded-md backdrop-blur-md border border-white/10">
                Thứ tự: <?php echo isset($a['display_order']) ? $a['display_order'] : 0; ?>
            </div>

            <div class="relative h-40 bg-slate-800">
                <?php if ($a['image_url']): ?>
                    <img src="<?php echo strpos($a['image_url'], 'http') === 0 ? htmlspecialchars($a['image_url']) : '../../' . htmlspecialchars($a['image_url']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-slate-600">
                        <i data-lucide="image" class="w-10 h-10 opacity-20"></i>
                    </div>
                <?php endif; ?>
                <div class="absolute top-3 right-3 flex gap-1.5">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $a['is_active']; ?>">
                        <button type="submit" class="p-2 rounded-lg backdrop-blur-md <?php echo $a['is_active'] ? 'bg-emerald-500/40 text-white' : 'bg-slate-700/60 text-slate-300'; ?> hover:scale-105 transition shadow-lg">
                            <i data-lucide="<?php echo $a['is_active'] ? 'eye' : 'eye-off'; ?>" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col">
                <div class="flex-1">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider mb-3 <?php echo $badgeClass; ?>">
                        <i data-lucide="<?php echo $icon; ?>" class="w-3 h-3"></i> <?php echo $statusText; ?>
                    </div>

                    <h3 class="font-bold text-white text-lg mb-1.5 line-clamp-1" title="<?php echo htmlspecialchars($a['title']); ?>">
                        <?php echo htmlspecialchars($a['title']); ?>
                    </h3>
                    <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-4"><?php echo strip_tags($a['content']); ?></p>
                </div>
                <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-widest">
                        ID #<?php echo $a['id']; ?> · <?php echo date('d/m/Y', strtotime($a['created_at'])); ?>
                    </span>
                    <div class="flex items-center gap-2">
                        <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE)); ?>)" 
                                class="p-2 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Xóa thông báo này?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <button type="submit" class="p-2 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition font-black">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="ann-modal" class="fixed inset-0 z-[10000] bg-black/60 backdrop-blur-sm hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#1e293b] w-full max-w-2xl rounded-3xl border border-white/10 overflow-hidden shadow-2xl transform transition-all flex flex-col max-h-[90vh]">
        <div class="px-8 py-5 border-b border-white/5 flex justify-between items-center bg-white/5 shrink-0">
            <h3 class="font-black text-white text-lg flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-amber-500" id="modal-icon"></i>
                <span id="modal-title">Thêm thông báo mới</span>
            </h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white transition"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id">
            
            <div class="p-8 space-y-5 overflow-y-auto custom-scrollbar flex-1">
                <div class="space-y-2">
                    <label class="text-[11px] font-bold text-amber-600 uppercase tracking-widest pl-1">Tiêu đề thông báo</label>
                    <input type="text" name="title" id="modal-title-input" required 
                           class="w-full px-5 py-3.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-white/10 rounded-2xl text-slate-800 dark:text-white font-semibold outline-none focus:border-amber-500/50 transition shadow-inner" placeholder="VD: Khuyến mãi món mới...">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-amber-600 uppercase tracking-widest pl-1">Tải ảnh lên</label>
                        <input type="file" name="image_file" accept="image/*"
                               class="w-full px-4 py-3 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-white/10 rounded-2xl text-slate-500 text-sm outline-none focus:border-amber-500/50 transition file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-amber-600 uppercase tracking-widest pl-1">Hoặc dùng Link (URL)</label>
                        <input type="text" name="image_url" id="modal-image-input" 
                               class="w-full px-5 py-3.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-white/10 rounded-2xl text-slate-800 dark:text-white font-medium outline-none focus:border-amber-500/50 transition" placeholder="https://...">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[11px] font-bold text-amber-600 uppercase tracking-widest pl-1">Nội dung chi tiết (HTML)</label>
                    <textarea name="content" id="modal-content-input" rows="4" 
                              class="w-full px-5 py-3.5 bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-white/10 rounded-2xl text-slate-800 dark:text-white font-medium outline-none focus:border-amber-500/50 transition" placeholder="Nhập nội dung thông báo..."></textarea>
                </div>

                <div class="p-4 bg-slate-800/50 rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-widest pl-1">Thứ tự hiển thị</label>
                        <input type="number" name="display_order" id="modal-display-order-input" value="0" min="0"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-white/10 rounded-xl text-white font-medium outline-none focus:border-amber-500/50 transition text-center">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-widest pl-1">Bắt đầu từ</label>
                        <input type="datetime-local" name="start_time" id="modal-start-time-input" 
                               class="w-full px-3 py-2.5 bg-slate-900 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/50 transition">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-widest pl-1">Kết thúc lúc</label>
                        <input type="datetime-local" name="end_time" id="modal-end-time-input" 
                               class="w-full px-3 py-2.5 bg-slate-900 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/50 transition">
                    </div>
                </div>

                <div class="pt-2 flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="modal-active-input" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                        </label>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Đang hoạt động</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_image" id="modal-show-image-input" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                        </label>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Hiện ảnh trên UI</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_title" id="modal-show-title-input" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                        </label>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Hiện tiêu đề</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_content" id="modal-show-content-input" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 shadow-inner"></div>
                        </label>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Hiện nội dung</span>
                    </div>
                </div>
            </div>

            <div class="p-6 pt-4 border-t border-white/5 flex gap-3 bg-white/5 shrink-0">
                <button type="button" onclick="closeModal()" class="flex-1 py-3.5 bg-slate-800 text-slate-400 font-bold rounded-2xl hover:bg-slate-700 transition">Hủy</button>
                <button type="submit" class="flex-[2] py-3.5 bg-gradient-to-r from-amber-500 to-amber-600 text-white font-black rounded-2xl shadow-lg shadow-amber-500/20 hover:from-amber-600 hover:to-amber-700 transition transform hover:translate-y-[-2px] active:scale-95">Lưu thông báo</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode, data = null) {
    const modal = document.getElementById('ann-modal');
    const title = document.getElementById('modal-title');
    const action = document.getElementById('modal-action');
    const id = document.getElementById('modal-id');
    const titleInp = document.getElementById('modal-title-input');
    const imageInp = document.getElementById('modal-image-input');
    const contentInp = document.getElementById('modal-content-input');
    
    // Các trường Toggle
    const activeInp = document.getElementById('modal-active-input');
    const showImgInp = document.getElementById('modal-show-image-input');
    const showTitleInp = document.getElementById('modal-show-title-input');
    const showContentInp = document.getElementById('modal-show-content-input');

    // Các trường Thời gian & Thứ tự
    const orderInp = document.getElementById('modal-display-order-input');
    const startInp = document.getElementById('modal-start-time-input');
    const endInp = document.getElementById('modal-end-time-input');
    
    if (mode === 'add') {
        title.innerText = 'Thêm thông báo mới';
        action.value = 'add'; id.value = '';
        titleInp.value = ''; imageInp.value = ''; contentInp.value = '';
        
        orderInp.value = '0'; startInp.value = ''; endInp.value = '';
        activeInp.checked = true;
        showImgInp.checked = true;
        showTitleInp.checked = true;
        showContentInp.checked = true;
    } else {
        title.innerText = 'Sửa thông báo #' + data.id;
        action.value = 'edit'; id.value = data.id;
        titleInp.value = data.title;
        imageInp.value = data.image_url || '';
        contentInp.value = data.content || '';
        
        orderInp.value = data.display_order || '0';
        startInp.value = data.start_time ? data.start_time.replace(' ', 'T').slice(0, 16) : '';
        endInp.value = data.end_time ? data.end_time.replace(' ', 'T').slice(0, 16) : '';

        activeInp.checked = parseInt(data.is_active) === 1;
        showImgInp.checked = parseInt(data.show_image ?? 1) === 1;
        showTitleInp.checked = parseInt(data.show_title ?? 1) === 1;
        showContentInp.checked = parseInt(data.show_content ?? 1) === 1;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => modal.children[0].classList.add('scale-100'), 10);
}

function closeModal() {
    const modal = document.getElementById('ann-modal');
    modal.children[0].classList.remove('scale-100');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

document.getElementById('ann-modal').onclick = function(e){ if(e.target===this) closeModal(); };
</script>

<?php include __DIR__ . '/admin_layout_close.php'; ?>
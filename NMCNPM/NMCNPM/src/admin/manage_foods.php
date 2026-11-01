<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart_order_helpers.php';

// Giải phóng session ngay sau khi lấy quyền admin để tránh treo web
session_write_close();

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

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- ADD ----
    if ($action === 'add') {
        $title       = trim($_POST['title'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $price       = floatval($_POST['price'] ?? 0);
        $calories    = intval($_POST['calories'] ?? 250);
        $prep_time   = trim($_POST['prep_time'] ?? '15 phút');
        $ingredients = trim($_POST['ingredients'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0) ?: null;
        $is_vegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
        $is_highlight  = isset($_POST['is_highlight']) ? 1 : 0;
        $image_url   = trim($_POST['image_url'] ?? '');
        $uploadBase  = '../../uploads';
        if (!is_dir($uploadBase)) mkdir($uploadBase, 0777, true);

        $hasFile = isset($_FILES['image']) && $_FILES['image']['error'] === 0;

        if ($title === '' || $price <= 0) {
            $msg = 'Vui lòng điền đầy đủ Tên và Giá món ăn.'; $msgType = 'error';
        } elseif (!$hasFile && empty($image_url)) {
            $msg = 'Vui lòng dán link hình ảnh hoặc tải file ảnh lên.'; $msgType = 'error';
        } else {
            $dbPath = '';
            if ($hasFile) {
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', basename($_FILES['image']['name']));
                $target   = $uploadBase . '/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $dbPath = 'uploads/' . $filename;
                } else {
                    $msg = 'Không ghi được file ảnh.'; $msgType = 'error';
                }
            } else {
                $dbPath = $image_url;
            }

            if (!empty($dbPath)) {
                $stmt = $conn->prepare("INSERT INTO products (title,description,price,image,calories,prep_time,ingredients,category_id,is_vegetarian,is_highlight) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssdsissiii', $title, $desc, $price, $dbPath, $calories, $prep_time, $ingredients, $category_id, $is_vegetarian, $is_highlight);
                if ($stmt->execute()) {
                    $msg = 'Thêm món ăn thành công!'; $msgType = 'success';
                    log_system_action($conn, $_SESSION['user_id'] ?? 0, 'food_add', $title, "Thêm món mới: $title (" . number_format($price, 0, ',', '.') . "đ)");
                } else {
                    $msg = 'Lỗi CSDL: ' . $conn->error; $msgType = 'error';
                }
            }
        }
    }

    // ---- EDIT ----
    elseif ($action === 'edit') {
        $id          = intval($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $price       = floatval($_POST['price'] ?? 0);
        $calories    = intval($_POST['calories'] ?? 250);
        $prep_time   = trim($_POST['prep_time'] ?? '15 phút');
        $ingredients = trim($_POST['ingredients'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0) ?: null;
        $is_vegetarian = isset($_POST['is_vegetarian']) ? 1 : 0;
        $is_highlight  = isset($_POST['is_highlight']) ? 1 : 0;
        $image_url   = trim($_POST['image_url'] ?? '');

        if ($id <= 0 || $title === '' || $price <= 0) {
            $msg = 'Dữ liệu không hợp lệ.'; $msgType = 'error';
        } else {
            // Handle new image (file upload or URL)
            $newImage = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $uploadBase = '../../uploads';
                if (!is_dir($uploadBase)) mkdir($uploadBase, 0777, true);
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', basename($_FILES['image']['name']));
                $target   = $uploadBase . '/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $newImage = 'uploads/' . $filename;
                }
            } elseif (!empty($image_url)) {
                $newImage = $image_url;
            }

            if ($newImage) {
                $stmt = $conn->prepare("UPDATE products SET title=?,description=?,price=?,image=?,calories=?,prep_time=?,ingredients=?,category_id=?,is_vegetarian=?,is_highlight=? WHERE id=?");
                $stmt->bind_param('ssdsissiiii', $title, $desc, $price, $newImage, $calories, $prep_time, $ingredients, $category_id, $is_vegetarian, $is_highlight, $id);
            } else {
                $stmt = $conn->prepare("UPDATE products SET title=?,description=?,price=?,calories=?,prep_time=?,ingredients=?,category_id=?,is_vegetarian=?,is_highlight=? WHERE id=?");
                $stmt->bind_param('ssdssissiii', $title, $desc, $price, $calories, $prep_time, $ingredients, $category_id, $is_vegetarian, $is_highlight, $id);
            }
            if ($stmt->execute()) {
                $msg = 'Cập nhật món ăn thành công!'; $msgType = 'success';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'food_edit', $title, "Cập nhật món ăn: $title (" . number_format($price, 0, ',', '.') . "đ)");
            } else {
                $msg = 'Lỗi CSDL: ' . $conn->error; $msgType = 'error';
            }
        }
    }

    // ---- TOGGLE HIDE ----
    elseif ($action === 'toggle_hide') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $p = $conn->query("SELECT title, is_hidden FROM products WHERE id = $id")->fetch_assoc();
            $conn->query("UPDATE products SET is_hidden = 1 - is_hidden WHERE id = $id");
            $msg = 'Đã cập nhật trạng thái hiển thị.'; $msgType = 'success';
            if ($p) {
                $statusTxt = ($p['is_hidden'] == 0) ? 'Ẩn khỏi menu' : 'Hiện lại trên menu';
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'food_toggle', $p['title'], "Đã đổi trạng thái sang: $statusTxt món '{$p['title']}'");
            }
        }
    }

    // ---- DELETE ----
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $p = $conn->query("SELECT title FROM products WHERE id = $id")->fetch_assoc();
            $conn->query("DELETE FROM products WHERE id = $id");
            $msg = 'Đã xóa món ăn.'; $msgType = 'success';
            if ($p) {
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'food_delete', $p['title'], "Xóa món ăn: '{$p['title']}'");
            }
        }
    }
}

// ─── Fetch categories ───────────────────────────────────────────────────────
$categories = [];
$catRes = $conn->query("SELECT * FROM categories_products ORDER BY id ASC");
if ($catRes) while ($crow = $catRes->fetch_assoc()) $categories[] = $crow;

// ─── Fetch all products ─────────────────────────────────────────────────────
$products = [];
$res = $conn->query("SELECT p.*, cp.name AS category_name FROM products p LEFT JOIN categories_products cp ON cp.id = p.category_id ORDER BY p.id DESC");
if ($res) while ($row = $res->fetch_assoc()) $products[] = $row;
$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'foods';
$page_title   = 'Quản lý Thực đơn';

include __DIR__ . '/admin_layout_open.php';
?>

    <!-- ─── HEADER ─────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-xl font-extrabold text-white">Danh sách món ăn</h2>
            <p class="text-xs text-slate-500 mt-1">Thêm, chỉnh sửa, ẩn và xóa món ăn trong hệ thống</p>
        </div>
        <button onclick="openAddModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-[#d97706] text-white font-bold rounded-xl hover:from-amber-600 hover:to-amber-700 transition shadow-lg text-sm active:scale-95">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Thêm món mới
        </button>
    </div>

    <!-- ─── STATUS MESSAGE ─────────────────────────── -->
    <?php if ($msg): ?>
    <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl border <?php echo $msgType === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400'; ?>">
        <i data-lucide="<?php echo $msgType === 'success' ? 'check-circle-2' : 'alert-triangle'; ?>" class="w-5 h-5 mt-0.5 shrink-0"></i>
        <p class="font-semibold text-sm"><?php echo htmlspecialchars($msg); ?></p>
    </div>
    <?php endif; ?>

    <!-- ─── STATS STRIP ───────────────────────────── -->
    <?php
    $totalProducts = count($products);
    $hiddenCount   = count(array_filter($products, fn($p) => $p['is_hidden']));
    $visibleCount  = $totalProducts - $hiddenCount;
    ?>
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="content-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-500/10 text-amber-500 rounded-xl flex items-center justify-center">
                <i data-lucide="utensils" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Tổng món</p>
                <p class="text-xl font-black text-white"><?php echo $totalProducts; ?></p>
            </div>
        </div>
        <div class="content-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-500/10 text-emerald-500 rounded-xl flex items-center justify-center">
                <i data-lucide="eye" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Đang hiện</p>
                <p class="text-xl font-black text-white"><?php echo $visibleCount; ?></p>
            </div>
        </div>
        <div class="content-card p-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-red-500/10 text-red-500 rounded-xl flex items-center justify-center">
                <i data-lucide="eye-off" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Đang ẩn</p>
                <p class="text-xl font-black text-white"><?php echo $hiddenCount; ?></p>
            </div>
        </div>
    </div>

    <!-- ─── PRODUCTS TABLE ─────────────────────────── -->
    <div class="content-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-slate-500 text-left text-xs uppercase tracking-wider">
                        <th class="px-5 py-4 w-16">#</th>
                        <th class="px-5 py-4">Ảnh</th>
                        <th class="px-5 py-4">Tên món</th>
                        <th class="px-5 py-4">Giá</th>
                        <th class="px-5 py-4">Trạng thái</th>
                        <th class="px-5 py-4 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-16 text-gray-400 italic">
                            Chưa có món ăn nào. Bấm "Thêm món mới" để bắt đầu!
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr class="hover:bg-white/5 transition-colors <?php echo $p['is_hidden'] ? 'opacity-40' : ''; ?>">
                        <td class="px-5 py-3.5 text-slate-500 font-mono text-xs">#<?php echo $p['id']; ?></td>
                        <td class="px-5 py-3.5">
                            <div class="w-14 h-14 rounded-2xl overflow-hidden bg-slate-800 border border-white/10">
                                <img src="<?php
    $imgSrc = $p['image'];
    if (!preg_match('#^https?://#', $imgSrc)) {
        $imgSrc = '../../' . ltrim($imgSrc, '/');
    }
    echo htmlspecialchars($imgSrc);
?>" alt="" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2256%22 height=%2256%22%3E%3Crect width=%2256%22 height=%2256%22 fill=%22%23fef3c7%22/%3E%3C/svg%3E'">
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-white max-w-xs truncate"><?php echo htmlspecialchars($p['title']); ?></p>
                            <p class="text-xs text-slate-500 truncate max-w-xs mt-0.5"><?php echo htmlspecialchars($p['description'] ?: '—'); ?></p>
                        </td>
                        <td class="px-5 py-3.5 font-bold text-amber-500 whitespace-nowrap">
                            <?php echo number_format($p['price'], 0, ',', '.'); ?>đ
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $p['is_hidden'] ? 'status-badge-hidden' : 'status-badge-shown'; ?>">
                                <i data-lucide="<?php echo $p['is_hidden'] ? 'eye-off' : 'eye'; ?>" class="w-3 h-3"></i>
                                <?php echo $p['is_hidden'] ? 'Đã ẩn' : 'Đang hiện'; ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                <!-- Edit -->
                                <button onclick="openEditModal(<?php
                                    echo htmlspecialchars(json_encode([
                                        'id' => $p['id'],
                                        'title' => $p['title'],
                                        'description' => $p['description'],
                                        'price' => $p['price'],
                                        'image' => $p['image'],
                                        'calories' => $p['calories'] ?? 250,
                                        'prep_time' => $p['prep_time'] ?? '15 phút',
                                        'ingredients' => $p['ingredients'] ?? '',
                                        'category_id' => $p['category_id'] ?? 0,
                                        'is_vegetarian' => $p['is_vegetarian'] ?? 0,
                                        'is_highlight' => $p['is_highlight'] ?? 0,
                                    ], JSON_UNESCAPED_UNICODE));
                                ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-blue-500/10 text-blue-400 font-semibold hover:bg-blue-500/20 transition text-xs">
                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Sửa
                                </button>

                                <!-- Toggle Hide/Show -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_hide">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl font-semibold hover:opacity-80 transition text-xs <?php echo $p['is_hidden'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-orange-500/10 text-orange-400'; ?>">
                                        <i data-lucide="<?php echo $p['is_hidden'] ? 'eye' : 'eye-off'; ?>" class="w-3.5 h-3.5"></i>
                                        <?php echo $p['is_hidden'] ? 'Hiện' : 'Ẩn'; ?>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa món «<?php echo htmlspecialchars(addslashes($p['title'])); ?>»?\nHành động này không thể hoàn tác!')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-red-500/10 text-red-400 font-semibold hover:bg-red-500/20 transition text-xs">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Xóa
                                    </button>
                                </form>
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

<!-- ─────────────────────── ADD MODAL ─────────────────────────────────────── -->
<div id="add-modal" class="fixed inset-0 z-[9999] bg-[#2d261a]/50 modal-overlay hidden items-center justify-center p-4">
    <div class="bg-[#fcfbf8] w-full max-w-lg rounded-3xl shadow-2xl border border-amber-100 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-amber-100 bg-white/80">
            <h3 class="font-bold text-lg text-[#2d261a] flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-amber-500"></i> Thêm món mới
            </h3>
            <button onclick="closeAddModal()" class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[80vh]">
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" value="add">

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Tên món ăn <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="VD: Gà rán rớt nước mắm" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Giá (VNĐ) <span class="text-red-500">*</span></label>
                        <input type="number" step="1000" min="0" name="price" required placeholder="25000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Danh mục</label>
                        <select name="category_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium">
                            <option value="0">— Không phân loại —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Thời gian chuẩn bị</label>
                    <input type="text" name="prep_time" value="15 phút" placeholder="15 phút" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400">
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Hình ảnh món ăn <span class="text-red-500">*</span></label>
                        <div class="inline-flex p-0.5 bg-gray-100 rounded-xl border border-gray-200 text-xs font-bold">
                            <button type="button" id="btn-add-mode-link" onclick="setAddImgMode('link')" class="px-3 py-1 rounded-lg bg-amber-500 text-white shadow-xs transition">
                                🔗 Dán link
                            </button>
                            <button type="button" id="btn-add-mode-file" onclick="setAddImgMode('file')" class="px-3 py-1 rounded-lg text-gray-500 hover:text-gray-800 transition">
                                📁 Tải file
                            </button>
                        </div>
                    </div>

                    <!-- Mode 1: URL input -->
                    <div id="box-add-mode-link" class="space-y-1.5">
                        <div class="relative">
                            <input type="url" name="image_url" id="add-image-url" placeholder="Dán đường dẫn ảnh (VD: https://images.unsplash.com/...)" 
                                   oninput="previewAddImageUrl(this.value)"
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400 text-sm">
                            <i data-lucide="link" class="w-4 h-4 text-amber-500 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        </div>
                    </div>

                    <!-- Mode 2: File upload -->
                    <div id="box-add-mode-file" class="hidden space-y-1.5">
                        <div class="p-2 border border-dashed border-gray-300 rounded-2xl bg-gray-50 focus-within:border-amber-500">
                            <input type="file" name="image" id="add-image-file" accept="image/*" onchange="previewAddImageFile(this)" class="w-full text-sm text-gray-500">
                        </div>
                    </div>

                    <!-- Image Preview Card -->
                    <div id="add-img-preview-box" class="hidden items-center gap-3 p-3 bg-amber-50/80 border border-amber-200 rounded-2xl">
                        <div class="w-14 h-14 rounded-xl overflow-hidden bg-white border border-amber-300 shrink-0 shadow-inner">
                            <img id="add-img-preview-tag" src="" alt="Preview" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-amber-900">Xem trước hình ảnh</p>
                            <p id="add-img-preview-info" class="text-[11px] text-gray-500 truncate mt-0.5"></p>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Thành phần / Nguyên liệu</label>
                    <textarea name="ingredients" rows="2" placeholder="Gà, tỏi, ớt, nước mắm..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400 resize-none"></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Mô tả (tuỳ chọn)</label>
                    <textarea name="description" rows="2" placeholder="Nhập mô tả hấp dẫn cho món ăn..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium placeholder:text-gray-400 resize-none"></textarea>
                </div>

                <div class="flex items-center gap-6 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_vegetarian" class="w-5 h-5 rounded border-gray-300 text-green-500 focus:ring-green-200">
                        <span class="text-sm font-bold text-[#423826]">🥬 Món chay</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_highlight" class="w-5 h-5 rounded border-gray-300 text-amber-500 focus:ring-amber-200">
                        <span class="text-sm font-bold text-[#423826]">⭐ Nổi bật</span>
                    </label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAddModal()" class="flex-1 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-gray-600 font-semibold hover:bg-gray-100 transition">Hủy</button>
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-amber-500 to-[#d97706] text-white font-bold rounded-2xl hover:from-amber-600 hover:to-amber-700 transition shadow-lg shadow-amber-500/25 flex items-center justify-center gap-2">
                        <i data-lucide="wand-2" class="w-4 h-4"></i> Đăng bán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─────────────────────── EDIT MODAL ─────────────────────────────────────── -->
<div id="edit-modal" class="fixed inset-0 z-[9999] bg-[#2d261a]/50 modal-overlay hidden items-center justify-center p-4">
    <div class="bg-[#fcfbf8] w-full max-w-lg rounded-3xl shadow-2xl border border-amber-100 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-amber-100 bg-white/80">
            <h3 class="font-bold text-lg text-[#2d261a] flex items-center gap-2">
                <i data-lucide="pencil" class="w-5 h-5 text-blue-500"></i> Chỉnh sửa món ăn
            </h3>
            <button onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 flex items-center justify-center">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[80vh]">
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="calories" id="edit-calories" value="250">

                <!-- Current image preview & edit options -->
                <div class="p-3 bg-amber-50 rounded-2xl border border-amber-100 space-y-2.5">
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-xl overflow-hidden border border-amber-200 shrink-0 bg-white shadow-inner">
                            <img id="edit-img-preview" src="" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-[#a39a85] font-semibold">Hình ảnh (bỏ trống để giữ nguyên)</p>
                            <div class="inline-flex mt-1.5 p-0.5 bg-white/90 rounded-lg border border-amber-200 text-xs font-bold">
                                <button type="button" id="btn-edit-mode-link" onclick="setEditImgMode('link')" class="px-2.5 py-0.5 rounded bg-amber-500 text-white shadow-xs transition">
                                    🔗 Link ảnh
                                </button>
                                <button type="button" id="btn-edit-mode-file" onclick="setEditImgMode('file')" class="px-2.5 py-0.5 rounded text-gray-600 hover:text-gray-800 transition">
                                    📁 File máy
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="box-edit-mode-link" class="relative">
                        <input type="url" name="image_url" id="edit-image-url" placeholder="Dán link ảnh mới (https://...)" 
                               oninput="previewEditImageUrl(this.value)"
                               class="w-full pl-8 pr-3 py-2 bg-white border border-amber-200 rounded-xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] text-xs font-medium placeholder:text-gray-400">
                        <i data-lucide="link" class="w-3.5 h-3.5 text-amber-500 absolute left-2.5 top-1/2 -translate-y-1/2"></i>
                    </div>

                    <div id="box-edit-mode-file" class="hidden">
                        <div class="p-1.5 border border-dashed border-amber-300 rounded-xl bg-white/70">
                            <input type="file" name="image" id="edit-image-file" accept="image/*" onchange="previewEditImageFile(this)" class="text-xs text-gray-500 w-full">
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Tên món ăn <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="edit-title" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Giá (VNĐ) <span class="text-red-500">*</span></label>
                        <input type="number" step="1000" min="0" name="price" id="edit-price" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Danh mục</label>
                        <select name="category_id" id="edit-category" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium">
                            <option value="0">— Không phân loại —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Thời gian chuẩn bị</label>
                    <input type="text" name="prep_time" id="edit-prep-time" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Thành phần / Nguyên liệu</label>
                    <textarea name="ingredients" id="edit-ingredients" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium resize-none"></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-[#d97706] uppercase tracking-wider">Mô tả</label>
                    <textarea name="description" id="edit-desc" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-200 focus:border-amber-500 outline-none text-[#423826] font-medium resize-none"></textarea>
                </div>

                <div class="flex items-center gap-6 py-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_vegetarian" id="edit-vegetarian" class="w-5 h-5 rounded border-gray-300 text-green-500 focus:ring-green-200">
                        <span class="text-sm font-bold text-[#423826]">🥬 Món chay</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_highlight" id="edit-highlight" class="w-5 h-5 rounded border-gray-300 text-amber-500 focus:ring-amber-200">
                        <span class="text-sm font-bold text-[#423826]">⭐ Nổi bật</span>
                    </label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="flex-1 py-3 rounded-2xl border border-gray-200 bg-gray-50 text-gray-600 font-semibold hover:bg-gray-100 transition">Hủy</button>
                    <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold rounded-2xl hover:from-blue-600 hover:to-blue-700 transition shadow-lg flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // ── Image Mode Switcher (Add Modal) ─────────────────────────────────────────
    function setAddImgMode(mode) {
        const btnLink = document.getElementById('btn-add-mode-link');
        const btnFile = document.getElementById('btn-add-mode-file');
        const boxLink = document.getElementById('box-add-mode-link');
        const boxFile = document.getElementById('box-add-mode-file');

        if (mode === 'link') {
            btnLink.className = 'px-3 py-1 rounded-lg bg-amber-500 text-white shadow-xs transition';
            btnFile.className = 'px-3 py-1 rounded-lg text-gray-500 hover:text-gray-800 transition';
            boxLink.classList.remove('hidden');
            boxFile.classList.add('hidden');
            const urlVal = document.getElementById('add-image-url').value.trim();
            if (urlVal) previewAddImageUrl(urlVal);
            else hideAddPreview();
        } else {
            btnFile.className = 'px-3 py-1 rounded-lg bg-amber-500 text-white shadow-xs transition';
            btnLink.className = 'px-3 py-1 rounded-lg text-gray-500 hover:text-gray-800 transition';
            boxFile.classList.remove('hidden');
            boxLink.classList.add('hidden');
            const fileInput = document.getElementById('add-image-file');
            if (fileInput.files && fileInput.files[0]) previewAddImageFile(fileInput);
            else hideAddPreview();
        }
        lucide.createIcons();
    }

    function previewAddImageUrl(url) {
        const pBox = document.getElementById('add-img-preview-box');
        const pTag = document.getElementById('add-img-preview-tag');
        const pInfo = document.getElementById('add-img-preview-info');
        url = (url || '').trim();
        if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:'))) {
            pTag.src = url;
            pInfo.textContent = url;
            pBox.classList.remove('hidden');
            pBox.classList.add('flex');
        } else {
            hideAddPreview();
        }
    }

    function previewAddImageFile(input) {
        const pBox = document.getElementById('add-img-preview-box');
        const pTag = document.getElementById('add-img-preview-tag');
        const pInfo = document.getElementById('add-img-preview-info');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                pTag.src = e.target.result;
                pInfo.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                pBox.classList.remove('hidden');
                pBox.classList.add('flex');
            };
            reader.readAsDataURL(file);
        } else {
            hideAddPreview();
        }
    }

    function hideAddPreview() {
        const pBox = document.getElementById('add-img-preview-box');
        pBox.classList.add('hidden');
        pBox.classList.remove('flex');
    }

    // ── Image Mode Switcher (Edit Modal) ────────────────────────────────────────
    function setEditImgMode(mode) {
        const btnLink = document.getElementById('btn-edit-mode-link');
        const btnFile = document.getElementById('btn-edit-mode-file');
        const boxLink = document.getElementById('box-edit-mode-link');
        const boxFile = document.getElementById('box-edit-mode-file');

        if (mode === 'link') {
            btnLink.className = 'px-2.5 py-0.5 rounded bg-amber-500 text-white shadow-xs transition';
            btnFile.className = 'px-2.5 py-0.5 rounded text-gray-600 hover:text-gray-800 transition';
            boxLink.classList.remove('hidden');
            boxFile.classList.add('hidden');
        } else {
            btnFile.className = 'px-2.5 py-0.5 rounded bg-amber-500 text-white shadow-xs transition';
            btnLink.className = 'px-2.5 py-0.5 rounded text-gray-600 hover:text-gray-800 transition';
            boxFile.classList.remove('hidden');
            boxLink.classList.add('hidden');
        }
        lucide.createIcons();
    }

    function previewEditImageUrl(url) {
        const pTag = document.getElementById('edit-img-preview');
        url = (url || '').trim();
        if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:'))) {
            pTag.src = url;
        } else if (pTag.dataset.origSrc) {
            pTag.src = pTag.dataset.origSrc;
        }
    }

    function previewEditImageFile(input) {
        const pTag = document.getElementById('edit-img-preview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                pTag.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        } else if (pTag.dataset.origSrc) {
            pTag.src = pTag.dataset.origSrc;
        }
    }

    // ── Add Modal ──────────────────────────────────────────────────────────────
    function openAddModal() {
        setAddImgMode('link');
        document.getElementById('add-image-url').value = '';
        const f = document.getElementById('add-image-file');
        if (f) f.value = '';
        hideAddPreview();

        const m = document.getElementById('add-modal');
        m.classList.remove('hidden');
        m.classList.add('flex');
        lucide.createIcons();
    }
    function closeAddModal() {
        const m = document.getElementById('add-modal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // ── Edit Modal ─────────────────────────────────────────────────────────────
    function openEditModal(data) {
        document.getElementById('edit-id').value         = data.id;
        document.getElementById('edit-title').value      = data.title;
        document.getElementById('edit-price').value      = data.price;
        document.getElementById('edit-desc').value       = data.description || '';
        document.getElementById('edit-calories').value   = data.calories || 250;
        document.getElementById('edit-prep-time').value  = data.prep_time || '15 phút';
        document.getElementById('edit-ingredients').value = data.ingredients || '';
        document.getElementById('edit-category').value   = data.category_id || 0;
        document.getElementById('edit-vegetarian').checked  = !!parseInt(data.is_vegetarian);
        document.getElementById('edit-highlight').checked   = !!parseInt(data.is_highlight);

        const imgPath = data.image
            ? (/^https?:\/\//i.test(data.image) ? data.image : '../../' + data.image.replace(/^\/+/, ''))
            : '';
        const pTag = document.getElementById('edit-img-preview');
        pTag.src = imgPath;
        pTag.dataset.origSrc = imgPath;

        const editUrlInput = document.getElementById('edit-image-url');
        const editFileInput = document.getElementById('edit-image-file');
        if (editFileInput) editFileInput.value = '';

        if (/^https?:\/\//i.test(data.image || '')) {
            editUrlInput.value = data.image;
            setEditImgMode('link');
        } else {
            editUrlInput.value = '';
            setEditImgMode('file');
        }

        const m = document.getElementById('edit-modal');
        m.classList.remove('hidden');
        m.classList.add('flex');
        lucide.createIcons();
    }
    function closeEditModal() {
        const m = document.getElementById('edit-modal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // Close modals on backdrop click
    ['add-modal','edit-modal'].forEach(id => {
        const m = document.getElementById(id);
        if (m) {
            m.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        }
    });

    // Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
</script>

<?php include __DIR__ . '/admin_layout_close.php'; ?>

<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'daily_menu';
$page_title   = 'Thực đơn theo ngày';

require_once '../includes/config.php';
require_once '../includes/cart_order_helpers.php';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid       = intval($_POST['product_id'] ?? 0);
        $date      = $_POST['serving_date'] ?? date('Y-m-d');
        $meal      = $_POST['meal_type'] ?? 'Trưa';
        $highlight = isset($_POST['is_highlight']) ? 1 : 0;
        if ($pid > 0 && in_array($meal, ['Sáng','Trưa','Tối'])) {
            // Prevent duplicate
            $exists = $conn->query("SELECT id FROM daily_menu WHERE product_id=$pid AND serving_date='$date' AND meal_type='$meal'");
            if ($exists && $exists->num_rows > 0) {
                $msg = 'Món này đã có trong thực đơn ngày hôm đó!'; $msgType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO daily_menu (product_id,serving_date,meal_type,is_highlight) VALUES (?,?,?,?)");
                $stmt->bind_param('issi', $pid, $date, $meal, $highlight);
                $stmt->execute();
                $msg = 'Đã thêm món vào thực đơn!'; $msgType = 'success';
                
                $prod = $conn->query("SELECT title FROM products WHERE id = $pid")->fetch_assoc();
                $pTitle = $prod['title'] ?? "Món #$pid";
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'menu_add', $pTitle, "Thêm '$pTitle' vào thực đơn bữa $meal ($date)");
            }
        }
    } elseif ($action === 'delete') {
        $dmId = intval($_POST['dm_id'] ?? 0);
        if ($dmId > 0) {
            $dm = $conn->query("SELECT p.title, dm.meal_type, dm.serving_date FROM daily_menu dm JOIN products p ON dm.product_id = p.id WHERE dm.id = $dmId")->fetch_assoc();
            $conn->query("DELETE FROM daily_menu WHERE id=$dmId"); 
            $msg = 'Đã xóa khỏi thực đơn.'; $msgType = 'success';
            if ($dm) {
                log_system_action($conn, $_SESSION['user_id'] ?? 0, 'menu_delete', $dm['title'], "Xóa '{$dm['title']}' khỏi thực đơn bữa {$dm['meal_type']} ({$dm['serving_date']})");
            }
        }
    } elseif ($action === 'toggle_highlight') {
        $dmId = intval($_POST['dm_id'] ?? 0);
        if ($dmId > 0) {
            $conn->query("UPDATE daily_menu SET is_highlight = 1 - is_highlight WHERE id=$dmId");
            $msg = 'Đã cập nhật gợi ý.'; $msgType = 'success';
        }
    }
}

// ─── Selected date ─────────────────────────────────────────────────────────
$view_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $view_date)) $view_date = date('Y-m-d');

// Fetch menu for date
$menu_rows = [];
$mres = $conn->query("SELECT dm.id, dm.meal_type, dm.is_highlight, p.id as product_id, p.title, p.price, p.image
    FROM daily_menu dm JOIN products p ON p.id=dm.product_id
    WHERE dm.serving_date='$view_date'
    ORDER BY FIELD(dm.meal_type,'Sáng','Trưa','Tối'), dm.is_highlight DESC");
if ($mres) while ($r = $mres->fetch_assoc()) $menu_rows[] = $r;

// Group by meal
$byMeal = ['Sáng'=>[],'Trưa'=>[],'Tối'=>[]];
foreach ($menu_rows as $row) $byMeal[$row['meal_type']][] = $row;

// All visible products for selector
$all_products = [];
$pres = $conn->query("SELECT id, title, price FROM products WHERE is_hidden=0 ORDER BY title ASC");
if ($pres) while ($r = $pres->fetch_assoc()) $all_products[] = $r;

include 'admin_layout_open.php';
?>

<!-- Status -->
<?php if ($msg): ?>
<div class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success'?'check-circle':'alert-triangle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<!-- Date picker + Add form -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-7">
    <!-- Date picker -->
    <form method="GET" class="content-card p-5 flex flex-col gap-3">
        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">📅 Chọn ngày xem</label>
        <input type="date" name="date" value="<?php echo $view_date; ?>" class="w-full px-4 py-2.5 rounded-xl bg-slate-800 border border-white/10 text-white text-sm outline-none focus:border-amber-500/50">
        <button type="submit" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm rounded-xl transition">Xem thực đơn</button>
        <p class="text-xs text-slate-500 text-center">Đang xem: <span class="text-amber-400 font-bold"><?php echo date('d/m/Y', strtotime($view_date)); ?></span></p>
    </form>

    <!-- Add form (spans 2) -->
    <form method="POST" class="content-card p-5 md:col-span-2">
        <input type="hidden" name="action" value="add">
        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-4">➕ Thêm món vào thực đơn</label>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
                <label class="text-xs text-slate-500 mb-1.5 block">Chọn ngày</label>
                <input type="date" name="serving_date" value="<?php echo $view_date; ?>" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs outline-none focus:border-amber-500/50">
            </div>
            <div>
                <label class="text-xs text-slate-500 mb-1.5 block">Bữa</label>
                <select name="meal_type" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs outline-none focus:border-amber-500/50">
                    <option>Sáng</option><option>Trưa</option><option>Tối</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="text-xs text-slate-500 mb-1.5 block">Chọn món ăn</label>
            <select name="product_id" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-white/10 text-white text-xs outline-none focus:border-amber-500/50">
                <?php foreach ($all_products as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?> (<?php echo number_format($p['price'],0,',','.'); ?>đ)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="flex items-center gap-2 text-xs text-slate-400 mb-4">
            <input type="checkbox" name="is_highlight" class="rounded"> Đánh dấu là Món gợi ý (highlight)
        </label>
        <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-amber-500 to-amber-700 text-white font-bold text-sm rounded-xl transition hover:from-amber-600 hover:to-amber-800">Thêm vào thực đơn</button>
    </form>
</div>

<!-- Menu by meal -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5">
<?php foreach (['Sáng','Trưa','Tối'] as $meal):
    $mealIcon = match($meal) { 'Sáng'=>'🌅','Trưa'=>'☀️',default=>'🌙' };
    $items = $byMeal[$meal]; ?>
<div class="content-card p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-extrabold text-white"><?php echo "$mealIcon Bữa $meal"; ?></h3>
        <span class="text-[10px] text-slate-500 font-bold"><?php echo count($items); ?> món</span>
    </div>
    <?php if (empty($items)): ?>
    <p class="text-slate-600 text-xs italic text-center py-6">Chưa có món.</p>
    <?php else: ?>
    <div class="space-y-2.5">
        <?php foreach ($items as $item): ?>
        <div class="flex items-center gap-3 bg-slate-800/60 rounded-xl p-2.5 <?php echo $item['is_highlight']?'border border-amber-500/30':''; ?>">
            <div class="w-10 h-10 rounded-xl overflow-hidden shrink-0 bg-slate-700">
                <?php $imgSrc = $item['image']; if (!preg_match('#^https?://#', $imgSrc)) $imgSrc = '../../'.$imgSrc; ?>
                <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($item['title']); ?>
                    <?php if ($item['is_highlight']): ?><span class="text-[9px] text-amber-400 font-black"> ★ Gợi ý</span><?php endif; ?>
                </p>
                <p class="text-[10px] text-amber-400"><?php echo number_format($item['price'],0,',','.'); ?>đ</p>
            </div>
            <div class="flex gap-1">
                <form method="POST"><input type="hidden" name="action" value="toggle_highlight"><input type="hidden" name="dm_id" value="<?php echo $item['id']; ?>">
                    <button class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 flex items-center justify-center transition" title="Toggle gợi ý">
                        <i data-lucide="star" class="w-3 h-3 <?php echo $item['is_highlight']?'fill-current':''; ?>"></i>
                    </button>
                </form>
                <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="dm_id" value="<?php echo $item['id']; ?>">
                    <button class="w-7 h-7 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 flex items-center justify-center transition">
                        <i data-lucide="trash-2" class="w-3 h-3"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php include 'admin_layout_close.php'; ?>

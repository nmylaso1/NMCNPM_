<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
require_once '../includes/config.php';

$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'reviews';
$page_title   = 'Quản lý Đánh giá';

$msg = ''; $msgType = '';

// ─── POST Actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get review info to update product rating
            $reviewRes = $conn->query("SELECT product_id FROM product_reviews WHERE id=$id");
            if ($reviewRes && $review = $reviewRes->fetch_assoc()) {
                $pid = (int)$review['product_id'];
                $conn->query("DELETE FROM product_reviews WHERE id=$id");
                // Recalculate product rating
                $avgRes = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM product_reviews WHERE product_id=$pid");
                $avgData = $avgRes->fetch_assoc();
                $newRating = round((float)($avgData['avg_rating'] ?? 0), 1);
                $newCount  = (int)($avgData['cnt'] ?? 0);
                $conn->query("UPDATE products SET rating=$newRating, reviews_count=$newCount WHERE id=$pid");
            }
            $msg = 'Đã xóa đánh giá.'; $msgType = 'success';
        }
    }
}

// ─── Filtering ─────────────────────────────────────────────────────────────
$filterProduct = intval($_GET['product'] ?? 0);
$filterRating  = intval($_GET['rating'] ?? 0);

$where = "1=1";
if ($filterProduct > 0) $where .= " AND pr.product_id = $filterProduct";
if ($filterRating > 0 && $filterRating <= 5) $where .= " AND pr.rating = $filterRating";

// ─── Fetch reviews ─────────────────────────────────────────────────────────
$reviews = [];
$res = $conn->query("
    SELECT pr.*, 
           a.full_name, a.email,
           p.title AS product_title, p.image AS product_image
    FROM product_reviews pr
    LEFT JOIN accounts a ON a.id = pr.user_id
    LEFT JOIN products p ON p.id = pr.product_id
    WHERE $where
    ORDER BY pr.created_at DESC
    LIMIT 100
");
if ($res) while ($r = $res->fetch_assoc()) $reviews[] = $r;

// Stats
$totalReviews = 0;
$avgRating    = 0;
$statRes = $conn->query("SELECT COUNT(*) as cnt, AVG(rating) as avg FROM product_reviews");
if ($statRes) {
    $stat = $statRes->fetch_assoc();
    $totalReviews = (int)$stat['cnt'];
    $avgRating    = round((float)$stat['avg'], 1);
}

// Products for filter dropdown
$products = [];
$pRes = $conn->query("SELECT id, title FROM products ORDER BY title ASC");
if ($pRes) while ($pr = $pRes->fetch_assoc()) $products[] = $pr;

include 'admin_layout_open.php';
?>

<?php if ($msg): ?>
<div class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="<?php echo $msgType==='success'?'check-circle':'alert-triangle'; ?>" class="w-4 h-4 mt-0.5 shrink-0"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-xl font-extrabold text-white">Đánh giá sản phẩm</h2>
        <p class="text-xs text-slate-500 mt-1">Kiểm duyệt và quản lý đánh giá từ khách hàng</p>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
    <div class="content-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-amber-500/10 text-amber-500 rounded-xl flex items-center justify-center">
            <i data-lucide="message-square" class="w-5 h-5"></i>
        </div>
        <div>
            <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Tổng đánh giá</p>
            <p class="text-xl font-black text-white"><?php echo $totalReviews; ?></p>
        </div>
    </div>
    <div class="content-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-yellow-500/10 text-yellow-500 rounded-xl flex items-center justify-center">
            <i data-lucide="star" class="w-5 h-5"></i>
        </div>
        <div>
            <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Điểm TB</p>
            <p class="text-xl font-black text-white"><?php echo $avgRating; ?> <span class="text-sm text-slate-500">/ 5</span></p>
        </div>
    </div>
    <div class="content-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-500/10 text-emerald-500 rounded-xl flex items-center justify-center">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
        <div>
            <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Sản phẩm</p>
            <p class="text-xl font-black text-white"><?php echo count($products); ?></p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="flex flex-wrap gap-3 mb-6">
    <form method="GET" class="flex gap-2 flex-wrap">
        <select name="product" class="px-4 py-2 text-sm rounded-xl bg-slate-800 border border-white/10 text-white outline-none">
            <option value="0">Tất cả sản phẩm</option>
            <?php foreach ($products as $pr): ?>
            <option value="<?php echo $pr['id']; ?>" <?php echo $filterProduct == $pr['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pr['title']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="rating" class="px-4 py-2 text-sm rounded-xl bg-slate-800 border border-white/10 text-white outline-none">
            <option value="0">Tất cả sao</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?php echo $i; ?>" <?php echo $filterRating == $i ? 'selected' : ''; ?>><?php echo $i; ?> sao</option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-xl transition">Lọc</button>
        <?php if ($filterProduct || $filterRating): ?>
        <a href="manage_reviews.php" class="px-4 py-2 bg-slate-700 text-slate-300 text-sm font-bold rounded-xl hover:bg-slate-600 transition">Xóa bộ lọc</a>
        <?php endif; ?>
    </form>
</div>

<!-- Reviews Table -->
<div class="content-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-white/5 text-slate-500 text-left text-xs uppercase tracking-wider">
                    <th class="px-5 py-4">#</th>
                    <th class="px-5 py-4">Người dùng</th>
                    <th class="px-5 py-4">Sản phẩm</th>
                    <th class="px-5 py-4">Điểm</th>
                    <th class="px-5 py-4">Nhận xét</th>
                    <th class="px-5 py-4">Ngày</th>
                    <th class="px-5 py-4 text-center">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (empty($reviews)): ?>
                <tr><td colspan="7" class="text-center py-12 text-slate-500 italic">Chưa có đánh giá nào.</td></tr>
                <?php else: foreach ($reviews as $rv): ?>
                <tr class="hover:bg-white/5 transition">
                    <td class="px-5 py-3.5 text-slate-600 font-mono text-xs">#<?php echo $rv['id']; ?></td>
                    <td class="px-5 py-3.5">
                        <p class="font-bold text-white"><?php echo htmlspecialchars($rv['full_name'] ?? 'Ẩn danh'); ?></p>
                        <p class="text-[11px] text-slate-500"><?php echo htmlspecialchars($rv['email'] ?? ''); ?></p>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <?php if ($rv['product_image']): ?>
                            <img src="<?php
                                $img = $rv['product_image'];
                                echo htmlspecialchars(preg_match('#^https?://#', $img) ? $img : '../../' . ltrim($img, '/'));
                            ?>" class="w-8 h-8 rounded-lg object-cover border border-white/10" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span class="font-semibold text-slate-300 truncate max-w-[200px]"><?php echo htmlspecialchars($rv['product_title'] ?? '—'); ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <svg class="w-4 h-4 <?php echo $s <= $rv['rating'] ? 'text-amber-400' : 'text-slate-700'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <?php endfor; ?>
                            <span class="text-xs font-bold text-slate-400 ml-1"><?php echo $rv['rating']; ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <p class="text-xs text-slate-400 italic break-words max-w-[200px]">
                            <?php echo htmlspecialchars($rv['comment'] ?? '—'); ?>
                        </p>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-500"><?php echo date('d/m/Y H:i', strtotime($rv['created_at'])); ?></td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-center">
                            <form method="POST" class="inline" onsubmit="return confirm('Xóa đánh giá này? Rating sản phẩm sẽ được tính lại.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $rv['id']; ?>">
                                <button class="text-xs px-3 py-1.5 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 font-semibold transition">
                                    <i data-lucide="trash-2" class="w-3 h-3 inline"></i> Xóa
                                </button>
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

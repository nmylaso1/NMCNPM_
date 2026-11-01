<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../../login.php'); exit;
}
$admin_name   = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'settings';
$page_title   = 'Cài đặt hệ thống';

require_once __DIR__ . '/../includes/config.php';

$defaultSettings = [
    'canteen_name'    => 'Nhà Hàng Gourmet',
    'canteen_hotline' => '0909 123 456',
    'canteen_address' => '123 Đường Ẩm Thực, Quận 1, TP.HCM',
    'open_time'       => '06:30',
    'close_time'      => '21:00',
    'delivery_fee'    => '15000',
    'free_delivery_from' => '150000',
];

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    foreach ($defaultSettings as $key => $_) {
        $val = $conn->real_escape_string(trim($_POST[$key] ?? ''));
        $conn->query("INSERT INTO canteen_settings (setting_key,setting_value) VALUES ('$key','$val')
                      ON DUPLICATE KEY UPDATE setting_value='$val'");
    }
    $msg = 'Đã lưu cài đặt!'; $msgType = 'success';
}

// Load current settings
$settings = $defaultSettings;
$sres = $conn->query("SELECT setting_key, setting_value FROM canteen_settings");
if ($sres) while ($r = $sres->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];

include __DIR__ . '/admin_layout_open.php';
?>

<?php if ($msg): ?>
<div class="mb-5 flex items-start gap-3 p-4 rounded-2xl <?php echo $msgType==='success'?'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400':'bg-red-500/10 border border-red-500/20 text-red-400'; ?>">
    <i data-lucide="check-circle" class="w-4 h-4 mt-0.5"></i>
    <p class="text-sm font-semibold"><?php echo htmlspecialchars($msg); ?></p>
</div>
<?php endif; ?>

<form method="POST" class="space-y-5 max-w-3xl">
    <input type="hidden" name="action" value="save">

    <div class="content-card p-6">
        <h3 class="text-sm font-extrabold text-white mb-5 flex items-center gap-2"><i data-lucide="store" class="w-4 h-4 text-amber-400"></i> Thông tin nhà hàng</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php $fields = [
                ['canteen_name','Tên nhà hàng','text'],
                ['canteen_hotline','Số điện thoại','text'],
                ['canteen_address','Địa chỉ','text'],
            ];
            foreach ($fields as [$key,$label,$type]): ?>
            <div>
                <label class="text-xs text-slate-400 font-semibold mb-1.5 block"><?php echo $label; ?></label>
                <input type="<?php echo $type; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($settings[$key]); ?>"
                    class="w-full px-4 py-2.5 bg-slate-800 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/40">
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content-card p-6">
        <h3 class="text-sm font-extrabold text-white mb-5 flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-sky-400"></i> Giờ hoạt động</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-slate-400 font-semibold mb-1.5 block">Giờ mở cửa</label>
                <input type="time" name="open_time" value="<?php echo htmlspecialchars($settings['open_time']); ?>"
                    class="w-full px-4 py-2.5 bg-slate-800 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/40">
            </div>
            <div>
                <label class="text-xs text-slate-400 font-semibold mb-1.5 block">Giờ đóng cửa</label>
                <input type="time" name="close_time" value="<?php echo htmlspecialchars($settings['close_time']); ?>"
                    class="w-full px-4 py-2.5 bg-slate-800 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/40">
            </div>
        </div>
    </div>

    <div class="content-card p-6">
        <h3 class="text-sm font-extrabold text-white mb-5 flex items-center gap-2"><i data-lucide="truck" class="w-4 h-4 text-emerald-400"></i> Phí giao hàng</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-slate-400 font-semibold mb-1.5 block">Phí giao (VNĐ)</label>
                <input type="number" min="0" step="500" name="delivery_fee" value="<?php echo htmlspecialchars($settings['delivery_fee']); ?>"
                    class="w-full px-4 py-2.5 bg-slate-800 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/40">
            </div>
            <div>
                <label class="text-xs text-slate-400 font-semibold mb-1.5 block">Miễn phí từ (VNĐ)</label>
                <input type="number" min="0" step="1000" name="free_delivery_from" value="<?php echo htmlspecialchars($settings['free_delivery_from']); ?>"
                    class="w-full px-4 py-2.5 bg-slate-800 border border-white/10 rounded-xl text-white text-sm outline-none focus:border-amber-500/40">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-amber-500 to-amber-700 text-white font-extrabold rounded-xl hover:from-amber-600 hover:to-amber-800 transition shadow-lg flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i> Lưu cài đặt
        </button>
    </div>
</form>

<?php include __DIR__ . '/admin_layout_close.php'; ?>

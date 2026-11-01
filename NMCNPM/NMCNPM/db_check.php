<?php
/**
 * db_check.php — Công cụ kiểm tra kết nối Database nhanh
 * Chạy: http://localhost:8000/db_check.php
 *
 * ⚠️ XÓA FILE NÀY TRƯỚC KHI DEPLOY LÊN PRODUCTION
 */

require_once __DIR__ . '/src/includes/config.php';

header('Content-Type: text/html; charset=utf-8');

$ok    = '#22c55e';
$err   = '#ef4444';
$warn  = '#f59e0b';
$info  = '#38bdf8';

function row(string $label, bool|string $status, string $detail = '', string $color = ''): void
{
    $icon   = $status === true  ? '✅' : ($status === false ? '❌' : 'ℹ️');
    $bg     = $status === true  ? '#14532d' : ($status === false ? '#450a0a' : '#0c2231');
    $border = $status === true  ? '#16a34a' : ($status === false ? '#dc2626' : '#0ea5e9');
    echo "<div style='background:$bg;border-left:4px solid $border;padding:12px 16px;border-radius:8px;margin-bottom:8px;font-size:14px'>
            <span style='font-weight:700'>$icon $label</span>
            <span style='color:#94a3b8;margin-left:12px'>$detail</span>
          </div>";
}

$start = microtime(true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🔌 DB Check — Nhà Hàng Gourmet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 680px; margin: 0 auto; }
        h1 { font-size: 24px; margin-bottom: 6px; }
        .subtitle { color: #64748b; font-size: 13px; margin-bottom: 28px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin: 24px 0 10px; }
        .tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 99px; background: #1e3a5f; color: #38bdf8; margin-left: 8px; vertical-align: middle; }
        code { background: #1e293b; padding: 2px 6px; border-radius: 4px; font-size: 12px; color: #fbbf24; }
        .footer { margin-top: 32px; padding: 16px; background: #1e293b; border-radius: 10px; font-size: 12px; color: #64748b; text-align: center; }
        .footer b { color: #f87171; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔌 Database Connection Check</h1>
    <p class="subtitle">Nhà Hàng Gourmet — Chạy lúc: <?php echo date('d/m/Y H:i:s'); ?></p>

    <h2>⚙️ Cấu hình .env</h2>
    <?php
    $host = env_value('DB_HOST', '?');
    $user = env_value('DB_USER', '?');
    $pass = env_value('DB_PASS', '');
    $name = env_value('DB_NAME', '?');
    $port = env_value('DB_PORT', '?');
    row('DB_HOST', 'info', $host);
    row('DB_USER', 'info', $user);
    row('DB_PASS', 'info', $pass !== '' ? str_repeat('*', min(strlen($pass), 8)) . '  (' . strlen($pass) . ' ký tự)' : '⚠️ TRỐNG');
    row('DB_NAME', 'info', $name);
    row('DB_PORT', 'info', $port);
    ?>

    <h2>🔗 Kết nối MySQL</h2>
    <?php
    if ($conn) {
        $ms = round((microtime(true) - $start) * 1000, 1);
        row('Kết nối thành công', true, "Thời gian: {$ms}ms");

        // Kiểm tra charset
        $csRes = $conn->query("SHOW VARIABLES LIKE 'character_set_connection'");
        $cs = $csRes ? $csRes->fetch_assoc()['Value'] : '?';
        row('Charset', $cs === 'utf8mb4', $cs);

        // Kiểm tra timezone
        $tzRes = $conn->query("SELECT @@session.time_zone as tz, NOW() as now");
        $tzRow = $tzRes ? $tzRes->fetch_assoc() : null;
        row('Timezone', 'info', ($tzRow['tz'] ?? '?') . ' — Giờ server: ' . ($tzRow['now'] ?? '?'));
    } else {
        row('Kết nối thất bại', false, "Host: $host | Port: $port | DB: $name | User: $user");
    }
    ?>

    <?php if ($conn): ?>
    <h2>📊 Trạng thái các bảng</h2>
    <?php
    $tables = [
        'accounts'            => 'Tài khoản',
        'products'            => 'Món ăn',
        'categories_products' => 'Danh mục',
        'orders'              => 'Đơn hàng',
        'order_items'         => 'Chi tiết đơn',
        'carts'               => 'Giỏ hàng',
        'coupons'             => 'Mã giảm giá',
        'reservations'        => 'Đặt bàn',
        'product_reviews'     => 'Đánh giá',
        'shipping_policies'   => 'Phí giao hàng',
        'canteen_settings'    => 'Cài đặt nhà hàng',
        'daily_menu'          => 'Thực đơn ngày (legacy)',
    ];
    foreach ($tables as $table => $label) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM `$table`");
        if ($res) {
            $count = (int)$res->fetch_assoc()['c'];
            $note  = $table === 'daily_menu' ? ' — (bảng cũ, không còn dùng)' : '';
            row("$label <code>$table</code>", true, "$count bản ghi$note");
        } else {
            row("$label <code>$table</code>", false, 'Bảng chưa tồn tại — cần import database/database.sql');
        }
    }
    ?>

    <h2>👤 Tài khoản admin</h2>
    <?php
    $adminRes = $conn->query("SELECT id, full_name, email, role, created_at FROM accounts WHERE role='admin' LIMIT 5");
    if ($adminRes && $adminRes->num_rows > 0) {
        while ($a = $adminRes->fetch_assoc()) {
            row("#{$a['id']} {$a['full_name']}", true, "{$a['email']} — role: <b>{$a['role']}</b> — tạo: {$a['created_at']}");
        }
    } else {
        row('Chưa có tài khoản admin', false, 'Cần import database/database.sql hoặc INSERT thủ công');
    }
    ?>
    <?php endif; ?>

    <div class="footer">
        ⚠️ <b>Xóa file này trước khi nộp / deploy!</b>
        File: <code>db_check.php</code> — chỉ dùng để debug nội bộ
    </div>
</div>
</body>
</html>


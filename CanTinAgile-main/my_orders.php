<?php
session_start();

require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$canteenSettings = getCanteenSettings($conn);

$user_id = (int) $_SESSION['user_id'];
$full_name_session = $_SESSION['full_name'] ?? 'Người dùng';

// Tab filter (Trạng thái đơn hàng)
$tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all', 'pending', 'paid', 'rejected', 'cancelled'];
if (!in_array($tab, $allowed_tabs))
    $tab = 'all';

// Phân trang
$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page < 1)
    $page = 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Query lấy tổng số đơn hàng để phân trang
$where_clause = "user_id = $user_id";
if ($tab !== 'all') {
    $tab_escaped = $conn->real_escape_string($tab);
    $where_clause .= " AND status = '$tab_escaped'";
}

$count_res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE $where_clause");
$total_orders = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Query lấy danh sách đơn hàng của user có LIMIT
$orders_res = $conn->query("
    SELECT * FROM orders 
    WHERE $where_clause 
    ORDER BY created_at DESC
    LIMIT $offset, $per_page
");

$orders = [];
if ($orders_res) {
    while ($row = $orders_res->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Lấy items của từng đơn hàng kèm theo ảnh từ bảng products
$order_items_map = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $ids_csv = implode(',', $order_ids);
    $items_res = $conn->query("
        SELECT oi.*, 
               p.image, p.description, p.calories, p.prep_time, p.ingredients,
               p.is_vegetarian, p.is_highlight, p.rating, p.reviews_count, p.sales_count,
               cp.name AS category_name
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        LEFT JOIN categories_products cp ON cp.id = p.category_id
        WHERE oi.order_id IN ($ids_csv)
        ORDER BY oi.id ASC
    ");
    if ($items_res) {
        while ($item = $items_res->fetch_assoc()) {
            $order_items_map[$item['order_id']][] = $item;
        }
    }
}

// Lấy thông tin giỏ hàng để hiện badge
syncSessionCartFromDb($conn, (int) $user_id);
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int) ($item['quantity'] ?? 0);
    }
}

// Lấy thông tin page agent
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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'HDPTTM Canteen'); ?>
    </title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap&subset=vietnamese"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <style>
        * {
            font-family: 'Quicksand', sans-serif;
        }

        body {
            background: #fdfaf6;
            color: #423826;
            min-height: 100vh;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #f1f1f1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-cooking {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-paid {
            background: #dcfce7;
            color: #15803d;
        }

        .status-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-cancelled {
            background: #f3f4f6;
            color: #4b5563;
        }

        .tab-btn {
            padding: 8px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-active {
            background: #d97706;
            color: white;
        }

        .tab-inactive {
            background: white;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }

        .tab-inactive:hover {
            color: #d97706;
            border-color: #fcd34d;
        }

        .price-text {
            color: #d97706;
            font-weight: 800;
        }

        /* Pagination Styling */
        .pg-btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.2s;
        }

        .pg-active {
            background: #d97706;
            color: white;
        }

        .pg-inactive {
            background: white;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }

        .pg-inactive:hover {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #d97706;
        }

        .pg-disabled {
            background: #f9fafb;
            color: #d1d5db;
            border: 1px solid #f3f4f6;
            cursor: not-allowed;
        }
    </style>
</head>

<body class="flex flex-col">

    <!-- NAVBAR -->
    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1">
                        <?php
                        $cName = $canteenSettings['canteen_name'] ?? 'CănTin';
                        $parts = explode(' ', $cName, 2);
                        if (count($parts) > 1) {
                            echo htmlspecialchars($parts[0]) . ' <span>' . htmlspecialchars($parts[1]) . '</span>';
                        } else {
                            echo htmlspecialchars($cName);
                        }
                        ?>
                    </div>
                </a>
                <div class="navbar-items">
                    <a href="dashboard.php" class="navbar-item">
                        <i data-lucide="house" class="w-4 h-4"></i>
                        Trang chủ
                    </a>
                    <a href="dashboard.php#thucdon-section" class="navbar-item">
                        <i data-lucide="utensils-crossed" class="w-4 h-4"></i>
                        Thực đơn
                    </a>
                    <a href="profile.php" class="navbar-item">
                        <i data-lucide="user-circle" class="w-4 h-4"></i>
                        Hồ sơ
                    </a>
                    <a href="order.php" class="navbar-item relative pr-8">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <?php if ($cart_count > 0): ?>
                            <span
                                class="absolute -top-1 -right-1 bg-white text-[#d97706] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-amber-200"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                        <a href="src/admin/index.php" class="navbar-item"
                            style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:12px;padding:6px 14px;">
                            <i data-lucide="<?php echo $_SESSION['role'] === 'admin' ? 'shield-check' : 'briefcase'; ?>" class="w-4 h-4"></i>
                            <?php echo $_SESSION['role'] === 'admin' ? 'Admin' : 'Quản lý'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 py-8 relative z-10 w-full flex-grow">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <a href="profile.php"
                    class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-amber-600 shadow-sm border border-amber-100 hover:bg-amber-50">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-[#2d261a]">Đơn hàng của tôi</h1>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-0.5">Lịch sử đặt món (Trang
                        <?php echo $page; ?>/<?php echo max(1, $total_pages); ?>)</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2 scrollbar-hide">
            <?php
            $tabs_labels = [
                'all' => 'Tất cả',
                'pending' => '⏳ Chờ duyệt',
                'cooking' => '🔥 Đang nấu',
                'paid' => '✅ Đã nhận',
                'rejected' => '❌ Từ chối',
                'cancelled' => '🗑️ Đã hủy'
            ];
            foreach ($tabs_labels as $key => $label):
                ?>
                <a href="?tab=<?php echo $key; ?>&p=1"
                    class="tab-btn <?php echo $tab === $key ? 'tab-active' : 'tab-inactive'; ?>">
                    <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Orders -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-3xl p-16 text-center shadow-sm border border-gray-100">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="shopping-basket" class="w-10 h-10 text-amber-300"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Chưa có đơn hàng</h3>
                <p class="text-gray-400 font-medium mb-8">Bạn chưa có đơn hàng nào trong trạng thái này.</p>
                <a href="dashboard.php"
                    class="bg-amber-500 text-white px-8 py-3 rounded-xl font-bold hover:bg-amber-600 transition">
                    Tiếp tục mua sắm
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6 mb-12">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card p-6">
                        <!-- Header -->
                        <div class="flex flex-wrap justify-between items-start gap-4 mb-6 pb-6 border-b border-gray-50">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <span
                                        class="font-bold text-[#2d261a]">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                                    <?php
                                    $status_cls = "status-" . $order['status'];
                                    $labels = [
                                        'pending' => 'Chờ xác nhận',
                                        'cooking' => 'Đang nấu',
                                        'paid' => 'Đã hoàn tất',
                                        'rejected' => 'Bị từ chối',
                                        'cancelled' => 'Đã hủy'
                                    ];
                                    $status_txt = $labels[$order['status']] ?? $order['status'];
                                    ?>
                                    <span class="status-badge <?php echo $status_cls; ?>"><?php echo $status_txt; ?></span>
                                </div>
                                <p class="text-[11px] text-gray-400 font-bold uppercase tracking-wide">
                                    📅 <?php echo date('d/m/Y - H:i', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Tổng cộng</p>
                                <p class="text-xl font-black price-text">
                                    <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ</p>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="space-y-4 mb-6">
                            <?php
                            $items = $order_items_map[$order['id']] ?? [];
                            foreach ($items as $item):
                                $img = $item['image'] ?: 'assets/img/paimon-thinking.png';
                                ?>
                                <div class="item-row flex items-center gap-4 py-2 cursor-pointer group hover:bg-amber-50/50 rounded-xl transition-colors px-2 -mx-2"
                                    onclick='openFoodDetailModal(<?php echo htmlspecialchars(json_encode([
                                        "id" => $item["product_id"],
                                        "title" => $item["product_name"],
                                        "price" => (float) $item["unit_price"],
                                        "image" => (string) $item["image"],
                                        "description" => (string) $item["description"],
                                        "calories" => (int) $item["calories"],
                                        "prep_time" => (string) $item["prep_time"],
                                        "ingredients" => (string) $item["ingredients"],
                                        "is_vegetarian" => (int) $item["is_vegetarian"],
                                        "is_highlight" => (int) $item["is_highlight"],
                                        "rating" => (float) $item["rating"],
                                        "reviews_count" => (int) $item["reviews_count"],
                                        "sales_count" => (int) $item["sales_count"],
                                        "category_name" => (string) $item["category_name"]
                                    ], JSON_HEX_APOS), ENT_QUOTES); ?>)'>
                                    <div
                                        class="w-14 h-14 rounded-xl overflow-hidden bg-gray-50 shrink-0 border border-gray-100 group-hover:border-amber-200 transition-colors">
                                        <img src="<?php echo htmlspecialchars($img); ?>"
                                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                            loading="lazy">
                                    </div>
                                    <div class="flex-grow">
                                        <h3 class="font-bold text-sm text-gray-800">
                                            <?php echo htmlspecialchars($item['product_name']); ?></h3>
                                        <p class="text-xs text-gray-400">Số lượng: <?php echo $item['quantity']; ?></p>
                                    </div>
                                    <div class="text-right flex flex-col items-end gap-2">
                                        <p class="font-bold text-sm"><?php echo number_format($item['line_total'], 0, ',', '.'); ?>đ</p>
                                        <?php if ($order['status'] === 'paid'): ?>
                                            <button type="button" 
                                                class="text-[10px] font-black bg-amber-500 text-white px-2.5 py-1 rounded-lg hover:bg-amber-600 transition shadow-sm shadow-amber-500/20 flex items-center gap-1"
                                                onclick="event.stopPropagation(); openFoodDetailModal(<?php echo htmlspecialchars(json_encode([
                                                    "id" => $item["product_id"],
                                                    "title" => $item["product_name"],
                                                    "price" => (float) $item["unit_price"],
                                                    "image" => (string) $item["image"],
                                                    "description" => (string) $item["description"],
                                                    "calories" => (int) $item["calories"],
                                                    "prep_time" => (string) $item["prep_time"],
                                                    "ingredients" => (string) $item["ingredients"],
                                                    "is_vegetarian" => (int) $item["is_vegetarian"],
                                                    "is_highlight" => (int) $item["is_highlight"],
                                                    "rating" => (float) $item["rating"],
                                                    "reviews_count" => (int) $item["reviews_count"],
                                                    "sales_count" => (int) $item["sales_count"],
                                                    "category_name" => (string) $item["category_name"]
                                                ], JSON_HEX_APOS), ENT_QUOTES); ?>)">
                                                <i data-lucide="star" class="w-3 h-3 fill-current"></i> Đánh giá
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ((int) $order['shipping_fee'] > 0): ?>
                                <div
                                    class="flex justify-between items-center py-2.5 px-4 bg-amber-50/50 rounded-2xl border border-dashed border-amber-200 mt-4 mb-1">
                                    <div
                                        class="flex items-center gap-2.5 text-[10px] font-extrabold text-amber-800 uppercase tracking-wider">
                                        <div
                                            class="w-7 h-7 bg-amber-500 rounded-lg flex items-center justify-center text-white shadow-sm">
                                            <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                                        </div>
                                        Phí giao hàng
                                    </div>
                                    <span
                                        class="font-black text-amber-600 text-sm">+<?php echo number_format($order['shipping_fee'], 0, ',', '.'); ?>đ</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-50">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php
                                $pm = $order['payment_method'] ?? 'direct';
                                ?>
                                <div
                                    class="h-8 text-[11px] font-bold text-gray-500 bg-gray-50 px-3 rounded-lg border border-gray-100 flex items-center gap-1.5 whitespace-nowrap">
                                    <i data-lucide="<?php echo $pm === 'payos' ? 'credit-card' : 'banknote'; ?>"
                                        class="w-3.5 h-3.5 shrink-0"></i>
                                    <?php echo $pm === 'payos' ? 'Chuyển khoản' : 'Tiền mặt'; ?>
                                </div>

                                <?php if ($order['delivery_room']): ?>
                                    <div
                                        class="h-8 text-[11px] font-bold text-gray-500 bg-gray-50 px-3 rounded-lg border border-gray-100 flex items-center gap-1.5 whitespace-nowrap">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 shrink-0"></i>
                                        <span class="max-w-[100px] truncate">Phòng:
                                            <?php echo htmlspecialchars($order['delivery_room']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['note']):
                                    // Tách note của user và thông tin hệ thống
                                    $raw_note = (string) $order['note'];
                                    $parts = explode('--- [Hệ thống] ---', $raw_note);
                                    $display_note = trim($parts[0]);

                                    // Legacy cleanup (for older orders)
                                    $display_note = preg_replace('/--- Thanh toán PayOS ---\nID: .*/s', '', $display_note);
                                    $display_note = preg_replace('/\[PayOS ID: .*\]/si', '', $display_note);
                                    $display_note = preg_replace('/\[PAYOS_.*\]/si', '', $display_note);
                                    $display_note = trim($display_note);

                                    if ($display_note):
                                        ?>
                                        <div
                                            class="h-8 text-[11px] font-bold text-gray-500 bg-gray-50 px-3 rounded-lg border border-gray-100 flex items-center gap-1.5 whitespace-nowrap">
                                            <i data-lucide="sticky-note" class="w-3.5 h-3.5 shrink-0"></i>
                                            <span class="max-w-[150px] truncate">Ghi chú:
                                                <?php echo htmlspecialchars($display_note); ?></span>
                                        </div>
                                    <?php endif; endif; ?>
                            </div>

                            <?php if ($order['status'] === 'pending'): ?>
                                <button
                                    onclick="cancelOrder(<?php echo $order['id']; ?>, '<?php echo addslashes($order['order_code']); ?>')"
                                    class="text-xs font-bold text-red-500 hover:bg-red-50 px-4 py-2 rounded-lg border border-red-100 transition">
                                    Hủy đơn
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2">
                    <!-- First/Prev -->
                    <?php if ($page > 1): ?>
                        <a href="?tab=<?php echo $tab; ?>&p=<?php echo $page - 1; ?>" class="pg-btn pg-inactive"><i
                                data-lucide="chevron-left" class="w-4 h-4"></i></a>
                    <?php else: ?>
                        <span class="pg-btn pg-disabled"><i data-lucide="chevron-left" class="w-4 h-4"></i></span>
                    <?php endif; ?>

                    <!-- Page numbers -->
                    <?php
                    $start_pg = max(1, $page - 2);
                    $end_pg = min($total_pages, $page + 2);
                    for ($i = $start_pg; $i <= $end_pg; $i++):
                        ?>
                        <a href="?tab=<?php echo $tab; ?>&p=<?php echo $i; ?>"
                            class="pg-btn <?php echo $i === $page ? 'pg-active' : 'pg-inactive'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next/Last -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?tab=<?php echo $tab; ?>&p=<?php echo $page + 1; ?>" class="pg-btn pg-inactive"><i
                                data-lucide="chevron-right" class="w-4 h-4"></i></a>
                    <?php else: ?>
                        <span class="pg-btn pg-disabled"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <!-- Footer removed -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        async function cancelOrder(orderId, orderCode) {
            const result = await Swal.fire({
                title: '<span style="font-family:\'Quicksand\';font-weight:800;">Hủy đơn hàng?</span>',
                text: `Bạn muốn hủy đơn ${orderCode}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'Đúng, hủy nó!',
                cancelButtonText: 'Không, quay lại',
                customClass: { popup: 'rounded-2xl' }
            });

            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'cancel_prepared_order');
                fd.append('order_id', orderId);

                try {
                    const res = await fetch('order.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Đã hủy!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false,
                            customClass: { popup: 'rounded-2xl' }
                        });
                        setTimeout(() => location.reload(), 2100);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message, customClass: { popup: 'rounded-2xl' } });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Vui lòng thử lại sau.', customClass: { popup: 'rounded-2xl' } });
                }
            }
        }
    </script>
    <?php if ($pageAgentScriptSrc): ?>
        <style>
            #page-agent-runtime_agent-panel:not([data-pa-ui-enhanced="1"]) {
                display: none !important;
            }
        </style>
        <script>
            window.__PAGE_AGENT_SCRIPT_SRC__ = <?php echo json_encode($pageAgentScriptSrc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        </script>
        <!-- Alibaba Page Agent -->
        <script src="<?php echo htmlspecialchars($pageAgentScriptSrc, ENT_QUOTES, 'UTF-8'); ?>" crossorigin="true"></script>
    <?php endif; ?>
    <?php include 'src/includes/food_detail_modal.php'; ?>
    <script
        src="assets/js/page-agent-ui.js?v=<?php echo rawurlencode((string) (@filemtime(__DIR__ . '/assets/js/page-agent-ui.js') ?: time())); ?>"></script>
    <?php include 'src/includes/order_tracking_widget.php'; ?>
</body>

</html>
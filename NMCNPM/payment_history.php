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

// Xử lý bộ lọc
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Xây dựng câu truy vấn SQL
$where_clauses = ["user_id = $user_id"];
if ($status_filter !== 'all') {
    $where_clauses[] = "status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($start_date) {
    $where_clauses[] = "created_at >= '" . $conn->real_escape_string($start_date) . " 00:00:00'";
}
if ($end_date) {
    $where_clauses[] = "created_at <= '" . $conn->real_escape_string($end_date) . " 23:59:59'";
}

$where_sql = implode(" AND ", $where_clauses);

// Phân trang
$page = isset($_GET['p']) ? (int) $_GET['p'] : 1;
if ($page < 1) $page = 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$count_res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE $where_sql");
$total_transactions = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_transactions / $per_page);

// Query lấy danh sách giao dịch
$orders_res = $conn->query("
    SELECT * FROM orders 
    WHERE $where_sql 
    ORDER BY created_at DESC
    LIMIT $offset, $per_page
");

$orders = [];
if ($orders_res) {
    while ($row = $orders_res->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Lấy items của từng đơn hàng (để hiển thị tóm tắt)
$order_items_summary = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $ids_csv = implode(',', $order_ids);
    $items_res = $conn->query("
        SELECT order_id, product_name, quantity 
        FROM order_items 
        WHERE order_id IN ($ids_csv)
    ");
    if ($items_res) {
        while ($item = $items_res->fetch_assoc()) {
            $order_items_summary[$item['order_id']][] = $item['product_name'] . ' (x' . $item['quantity'] . ')';
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử thanh toán - <?php echo htmlspecialchars($canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet'); ?></title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/header.css">
    <script src="assets/js/header-transition.js" defer></script>
    <style>
        * { font-family: 'Quicksand', sans-serif; }
        body { background: #fdfaf6; color: #423826; min-height: 100vh; }
        .transaction-card {
            background: white;
            border-radius: 24px;
            border: 1px solid #f1f1f1;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .transaction-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04); }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }
        .status-cooking { background: #dbeafe; color: #1e40af; }

        .filter-input {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 10px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            outline: none;
            transition: all 0.2s;
        }
        .filter-input:focus { border-color: #d97706; box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.1); }
        
        .pg-btn {
            width: 44px; height: 44px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 14px; font-weight: 700; transition: all 0.2s;
        }
        .pg-active { background: #d97706; color: white; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
        .pg-inactive { background: white; color: #6b7280; border: 1px solid #e5e7eb; }
        .pg-inactive:hover { background: #fffbeb; border-color: #fcd34d; color: #d97706; }
    </style>
</head>
<body class="flex flex-col">
    <!-- NAVBAR -->
    <div class="navbar-sticky">
        <div class="navbar-inner">
            <div class="navbar-row">
                <a href="dashboard.php" class="navbar-brand hover:opacity-80 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo" class="navbar-logo group-hover:rotate-12 transition-transform duration-300">
                    <div class="flex items-center gap-1 whitespace-nowrap flex-shrink-0">
                        <?php 
                        $cName = $canteenSettings['canteen_name'] ?? 'Nhà Hàng Gourmet';
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
                    <a href="dashboard.php" class="navbar-item"><i data-lucide="house" class="w-4 h-4"></i> Trang chủ</a>
                    <a href="menu.php" class="navbar-item"><i data-lucide="utensils-crossed" class="w-4 h-4"></i> Thực đơn</a>
                    <a href="profile.php" class="navbar-item"><i data-lucide="user-circle" class="w-4 h-4"></i> Hồ sơ</a>
                    <a href="order.php" class="navbar-item relative pr-7">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Giỏ hàng
                        <?php if ($cart_count > 0): ?>
                            <span id="cart-count-badge" class="absolute -top-1 -right-1 bg-white text-[#d97706] text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm border border-amber-200"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Nút Giới Thiệu (Icon tròn góc riêng) -->
                    <a href="about.php" class="w-8 h-8 rounded-full bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200/80 flex items-center justify-center transition-all ml-1 flex-shrink-0" title="Giới thiệu nhà hàng & Không gian">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 py-8 w-full flex-grow">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <a href="profile.php" class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-amber-600 shadow-sm border border-amber-50">
                        <i data-lucide="chevron-left" class="w-5 h-5"></i>
                    </a>
                    <h1 class="text-2xl font-extrabold text-[#2d261a]">Lịch sử thanh toán</h1>
                </div>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-0.5">Danh sách các giao dịch của bạn</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Từ ngày</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="filter-input w-full">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Đến ngày</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="filter-input w-full">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider ml-1">Trạng thái</label>
                    <select name="status" class="filter-input w-full appearance-none">
                        <option value="all">Tất cả</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Thành công</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Đang xử lý</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Thất bại (Từ chối)</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 h-[46px] bg-[#d97706] text-white font-bold rounded-2xl hover:bg-amber-700 transition shadow-lg shadow-amber-600/20 active:scale-95 flex items-center justify-center gap-2">
                        <i data-lucide="filter" class="w-4 h-4"></i> Lọc
                    </button>
                    <a href="payment_history.php" class="w-[46px] h-[46px] bg-gray-50 text-gray-400 rounded-2xl border border-gray-200 flex items-center justify-center hover:bg-gray-100 transition shadow-sm">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Transactions -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center shadow-sm border border-gray-100 mt-10">
                <div class="w-24 h-24 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="receipt-text" class="w-12 h-12 text-amber-300"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Không tìm thấy giao dịch</h3>
                <p class="text-gray-400 font-medium mb-8">Bạn chưa có giao dịch nào phù hợp với bộ lọc này.</p>
                <a href="dashboard.php" class="bg-[#d97706] text-white px-8 py-3 rounded-2xl font-bold hover:bg-amber-700 transition">
                    Đặt món ngay
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status_map = [
                        'pending' => ['Đang xử lý', 'status-pending', 'clock'],
                        'cooking' => ['Đang chuẩn bị', 'status-cooking', 'flame'],
                        'paid' => ['Thành công', 'status-paid', 'check-circle'],
                        'rejected' => ['Thất bại', 'status-rejected', 'x-circle'],
                        'cancelled' => ['Đã hủy', 'status-cancelled', 'ban']
                    ];
                    $st = $status_map[$order['status']] ?? ['Không rõ', 'status-cancelled', 'help-circle'];
                    ?>
                    <div class="transaction-card p-5 cursor-pointer" onclick="viewDetails(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_code']); ?>')">
                        <div class="flex flex-wrap justify-between items-center gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
                                    <i data-lucide="<?php echo $st[2]; ?>" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="font-bold text-[#2d261a]">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                                        <span class="status-badge <?php echo $st[1]; ?>">
                                            <i data-lucide="<?php echo $st[2]; ?>" class="w-3 h-3"></i>
                                            <?php echo $st[0]; ?>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 font-bold uppercase tracking-wide">
                                        📅 <?php echo date('H:i - d/m/Y', strtotime($order['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex-grow md:max-w-[250px]">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Nội dung thanh toán</p>
                                <p class="text-xs text-gray-600 font-medium truncate">
                                    <?php 
                                    $items = $order_items_summary[$order['id']] ?? [];
                                    echo implode(', ', $items) ?: 'Giao dịch món ăn';
                                    ?>
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Số tiền</p>
                                <p class="text-lg font-black text-[#d97706]">
                                    <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>đ
                                </p>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">
                                    <?php echo $order['payment_method'] === 'payos' ? '💳 PayOS' : '💵 Tiền mặt'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-2 mt-12">
                    <?php if ($page > 1): ?>
                        <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status_filter; ?>&p=<?php echo $page - 1; ?>" class="pg-btn pg-inactive"><i data-lucide="chevron-left" class="w-5 h-5"></i></a>
                    <?php endif; ?>

                    <?php
                    $start_pg = max(1, $page - 2);
                    $end_pg = min($total_pages, $page + 2);
                    for ($i = $start_pg; $i <= $end_pg; $i++):
                    ?>
                        <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status_filter; ?>&p=<?php echo $i; ?>" class="pg-btn <?php echo $i === $page ? 'pg-active' : 'pg-inactive'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status_filter; ?>&p=<?php echo $page + 1; ?>" class="pg-btn pg-inactive"><i data-lucide="chevron-right" class="w-5 h-5"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Detail Modal -->
    <div id="detail-modal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg p-4">
            <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="bg-[#d97706] p-8 text-white relative">
                    <h2 class="text-2xl font-black mb-1">Chi tiết giao dịch</h2>
                    <p class="text-amber-100 font-bold tracking-widest text-sm opacity-80" id="modal-order-code"></p>
                    <button onclick="closeModal()" class="absolute top-8 right-8 text-white/50 hover:text-white transition">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div class="p-8 max-h-[60vh] overflow-y-auto custom-scrollbar">
                    <div id="modal-content" class="space-y-6">
                        <!-- Content loaded via AJAX -->
                        <div class="flex flex-col items-center justify-center py-10 opacity-20">
                            <i data-lucide="loader-2" class="w-10 h-10 animate-spin"></i>
                            <p class="font-bold mt-4">Đang tải dữ liệu...</p>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tổng thanh toán</p>
                        <p class="text-2xl font-black text-[#d97706]" id="modal-total-amount">0đ</p>
                    </div>
                    <button onclick="closeModal()" class="px-8 py-3 bg-white border border-gray-200 text-gray-700 font-bold rounded-2xl hover:bg-gray-100 transition shadow-sm">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        lucide.createIcons();

        function closeModal() {
            document.getElementById('detail-modal').classList.add('hidden');
        }

        async function viewDetails(orderId, orderCode) {
            document.getElementById('modal-order-code').innerText = '#' + orderCode;
            document.getElementById('detail-modal').classList.remove('hidden');
            const content = document.getElementById('modal-content');
            content.innerHTML = '<div class="flex flex-col items-center justify-center py-10 opacity-20"><i data-lucide="loader-2" class="w-10 h-10 animate-spin"></i><p class="font-bold mt-4">Đang tải dữ liệu...</p></div>';
            lucide.createIcons();

            try {
                const res = await fetch(`src/api/get_student_payment_details.php?order_id=${orderId}`);
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('modal-total-amount').innerText = new Intl.NumberFormat('vi-VN').format(data.order.total_amount) + 'đ';
                    
                    let itemsHtml = '<div class="space-y-3">';
                    data.items.forEach(item => {
                        itemsHtml += `
                            <div class="flex items-center justify-between p-4 rounded-2xl bg-gray-50 border border-gray-100">
                                <div>
                                    <p class="font-bold text-gray-800">${item.product_name}</p>
                                    <p class="text-[11px] text-gray-400 font-bold uppercase tracking-wider">Đơn giá: ${new Intl.NumberFormat('vi-VN').format(item.unit_price)}đ</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-amber-600">x${item.quantity}</p>
                                    <p class="font-black text-gray-800">${new Intl.NumberFormat('vi-VN').format(item.unit_price * item.quantity)}đ</p>
                                </div>
                            </div>
                        `;
                    });
                    itemsHtml += '</div>';

                    let summaryHtml = `
                        <div class="grid grid-cols-2 gap-4 pb-6 border-b border-gray-100">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Phương thức</p>
                                <p class="font-bold text-gray-700">${data.order.payment_method === 'payos' ? '💳 PayOS' : '💵 Tiền mặt'}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Thời gian</p>
                                <p class="font-bold text-gray-700">${data.order.created_at}</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-[0.2em] mb-4">Danh sách món</h4>
                            ${itemsHtml}
                        </div>
                    `;

                    if (data.order.note) {
                        summaryHtml += `
                            <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100">
                                <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                                    <i data-lucide="sticky-note" class="w-3 h-3"></i> Ghi chú
                                </p>
                                <p class="text-xs text-amber-800 font-medium">${data.order.note.replace(/\n/g, '<br>')}</p>
                            </div>
                        `;
                    }

                    content.innerHTML = summaryHtml;
                    lucide.createIcons();
                } else {
                    content.innerHTML = `<div class="text-center py-10 text-red-500 font-bold">${data.message}</div>`;
                }
            } catch (e) {
                content.innerHTML = '<div class="text-center py-10 text-red-500 font-bold">Lỗi kết nối máy chủ. Vui lòng thử lại.</div>';
            }
        }
    </script>
</body>
</html>

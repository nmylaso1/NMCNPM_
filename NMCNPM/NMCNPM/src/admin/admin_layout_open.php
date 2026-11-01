<?php
// Shared Admin Sidebar include
// Usage: include 'admin_layout.php';
// Required vars: $admin_name (string), $current_page (string key)
$admin_name   = $admin_name ?? ($_SESSION['full_name'] ?? 'Admin');
$current_page = $current_page ?? '';

// Centralized DB Schema Check
require_once dirname(__DIR__) . '/includes/cart_order_helpers.php';


// Determine base href from depth (files at src/admin/ need ../../)
$base = '../../';

$user_role = $_SESSION['role'] ?? 'admin';
$is_staff  = ($user_role === 'staff');
$is_admin  = ($user_role === 'admin');

$nav_items = [
    'index'       => ['icon' => 'layout-dashboard', 'label' => 'Tổng quan',          'href' => $base . 'src/admin/index.php'],
    'foods'       => ['icon' => 'utensils',          'label' => 'Món ăn',             'href' => $base . 'src/admin/manage_foods.php'],
    'categories'  => ['icon' => 'tags',              'label' => 'Danh mục',           'href' => $base . 'src/admin/manage_categories.php'],
    'orders'      => ['icon' => 'shopping-bag',      'label' => 'Đơn hàng',           'href' => $base . 'src/admin/manage_orders.php'],
    'reservations'=> ['icon' => 'calendar-check',    'label' => 'Đặt bàn & Phục vụ',  'href' => $base . 'src/admin/manage_reservations.php'],
    'coupons'     => ['icon' => 'ticket',            'label' => 'Mã giảm giá',        'href' => $base . 'src/admin/manage_coupons.php'],
    'shipping'    => ['icon' => 'truck',             'label' => 'Phí giao hàng',      'href' => $base . 'src/admin/manage_shipping.php'],
    'reviews'     => ['icon' => 'star',              'label' => 'Đánh giá',           'href' => $base . 'src/admin/manage_reviews.php'],
    'users'       => ['icon' => 'users',             'label' => 'Tài khoản',          'href' => $base . 'src/admin/manage_users.php'],
    // Mục Lịch sử thao tác (Admin only):
    'login_history'=> ['icon' => 'activity',         'label' => 'Lịch sử thao tác',   'href' => $base . 'src/admin/login_history.php'],
    'stats'       => ['icon' => 'bar-chart-2',       'label' => 'Thống kê',           'href' => $base . 'src/admin/stats.php'],
    'payments'    => ['icon' => 'history',           'label' => 'Lịch sử thanh toán', 'href' => $base . 'src/admin/manage_payments.php'],
    'notifications' => ['icon' => 'bell',            'label' => 'Thông báo',          'href' => $base . 'src/admin/notifications.php'],
    'settings'    => ['icon' => 'settings',          'label' => 'Cài đặt',            'href' => $base . 'src/admin/settings.php'],
];

// Nhân viên: Ẩn mã giảm giá, tài khoản/phân quyền và lịch sử hoạt động hệ thống
if ($is_staff) {
    unset($nav_items['coupons']);
    unset($nav_items['users']);
    unset($nav_items['login_history']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Quản Trị'); ?> - Nhà Hàng Gourmet</title>
    <link rel="icon" type="image/png" href="../../assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Quicksand','sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <style>
        :root {
            /* Light Theme (Default) */
            --bg-body: #f1f5f9;
            --bg-sidebar: #ffffff;
            --sidebar-border: rgba(0,0,0,0.05);
            --bg-card: #ffffff;
            --card-border: rgba(0,0,0,0.08);
            --text-main: #0f172a;
            --text-muted: #475569;
            --header-bg: rgba(255,255,255,0.85);
            --header-border: rgba(0,0,0,0.05);
            --nav-item-color: #475569;
            --nav-item-hover-bg: #f8fafc;
            --nav-item-hover-color: #0f172a;
            --stat-card-bg: #ffffff;
        }

        .dark {
            /* Dark Theme */
            --bg-body: #0f172a;
            --bg-sidebar: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            --sidebar-border: rgba(255,255,255,0.06);
            --bg-card: rgba(30,41,59,0.8);
            --card-border: rgba(255,255,255,0.07);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --header-bg: rgba(15,23,42,0.95);
            --header-border: rgba(255,255,255,0.05);
            --nav-item-color: #94a3b8;
            --nav-item-hover-bg: rgba(255,255,255,0.07);
            --nav-item-hover-color: #f1f5f9;
            --stat-card-bg: linear-gradient(135deg,rgba(30,41,59,0.95),rgba(15,23,42,0.95));
        }

        body { font-family:'Quicksand',sans-serif; background: var(--bg-body); color: var(--text-main); transition: background 0.3s, color 0.3s; }
        
        /* Global UI Fixes for Light Mode (overrides hardcoded dark classes) */
        html:not(.dark) .text-white { color: var(--text-main) !important; }
        html:not(.dark) .text-slate-100 { color: var(--text-main) !important; }
        html:not(.dark) .text-slate-200 { color: var(--text-main) !important; }
        html:not(.dark) .text-slate-300 { color: #334155 !important; }
        html:not(.dark) .text-slate-400 { color: #475569 !important; }
        html:not(.dark) .text-slate-500 { color: var(--text-muted) !important; }
        
        /* Semantic Color Overrides for better contrast in Light Mode */
        html:not(.dark) .text-emerald-400 { color: #059669 !important; }
        html:not(.dark) .text-amber-400 { color: #b45309 !important; }
        html:not(.dark) .text-red-400 { color: #dc2626 !important; }
        html:not(.dark) .text-blue-400 { color: #2563eb !important; }
        html:not(.dark) .text-rose-400 { color: #e11d48 !important; }

        html:not(.dark) .bg-slate-800 { background-color: #f8fafc !important; }
        html:not(.dark) .bg-slate-700 { background-color: #f1f5f9 !important; }
        html:not(.dark) .bg-slate-900 { background-color: #f1f5f9 !important; }
        html:not(.dark) .bg-\[\#1e293b\] { background-color: var(--bg-card) !important; }
        html:not(.dark) .bg-white\/5 { background-color: rgba(0,0,0,0.04) !important; }
        html:not(.dark) .bg-white\/10 { background-color: rgba(0,0,0,0.06) !important; }
        html:not(.dark) .bg-white\/30 { background-color: rgba(0,0,0,0.1) !important; }
        html:not(.dark) .bg-black\/20 { background-color: rgba(0,0,0,0.03) !important; }
        html:not(.dark) .bg-black\/10 { background-color: rgba(0,0,0,0.02) !important; }
        html:not(.dark) .bg-black\/50 { background-color: rgba(0,0,0,0.4) !important; }
        html:not(.dark) .border-white\/5 { border-color: rgba(0,0,0,0.08) !important; }
        html:not(.dark) .border-white\/10 { border-color: rgba(0,0,0,0.1) !important; }
        html:not(.dark) .divide-white\/5 > * { border-color: rgba(0,0,0,0.08) !important; }
        html:not(.dark) .hover\:bg-white\/5:hover { background-color: rgba(0,0,0,0.05) !important; }
        html:not(.dark) .hover\:bg-white\/10:hover { background-color: rgba(0,0,0,0.08) !important; }
        html:not(.dark) tr.hover\:bg-white\/5:hover { background-color: rgba(0,0,0,0.03) !important; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.1); border-radius: 99px; }
        .dark ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }
        ::-webkit-scrollbar-thumb:hover { background: rgba(217, 119, 6, 0.5); }
        /* For Firefox */
        * { scrollbar-width: thin; scrollbar-color: rgba(0, 0, 0, 0.1) transparent; }
        .dark * { scrollbar-color: rgba(255, 255, 255, 0.1) transparent; }

        /* Sidebar */
        #admin-sidebar {
            background: var(--bg-sidebar);
            border-right: 1px solid var(--sidebar-border);
            position: sticky;
            top: 0;
            height: 100vh;
            transition: background 0.3s, border 0.3s, width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        /* Collapsed Sidebar */
        #admin-sidebar.collapsed {
            width: 80px;
        }
        #admin-sidebar.collapsed .sidebar-text,
        #admin-sidebar.collapsed .sidebar-header-text,
        #admin-sidebar.collapsed .sidebar-footer-text {
            display: none;
        }
        #admin-sidebar.collapsed .sidebar-logo-container {
            margin-left: 0;
            justify-content: center;
        }
        #admin-sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 10px;
        }
        #admin-sidebar.collapsed .nav-item i {
            margin: 0;
            width: 20px;
            height: 20px;
        }
        #admin-sidebar.collapsed nav p {
            text-align: center;
            font-size: 8px;
            padding: 10px 0 5px;
        }

        .nav-item {
            display:flex; align-items:center; gap:10px;
            padding:10px 14px; border-radius:12px;
            font-weight:600; font-size:0.85rem;
            color: var(--nav-item-color); transition:all 0.18s;
            text-decoration:none;
        }
        .nav-item:hover { background: var(--nav-item-hover-bg); color: var(--nav-item-hover-color); }
        .nav-item.active {
            background:linear-gradient(135deg,#d97706,#b45309);
            color:#fff !important;
            box-shadow:0 4px 14px rgba(217,119,6,0.35);
        }
        .nav-item.active svg { stroke:#fff !important; }
        
        /* Sidebar Logo/Text */
        #admin-sidebar .text-white { color: var(--text-main) !important; }
        #admin-sidebar .text-slate-500 { color: var(--text-muted) !important; }

        /* Main area */
        #admin-main { background: var(--bg-body); min-height:100vh; transition: background 0.3s; }
        
        /* Cards */
        .stat-card {
            background: var(--stat-card-bg);
            border: 1px solid var(--card-border);
            border-radius:20px; padding:24px;
            transition:transform 0.2s, box-shadow 0.2s, background 0.3s, border 0.3s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 12px 30px rgba(0,0,0,0.05); }
        .dark .stat-card:hover { box-shadow:0 12px 30px rgba(0,0,0,0.3); }

        /* Content card */
        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--card-border);
            border-radius:20px;
            transition: background 0.3s, border 0.3s;
        }

        /* Generic utilities for theme */
        .theme-text-main { color: var(--text-main); }
        .theme-text-muted { color: var(--text-muted); }
        .theme-border { border-color: var(--card-border); }

        /* --- PREMIUM UI COMPONENTS --- */
        
        /* Native Select Hiding (when replaced) */
        .select-hidden {
            display: none !important;
        }

        /* Premium Select Container */
        .premium-select-container {
            position: relative;
            width: 100%;
            user-select: none;
        }

        .premium-select-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            color: #374151;
        }
        .dark .premium-select-trigger {
            background: rgba(30, 41, 59, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
            color: #f3f4f6;
        }

        .premium-select-trigger:hover {
            border-color: #d1d5db;
            background: #f3f4f6;
        }
        .dark .premium-select-trigger:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .premium-select-container.active .premium-select-trigger {
            border-color: #d97706;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.1);
            background: #fff;
        }
        .dark .premium-select-container.active .premium-select-trigger {
            background: #1e293b;
        }

        .premium-select-trigger i {
            color: #d97706;
            transition: transform 0.3s;
        }
        .premium-select-container.active .premium-select-trigger i {
            transform: rotate(180deg);
        }

        /* Dropdown Options List */
        .premium-select-options {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #f3f4f6;
            border-radius: 1.2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            z-index: 100;
            max-height: 250px;
            overflow-y: auto;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .dark .premium-select-options {
            background: #1e293b;
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .premium-select-container.active .premium-select-options {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .premium-option {
            padding: 10px 16px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dark .premium-option { color: #94a3b8; }

        .premium-option:hover {
            background: #eff6ff;
            color: #2563eb;
        }
        .dark .premium-option:hover {
            background: rgba(37, 99, 235, 0.1);
            color: #60a5fa;
        }

        .premium-option.selected {
            background: #2563eb;
            color: #fff !important;
        }
        .dark .premium-option.selected {
            background: #2563eb;
            color: #fff !important;
        }

        /* Custom Number Stepper (Up/Down) */
        .stepper-container {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .stepper-container input[type="number"] {
            -moz-appearance: textfield;
            text-align: center;
            padding-left: 3.2rem !important;
            padding-right: 3.2rem !important;
        }
        .stepper-container input[type="number"]::-webkit-inner-spin-button,
        .stepper-container input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .stepper-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #d97706;
            transition: all 0.2s;
            user-select: none;
            z-index: 10;
            border-radius: 10px;
            background: rgba(217, 119, 6, 0.08);
        }
        .stepper-btn:hover { background: rgba(217, 119, 6, 0.15); color: #b45309; }
        .stepper-btn:active { transform: translateY(-50%) scale(0.9); }
        .stepper-btn-minus { left: 8px; }
        .stepper-btn-plus { right: 8px; }

        /* Smooth focus ring */
        input:focus, .premium-select-container.active .premium-select-trigger, textarea:focus {
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.15) !important;
            border-color: #d97706 !important;
        }

        /* Mobile sidebar toggle */
        #sidebar-overlay { display:none; }
        @media(max-width:768px){
            #admin-sidebar { position:fixed; left:-280px !important; top:0; bottom:0; z-index:40; transition:left 0.3s; width:260px !important; }
            #admin-sidebar.open { left:0 !important; }
            #sidebar-overlay.open { display:block; position:fixed; inset:0; z-index:39; background:rgba(0,0,0,0.5); }
        }
    </style>
    <script>
        // Inline script to prevent theme and sidebar flicker
        (function() {
            const theme = localStorage.getItem('admin-theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
            
            const sidebarCollapsed = localStorage.getItem('admin-sidebar-collapsed') === 'true';
            document.addEventListener('DOMContentLoaded', () => {
                if (sidebarCollapsed && window.innerWidth > 768) {
                    document.getElementById('admin-sidebar').classList.add('collapsed');
                }
                initPremiumInputs();
                
                // Watch for dynamic content (Modals, etc.)
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.addedNodes.length) initPremiumInputs();
                    });
                });
                observer.observe(document.body, { childList: true, subtree: true });

                // Global click listener for Selects
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.premium-select-container')) {
                        document.querySelectorAll('.premium-select-container.active').forEach(el => el.classList.remove('active'));
                    }
                });
            });
        })();

        function initPremiumInputs() {
            // 1. Process Selects into Premium Dropdowns
            document.querySelectorAll('select:not(.premium-init):not(.select-hidden):not(.swal2-select)').forEach(select => {
                // Ignore selects that are part of SweetAlert or hidden
                if (select.closest('.swal2-container') || getComputedStyle(select).display === 'none') return;
                
                select.classList.add('premium-init');
                
                const container = document.createElement('div');
                container.className = 'premium-select-container';
                select.parentNode.insertBefore(container, select);
                
                const trigger = document.createElement('div');
                trigger.className = 'premium-select-trigger';
                
                const label = document.createElement('span');
                label.className = 'premium-select-label truncate';
                label.innerText = select.options[select.selectedIndex]?.text || 'Chọn...';
                
                const icon = document.createElement('i');
                icon.setAttribute('data-lucide', 'chevron-down');
                icon.className = 'w-4 h-4 shrink-0';
                
                trigger.appendChild(label);
                trigger.appendChild(icon);
                container.appendChild(trigger);
                
                const optionsList = document.createElement('div');
                optionsList.className = 'premium-select-options custom-scrollbar';
                
                const updateOptions = () => {
                    optionsList.innerHTML = '';
                    Array.from(select.options).forEach((opt, idx) => {
                        const div = document.createElement('div');
                        div.className = 'premium-option' + (opt.selected ? ' selected' : '');
                        div.innerText = opt.text;
                        div.onclick = (e) => {
                            e.stopPropagation();
                            select.selectedIndex = idx;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            label.innerText = opt.text;
                            container.classList.remove('active');
                            updateOptions();
                        };
                        optionsList.appendChild(div);
                    });
                };
                
                updateOptions();
                container.appendChild(optionsList);
                
                trigger.onclick = (e) => {
                    e.stopPropagation();
                    const isActive = container.classList.contains('active');
                    document.querySelectorAll('.premium-select-container.active').forEach(el => el.classList.remove('active'));
                    if (!isActive) container.classList.add('active');
                };
                
                // Hidden sync
                select.classList.add('select-hidden');
                
                // Listen for manual programmatic changes to select
                select.addEventListener('change', () => {
                    label.innerText = select.options[select.selectedIndex]?.text || 'Chọn...';
                    updateOptions();
                });

                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            // 2. Process Number Inputs into Steppers
            document.querySelectorAll('input[type="number"]:not(.stepper-init):not(.swal2-input)').forEach(input => {
                if (input.closest('.swal2-container') || getComputedStyle(input).display === 'none') return;
                if (input.closest('.stepper-container') || input.offsetParent === null) return;
                
                input.classList.add('stepper-init');
                const container = document.createElement('div');
                container.className = 'stepper-container';
                input.parentNode.insertBefore(container, input);
                container.appendChild(input);

                const btnMinus = document.createElement('div');
                btnMinus.className = 'stepper-btn stepper-btn-minus';
                btnMinus.innerHTML = '<i data-lucide="minus" class="w-4 h-4"></i>';
                btnMinus.onclick = (e) => {
                    e.preventDefault();
                    const step = parseFloat(input.step) || 1;
                    const min = input.min !== "" ? parseFloat(input.min) : -Infinity;
                    let val = (parseFloat(input.value) || 0) - step;
                    if (val < min) val = min;
                    input.value = val;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                };

                const btnPlus = document.createElement('div');
                btnPlus.className = 'stepper-btn stepper-btn-plus';
                btnPlus.innerHTML = '<i data-lucide="plus" class="w-4 h-4"></i>';
                btnPlus.onclick = (e) => {
                    e.preventDefault();
                    const step = parseFloat(input.step) || 1;
                    const max = input.max !== "" ? parseFloat(input.max) : Infinity;
                    let val = (parseFloat(input.value) || 0) + step;
                    if (val > max) val = max;
                    input.value = val;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                };

                container.appendChild(btnMinus);
                container.appendChild(btnPlus);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden">

<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<aside id="admin-sidebar" class="w-[240px] shrink-0 flex flex-col">
    <div class="px-5 py-6 border-b border-white/5">
        <a href="<?php echo $nav_items['index']['href']; ?>" class="flex items-center gap-3 sidebar-logo-container transition-all hover:opacity-80 active:scale-95 group">
            <img src="../../assets/img/hero/logo.png" alt="Logo" class="w-11 h-11 rounded-full shadow-lg shrink-0 object-contain group-hover:rotate-12 transition-transform duration-300">
            <div class="flex flex-col min-w-0">
                <span class="text-sm font-black text-white sidebar-header-text truncate tracking-tight">GOURMET</span>
                <span class="text-[10px] font-bold text-amber-400 sidebar-header-text uppercase tracking-widest leading-none">Restaurant</span>
            </div>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto p-3 space-y-0.5">
        <p class="text-[9px] font-black text-slate-600 uppercase tracking-widest px-3 pt-3 pb-1.5 sidebar-text">Menu chính</p>
        <?php foreach ($nav_items as $key => $item): ?>
        <a href="<?php echo $item['href']; ?>" class="nav-item <?php echo $current_page === $key ? 'active' : ''; ?>" title="<?php echo $item['label']; ?>">
            <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4 shrink-0"></i>
            <span class="sidebar-text"><?php echo $item['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-3 border-t border-white/5">
        <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-white/5 sidebar-footer-container">
            <div class="w-8 h-8 rounded-full <?php echo $is_staff ? 'bg-sky-500/20' : 'bg-amber-500/20'; ?> flex items-center justify-center shrink-0">
                <i data-lucide="<?php echo $is_staff ? 'user-check' : 'crown'; ?>" class="w-3.5 h-3.5 <?php echo $is_staff ? 'text-sky-400' : 'text-amber-400'; ?>"></i>
            </div>
            <div class="flex-1 min-w-0 sidebar-footer-text">
                <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                <p class="text-[10px] text-slate-500 truncate"><?php echo $is_staff ? 'Nhân viên' : 'Quản trị viên'; ?></p>
            </div>
        </div>
        <a href="<?php echo $base; ?>dashboard.php" class="mt-2 flex items-center gap-2 px-3 py-2 rounded-xl text-slate-500 hover:text-white hover:bg-white/5 transition text-xs font-semibold" title="Về trang người dùng">
            <i data-lucide="arrow-left-circle" class="w-3.5 h-3.5"></i>
            <span class="sidebar-text">Về trang người dùng</span>
        </a>
        <a href="<?php echo $base; ?>logout.php" class="flex items-center gap-2 px-3 py-2 rounded-xl text-red-400/70 hover:text-red-400 hover:bg-red-500/5 transition text-xs font-semibold" title="Đăng xuất">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            <span class="sidebar-text">Đăng xuất</span>
        </a>
    </div>
</aside>

<div id="admin-main" class="flex-1 flex flex-col h-screen overflow-y-auto">
    <header class="sticky top-0 z-30 px-6 py-4 flex items-center justify-between border-b" style="background: var(--header-bg); backdrop-filter:blur(16px); border-color: var(--header-border);">
        <div class="flex items-center">
            <button onclick="openSidebar()" class="md:hidden flex items-center justify-center w-9 h-9 rounded-xl bg-gray-500/10 hover:bg-gray-500/20 transition mr-3">
                <i data-lucide="menu" class="w-5 h-5 theme-text-main"></i>
            </button>
            <button onclick="toggleSidebar()" class="hidden md:flex items-center justify-center w-9 h-9 rounded-xl bg-gray-500/10 hover:bg-gray-500/20 transition mr-4 group" title="Thu gọn/Mở rộng">
                <i data-lucide="panel-left" class="w-5 h-5 theme-text-main group-hover:scale-110 transition-transform"></i>
            </button>
            <h1 class="text-base md:text-lg font-extrabold theme-text-main hidden sm:block"><?php echo htmlspecialchars($page_title ?? 'Tổng quan'); ?></h1>
        </div>
        
        <div class="flex items-center gap-3 ml-auto">
            <div class="relative">
                <button onclick="toggleNotifications()" class="w-9 h-9 rounded-xl flex items-center justify-center bg-gray-500/10 hover:bg-gray-500/20 transition group relative" title="Thông báo">
                    <i data-lucide="bell" class="w-4 h-4 text-slate-600 dark:text-slate-400 group-hover:scale-110 transition-transform"></i>
                    <span id="notif-badge" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white dark:border-slate-900 hidden animate-pulse">0</span>
                </button>
                
                <div id="notif-dropdown" class="absolute top-[calc(100%+12px)] right-0 w-80 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-white/10 shadow-2xl overflow-hidden hidden animate-in slide-in-from-top-2 duration-200 z-[100]">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center justify-between bg-slate-50/50 dark:bg-white/5">
                        <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest">Thông báo mới</h4>
                        <button onclick="markAllAsRead()" class="text-[10px] font-bold text-amber-600 hover:text-amber-700">Đánh dấu đã đọc</button>
                    </div>
                    <div id="notif-list" class="max-h-96 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-white/5">
                        <div class="px-5 py-8 text-center text-slate-400 italic text-xs">Không có thông báo mới nào</div>
                    </div>
                    <div class="p-3 bg-slate-50/50 dark:bg-white/5 border-t border-slate-100 dark:border-white/5">
                        <a href="<?php echo $base; ?>src/admin/manage_orders.php" class="block w-full py-2.5 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest hover:text-amber-600 transition">Xem tất cả đơn hàng</a>
                    </div>
                </div>
            </div>

            <button onclick="toggleTheme()" class="w-9 h-9 rounded-xl flex items-center justify-center bg-gray-500/10 hover:bg-gray-500/20 transition group" title="Đổi giao diện">
                <i data-lucide="sun" class="w-4 h-4 text-amber-500 hidden dark:block"></i>
                <i data-lucide="moon" class="w-4 h-4 text-slate-600 block dark:hidden"></i>
            </button>

            <span class="text-xs font-bold text-amber-500 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-full hidden xs:flex">
                <?php echo date('H:i · d/m/Y'); ?>
            </span>
        </div>
    </header>

    <div class="p-5 md:p-7 flex-1">

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const isCollapsed = sidebar.classList.toggle('collapsed');
            localStorage.setItem('admin-sidebar-collapsed', isCollapsed);
        }

        function openSidebar() {
            document.getElementById('admin-sidebar').classList.add('open');
            document.getElementById('sidebar-overlay').classList.add('open');
        }

        function closeSidebar() {
            document.getElementById('admin-sidebar').classList.remove('open');
            document.getElementById('sidebar-overlay').classList.remove('open');
        }

        /* --- NOTIFICATION SYSTEM --- */
        let unreadOrders = [];
        let toastQueue = [];

        function toggleNotifications() {
            const dropdown = document.getElementById('notif-dropdown');
            dropdown.classList.toggle('hidden');
        }

        let isCheckingNotifs = false;
        async function checkNewNotifications() {
            if (isCheckingNotifs || document.hidden) return;
            isCheckingNotifs = true;
            try {
                const res = await fetch('<?php echo $base; ?>src/api/admin_get_notifications.php');
                const data = await res.json();
                
                if (data.success) {
                    processNotifications(data.notifications);
                }
            } catch (e) { console.error('Notification check failed:', e); }
            finally {
                isCheckingNotifs = false;
            }
        }

        function processNotifications(newList) {
            const listContainer = document.getElementById('notif-list');
            const badge = document.getElementById('notif-badge');
            
            // Filter out already processed notifications
            const actuallyNew = newList.filter(n => !unreadOrders.find(old => old.id === n.id));
            
            unreadOrders = newList;

            // Update Badge
            if (unreadOrders.length > 0) {
                badge.innerText = unreadOrders.length;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }

            // Update List
            if (unreadOrders.length === 0) {
                listContainer.innerHTML = '<div class="px-5 py-8 text-center text-slate-400 italic text-xs">Không có thông báo mới nào</div>';
            } else {
                listContainer.innerHTML = unreadOrders.map(notif => `
                    <div class="px-5 py-4 hover:bg-slate-50 dark:hover:bg-white/5 transition cursor-pointer" onclick="markRead(${notif.id})">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0">
                                <i data-lucide="shopping-bag" class="w-4 h-4 text-amber-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-black text-amber-600 uppercase mb-0.5">${notif.order_code}</p>
                                <p class="text-[13px] font-bold theme-text-main truncate">${notif.full_name}</p>
                                <p class="text-[11px] font-semibold text-slate-500">Tổng tiền: ${new Intl.NumberFormat('vi-VN').format(notif.total_amount)}đ</p>
                            </div>
                            <span class="text-[9px] font-bold text-slate-400 mt-1 uppercase">${notif.time_ago}</span>
                        </div>
                    </div>
                `).join('');
                lucide.createIcons();
            }

            // Show Toasts for actually new items
            // actuallyNew.forEach(n => showToast(n));
        }

        function showToast(order) {
            const container = document.getElementById('toast-container');
            if (!container) {
                const div = document.createElement('div');
                div.id = 'toast-container';
                div.className = 'fixed bottom-8 right-8 z-[10000] flex flex-col gap-3 pointer-events-none';
                document.body.appendChild(div);
            }

            const toast = document.createElement('div');
            toast.className = 'w-80 bg-white dark:bg-slate-900 rounded-2xl border-l-4 border-amber-500 shadow-2xl p-4 flex items-start gap-3 animate-in slide-in-from-right duration-300 pointer-events-auto';
            toast.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0">
                    <i data-lucide="bell-ring" class="w-5 h-5 text-amber-500 animate-bounce"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h5 class="text-xs font-black text-amber-600 uppercase tracking-widest mb-1.5 flex items-center justify-between">
                        Đơn hàng mới!
                        <span class="text-[9px] bg-amber-500 text-white px-1.5 py-0.5 rounded">7s</span>
                    </h5>
                    <p class="text-sm font-bold theme-text-main truncate">${order.full_name}</p>
                    <p class="text-[11px] font-semibold text-slate-500">Mã đơn: <span class="text-amber-500/80">${order.order_code}</span></p>
                    <p class="text-xs font-black theme-text-main mt-1">${new Intl.NumberFormat('vi-VN').format(order.total_amount)}đ</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600" onclick="this.parentElement.remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            lucide.createIcons();

            // Auto-hide after 7 seconds
            setTimeout(() => {
                toast.classList.remove('animate-in', 'slide-in-from-right');
                toast.classList.add('animate-out', 'fade-out', 'slide-out-to-right');
                setTimeout(() => toast.remove(), 400);
            }, 7000);
        }

        async function markRead(id) {
            try {
                const res = await fetch('<?php echo $base; ?>src/api/admin_mark_notified.php?id=' + id);
                const data = await res.json();
                if (data.success) {
                    checkNewNotifications();
                    if (window.location.href.includes('manage_orders.php')) {
                        location.reload();
                    }
                }
            } catch (e) {}
        }

        async function markAllAsRead() {
            try {
                const res = await fetch('<?php echo $base; ?>src/api/admin_mark_notified.php?all=1');
                const data = await res.json();
                if (data.success) {
                    checkNewNotifications();
                }
            } catch (e) {}
        }

        // Global polling - every 35 seconds
        setInterval(checkNewNotifications, 35000);
        checkNewNotifications(); // Initial check
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) checkNewNotifications();
        });

        // Close dropdown on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative')) {
                document.getElementById('notif-dropdown')?.classList.add('hidden');
            }
        });
    </script>
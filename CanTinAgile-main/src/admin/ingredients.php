<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) { header('Location: ../../login.php'); exit; }
$admin_name = $_SESSION['full_name'] ?? 'Admin';
$current_page = 'ingredients'; $page_title = 'Quản lý Nguyên liệu';
include 'admin_layout_open.php';
?>
<div class="content-card p-10 text-center max-w-md mx-auto mt-10">
    <div class="w-16 h-16 rounded-2xl bg-teal-500/15 text-teal-400 flex items-center justify-center mx-auto mb-5">
        <i data-lucide="package" class="w-8 h-8"></i>
    </div>
    <h2 class="text-xl font-extrabold text-white mb-2">Quản lý Nguyên liệu (Kho)</h2>
    <p class="text-slate-500 text-sm mb-6">Module theo dõi nhập kho, tồn kho và cảnh báo hết nguyên liệu. Đây là tính năng nâng cao sẽ được xây dựng sau.</p>
    <span class="inline-block px-4 py-1.5 rounded-full text-xs font-black bg-teal-500/15 text-teal-400 border border-teal-500/20">🚧 Coming Soon</span>
</div>
<?php include 'admin_layout_close.php'; ?>

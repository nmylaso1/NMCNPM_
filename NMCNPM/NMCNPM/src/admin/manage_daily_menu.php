<?php
// Chức năng "Thực đơn theo ngày" đã được loại bỏ để phù hợp với mô hình Nhà Hàng Gourmet (sử dụng danh mục và thực đơn tổng thể).
// Tự động chuyển hướng về trang Quản lý Món ăn.
session_start();
header('Location: manage_foods.php');
exit;

<?php
// Tự động chuyển hướng khách vào Trang chủ Dashboard khi truy cập website
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: dashboard.php' . $queryString);
exit;

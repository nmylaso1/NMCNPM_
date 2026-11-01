<?php
require_once __DIR__ . '/../../src/includes/config.php';

if (!isset($conn) || !$conn) {
    echo "DB Connection failed\n";
    exit(1);
}

// 1. Table reservations
$sqlRes = "CREATE TABLE IF NOT EXISTS `reservations` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `reservation_code` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT(11) DEFAULT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `reservation_date` DATE NOT NULL,
    `reservation_time` TIME NOT NULL,
    `guest_count` INT(11) NOT NULL DEFAULT 2,
    `seating_area` VARCHAR(50) NOT NULL DEFAULT 'standard',
    `note` TEXT DEFAULT NULL,
    `status` ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_res_date` (`reservation_date`),
    INDEX `idx_res_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sqlRes)) {
    echo "Table reservations created or already exists.\n";
} else {
    echo "Error reservations: " . $conn->error . "\n";
}

// 2. Table service_requests
$sqlServ = "CREATE TABLE IF NOT EXISTS `service_requests` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `table_number` VARCHAR(50) NOT NULL,
    `request_type` VARCHAR(50) NOT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_srv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sqlServ)) {
    echo "Table service_requests created or already exists.\n";
} else {
    echo "Error service_requests: " . $conn->error . "\n";
}

// 3. Update settings for restaurant branding
$conn->query("UPDATE canteen_settings SET setting_value = 'Nhà Hàng Căn Tin Gourmet' WHERE setting_key = 'canteen_name' AND (setting_value LIKE '%canteen%' OR setting_value LIKE '%ccccc%')");
$conn->query("UPDATE canteen_settings SET setting_value = 'Số 88 Phố Ẩm Thực, Quận 1, TP. Hồ Chí Minh' WHERE setting_key = 'canteen_address' AND setting_value LIKE '%HUTECH%'");

echo "Migration finished successfully.\n";

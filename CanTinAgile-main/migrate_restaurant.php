<?php
require_once __DIR__ . '/src/includes/config.php';

if (!isset($conn) || !$conn) {
    echo "Could not connect to database! Host: $db_host\n";
    if (isset($tempConn) && mysqli_connect_error()) {
        echo "Error: " . mysqli_connect_error() . "\n";
    }
    exit(1);
}

// 1. Check if columns exist
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM orders");
while ($r = $res->fetch_assoc()) {
    $cols[] = $r['Field'];
}

echo "Current columns: " . implode(', ', $cols) . "\n";

// 2. Allow user_id to be NULL
$conn->query("ALTER TABLE orders MODIFY user_id INT NULL");
echo "Modified user_id to NULL: " . ($conn->error ? $conn->error : "OK") . "\n";

// 3. Add order_type if not exists
if (!in_array('order_type', $cols)) {
    $conn->query("ALTER TABLE orders ADD COLUMN order_type ENUM('dine_in', 'delivery', 'takeaway') NOT NULL DEFAULT 'dine_in'");
    echo "Added order_type: " . ($conn->error ? $conn->error : "OK") . "\n";
}

// 4. Add table_number if not exists
if (!in_array('table_number', $cols)) {
    $conn->query("ALTER TABLE orders ADD COLUMN table_number VARCHAR(50) NULL");
    echo "Added table_number: " . ($conn->error ? $conn->error : "OK") . "\n";
}

// 5. Add customer_name if not exists
if (!in_array('customer_name', $cols)) {
    $conn->query("ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NULL");
    echo "Added customer_name: " . ($conn->error ? $conn->error : "OK") . "\n";
}

// 6. Add customer_phone if not exists
if (!in_array('customer_phone', $cols)) {
    $conn->query("ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NULL");
    echo "Added customer_phone: " . ($conn->error ? $conn->error : "OK") . "\n";
}

// 7. Check if tables table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS restaurant_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL UNIQUE,
    capacity INT DEFAULT 4,
    status ENUM('available', 'occupied', 'reserved') DEFAULT 'available',
    qr_token VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Restaurant tables table: " . ($conn->error ? $conn->error : "OK") . "\n";

// Insert some default tables if empty
$tCount = $conn->query("SELECT COUNT(*) as c FROM restaurant_tables")->fetch_assoc()['c'] ?? 0;
if ($tCount == 0) {
    for ($i = 1; $i <= 12; $i++) {
        $tNum = "Bàn " . str_pad($i, 2, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(8));
        $conn->query("INSERT INTO restaurant_tables (table_number, capacity, qr_token) VALUES ('$tNum', 4, '$token')");
    }
    echo "Inserted 12 default tables.\n";
}

echo "Migration finished.\n";

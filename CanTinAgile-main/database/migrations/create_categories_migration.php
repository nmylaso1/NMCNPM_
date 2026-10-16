<?php
require_once __DIR__ . '/../../src/includes/config.php';

echo "Starting migration...\n";

// 1. Create categories_products table
$sql1 = "CREATE TABLE IF NOT EXISTS categories_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE
)";

if ($conn->query($sql1) === TRUE) {
    echo "Table categories_products created successfully or already exists.\n";
} else {
    echo "Error creating table categories_products: " . $conn->error . "\n";
}

// 2. Add category_id to products
$checkColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'category_id'");
if ($checkColumn->num_rows === 0) {
    $sql2 = "ALTER TABLE products ADD COLUMN category_id INT DEFAULT NULL";
    if ($conn->query($sql2) === TRUE) {
        echo "Column category_id added to products.\n";
        // Add foreign key
        $sqlFK = "ALTER TABLE products ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories_products(id) ON DELETE SET NULL";
        $conn->query($sqlFK);
    } else {
        echo "Error adding column category_id: " . $conn->error . "\n";
    }
} else {
    echo "Column category_id already exists in products.\n";
}

// 3. Seed categories if empty
$checkCats = $conn->query("SELECT COUNT(*) as count FROM categories_products");
$row = $checkCats->fetch_assoc();
if ($row['count'] == 0) {
    $seeds = [
        ['Cơm trưa', 'com-trua'],
        ['Món nước', 'mon-nuoc'],
        ['Đồ ăn vặt', 'do-an-vat'],
        ['Nước giải khát', 'nuoc-giai-khat']
    ];
    $stmt = $conn->prepare("INSERT INTO categories_products (name, slug) VALUES (?, ?)");
    foreach ($seeds as $seed) {
        $stmt->bind_param("ss", $seed[0], $seed[1]);
        $stmt->execute();
    }
    echo "Categories seeded.\n";
    
    // 4. Assign existing products to first category
    $getCat = $conn->query("SELECT id FROM categories_products LIMIT 1");
    if ($getCat && $cat = $getCat->fetch_assoc()) {
        $catId = $cat['id'];
        $conn->query("UPDATE products SET category_id = $catId WHERE category_id IS NULL");
        echo "Existing products assigned to category ID $catId.\n";
    }
}

echo "Migration finished.\n";
?>

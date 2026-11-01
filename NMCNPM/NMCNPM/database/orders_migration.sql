-- ============================================================
-- MIGRATION: Orders schema (English naming, clear checkout flow)
-- Chạy file này sau khi đã tạo carts + cart_items
-- ============================================================

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    cart_id BIGINT NULL,
    subtotal_amount DECIMAL(10,2) NOT NULL,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_room VARCHAR(100) NULL,
    status ENUM('pending', 'cooking', 'ready', 'paid', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    note TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE SET NULL,
    CONSTRAINT chk_orders_amounts CHECK (subtotal_amount >= 0 AND shipping_fee >= 0 AND discount_amount >= 0 AND total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_order_items_prices CHECK (unit_price >= 0 AND line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_orders_user_status ON orders (user_id, status);
CREATE INDEX idx_orders_created_at ON orders (created_at);
CREATE INDEX idx_order_items_order ON order_items (order_id);
CREATE INDEX idx_order_items_product ON order_items (product_id);

CREATE TABLE IF NOT EXISTS shipping_policies (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL DEFAULT 'default',
    threshold_1 DECIMAL(10,2) NOT NULL,
    fee_1 DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_shipping_policies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shipping_policies (policy_name, threshold_1, fee_1, is_active)
SELECT 'default', 100000, 10000, 1
WHERE NOT EXISTS (SELECT 1 FROM shipping_policies WHERE is_active = 1);

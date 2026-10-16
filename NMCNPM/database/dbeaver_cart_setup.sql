-- ============================================================
-- CART SCHEMA (ENGLISH NAMING, EASY TO UNDERSTAND)
-- Purpose:
--   - carts: one active cart per user
--   - cart_items: products user has selected in cart
--   - is_selected_for_checkout: mark which products are chosen for checkout
-- ============================================================

-- Clean legacy tables (if they exist)
DROP TABLE IF EXISTS them_logs;
DROP TABLE IF EXISTS them_items;
DROP TABLE IF EXISTS cart_item_logs;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;

CREATE TABLE carts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'checked_out', 'abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    checked_out_at TIMESTAMP NULL,
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT uq_carts_user UNIQUE (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    is_selected_for_checkout TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT uq_cart_items_cart_product UNIQUE (cart_id, product_id),
    CONSTRAINT chk_cart_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_cart_items_unit_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_item_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    action_type ENUM('add', 'update_quantity', 'remove', 'toggle_selection', 'clear') NOT NULL,
    old_quantity INT DEFAULT 0,
    new_quantity INT DEFAULT 0,
    action_time TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_item_logs_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item_logs_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item_logs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cart_items_cart_selected ON cart_items (cart_id, is_selected_for_checkout);
CREATE INDEX idx_cart_items_product ON cart_items (product_id);
CREATE INDEX idx_cart_item_logs_user_time ON cart_item_logs (user_id, action_time);

-- ============================================================
-- COUPON SYSTEM SETUP
-- ============================================================

-- 1. Create the coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID tự tăng',
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã giảm giá (VD: KM2026)',
    
    -- Loại giảm giá: fixed = số tiền cụ thể, percentage = %
    discount_type ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    
    -- Giá trị giảm (Nếu fixed thì là số tiền, nếu percentage thì là số từ 1-100)
    discount_value DECIMAL(15, 2) NOT NULL,
    
    -- Các điều kiện chặn
    min_order_value DECIMAL(15, 2) DEFAULT 0 COMMENT 'Đơn hàng tối thiểu để áp dụng',
    max_discount_amount DECIMAL(15, 2) DEFAULT NULL COMMENT 'Số tiền giảm tối đa (Dành cho loại %)',
    
    -- Giới hạn sử dụng
    usage_limit INT DEFAULT NULL COMMENT 'Tổng lượt dùng tối đa (NULL là không giới hạn)',
    used_count INT DEFAULT 0 COMMENT 'Số lượt đã dùng',
    user_limit INT DEFAULT 1 COMMENT 'Mỗi user được dùng bao nhiêu lần',
    
    -- Thời gian
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME NOT NULL,
    
    -- Trạng thái
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add coupon_id to the orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_id INT DEFAULT NULL AFTER cart_id;

-- 3. Add foreign key constraint for data integrity
-- Note: Errors may occur if the column already had a constraint or data is inconsistent.
-- Ensure columns match types (INT for id in coupons, INT for coupon_id in orders).
ALTER TABLE orders ADD CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL;

-- 4. Sample Data for Testing
INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_order_value, end_date) 
VALUES ('KM2026', 'percentage', 10, 50000, '2026-12-31 23:59:59');

INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_order_value, end_date) 
VALUES ('GIAM50K', 'fixed', 50000, 200000, '2026-12-31 23:59:59');

INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_order_value, end_date) 
VALUES ('TEST50', 'percentage', 50, 0, '2026-12-31 23:59:59');

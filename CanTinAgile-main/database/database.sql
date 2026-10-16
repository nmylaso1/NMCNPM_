
-- ==========================================
-- HỆ THỐNG QUẢN LÝ CĂN TIN - DATABASE SETUP
-- ==========================================

-- 1. XÓA BẢNG NẾU TỒN TẠI (Theo thứ tự khóa ngoại)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cart_item_logs;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS daily_menu;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories_products;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS shipping_policies;
DROP TABLE IF EXISTS canteen_settings;
DROP TABLE IF EXISTS announcements;
SET FOREIGN_KEY_CHECKS = 1;

-- 2. TẠO CẤU TRÚC BẢNG

-- Bảng tài khoản
CREATE TABLE accounts (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    role ENUM('admin', 'user', 'guest') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng danh mục sản phẩm
CREATE TABLE categories_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng sản phẩm
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(500) NOT NULL,
    calories INT DEFAULT 250,
    prep_time VARCHAR(50) DEFAULT '15 phút',
    ingredients TEXT DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT 4.5,
    reviews_count INT DEFAULT 0,
    is_vegetarian TINYINT(1) DEFAULT 0,
    is_highlight TINYINT(1) DEFAULT 0,
    is_hidden TINYINT(1) DEFAULT 0,
    category_id INT DEFAULT NULL,
    sales_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng đánh giá sản phẩm
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_product (user_id, product_id),
    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BƠM DỮ LIỆU MẪU (SEEDING CONTINUED)
INSERT INTO product_reviews (user_id, product_id, rating, comment) VALUES 
(2, 208, 5, 'Cơm gà rất ngon, thịt mềm và thơm. Sẽ ủng hộ dài dài!'),
(3, 208, 4, 'Vị vừa ăn, gà hơi ít một xíu nhưng tổng thể rất tốt.'),
(2, 209, 5, 'Phở bò tái lăn chuẩn vị, nước dùng ngọt thanh.'),
(3, 210, 5, 'Trà đào rất thơm, miếng đào to và giòn.');

-- Bảng thực đơn hàng ngày
CREATE TABLE daily_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    serving_date DATE NOT NULL,
    meal_type ENUM('Sáng', 'Trưa', 'Tối') NOT NULL,
    is_highlight TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_daily_menu_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_daily_menu_date_meal ON daily_menu (serving_date, meal_type);

-- Bảng giỏ hàng
CREATE TABLE carts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'checked_out', 'abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    checked_out_at TIMESTAMP NULL,
    UNIQUE KEY uq_carts_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng chi tiết giỏ hàng
CREATE TABLE cart_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    is_selected_for_checkout TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_items_cart_product_title (cart_id, product_id, product_name),
    KEY idx_cart_items_cart_id (cart_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng lịch sử thay đổi giỏ hàng
CREATE TABLE cart_item_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    action_type ENUM('add', 'update_quantity', 'remove', 'toggle_selection', 'clear') NOT NULL,
    old_quantity INT DEFAULT 0,
    new_quantity INT DEFAULT 0,
    action_time TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_logs_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_logs_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_logs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng đơn hàng
CREATE TABLE orders (
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
    payment_method ENUM('payos', 'direct') NOT NULL DEFAULT 'direct',
    note TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng chi tiết đơn hàng
CREATE TABLE order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng cấu hình vận chuyển
CREATE TABLE shipping_policies (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL DEFAULT 'default',
    threshold_1 DECIMAL(10,2) NOT NULL,
    fee_1 DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng cài đặt căn tin
CREATE TABLE canteen_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng thông báo
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. BƠM DỮ LIỆU MẪU (SEEDING)

-- Tài khoản mẫu (Mật khẩu mặc định: 123456 - Cần được mã hóa bcrypt trong thực tế)
INSERT INTO accounts (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin@canteen.vn', 'admin'),
('nhanvien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nhân Viên Căn Tin', 'nhanvien@canteen.vn', 'staff'),
('user01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn A', 'user01@student.vn', 'user'),
('guest01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Khách Vãng Lai', 'guest01@gmail.com', 'guest');

-- Danh mục mẫu
INSERT INTO categories_products (name, slug) VALUES 
('Món chính', 'mon-chinh'),
('Đồ uống', 'do-uong'),
('Tráng miệng', 'trang-mieng'),
('Ăn vặt', 'an-vat');

-- Sản phẩm mẫu
INSERT INTO products (title, description, price, image, calories, prep_time, ingredients, rating, reviews_count, is_highlight, category_id) VALUES 
('Cơm Gà Hải Nam', 'Cơm gà thơm ngon đúng điệu với thịt gà ta thả vườn.', 45000, 'https://images.unsplash.com/photo-1598511726623-d30d1736637e?q=80&w=800', 650, '15 phút', 'Gạo tẻ, Gà ta, Gừng, Hành lá', 4.8, 120, 1, 1),
('Phở Bò Tái Lăn', 'Phở bò truyền thống với thịt bò tươi áp chảo.', 55000, 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?q=80&w=800', 450, '10 phút', 'Bánh phở, Thịt bò, Nước dùng xương', 4.9, 85, 0, 1),
('Trà Đào Cam Sả', 'Thức uống giải nhiệt mùa hè mát lạnh.', 25000, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?q=80&w=800', 120, '5 phút', 'Trà đen, Đào miếng, Cam, Sả', 4.7, 210, 0, 2),
('Bánh Flan Cốt Dừa', 'Bánh Flan mềm mịn quyện cùng nước cốt dừa béo ngậy.', 15000, 'https://images.unsplash.com/photo-1579954115563-e72bf1381629?q=80&w=800', 220, '3 phút', 'Trứng, Sữa, Cốt dừa, Caramel', 4.6, 60, 0, 3);

-- Cài đặt căn tin mẫu
INSERT INTO canteen_settings (setting_key, setting_value) VALUES 
('canteen_name', 'ccccc sâsdfs'),
('canteen_hotline', '0909 123 456'),
('canteen_address', 'Trường Đại học Công nghệ TP.HCM (HUTECH)'),
('open_time', '06:30'),
('close_time', '20:00'),
('delivery_fee', '5000'),
('free_delivery_from', '50000');

-- Chính sách vận chuyển mẫu
INSERT INTO shipping_policies (policy_name, threshold_1, fee_1, is_active) VALUES 
('Mặc định', 100000, 10000, 1);

-- Thông báo mẫu
INSERT INTO announcements (content, is_active) VALUES 
('Chào mừng bạn đến với hệ thống đặt món trực tuyến của Căn Tin!', 1),
('Ưu đãi Free Ship cho đơn hàng trên 50k trong hôm nay!', 1);

-- Bảng Lịch sử thao tác Hệ thống (Action / Audit Logs)
CREATE TABLE IF NOT EXISTS system_action_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_time (created_at),
    INDEX idx_action_user (user_id),
    CONSTRAINT fk_action_user FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


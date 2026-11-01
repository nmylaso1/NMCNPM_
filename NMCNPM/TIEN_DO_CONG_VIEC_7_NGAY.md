# BẢNG KẾ HOẠCH & TIẾN ĐỘ THỰC HIỆN DỰ ÁN (7 NGÀY)
**Dự án:** Website Đặt Món Ăn Nhà Hàng / Căn Tin (CanTinAgile - Nhà Hàng Gourmet)  
**Môn học:** Nhập môn Công nghệ Phần mềm  
**Đơn vị thực hiện:** Nhóm 04  

---

## I. THÔNG TIN THÀNH VIÊN VÀ PHÂN VAI TRONG NHÓM

| STT | Thành viên | Ký hiệu | Vai trò | Trách nhiệm chính |
|:---:|:---|:---:|:---|:---|
| 1 | **Lý Ngọc Mỹ** | **NM** | Nhóm trưởng / Fullstack Lead | Quản lý tiến độ Trello, thiết kế kiến trúc hệ thống, tích hợp cổng thanh toán PayOS VietQR, phân hệ Thống kê Admin và Kiểm thử tích hợp. |
| 2 | **Nguyễn Nhật Nam** | **NN** | Frontend / UI-UX Lead | Thiết kế giao diện phía khách hàng (Trang chủ, Thực đơn, Giỏ hàng, Modal món ăn, Responsive mobile). |
| 3 | **Nguyễn Như Xuân** | **NX** | Backend & Database Developer | Xây dựng logic Xác thực (Login/Register/Session), thư viện Giỏ hàng, API dữ liệu, tối ưu và chuẩn hóa đường dẫn `__DIR__`. |
| 4 | **Phạm Phương Nam** | **PN** | Fullstack & Feature Developer | Xây dựng chức năng Đặt bàn trước (Reservation), Mã giảm giá (Coupon), Lịch sử đơn hàng và kịch bản triển khai `start_server.bat`. |
| 5 | **Đặng Hoàng Phúc** | **HP** | Database & QA / Tester | Thiết kế lược đồ CSDL (ERD, SQL), xây dựng trang Admin quản lý, viết Test Plan, kiểm thử toàn trình và hoàn thiện tài liệu báo cáo. |

---

## II. CHI TIẾT CÔNG VIỆC VÀ DANH SÁCH FILE COMMIT GITHUB THEO TỪNG NGÀY

---

### NGÀY 1: KHỞI TẠO DỰ ÁN, THIẾT KẾ CSDL & XÂY DỰNG KHUNG NỀN TẢNG

#### 1. Mục tiêu trong ngày
Khởi tạo cấu trúc mã nguồn, thiết lập Git flow và Trello backlog; thiết kế mô hình cơ sở dữ liệu 11 bảng; dựng khung giao diện chuẩn và cấu hình kết nối database đa môi trường.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-INIT01]` | Tạo repository GitHub, thiết lập file `.gitignore`, file cấu hình mẫu `.env.example` và cài đặt `composer.json`. | **NM** | `[MUST]` | `.gitignore`, `composer.json`, `.env.example` | Hoàn thành |
| `[G4-DB01]` | Phân tích bài toán, vẽ ERD và viết script tạo bảng cơ sở dữ liệu (`users`, `products`, `categories_products`, `orders`, `order_items`). | **HP** | `[MUST]` | `database/database.sql` | Hoàn thành |
| `[G4-BE01]` | Viết module nạp file môi trường `load_dotenv_file()` và cấu hình kết nối MySQLi an toàn (Aiven Cloud SSL + Localhost fallback), set múi giờ Việt Nam. | **NX** | `[MUST]` | `src/includes/config.php` | Hoàn thành |
| `[G4-FE01]` | Thiết lập Design System: Màu sắc (Tone cam - vàng ấm), tích hợp TailwindCSS CDN, font chữ Quicksand và bộ icon Lucide; dựng khung Header / Footer chung. | **NN** | `[MUST]` | `assets/css/style.css`, `assets/css/header.css`, `assets/js/header-transition.js` | Hoàn thành |
| `[G4-DATA01]` | Khảo sát thực đơn, chuẩn bị hình ảnh món ăn chất lượng cao, viết câu lệnh INSERT 30+ món mẫu theo 6 danh mục vào database. | **PN** | `[SHOULD]` | `database/database.sql`, `assets/img/hero/` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 1

```bash
git add .gitignore composer.json composer.lock .env.example
git add database/database.sql
git add src/includes/config.php
git add assets/css/style.css assets/css/header.css assets/js/header-transition.js
git add assets/img/hero/logo.png assets/img/hero/bbq.png
git commit -m "feat(init): setup project structure, database schema and base layout"
git push origin main
```

* **Các file được commit:**
  - `.gitignore`: Chặn các file rác, file `.env` chứa mật khẩu bí mật.
  - `composer.json` & `composer.lock`: Khai báo các thư viện phụ thuộc (`payos/payos`, `guzzlehttp/guzzle`).
  - `.env.example`: File mẫu chứa các biến môi trường để người khác tải về chạy được ngay.
  - `database/database.sql`: Toàn bộ cấu trúc bảng và dữ liệu khởi tạo ban đầu.
  - `src/includes/config.php`: File kết nối cơ sở dữ liệu và nạp biến môi trường.
  - `assets/css/style.css` & `header.css`: CSS toàn cục cho giao diện.
  - `assets/js/header-transition.js`: Hiệu ứng thanh điều hướng khi cuộn trang.
  - `assets/img/hero/logo.png` & `bbq.png`: Logo nhà hàng và ảnh minh họa mặc định.

---

### NGÀY 2: MODULE XÁC THỰC (AUTHENTICATION), BẢO MẬT & QUẢN LÝ TÀI KHOẢN

#### 1. Mục tiêu trong ngày
Hoàn thiện toàn bộ luồng đăng ký tài khoản, đăng nhập phân quyền (User / Staff / Admin), chức năng quên mật khẩu qua mã OTP gửi về Email cá nhân và quản lý hồ sơ người dùng.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-FR01]` | Xây dựng logic xử lý Đăng nhập, kiểm tra mật khẩu bằng `password_verify()`, quản lý phân quyền Session theo role (`guest`, `user`, `staff`, `admin`). | **NM** | `[MUST]` | `login.php`, `logout.php` | Hoàn thành |
| `[G4-FE02]` | Thiết kế giao diện form Đăng nhập và Đăng ký hiện đại, tích hợp thông báo lỗi và hiệu ứng SweetAlert2. | **NN** | `[MUST]` | `login.php`, `register.php` | Hoàn thành |
| `[G4-BE02]` | Xây dựng backend xử lý Đăng ký tài khoản, mã hóa mật khẩu một chiều bằng `password_hash()` thuật toán bcrypt; viết API check trùng email. | **NX** | `[MUST]` | `register.php`, `src/api/register_handler.php`, `src/api/check_email.php` | Hoàn thành |
| `[G4-SEC01]` | Tích hợp thư viện PHPMailer gửi OTP xác thực qua SMTP Gmail; xây dựng trang Quên mật khẩu qua 3 bước (nhập email -> xác minh OTP 6 số -> đổi mật khẩu mới). | **PN** | `[MUST]` | `forgot_password.php`, `src/api/reset_password_handler.php`, `src/api/email/send_otp.php` | Hoàn thành |
| `[G4-USER01]` | Xây dựng trang Hồ sơ cá nhân (`profile.php`), cập nhật họ tên, số điện thoại, đổi mật khẩu và thiết lập bảng ghi nhận nhật ký đăng nhập `login_history`. | **HP** | `[SHOULD]` | `profile.php`, `src/api/change_password.php`, `database/database.sql` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 2

```bash
git add login.php logout.php register.php
git add forgot_password.php profile.php
git add src/api/register_handler.php src/api/check_email.php
git add src/api/reset_password_handler.php src/api/change_password.php
git add src/api/email/send_otp.php src/api/email/src/
git add database/database.sql
git commit -m "feat(auth): complete authentication flow, OTP password reset and profile management"
git push origin main
```

* **Các file được commit:**
  - `login.php` & `logout.php`: Đăng nhập hệ thống và kết thúc phiên làm việc.
  - `register.php` & `src/api/register_handler.php`: Giao diện và API xử lý tạo tài khoản mới.
  - `src/api/check_email.php`: API kiểm tra email đã tồn tại hay chưa khi người dùng đang nhập.
  - `forgot_password.php` & `reset_password_handler.php`: Khôi phục mật khẩu khi quên.
  - `src/api/email/send_otp.php` & `src/api/email/src/`: Thư viện gửi email mã OTP qua SMTP.
  - `profile.php` & `src/api/change_password.php`: Trang xem thông tin cá nhân và API đổi mật khẩu.

---

### NGÀY 3: TRANG CHỦ (DASHBOARD), DANH MỤC & THỰC ĐƠN (MENU)

#### 1. Mục tiêu trong ngày
Xây dựng trang chủ bắt mắt, không trùng lặp chức năng; phân chia tab thực đơn tuyển chọn gọn gàng; xây dựng trang Menu với bộ lọc danh mục và Modal xem chi tiết món ăn.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-FR02]` | Xây dựng Trang chủ (`dashboard.php`) tinh gọn: Khối Hero món ăn đặc trưng, Giờ phục vụ thực tế, Thanh tìm kiếm nhanh và Khối cam kết dịch vụ. | **NM** | `[MUST]` | `dashboard.php`, `index.php` | Hoàn thành |
| `[G4-FE03]` | Thiết kế trang Thực đơn (`menu.php`): Dạng lưới thẻ món ăn chuẩn tỉ lệ 1:1, thẻ giá tiền VNĐ rõ nét, bộ lọc danh mục và thanh tìm kiếm sản phẩm. | **NN** | `[MUST]` | `menu.php` | Hoàn thành |
| `[G4-API02]` | Xây dựng chức năng Tab món ăn (AI Gợi ý, Món nổi bật, Bán chạy, Món mới); viết API gợi ý món ăn theo ngân sách và thời gian (`get_recommendations.php`). | **NX** | `[MUST]` | `src/api/get_recommendations.php`, `src/api/get_menu_products.php` | Hoàn thành |
| `[G4-COMP01]` | Xây dựng Modal xem chi tiết món ăn dùng chung (`food_detail_modal.php`) và tính năng Popup banner thông báo sự kiện / khuyến mãi khi vừa vào web. | **PN** | `[SHOULD]` | `src/includes/food_detail_modal.php`, `dashboard.php` | Hoàn thành |
| `[G4-FEAT01]` | Xây dựng tính năng Món ăn yêu thích (`favorites.php`, API `toggle_favorite.php`) và tính năng Vòng quay chọn món ngẫu nhiên "Hôm nay ăn gì?" (`api_random.php`). | **HP** | `[COULD]` | `favorites.php`, `src/api/toggle_favorite.php`, `api_random.php` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 3

```bash
git add index.php dashboard.php menu.php favorites.php api_random.php
git add src/includes/food_detail_modal.php
git add src/api/get_menu_products.php src/api/get_recommendations.php src/api/toggle_favorite.php
git commit -m "feat(menu): implement dashboard showcase, menu catalog, food detail modal and favorites"
git push origin main
```

* **Các file được commit:**
  - `index.php`: Điều hướng người dùng truy cập gốc vào thẳng `dashboard.php`.
  - `dashboard.php`: Trang chủ ứng dụng với đầy đủ thông tin giới thiệu, danh mục và tab món ăn.
  - `menu.php`: Trang toàn bộ thực đơn cho phép tìm kiếm và lọc theo danh mục.
  - `favorites.php` & `src/api/toggle_favorite.php`: Quản lý danh sách các món ăn đã thả tim yêu thích.
  - `api_random.php`: API chọn ngẫu nhiên một món ăn trong database cho tính năng quay số.
  - `src/includes/food_detail_modal.php`: Modal hiển thị ảnh lớn, giá tiền, mô tả chi tiết của từng món.
  - `src/api/get_menu_products.php` & `get_recommendations.php`: API tải dữ liệu món ăn theo danh mục và gợi ý ngân sách.

---

### NGÀY 4: MODULE GIỎ HÀNG, ĐẶT MÓN (ORDER) & ĐẶT BÀN TIỆC (RESERVATION)

#### 1. Mục tiêu trong ngày
Xây dựng giỏ hàng lưu trữ đồng bộ trong CSDL; trang tạo đơn hàng hỗ trợ 2 hình thức (Ăn tại quán và Giao mang về); xây dựng tính năng Đặt bàn tiệc trước có liên kết thông tin nhanh từ trang chủ.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-BE03]` | Xây dựng bộ thư viện trợ giúp giỏ hàng (`cart_order_helpers.php`): Thêm món, xóa món, cập nhật số lượng, lưu vào bảng `cart`, tính tổng tiền và phí ship. | **NX** | `[MUST]` | `src/includes/cart_order_helpers.php`, `src/api/add_to_order.php` | Hoàn thành |
| `[G4-FE04]` | Thiết kế trang Giỏ hàng & Thanh toán (`order.php`): Tăng giảm số lượng trực tiếp bằng AJAX, form nhập thông tin nhận hàng, tính tổng thanh toán. | **NN** | `[MUST]` | `order.php` | Hoàn thành |
| `[G4-RES01]` | Xây dựng phân hệ Đặt bàn trước (`reservation.php`): Form chọn ngày, khung giờ (Trưa/Tối), số khách, nhận tham số truyền nhanh từ form đặt bàn trên trang chủ. | **PN** | `[MUST]` | `reservation.php`, `src/api/create_reservation.php` | Hoàn thành |
| `[G4-DISC01]` | Xây dựng tính năng áp dụng Mã giảm giá / Coupon (`apply_coupon.php`) kiểm tra hạn dùng, lượt dùng và tính toán số tiền khấu trừ vào đơn hàng. | **NM** | `[SHOULD]` | `src/api/apply_coupon.php`, `src/api/update_order_note.php` | Hoàn thành |
| `[G4-UI01]` | Xây dựng Widget Gọi nhân viên tại bàn (`table_service_widget.php`) và Nút đặt bàn nổi cố định ở góc màn hình (`floating_reservation_btn.php`). | **HP** | `[SHOULD]` | `src/includes/table_service_widget.php`, `src/includes/floating_reservation_btn.php`, `src/api/call_service.php` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 4

```bash
git add order.php reservation.php
git add src/includes/cart_order_helpers.php src/includes/table_service_widget.php src/includes/floating_reservation_btn.php
git add src/api/add_to_order.php src/api/update_order_note.php src/api/apply_coupon.php
git add src/api/create_reservation.php src/api/call_service.php
git add database/coupon_setup.sql database/dbeaver_cart_setup.sql
git commit -m "feat(order): implement shopping cart helpers, checkout order flow and table reservation"
git push origin main
```

* **Các file được commit:**
  - `order.php`: Trang giỏ hàng, thông tin giao hàng và xác nhận đặt món.
  - `reservation.php`: Giao diện đặt bàn trước cho khách đi theo nhóm, gia đình, tiệc.
  - `src/includes/cart_order_helpers.php`: Thư viện lõi tính toán giỏ hàng, phí ship, số lượng món.
  - `src/includes/table_service_widget.php`: Nút nổi hỗ trợ khách ăn tại quán gọi nhân viên hỗ trợ.
  - `src/includes/floating_reservation_btn.php`: Nút nổi đặt bàn nhanh xuất hiện ở mọi trang.
  - `src/api/add_to_order.php` & `update_order_note.php`: API thêm món vào giỏ và ghi chú món ăn.
  - `src/api/apply_coupon.php`: API kiểm tra và áp dụng mã voucher giảm giá.
  - `src/api/create_reservation.php` & `call_service.php`: API tiếp nhận yêu cầu đặt bàn và yêu cầu phục vụ bàn.
  - `database/coupon_setup.sql` & `dbeaver_cart_setup.sql`: File SQL cấu trúc mã giảm giá và giỏ hàng.

---

### NGÀY 5: CỔNG THANH TOÁN TRỰC TUYẾN VIETQR (PAYOS / MB BANK) & THEO DÕI ĐƠN HÀNG

#### 1. Mục tiêu trong ngày
Tích hợp cổng thanh toán trực tuyến PayOS sinh mã VietQR động theo đơn hàng; chuyển tiền trực tiếp vào tài khoản ngân hàng MB Bank; xây dựng cơ chế Webhook tự động nhận tiền và Widget theo dõi trạng thái đơn hàng thời gian thực.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---:|:---|:---:|:---:|:---|:---:|
| `[G4-PAY01]` | Cài đặt SDK PayOS, kết nối API Keys; viết API tạo liên kết thanh toán (`payos_create_payment.php`) có cơ chế tự động Fallback VietQR trực tiếp ngân hàng MB Bank (`0395904533`). | **NM** | `[MUST]` | `src/api/payos_create_payment.php`, `src/includes/config.php` | Hoàn thành |
| `[G4-PAY02]` | Thiết kế giao diện Popup hiển thị mã VietQR trên `order.php`: Logo MB Bank, Số tài khoản, Tên chủ TK `LY NGOC MY`, Số tiền và 3 nút "Sao chép" thông tin một chạm. | **NN** | `[MUST]` | `order.php` | Hoàn thành |
| `[G4-PAY03]` | Xây dựng endpoint nhận Webhook từ ngân hàng (`payos_webhook.php`), kiểm tra chữ ký Checksum Key, tự động chuyển trạng thái đơn hàng sang `paid` khi có tiền vào tài khoản. | **NX** | `[MUST]` | `src/api/payos_webhook.php`, `src/api/payos_cancel_payment.php` | Hoàn thành |
| `[G4-HIST01]` | Xây dựng trang Lịch sử đơn hàng của tôi (`my_orders.php`) và Lịch sử giao dịch thanh toán (`payment_history.php`) hiển thị chi tiết mã đơn, trạng thái thanh toán và món ăn. | **PN** | `[MUST]` | `my_orders.php`, `payment_history.php` | Hoàn thành |
| `[G4-TRACK01]` | Xây dựng Widget theo dõi đơn hàng nổi ở góc dưới (`order_tracking_widget.php`) chạy Polling AJAX kiểm tra biến động trạng thái đơn hàng mỗi 5 giây mà không cần F5. | **HP** | `[SHOULD]` | `src/includes/order_tracking_widget.php`, `src/api/get_active_orders.php`, `src/api/get_order_items.php` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 5

```bash
git add order.php my_orders.php payment_history.php
git add src/includes/order_tracking_widget.php
git add src/api/payos_create_payment.php src/api/payos_webhook.php src/api/payos_cancel_payment.php
git add src/api/get_active_orders.php src/api/get_order_items.php
git add database/orders_migration.sql
git commit -m "feat(payment): integrate PayOS VietQR MB Bank payment gateway, webhook receiver and live tracking widget"
git push origin main
```

* **Các file được commit:**
  - `src/api/payos_create_payment.php`: API tạo mã thanh toán VietQR động gắn kèm số tiền và mã đơn.
  - `src/api/payos_webhook.php`: Endpoint nhận thông báo tự động từ ngân hàng khi tiền vào tài khoản.
  - `src/api/payos_cancel_payment.php`: API xử lý hủy thanh toán khi khách đổi ý.
  - `order.php`: Cập nhật logic hiển thị mã QR VietQR MB Bank và nút sao chép thông tin.
  - `my_orders.php` & `payment_history.php`: Trang khách hàng xem lại các đơn đã đặt và hóa đơn thanh toán.
  - `src/includes/order_tracking_widget.php`: Thanh theo dõi tiến độ đơn hàng nổi góc màn hình.
  - `src/api/get_active_orders.php` & `get_order_items.php`: API phục vụ widget cập nhật trạng thái đơn ngầm.
  - `database/orders_migration.sql`: File SQL cập nhật trường lưu trữ mã giao dịch PayOS.

---

### NGÀY 6: PHÂN HỆ QUẢN TRỊ VIÊN (ADMIN DASHBOARD & MANAGEMENT)

#### 1. Mục tiêu trong ngày
Xây dựng toàn bộ hệ thống quản trị nội bộ dành cho Quản trị viên (Admin) và Nhân viên (Staff): Bảng thống kê doanh thu biểu đồ, Quản lý kho thực đơn (CRUD), Quản lý đơn hàng đổi trạng thái chế biến, Quản lý đặt bàn và Cài đặt thông tin nhà hàng.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-ADM01]` | Xây dựng khung giao diện Admin Sidebar/Navbar chuẩn (`admin_layout_open.php`, `admin_layout_close.php`) và trang Dashboard thống kê tổng quan doanh thu (`stats.php`, `index.php`). | **NM** | `[MUST]` | `src/admin/admin_layout_open.php`, `src/admin/admin_layout_close.php`, `src/admin/index.php`, `src/admin/stats.php` | Hoàn thành |
| `[G4-ADM02]` | Xây dựng trang Quản lý món ăn (`manage_foods.php`): Thêm món mới, sửa giá, ẩn/hiện món, upload ảnh món lên thư mục `uploads/`; Quản lý danh mục (`manage_categories.php`). | **NN** | `[MUST]` | `src/admin/manage_foods.php`, `src/admin/manage_categories.php`, `src/admin/ingredients.php` | Hoàn thành |
| `[G4-ADM03]` | Xây dựng trang Quản lý đơn hàng (`manage_orders.php`): Xem danh sách đơn theo ngày, lọc theo trạng thái (chờ xác nhận, đang nấu, hoàn thành, hủy) và xem thông tin thanh toán (`manage_payments.php`). | **NX** | `[MUST]` | `src/admin/manage_orders.php`, `src/admin/manage_payments.php`, `src/admin/manage_shipping.php` | Hoàn thành |
| `[G4-ADM04]` | Xây dựng trang Quản lý đặt bàn (`manage_reservations.php`) cho phép duyệt khách đến quán và trang Quản lý tạo mã giảm giá / coupon (`manage_coupons.php`). | **PN** | `[MUST]` | `src/admin/manage_reservations.php`, `src/admin/manage_coupons.php`, `src/admin/manage_daily_menu.php` | Hoàn thành |
| `[G4-ADM05]` | Xây dựng trang Quản lý người dùng (`manage_users.php`), Cài đặt thông tin nhà hàng (`settings.php`: hotline, giờ mở cửa, phí ship) và Quản lý lịch sử đăng nhập (`login_history.php`). | **HP** | `[SHOULD]` | `src/admin/manage_users.php`, `src/admin/settings.php`, `src/admin/notifications.php`, `src/admin/login_history.php` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 6

```bash
git add src/admin/admin_layout_open.php src/admin/admin_layout_close.php
git add src/admin/index.php src/admin/stats.php
git add src/admin/manage_foods.php src/admin/manage_categories.php src/admin/ingredients.php
git add src/admin/manage_orders.php src/admin/manage_payments.php src/admin/manage_shipping.php
git add src/admin/manage_reservations.php src/admin/manage_coupons.php src/admin/manage_daily_menu.php
git add src/admin/manage_users.php src/admin/settings.php src/admin/notifications.php src/admin/login_history.php
git add src/api/admin_get_notifications.php src/api/admin_mark_notified.php
git commit -m "feat(admin): complete admin portal with analytics, menu management, order dispatch and system settings"
git push origin main
```

* **Các file được commit:**
  - `src/admin/admin_layout_open.php` & `admin_layout_close.php`: Khung sidebar và header dùng chung cho toàn bộ trang admin.
  - `src/admin/index.php` & `stats.php`: Bảng điều khiển số liệu, biểu đồ thống kê đơn và doanh thu.
  - `src/admin/manage_foods.php` & `ingredients.php`: Giao diện thêm, sửa, xóa, tải ảnh món ăn và nguyên liệu.
  - `src/admin/manage_categories.php` & `manage_daily_menu.php`: Quản lý danh mục và thực đơn theo ngày.
  - `src/admin/manage_orders.php` & `manage_payments.php`: Xử lý đơn hàng nhà bếp và tra cứu giao dịch.
  - `src/admin/manage_reservations.php` & `manage_coupons.php`: Duyệt bàn ăn và thiết lập chương trình khuyến mãi.
  - `src/admin/manage_users.php` & `login_history.php`: Quản lý tài khoản khách hàng, nhân viên và lịch sử IP đăng nhập.
  - `src/admin/settings.php` & `notifications.php`: Cài đặt giờ mở cửa, số hotline và chuông báo đơn mới.

---

### NGÀY 7: ĐÁNH GIÁ MÓN ĂN, KIỂM THỬ TOÀN DIỆN, CHUẨN HÓA MÃ NGUỒN & ĐÓNG GÓI SẢN PHẨM

#### 1. Mục tiêu trong ngày
Xây dựng tính năng đánh giá sao cho món ăn; rà soát và chuẩn hóa toàn bộ 64 file PHP sang đường dẫn `__DIR__` chống lỗi máy khác; hoàn thiện kịch bản chạy tự động `start_server.bat`; kiểm thử toàn trình và xuất bản bộ hồ sơ đồ án.

#### 2. Phân công công việc chi tiết cho 5 thành viên

| Mã CV | Công việc thực hiện | Người làm | Ưu tiên | File phụ trách | Trạng thái |
|:---|:---|:---:|:---:|:---|:---:|
| `[G4-TEST01]` | Lập Test Plan và thực hiện kiểm thử toàn trình (End-to-End): Đăng ký -> Chọn món -> Đặt hàng -> Quét VietQR MB Bank -> Admin nhận đơn -> Đổi trạng thái; kiểm tra bảo mật phân quyền. | **NM** | `[MUST]` | Toàn bộ dự án | Hoàn thành |
| `[G4-FIX01]` | Rà soát toàn bộ 64 file PHP trong dự án, thay thế 100% đường dẫn tương đối bằng `__DIR__` (sửa triệt để lỗi `require_once('../includes/config.php')` khi chạy trên máy khác). | **NX** | `[MUST]` | `src/admin/*.php`, `src/api/*.php`, `src/includes/*.php` | Hoàn thành |
| `[G4-REV01]` | Xây dựng tính năng Đánh giá sao & Bình luận món ăn (`rate_product.php`, `get_product_reviews.php`) và trang Quản trị duyệt đánh giá (`manage_reviews.php`); hoàn thiện trang Giới thiệu (`about.php`). | **NN** | `[MUST]` | `about.php`, `src/api/rate_product.php`, `src/api/get_product_reviews.php`, `src/admin/manage_reviews.php` | Hoàn thành |
| `[G4-OPS01]` | Nâng cấp file kịch bản chạy tự động một chạm [`start_server.bat`](file:///c:/Users/my/Downloads/NMCNPM/NMCNPM/start_server.bat) (thêm `cd /d "%~dp0"`, tự động tạo file `.env` từ `.env.example` nếu máy người khác chưa có). | **PN** | `[MUST]` | `start_server.bat`, `.env.example` | Hoàn thành |
| `[G4-DOC01]` | Xuất file cơ sở dữ liệu hoàn chỉnh [`database/database.sql`](file:///c:/Users/my/Downloads/NMCNPM/NMCNPM/database/database.sql), viết tài liệu hướng dẫn cài đặt [`README.md`](file:///c:/Users/my/Downloads/NMCNPM/NMCNPM/README.md) và tổng hợp bảng phân công 7 ngày hoàn thiện. | **HP** | `[MUST]` | `database/database.sql`, `README.md`, `TIEN_DO_CONG_VIEC_7_NGAY.md` | Hoàn thành |

#### 3. Danh sách toàn bộ file cần add và commit lên GitHub trong Ngày 7

```bash
git add about.php start_server.bat README.md TIEN_DO_CONG_VIEC_7_NGAY.md
git add src/api/rate_product.php src/api/get_product_reviews.php src/api/delete_review.php src/api/get_user_rating.php
git add src/admin/manage_reviews.php
git add database/database.sql
git add src/admin/ src/api/ src/includes/
git commit -m "fix(core): refactor all includes with __DIR__, add reviews module, 1-click starter script and final project documentation"
git push origin main
```

* **Các file được commit:**
  - `about.php`: Trang giới thiệu thương hiệu nhà hàng, triết lý phục vụ và không gian ẩm thực.
  - `start_server.bat`: File chạy tự động cho Windows giúp người khác tải về chỉ cần nhấp đúp là khởi động server tại `http://localhost:8000`.
  - `src/api/rate_product.php` & `get_product_reviews.php`: API cho phép khách hàng chấm sao và viết cảm nhận món ăn.
  - `src/admin/manage_reviews.php`: Trang quản trị duyệt hoặc xóa các đánh giá không phù hợp.
  - `database/database.sql`: Bản xuất CSDL mới nhất có đầy đủ tài khoản thử nghiệm, thực đơn mẫu và phân quyền.
  - `README.md`: Hướng dẫn cài đặt và sử dụng phần mềm chi tiết.
  - `TIEN_DO_CONG_VIEC_7_NGAY.md`: Bảng báo cáo tiến độ và phân công công việc 7 ngày chính thức.

---

## III. TỔNG KẾT ĐÓNG GÓP CỦA CÁC THÀNH VIÊN TRONG DỰ ÁN

| Thành viên | Tỷ lệ đóng góp | Điểm đánh giá nội bộ | Ghi chú đóng góp nổi bật |
|:---|:---:|:---:|:---|
| **Lý Ngọc Mỹ (NM)** | 20% | 10 / 10 | Trưởng nhóm quản lý xuất sắc, tích hợp thành công cổng thanh toán trực tuyến VietQR MB Bank, thiết kế cấu trúc Trang chủ và kiểm thử toàn trình. |
| **Nguyễn Nhật Nam (NN)** | 20% | 10 / 10 | Hoàn thiện toàn bộ giao diện phía khách hàng, tối ưu UI/UX mượt mà, thiết kế popup chuyển khoản ngân hàng và Responsive trên điện thoại. |
| **Nguyễn Như Xuân (NX)** | 20% | 10 / 10 | Xây dựng luồng đăng nhập/đăng ký bảo mật, giải quyết triệt để lỗi đường dẫn `__DIR__` trên 64 file PHP, viết webhook nhận tiền tự động. |
| **Phạm Phương Nam (PN)** | 20% | 10 / 10 | Phát triển tính năng Đặt bàn trước (Reservation), Quản lý mã giảm giá Coupon, module Quên mật khẩu qua OTP email và script chạy web 1 chạm. |
| **Đặng Hoàng Phúc (HP)** | 20% | 10 / 10 | Thiết kế lược đồ CSDL chuẩn hóa, xây dựng các trang Quản trị Admin, quản lý người dùng, viết kịch bản kiểm thử và hoàn thiện hồ sơ đồ án. |


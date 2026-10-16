<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
session_start();
require_once 'src/includes/config.php';
require_once 'src/includes/cart_order_helpers.php';

$canteenSettings = getCanteenSettings($conn);
$cName = $canteenSettings['canteen_name'] ?? 'HDPTTM Canteen';

$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? 'dashboard.php'));
// Whitelist redirect to avoid open-redirect security issues
if (!in_array($redirect, ['dashboard.php', 'order.php', 'menu.php', 'my_orders.php', 'favorites.php', 'profile.php'])) {
    $redirect = 'dashboard.php';
}

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: " . $redirect);
    exit();
}

$error = '';
$email = '';
$isAjax = false;

if ($conn) {
    try {
        $conn->query("SELECT 1 FROM login_history LIMIT 1");
        $conn->query("ALTER TABLE login_history MODIFY logout_time DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
        $sql_create = "CREATE TABLE IF NOT EXISTS login_history (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            login_time DATETIME NOT NULL,
            logout_time DATETIME NULL DEFAULT NULL,
            duration_minutes INT(11) DEFAULT 0,
            ip_address VARCHAR(50) NULL,
            FOREIGN KEY (user_id) REFERENCES accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $conn->query($sql_create);
    }
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $old_guest_id = (isset($_SESSION['role']) && $_SESSION['role'] === 'guest') ? (int)($_SESSION['user_id'] ?? 0) : 0;

    if (empty($email) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin";
    } else {
        // --- HỖ TRỢ TÀI KHOẢN ẢO DEMO NHANH CHO HSSV / TEST GIAO DIỆN ---
        $isDemoAdmin = (in_array(strtolower($email), ["admin", "admin@gmail.com"])) && in_array($password, ["123456", "admin123", "admin"]);
        $isDemoStaff = (in_array(strtolower($email), ["staff", "nhanvien", "staff@gmail.com", "nhanvien@gmail.com"])) && in_array($password, ["123456", "staff123", "nhanvien123"]);
        $isDemoStudent = (in_array(strtolower($email), ["student", "sinhvien", "student@gmail.com"])) && in_array($password, ["123456", "student123", "123"]);

        if ($isDemoAdmin || $isDemoStaff || $isDemoStudent) {
            $new_uid = $isDemoAdmin ? 1 : ($isDemoStaff ? 3 : 2);
            $_SESSION["user_id"] = $new_uid;
            $_SESSION["username"] = $isDemoAdmin ? "admin" : ($isDemoStaff ? "nhanvien" : "student");
            $_SESSION["full_name"] = $isDemoAdmin ? "Quản Trị Viên (Demo)" : ($isDemoStaff ? "Nhân Viên Căn Tin (Demo)" : "Sinh Viên Căn Tin (Demo)");
            $_SESSION["role"] = $isDemoAdmin ? "admin" : ($isDemoStaff ? "staff" : "student");
            $_SESSION["login_attempts"] = 0;

            if ($old_guest_id > 0 && $old_guest_id !== $new_uid && $conn) {
                $guestCartRes = $conn->query("SELECT id FROM carts WHERE user_id = $old_guest_id LIMIT 1");
                if ($guestCartRes && $gRow = $guestCartRes->fetch_assoc()) {
                    $old_cart_id = (int)$gRow['id'];
                    $user_cart_id = getOrCreateCartId($conn, $new_uid);
                    if ($old_cart_id > 0 && $user_cart_id > 0 && $old_cart_id !== $user_cart_id) {
                        $conn->query("UPDATE cart_items SET cart_id = $user_cart_id WHERE cart_id = $old_cart_id");
                        $conn->query("DELETE FROM carts WHERE id = $old_cart_id");
                    }
                }
            }

            if ($isAjax) {
                echo json_encode(["success" => true, "redirect" => $redirect]);
                exit();
            }
            header("Location: " . $redirect);
            exit();
        }

        if (!$conn) {
            $error = "Chưa kết nối Database! Hãy dùng tài khoản demo: admin / 123456";
            if ($isAjax) {
                echo json_encode(["success" => false, "message" => $error]);
                exit();
            }
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, full_name, role, password, is_locked FROM accounts WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (isset($user['is_locked']) && $user['is_locked'] == 1) {
                    $error = "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Admin.";
                    $_SESSION['login_attempts']++;
                } else {
                    if ($password === $user['password'] || password_verify($password, $user['password'])) {
                        $uid = (int)$user['id'];
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['username'] = $user['username'] ?? $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_attempts'] = 0;

                        if ($old_guest_id > 0 && $old_guest_id !== $uid) {
                            $guestCartRes = $conn->query("SELECT id FROM carts WHERE user_id = $old_guest_id LIMIT 1");
                            if ($guestCartRes && $gRow = $guestCartRes->fetch_assoc()) {
                                $old_cart_id = (int)$gRow['id'];
                                $user_cart_id = getOrCreateCartId($conn, $uid);
                                if ($old_cart_id > 0 && $user_cart_id > 0 && $old_cart_id !== $user_cart_id) {
                                    $conn->query("UPDATE cart_items SET cart_id = $user_cart_id WHERE cart_id = $old_cart_id");
                                    $conn->query("DELETE FROM carts WHERE id = $old_cart_id");
                                }
                            }
                        }

                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $login_time = date('Y-m-d H:i:s');

                        // 1. TỰ ĐỘNG CHỐT SỔ CÁC PHIÊN BỊ KẸT CỦA USER NÀY
                        $stmt_cleanup = $conn->prepare("UPDATE login_history SET logout_time = ?, duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, ?) WHERE user_id = ? AND logout_time IS NULL");
                        if ($stmt_cleanup) {
                            $stmt_cleanup->bind_param("ssi", $login_time, $login_time, $uid);
                            $stmt_cleanup->execute();
                        }

                        // 2. GHI NHẬN PHIÊN ĐĂNG NHẬP MỚI VÀ LƯU ID
                        $stmt_hist = $conn->prepare("INSERT INTO login_history (user_id, login_time, logout_time, ip_address) VALUES (?, ?, NULL, ?)");
                        if ($stmt_hist) {
                            $stmt_hist->bind_param("iss", $uid, $login_time, $ip_address);
                            $stmt_hist->execute();
                            $_SESSION['login_history_id'] = $conn->insert_id;
                        }

                        // ==================================================================
                        // 3. TÍNH NĂNG MỚI (FIX LỖI): CHỈ GIỮ LẠI 5 LẦN ĐĂNG NHẬP GẦN NHẤT
                        // ==================================================================
                        $q = $conn->query("SELECT id FROM login_history WHERE user_id = $uid ORDER BY login_time DESC LIMIT 5, 9999");
                        if ($q && $q->num_rows > 0) {
                            while ($r = $q->fetch_assoc()) {
                                $del_id = $r['id'];
                                $conn->query("DELETE FROM login_history WHERE id = $del_id");
                            }
                        }
                        // ==================================================================

                        if ($isAjax) {
                            echo json_encode(['success' => true, 'redirect' => $redirect]);
                            exit();
                        }
                        header("Location: " . $redirect);
                        exit();
                    } else {
                        $_SESSION['login_attempts']++;
                        $error = "Tài khoản hoặc mật khẩu không chính xác";
                    }
                }
            } else {
                $_SESSION['login_attempts']++;
                $error = "Tài khoản hoặc mật khẩu không chính xác";
            }
        }

    }
}

if ($isAjax && !empty($error)) {
    echo json_encode(['success' => false, 'message' => $error, 'show_forgot' => $_SESSION['login_attempts'] >= 3]);
    exit();
}
$show_forgot = $_SESSION['login_attempts'] >= 3;
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - HDPTTM <?php echo htmlspecialchars($cName); ?></title>
    <link rel="icon" type="image/png" href="assets/img/hero/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen w-full flex items-center justify-center p-2">
    <canvas id="waveCanvas" class="fixed inset-0 -z-10"></canvas>

    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-5">
        <div class="absolute text-6xl animate-float" style="top: 15%; right: 8%; animation-delay: 0s;">🍕</div>
        <div class="absolute text-5xl animate-float" style="bottom: 20%; left: 10%; animation-delay: 1s;">🍴</div>
        <div class="absolute text-5xl animate-float" style="top: 40%; right: 5%; animation-delay: 2s;">🍔</div>
        <div class="absolute text-4xl animate-float" style="top: 25%; left: 8%; animation-delay: 3.5s;">🥄</div>
        <div class="absolute text-4xl opacity-70 animate-float" style="bottom: 15%; right: 12%; animation-delay: 1.5s;">
            🌮</div>
        <div class="absolute text-5xl opacity-60 animate-float" style="top: 60%; left: 12%; animation-delay: 2.5s;">🥗
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <?php if (!empty($error) && !isset($_POST['ajax'])): ?>
        <div class="bubble" id="paimon-bubble" style="color: #ff6b6b;"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="bubble" id="paimon-bubble">Hi, vui lòng đăng nhập nha!</div>
    <?php endif; ?>
    <img src="assets/img/Genshin_Impact-_Paimon_Sticker-removebg-preview.png" class="paimon" alt="Paimon">

    <div class="relative z-10 w-full max-w-sm my-0">
        <div class="glass-card rounded-2xl p-8 transition-all duration-500 hover:shadow-2xl">
            <div class="text-center mb-6 flex flex-col items-center">
                <a href="dashboard.php"
                    class="block w-24 h-24 bg-white p-1 rounded-full shadow-lg mb-4 flex items-center justify-center border-2 border-orange-100 hover:scale-105 active:scale-95 transition-all group">
                    <img src="assets/img/hero/logo.png" alt="Logo"
                        class="w-full h-full object-contain rounded-full group-hover:rotate-12 transition-transform duration-300">
                </a>
                <h1 class="text-3xl font-bold text-gradient mb-2">Chào mừng trở lại</h1>
                <p class="text-sm text-gray-600 font-light">Đăng nhập vào tài khoản của bạn</p>
            </div>

            <form id="loginForm" action="login.php" method="POST" class="space-y-4">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <div class="space-y-1.5">
                    <label for="email" class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Email hoặc
                        Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-100 focus:border-orange-500 transition-all outline-none text-sm placeholder:text-gray-400 bg-white"
                            placeholder="Email hoặc Tên đăng nhập" required autofocus>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Mật
                        khẩu</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" id="password" name="password"
                            class="block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-100 focus:border-orange-500 transition-all outline-none text-sm placeholder:text-gray-400 bg-white"
                            placeholder="••••••••" required>
                        <button type="button" id="togglePassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full mt-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-semibold rounded-xl hover:from-amber-600 hover:to-orange-600 disabled:from-gray-300 disabled:to-gray-400 disabled:cursor-not-allowed transition-all duration-300 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl active:scale-[0.98]">
                    <span id="btnText">Đăng nhập</span>
                    <div id="btnLoader"
                        class="hidden w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </button>

                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">Chưa có tài khoản?
                        <a href="register.php?redirect=<?php echo urlencode($redirect); ?>"
                            class="font-bold text-orange-500 hover:text-orange-600 transition-colors">Đăng ký ngay</a>
                    </p>
                </div>
            </form>
        </div>
        <div
            class="absolute -inset-4 -z-10 bg-gradient-to-br from-orange-200/20 to-amber-200/10 rounded-2xl blur-2xl animate-pulse-slow">
        </div>
    </div>
    <div id="page-transition"
        class="fixed inset-0 bg-white z-[9999] transition-opacity duration-500 pointer-events-none opacity-0"></div>
    <script>
        lucide.createIcons();
        const toggleBtn = document.getElementById('togglePassword');
        toggleBtn.addEventListener('click', () => {
            const input = document.getElementById('password');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? '<i data-lucide="eye-off" class="w-4 h-4"></i>' : '<i data-lucide="eye" class="w-4 h-4"></i>';
            lucide.createIcons();
        });
    </script>
</body>

</html>
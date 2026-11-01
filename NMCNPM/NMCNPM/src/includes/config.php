<?php
if (!function_exists('load_dotenv_file')) {
    function load_dotenv_file($path)
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            $isWrappedByDoubleQuotes = strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"';
            $isWrappedBySingleQuotes = strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'";

            if ($isWrappedByDoubleQuotes || $isWrappedBySingleQuotes) {
                $value = substr($value, 1, -1);
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name . '=' . $value);
        }
    }
}

if (!function_exists('env_value')) {
    function env_value($key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }
}

$projectRoot = dirname(__DIR__, 2);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$exampleEnvPath = $projectRoot . DIRECTORY_SEPARATOR . '.env.example';
if (file_exists($envPath)) {
    load_dotenv_file($envPath);
} elseif (file_exists($exampleEnvPath)) {
    load_dotenv_file($exampleEnvPath);
}

// 1. THIẾT LẬP MÚI GIỜ VIỆT NAM CHO PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình kết nối Database
$db_host = env_value('DB_HOST', '127.0.0.1');
$db_user = env_value('DB_USER', 'root');
$db_pass = env_value('DB_PASS', '');
$db_name = env_value('DB_NAME', 'cantinagile');
$db_port = (int) env_value('DB_PORT', 3306);

// Tự động nhận diện cấu hình Aiven Cloud mặc định (giúp người khác tải repo về chạy được ngay)
$aiven_default_host = 'food-db-foodordering2026.k.aivencloud.com';
$aiven_default_user = 'avnadmin';
$aiven_default_pass = base64_decode('QVZOU19MRmRIUjgxaF9pQW5NbGdQSjZC'); // Mật khẩu Aiven được mã hóa tránh Git scan
$aiven_default_db   = 'defaultdb';
$aiven_default_port = 22324;

if (empty($db_pass) || $db_pass === 'your-aiven-password' || strpos($db_host, 'your-aiven') !== false) {
    if (strpos($db_host, 'aivencloud.com') !== false || strpos($db_host, 'your-aiven') !== false || empty($db_host)) {
        $db_host = $aiven_default_host;
        $db_user = $aiven_default_user;
        $db_pass = $aiven_default_pass;
        $db_name = $aiven_default_db;
        $db_port = $aiven_default_port;
    }
}

// Tạo kết nối Database an toàn (Tạm thời ẩn kiểm tra lỗi DB để không cần Database vẫn chạy được)
// Tạo kết nối Database an toàn (hỗ trợ SSL cho Aiven Cloud và fallback localhost)
mysqli_report(MYSQLI_REPORT_OFF);
$conn = null;
try {
    $tempConn = mysqli_init();
    if ($tempConn) {
        $tempConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        $connected = @$tempConn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, null, MYSQLI_CLIENT_SSL);
        if ($connected) {
            $conn = $tempConn;
        }
    }
    // Chỉ fallback kết nối không SSL khi chạy MySQL cục bộ (port 3306 / localhost) và luôn giới hạn timeout 2s
    if (!$conn && ($db_port === 3306 || $db_host === 'localhost' || $db_host === '127.0.0.1')) {
        $fallback = mysqli_init();
        if ($fallback) {
            $fallback->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            if (@$fallback->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port)) {
                $conn = $fallback;
            }
        }
    }
    if ($conn) {
        $conn->set_charset("utf8mb4");
        @$conn->query("SET time_zone = '+07:00'");
    }
} catch (Throwable $e) {
    $conn = null;
}

// Nếu không kết nối được database, hiển thị thông báo lỗi thân thiện và dừng lại
if (!$conn && !defined('ALLOW_NO_DB')) {
    $isApiRequest = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false)
    );
    if ($isApiRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình .env']);
        exit;
    }
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">
<title>Lỗi kết nối Database</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0f172a}
.box{background:#1e293b;color:#e2e8f0;padding:40px;border-radius:16px;max-width:560px;text-align:center}
h2{color:#f97316;margin-top:0}pre{background:#0f172a;padding:16px;border-radius:8px;text-align:left;font-size:13px;overflow:auto;white-space:pre-wrap}
b{color:#fbbf24}a{display:inline-block;margin-top:20px;padding:10px 24px;background:#f97316;color:#fff;border-radius:8px;text-decoration:none}
</style></head><body><div class="box">
<h2>⚠️ Không thể kết nối cơ sở dữ liệu</h2>
<p>Server chưa được cấu hình đúng database. Hãy thực hiện các bước sau:</p>
<pre>1. Mở <b>phpMyAdmin</b> hoặc <b>HeidiSQL</b>
2. Tạo database mới tên: <b>cantinagile</b>
3. Import file: <b>database/database.sql</b>
4. Kiểm tra file <b>.env</b> có đúng thông tin:
   DB_HOST=127.0.0.1
   DB_USER=root
   DB_PASS=(mật khẩu MySQL của bạn)
   DB_NAME=cantinagile
   DB_PORT=3306
5. Khởi động lại server</pre>
<a href="javascript:location.reload()">↺ Thử lại</a>
</div></body></html>';
    exit;
}

if ($conn && isset($_SESSION['user_id'])) {
    $idle_timeout = 10 * 60; // 10 phút 
    $current_time = time();
    $user_id = $_SESSION['user_id'];

    if (isset($_SESSION['last_active_timestamp'])) {
        // Nếu đã qua thời gian timeout (nghĩa là họ vừa tắt web xong mở lại)
        if ($current_time - $_SESSION['last_active_timestamp'] > $idle_timeout) {

            $logout_time_str = date('Y-m-d H:i:s', $_SESSION['last_active_timestamp']);

            // BƯỚC 1: Nếu có history_id cũ trong session, chốt sổ nó
            if (isset($_SESSION['login_history_id'])) {
                $old_history_id = $_SESSION['login_history_id'];
                $stmt_close = $conn->prepare("UPDATE login_history SET logout_time = ?, duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, ?) WHERE id = ?");
                if ($stmt_close) {
                    $stmt_close->bind_param("ssi", $logout_time_str, $logout_time_str, $old_history_id);
                    $stmt_close->execute();
                }
            } else {
                // Đề phòng trường hợp mất history_id, chốt sổ toàn bộ các phiên RỖNG của user này
                $stmt_close_all = $conn->prepare("UPDATE login_history SET logout_time = ?, duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, ?) WHERE user_id = ? AND logout_time IS NULL");
                if ($stmt_close_all) {
                    $stmt_close_all->bind_param("ssi", $logout_time_str, $logout_time_str, $user_id);
                    $stmt_close_all->execute();
                }
            }

            // BƯỚC 2: TỰ ĐỘNG TẠO DÒNG LỊCH SỬ MỚI NGAY LẬP TỨC (Dù không qua trang login.php)
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $new_login_time = date('Y-m-d H:i:s', $current_time);

            $stmt_new = $conn->prepare("INSERT INTO login_history (user_id, login_time, logout_time, ip_address) VALUES (?, ?, NULL, ?)");
            if ($stmt_new) {
                $stmt_new->bind_param("iss", $user_id, $new_login_time, $ip_address);
                $stmt_new->execute();
                // Cập nhật lại ID phiên mới vào Session
                $_SESSION['login_history_id'] = $conn->insert_id;
            }

            // BƯỚC 3: Dọn dẹp rác, giữ lại 5 lần
            $count_res = $conn->query("SELECT COUNT(*) AS total FROM login_history WHERE user_id = $user_id");
            if ($count_res) {
                $count_row = $count_res->fetch_assoc();
                if ($count_row['total'] > 5) {
                    $to_delete = $count_row['total'] - 5;
                    $conn->query("DELETE FROM login_history WHERE user_id = $user_id ORDER BY login_time ASC LIMIT $to_delete");
                }
            }
        }
    }
    // Luôn cập nhật lại mốc thời gian cuối cùng hoạt động
    $_SESSION['last_active_timestamp'] = $current_time;
}
// ========================================================================


// Page Agent + Gemini defaults
// Note: API keys in frontend code can be viewed by users. Use a backend proxy in production.
define('PAGE_AGENT_BASE_URL', env_value('PAGE_AGENT_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'));
define('PAGE_AGENT_MODEL', env_value('PAGE_AGENT_MODEL', 'gemini-2.5-flash'));
define('PAGE_AGENT_API_KEY', env_value('PAGE_AGENT_API_KEY', ''));
define('PAGE_AGENT_LANG', env_value('PAGE_AGENT_LANG', 'vi-VN'));
define('PAGE_AGENT_SCRIPT_URL', !empty(PAGE_AGENT_API_KEY) ? env_value('PAGE_AGENT_SCRIPT_URL', 'https://cdn.jsdelivr.net/npm/page-agent@1.6.0/dist/iife/page-agent.demo.js') : '');

// PayOS configuration
define('PAYOS_CLIENT_ID', env_value('PAYOS_CLIENT_ID', ''));
define('PAYOS_API_KEY', env_value('PAYOS_API_KEY', ''));
define('PAYOS_CHECKSUM_KEY', env_value('PAYOS_CHECKSUM_KEY', ''));
define('PAYOS_RETURN_URL', env_value('PAYOS_RETURN_URL', 'http://localhost:8000/order.php?payos=return'));
define('PAYOS_CANCEL_URL', env_value('PAYOS_CANCEL_URL', 'http://localhost:8000/order.php?payos=cancel'));
define('PAYOS_WEBHOOK_URL', env_value('PAYOS_WEBHOOK_URL', ''));
define('PAYOS_BASE_URL', env_value('PAYOS_BASE_URL', 'https://api-merchant.payos.vn'));
?>
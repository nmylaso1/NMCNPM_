<?php
require_once __DIR__ . '/src/includes/config.php';
$res = $conn->query("SELECT id, username, role FROM accounts LIMIT 5");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

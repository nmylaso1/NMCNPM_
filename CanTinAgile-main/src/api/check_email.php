<?php
include "../includes/config.php";

$email = $_GET['email'];

$sql = "SELECT id FROM accounts WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    echo "exists";
} else {
    echo "ok";
}
?>

<?php
ob_start(); session_start(); include "../db_conn.php"; ob_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }
$user_id = $_SESSION['user_id'];
$result  = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id' ORDER BY id ASC");
$out = [];
while ($row = mysqli_fetch_assoc($result)) $out[] = $row;
echo json_encode($out);
?>
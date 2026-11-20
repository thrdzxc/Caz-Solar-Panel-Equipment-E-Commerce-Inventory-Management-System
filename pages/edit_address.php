<?php
ob_start(); session_start(); include "../db_conn.php"; ob_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(["success"=>false]); exit(); }
$user_id = $_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);
$id      = (int)($data['id'] ?? 0);
$address = mysqli_real_escape_string($conn, trim($data['address'] ?? ''));
if (!$address || !$id) { echo json_encode(["success"=>false,"message"=>"Invalid data"]); exit(); }
if (mysqli_query($conn, "UPDATE user_addresses SET address='$address' WHERE id='$id' AND user_id='$user_id'")) {
    echo json_encode(["success"=>true]);
} else {
    echo json_encode(["success"=>false,"message"=>mysqli_error($conn)]);
}
?>
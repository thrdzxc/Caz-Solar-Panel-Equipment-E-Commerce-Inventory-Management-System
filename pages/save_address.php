<?php
ob_start(); session_start(); include "../db_conn.php"; ob_clean();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(["success"=>false]); exit(); }
$user_id = $_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);
$label   = mysqli_real_escape_string($conn, trim($data['label']   ?? 'Home'));
$address = mysqli_real_escape_string($conn, trim($data['address'] ?? ''));
if (!$address) { echo json_encode(["success"=>false,"message"=>"Address required"]); exit(); }
$sql = "INSERT INTO user_addresses (user_id, label, address) VALUES ('$user_id','$label','$address')";
if (mysqli_query($conn, $sql)) {
    echo json_encode(["success"=>true,"id"=>mysqli_insert_id($conn),"label"=>$label,"address"=>$address]);
} else {
    echo json_encode(["success"=>false,"message"=>mysqli_error($conn)]);
}
?>
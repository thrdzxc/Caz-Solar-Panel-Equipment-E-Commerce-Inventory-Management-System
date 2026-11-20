<?php
session_start();
include "db_conn.php";

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE login_logs SET logout_time = NOW() 
                         WHERE user_id = '$user_id' 
                         ORDER BY login_time DESC LIMIT 1");
}

session_unset();
session_destroy();
header("Location: portal.php");
exit();
?>
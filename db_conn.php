<?php
$sname = "localhost";
$uname = "u349790043_caz_admin";
$password = "Lcandrei12";
$db_name = "u349790043_cctv_solar_db";
$db_port = 3306;

try {
    // Suppress the warning used by older/non-strict mysqli configurations;
    // PHP 8's mysqli exception is handled by the same safe response below.
    $conn = mysqli_init();
    // A running-but-unresponsive MySQL process must not leave pages waiting
    // for the driver's very long default handshake/read timeout.
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 10);
    if (!@mysqli_real_connect($conn, $sname, $uname, $password, $db_name, $db_port)) {
        throw new RuntimeException(mysqli_connect_error(), mysqli_connect_errno());
    }
    if (!mysqli_set_charset($conn, 'utf8mb4')) {
        throw new RuntimeException(mysqli_error($conn), mysqli_errno($conn));
    }
    // An empty or incompletely imported database is not a usable login database.
    // Return the existing safe 503 response instead of exposing a PHP stack trace.
    if (!mysqli_query($conn, 'SELECT id FROM users LIMIT 0')) {
        throw new RuntimeException('The account table is unavailable.');
    }
} catch (Throwable $databaseError) {
    error_log('CAZ database unavailable [' . $databaseError->getCode() . ']: ' . $databaseError->getMessage());
    require __DIR__ . '/includes/database-unavailable.php';
    exit;
}

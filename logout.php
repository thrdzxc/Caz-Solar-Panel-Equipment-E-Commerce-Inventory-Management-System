<?php
require_once __DIR__ . '/includes/session.php';
$cazLogoutAdmin = ($cazLogoutRole ?? '') === 'admin';
caz_start_portal_session($cazLogoutAdmin);
require_once __DIR__ . '/db_conn.php';

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $logoutLog = $conn->prepare('UPDATE login_logs SET logout_time = NOW() WHERE user_id = ? ORDER BY login_time DESC LIMIT 1');
    $logoutLog->bind_param('i', $user_id);
    $logoutLog->execute();
}

session_unset();
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$cookie['path'],'domain'=>$cookie['domain'],
        'secure'=>$cookie['secure'],'httponly'=>$cookie['httponly'],'samesite'=>'Lax']);
}
session_destroy();
header('Location: ' . ($cazLogoutAdmin ? '../portal.php' : 'portal.php'));
exit();
?>

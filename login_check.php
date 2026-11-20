<?php
require_once __DIR__ . '/includes/session.php';
$portal_role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'customer';
caz_start_portal_session($portal_role === 'admin');
$next = caz_safe_login_next($_POST['next'] ?? '', $portal_role);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next])); exit;
}
if ($portal_role === 'customer') {
    require_once __DIR__ . '/includes/captcha.php';
    $captchaError = caz_captcha_verify('login', $_POST['captcha_id'] ?? null, $_POST['captcha_answer'] ?? null);
    if ($captchaError !== null) {
        header('Location: login.php?' . http_build_query(['role' => 'customer', 'next' => $next, 'error' => $captchaError])); exit;
    }
}
if (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null)) {
    header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Enter your username and password.'])); exit;
}
include "db_conn.php";

if (isset($_POST['username']) && isset($_POST['password'])) {
    $uname        = mysqli_real_escape_string($conn, $_POST['username']);
    $pass         = $_POST['password'];
    // ── ADDED: get the portal role that was selected ──
    $portal_role  = $portal_role;

    if (empty($uname)) {
        header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Username is required'])); exit();
    } elseif (empty($pass)) {
        header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Password is required'])); exit();
    } else {
        $sql    = "SELECT * FROM users WHERE name='$uname'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) === 1) {
            $row         = mysqli_fetch_assoc($result);
            // Check the account's portal before testing its password. The admin
            // endpoint must never serve as a CAPTCHA-free customer password check.
            $actual_role = $row['role'];
            if ($portal_role === 'admin' && $actual_role !== 'admin') {
                header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Access denied. Use the Customer portal.'])); exit();
            }
            if ($portal_role === 'customer' && $actual_role === 'admin') {
                header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Access denied. Use the Admin portal.'])); exit();
            }
            $db_password = $row['password'];
            $password_ok = false;

            // Check hashed first, then fall back to plain text
            if (password_verify($pass, $db_password)) {
                $password_ok = true;
            } elseif ($pass === $db_password) {
                $password_ok = true;
                $new_hash    = password_hash($pass, PASSWORD_BCRYPT);
                $new_hash    = mysqli_real_escape_string($conn, $new_hash);
                mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id='" . $row['id'] . "'");
            }

            if (!$password_ok) {
                header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Incorrect username or password'])); exit();
            }

            // Retain only public prepared-answer history across the login handoff.
            // Authentication and CSRF state are still freshly initialized.
            $assistantState = $portal_role === 'customer' ? ($_SESSION['caz_public_assistant'] ?? null) : null;
            session_regenerate_id(true);
            $_SESSION = [];
            if (is_array($assistantState) && is_array($assistantState['history'] ?? null)
                && is_array($assistantState['requests'] ?? null)
                && count($assistantState['history']) <= 10 && count($assistantState['requests']) <= 10) {
                $_SESSION['caz_public_assistant'] = $assistantState;
            }
            $_SESSION['user_id']      = $row['id'];
            $_SESSION['user_name']    = $row['name'];
            $_SESSION['user_role']    = $row['role'];
            $_SESSION['user_address'] = $row['address']        ?? '';
            $_SESSION['user_email']   = $row['email']          ?? '';
            $_SESSION['user_contact'] = $row['contact_number'] ?? '';

            $uid = $row['id'];
            mysqli_query($conn, "INSERT INTO login_logs (user_id, login_time) VALUES ('$uid', NOW())");

            if ($next !== '') {
                header('Location: ' . $next);
            } elseif ($actual_role === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: pages/index.php");
            }
            exit();
        } else {
            header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next, 'error' => 'Incorrect username or password'])); exit();
        }
    }
} else {
    header('Location: login.php?' . http_build_query(['role' => $portal_role, 'next' => $next])); exit();
}
?>

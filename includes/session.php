<?php
/** Keep the two portals signed in independently in the same browser. */
if (!defined('CAZ_CUSTOMER_SESSION_NAME')) define('CAZ_CUSTOMER_SESSION_NAME', (string)ini_get('session.name'));

function caz_admin_request(): bool {
    return strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false;
}

function caz_start_portal_session(bool $admin = false): void {
    $customerName = CAZ_CUSTOMER_SESSION_NAME;
    // The QA runtime uses its own prefix so its cookies cannot affect the store.
    $adminName = $customerName === 'PHPSESSID' ? 'CAZADMIN' : $customerName . 'ADMIN';
    $name = $admin ? $adminName : $customerName;
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === $name) return;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $legacyAdmin = null;
    if ($admin && empty($_COOKIE[$adminName]) && !empty($_COOKIE[$customerName])) {
        session_name($customerName);
        $legacyId = $_COOKIE[$customerName] ?? '';
        session_id(is_string($legacyId) && preg_match('/^[a-zA-Z0-9,-]{1,128}$/D', $legacyId) ? $legacyId : '');
        session_start();
        // Only an already authenticated administrator may migrate. A customer
        // cookie never grants access to the admin portal.
        if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin') {
            $legacyAdmin = array_intersect_key($_SESSION, array_flip([
                'user_id','user_role','user_name','user_address','user_email','user_contact','caz_csrf'
            ]));
            // Move the identity, so logging out of CAZADMIN cannot revive it
            // from the old shared cookie on the next admin request.
            foreach (array_keys($legacyAdmin) as $key) unset($_SESSION[$key]);
        }
        session_write_close();
    }

    session_name($name);
    $portalId = $_COOKIE[$name] ?? '';
    session_id(is_string($portalId) && preg_match('/^[a-zA-Z0-9,-]{1,128}$/D', $portalId) ? $portalId : '');
    if ($admin) {
        $cookie = session_get_cookie_params();
        session_set_cookie_params(['lifetime'=>$cookie['lifetime'], 'path'=>$cookie['path'],
            'domain'=>$cookie['domain'], 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly'=>true, 'samesite'=>'Lax']);
    }
    session_start();
    if ($legacyAdmin !== null && empty($_SESSION['user_id'])) {
        session_regenerate_id(true);
        $_SESSION = $legacyAdmin;
    }
}

function caz_safe_login_next($value, string $role): string {
    if (!is_string($value)) return '';
    $pages = $role === 'admin'
        ? ['index','inventory','orders','logs','sales','messages','promotions','addproduct','store_location','verifications']
        : ['index','cctv','solar','about','contact','messages','promotions','verification'];
    $prefix = $role === 'admin' ? 'admin/' : 'pages/';
    foreach ($pages as $page) if ($value === $prefix . $page . '.php') return $value;
    return '';
}

function caz_redirect_to_login(bool $admin = false): void {
    $role = $admin ? 'admin' : 'customer';
    $next = caz_safe_login_next(($admin ? 'admin/' : 'pages/') . basename($_SERVER['SCRIPT_NAME'] ?? ''), $role);
    header('Location: ../login.php?role=' . $role . ($next ? '&next=' . rawurlencode($next) : ''));
    exit;
}

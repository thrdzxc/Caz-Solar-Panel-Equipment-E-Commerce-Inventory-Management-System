<?php
require_once __DIR__ . '/session.php';
caz_start_portal_session(caz_admin_request());
require_once __DIR__ . '/../db_conn.php';
function caz_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function caz_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function caz_require_user(bool $admin = false, bool $json = false): void {
    if (empty($_SESSION['user_id'])) {
        if ($json) caz_json(['success'=>false, 'message'=>'Please sign in.'], 401);
        caz_redirect_to_login($admin);
    }
    if ($admin && ($_SESSION['user_role'] ?? '') !== 'admin') {
        if ($json) caz_json(['success'=>false, 'message'=>'Admin access required.'], 403);
        caz_redirect_to_login(true);
    }
}
function caz_csrf_token(): string {
    if (empty($_SESSION['caz_csrf'])) $_SESSION['caz_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['caz_csrf'];
}
function caz_check_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || !hash_equals(caz_csrf_token(), $token)) {
        caz_json(['success'=>false,'message'=>'Your form expired. Refresh the page and try again.'],403);
    }
}

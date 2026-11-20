<?php
// This response deliberately does not display connection settings or diagnostics.
while (ob_get_level() > 0) {
    ob_end_clean();
}
$cazRequestPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$cazEndpoint = basename($cazRequestPath);
$cazJsonEndpoints = [
    'cart_handler.php', 'messages_api.php', 'place_order.php', 'promotion_quote.php',
    'profile_photo.php', 'customer_photo.php', 'identity_document.php', 'get_orders.php', 'get_notifications.php', 'get_product.php',
    'get_addresses.php', 'save_address.php', 'edit_address.php', 'delete_address.php',
    'update_order_address.php', 'update_profile.php', 'reset_password.php', 'store_location_api.php',
];
$cazWantsJson = in_array($cazEndpoint, $cazJsonEndpoints, true)
    || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
    || stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false
    || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
foreach (headers_list() as $cazHeader) {
    if (stripos($cazHeader, 'Content-Type: application/json') === 0) $cazWantsJson = true;
}
if (!headers_sent()) {
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 30');
    header('Content-Type: ' . ($cazWantsJson ? 'application/json' : 'text/html') . '; charset=utf-8');
}
$cazUnavailableMessage = 'The store is temporarily unavailable. Please try again shortly. Your saved information has not been changed.';
if ($cazWantsJson) {
    // Existing cart/checkout, messaging and profile clients use different error keys.
    echo json_encode(['success'=>false, 'ok'=>false, 'status'=>'error',
        'error'=>$cazUnavailableMessage, 'message'=>$cazUnavailableMessage,
        'code'=>'database_unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cazRequestedRole = $_POST['role'] ?? $_GET['role'] ?? '';
$cazRetryRole = $cazRequestedRole === 'admin' || preg_match('~/admin/[^/]*$~', $cazRequestPath)
    || ($_SESSION['user_role'] ?? '') === 'admin' ? 'admin' : 'customer';
$cazBasePath = preg_replace('~/(?:pages|admin)/[^/]*$~', '', $cazRequestPath, 1, $cazNestedCount);
if (!$cazNestedCount) $cazBasePath = rtrim(str_replace('\\', '/', dirname($cazRequestPath)), '/.');
// Keep links on this installation even if an unexpected server path is supplied.
if ($cazBasePath !== '' && (!str_starts_with($cazBasePath, '/') || str_starts_with($cazBasePath, '//'))) $cazBasePath = '';
$cazRetryUrl = htmlspecialchars($cazBasePath . '/login.php?role=' . $cazRetryRole, ENT_QUOTES, 'UTF-8');
$cazPortalUrl = htmlspecialchars($cazBasePath . '/portal.php', ENT_QUOTES, 'UTF-8');
$cazBackgroundUrl = htmlspecialchars($cazBasePath . '/images/caz-background.jpg', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Temporarily unavailable · CAZ</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#07474e;color:#f2f8ff;font:16px/1.65 "Segoe UI",Arial,sans-serif}
        .backdrop{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;opacity:.45;z-index:-1;pointer-events:none}
        main{width:100%;max-width:610px;padding:clamp(24px,5vw,44px);border:1px solid #84c0e866;border-radius:24px;background:rgba(5,59,64,.88);box-shadow:0 24px 70px #0005;backdrop-filter:blur(18px)}
        .eyebrow{margin:0 0 12px;color:#9adfff;font-weight:700;letter-spacing:.14em;font-size:13px}h1{font-size:clamp(26px,5vw,34px);line-height:1.2;margin:0 0 18px}p{color:#d1e3f4;margin:0 0 20px}
        aside{padding:18px;border:1px solid #8ecae642;border-radius:14px;background:#5d91b914;margin:24px 0}aside p{margin:5px 0 0;font-size:15px}strong{color:#f2f8ff}
        nav{display:flex;gap:12px;flex-wrap:wrap}a{color:#e3f5ff;text-decoration:none;padding:11px 20px;border:1px solid #8cb8d5;border-radius:10px;font-weight:700}a:first-child{background:#ac2840;border-color:#db7080;color:#fff}a:hover{filter:brightness(1.15)}a:focus-visible{outline:3px solid #85d7ff;outline-offset:4px}
    </style>
</head>
<body>
<img class="backdrop" src="<?php echo $cazBackgroundUrl; ?>" alt="">
<main>
    <p class="eyebrow">CAZ SOLAR &amp; CCTV</p>
    <h1>We’ll be back in a moment</h1>
    <p>The store cannot load its database right now. This request has not changed your saved information. Please try signing in again shortly.</p>
    <aside><strong>Running the system on this computer?</strong><p>Open the XAMPP Control Panel and start <strong>MySQL</strong>. Once it shows “Running,” return here and try again. If MySQL is already running, ask your system administrator to check the configured database and whether its tables have been imported.</p></aside>
    <nav aria-label="Recovery options"><a href="<?php echo $cazRetryUrl; ?>">Return to sign in</a><a href="<?php echo $cazPortalUrl; ?>">Choose a portal</a></nav>
</main>
</body>
</html>

<?php
/** Authenticated, portal-specific live order counters. No schema changes required. */
require_once __DIR__ . '/feature_bootstrap.php';
$isAdmin = isset($cazOrderAdmin) && $cazOrderAdmin === true;
caz_require_user($isAdmin, true);
$viewerId = (int)$_SESSION['user_id'];
try {
    $viewer = $conn->prepare('SELECT role FROM users WHERE id=?');
    $viewer->bind_param('i', $viewerId); $viewer->execute();
    $role = $viewer->get_result()->fetch_row()[0] ?? null;
    if ($role !== ($isAdmin ? 'admin' : 'customer')) caz_json(['ok'=>false,'message'=>'Please sign in to the correct portal.'],403);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && !(!$isAdmin && $method === 'POST')) {
        header('Allow: ' . ($isAdmin ? 'GET' : 'GET, POST'));
        caz_json(['ok'=>false,'message'=>'This request method is not supported.'],405);
    }
    if ($method === 'POST') {
        caz_check_csrf();
        $seen = filter_var($_POST['seen_through'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if ($seen === false || $seen === null) caz_json(['ok'=>false,'message'=>'Choose a valid notification.'],422);
        // A watermark acknowledges only updates already shown, never a future update.
        $maximum = $conn->prepare('SELECT COALESCE(MAX(n.id),0) FROM notifications n JOIN orders o ON o.id=n.order_id WHERE n.user_id=? AND o.user_id=?');
        $maximum->bind_param('ii', $viewerId, $viewerId); $maximum->execute();
        if ($seen > (int)$maximum->get_result()->fetch_row()[0]) caz_json(['ok'=>false,'message'=>'Refresh your order updates and try again.'],422);
        $read = $conn->prepare('UPDATE notifications n JOIN orders o ON o.id=n.order_id SET n.is_read=1 WHERE n.user_id=? AND o.user_id=? AND n.id<=?');
        $read->bind_param('iii', $viewerId, $viewerId, $seen); $read->execute();
    }
    if ($isAdmin) {
        $totals = $conn->query("SELECT COALESCE(SUM(status='pending'),0) AS amount, COALESCE(MAX(id),0) AS latest_id FROM orders")->fetch_assoc();
        $latest = $conn->query('SELECT id,status FROM orders ORDER BY id DESC LIMIT 1')->fetch_assoc();
        $message = $latest ? 'New order #CZ-' . (int)$latest['id'] . ' received. Open Orders to review it.' : '';
        $event = $latest ? ['id'=>(int)$latest['id'],'order_id'=>(int)$latest['id'],'status'=>$latest['status'],'message'=>$message] : null;
    } else {
        $summary = $conn->prepare('SELECT COALESCE(SUM(n.is_read=0),0) AS amount, COALESCE(MAX(n.id),0) AS latest_id FROM notifications n JOIN orders o ON o.id=n.order_id WHERE n.user_id=? AND o.user_id=?');
        $summary->bind_param('ii', $viewerId, $viewerId); $summary->execute(); $totals=$summary->get_result()->fetch_assoc();
        $statement=$conn->prepare('SELECT n.id,n.order_id,n.message,o.status FROM notifications n JOIN orders o ON o.id=n.order_id WHERE n.user_id=? AND o.user_id=? ORDER BY n.id DESC LIMIT 1');
        $statement->bind_param('ii', $viewerId, $viewerId); $statement->execute(); $latest=$statement->get_result()->fetch_assoc();
        $event=$latest ? ['id'=>(int)$latest['id'],'order_id'=>(int)$latest['order_id'],'status'=>$latest['status'],'message'=>$latest['message']] : null;
    }
    caz_json(['ok'=>true,'count'=>(int)$totals['amount'],'latest_id'=>(int)$totals['latest_id'],'latest'=>$event,'admin'=>$isAdmin]);
} catch (Throwable $error) {
    error_log('CAZ order status: ' . $error->getMessage());
    caz_json(['ok'=>false,'message'=>'Order updates are temporarily unavailable. Retrying shortly.'],500);
}

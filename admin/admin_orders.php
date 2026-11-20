<?php
// Keep old bookmarks working without a second, unsafe status-update path.
require_once __DIR__ . '/../includes/admin_guard.php';
$orderId = filter_var($_GET['id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
header('Location: ' . ($orderId ? 'manage_order.php?id=' . $orderId : 'orders.php'), true, 303);
exit;

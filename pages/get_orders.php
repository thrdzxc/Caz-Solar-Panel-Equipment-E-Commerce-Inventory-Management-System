<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';
require_once __DIR__ . '/../includes/delivery_location.php';
caz_require_user(false,true);
$userId = (int)$_SESSION['user_id'];
$inTransaction = false;
try {
    caz_promotions_schema($conn);
    caz_locations_schema($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        caz_check_csrf();
        $data = json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
        $orderId = filter_var($data['order_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$orderId) throw new InvalidArgumentException('Choose a valid order.');
        $conn->begin_transaction();
        $inTransaction = true;
        // The lock makes retries see the canceled state before restoring stock.
        $stmt = $conn->prepare('SELECT status FROM orders WHERE id=? AND user_id=? FOR UPDATE');
        $stmt->bind_param('ii',$orderId,$userId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order || strtolower($order['status']) !== 'pending') {
            throw new InvalidArgumentException('Order not found or cannot be canceled.');
        }
        $update = $conn->prepare("UPDATE orders SET status='canceled' WHERE id=? AND user_id=? AND status='pending'");
        $update->bind_param('ii',$orderId,$userId);
        $update->execute();
        if ($update->affected_rows !== 1) throw new InvalidArgumentException('This order has already changed. Refresh your orders.');
        $itemsStmt = $conn->prepare('SELECT product_id,SUM(quantity) AS quantity FROM order_items WHERE order_id=? GROUP BY product_id ORDER BY product_id');
        $itemsStmt->bind_param('i',$orderId);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stockStmt = $conn->prepare('UPDATE products SET stock=stock+? WHERE id=?');
        $inventoryStmt = $conn->prepare('UPDATE inventory SET current_stock=current_stock+?,stock_out=GREATEST(0,stock_out-?) WHERE product_id=?');
        foreach ($items as $item) {
            $pid = (int)$item['product_id']; $qty = (int)$item['quantity'];
            $stockStmt->bind_param('ii',$qty,$pid); $stockStmt->execute();
            $inventoryStmt->bind_param('iii',$qty,$qty,$pid); $inventoryStmt->execute();
        }
        $activity = 'Order Canceled - Order #' . $orderId;
        $logStmt = $conn->prepare('INSERT INTO customer_logs (user_id,activity,date) VALUES (?,?,NOW())');
        $logStmt->bind_param('is',$userId,$activity); $logStmt->execute();
        $notification = 'Your order #CZ-' . $orderId . ' was canceled. Its reserved stock has been returned.';
        $noticeStmt = $conn->prepare('INSERT INTO notifications (user_id,order_id,message,is_read) VALUES (?,?,?,0)');
        $noticeStmt->bind_param('iis',$userId,$orderId,$notification); $noticeStmt->execute();
        $conn->commit(); $inTransaction = false;
        caz_json(['success'=>true]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') caz_json(['success'=>false,'message'=>'Use GET or POST.'],405);
    $status = is_string($_GET['status'] ?? null) ? strtolower($_GET['status']) : 'pending';
    if (!in_array($status,['pending','approved','shipped','canceled'],true)) throw new InvalidArgumentException('Choose a valid order status.');
    $stmt = $conn->prepare("SELECT o.id AS order_id,o.order_date,o.total_amount,o.status,o.shipping_address,
        col.latitude AS delivery_latitude,col.longitude AS delivery_longitude,
        oi.product_id,oi.quantity,oi.price,COALESCE(p.product_name,'Unavailable product') AS product_name,p.image,
        cop.title AS promotion_title,cop.benefit AS promotion_benefit,cop.original_unit_price AS promotion_original_price
        FROM orders o JOIN order_items oi ON o.id=oi.order_id LEFT JOIN products p ON oi.product_id=p.id
        LEFT JOIN caz_order_promotions cop ON cop.order_id=o.id AND cop.product_id=oi.product_id
        LEFT JOIN caz_order_locations col ON col.order_id=o.id
        WHERE o.user_id=? AND o.status=? ORDER BY o.order_date DESC,o.id DESC,oi.product_id");
    $stmt->bind_param('is',$userId,$status);
    $stmt->execute();
    caz_json(['success'=>true,'orders'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
} catch (InvalidArgumentException | JsonException $e) {
    if ($inTransaction) $conn->rollback();
    caz_json(['success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    if ($inTransaction) $conn->rollback();
    error_log('CAZ orders: ' . $e->getMessage());
    caz_json(['success'=>false,'message'=>'Unable to update your orders. Please try again.'],500);
}

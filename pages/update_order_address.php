<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/delivery_location.php';
caz_require_user(false, true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') caz_json(['success'=>false,'message'=>'Use POST.'], 405);
caz_check_csrf();
$inTransaction = false;
try {
    $data = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('Submit a valid delivery address.');
    $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $address = trim(is_string($data['shipping_address'] ?? null) ? $data['shipping_address'] : '');
    if (!$orderId || $address === '' || mb_strlen($address) > 1000) {
        throw new InvalidArgumentException('Choose an order and a delivery address up to 1,000 characters.');
    }
    $hasLocation = array_key_exists('delivery_location', $data);
    $location = caz_validate_delivery_location($data['delivery_location'] ?? null);
    caz_locations_schema($conn);
    $conn->begin_transaction();
    $inTransaction = true;
    $userId = (int)$_SESSION['user_id'];
    $check = $conn->prepare('SELECT status,shipping_address FROM orders WHERE id=? AND user_id=? FOR UPDATE');
    $check->bind_param('ii', $orderId, $userId);
    $check->execute();
    $order = $check->get_result()->fetch_assoc();
    if (!$order || strtolower($order['status']) !== 'pending') {
        throw new InvalidArgumentException('Order not found or no longer pending.');
    }
    $update = $conn->prepare('UPDATE orders SET shipping_address=? WHERE id=? AND user_id=?');
    $update->bind_param('sii', $address, $orderId, $userId);
    $update->execute();
    // Never leave the previous pin attached to a different written address.
    if ($hasLocation || $address !== $order['shipping_address']) {
        caz_save_order_location($conn, $orderId, $location);
    }
    $activity = 'Shipping Address Updated - Order #' . $orderId;
    $log = $conn->prepare('INSERT INTO customer_logs (user_id,activity,date) VALUES (?,?,NOW())');
    $log->bind_param('is', $userId, $activity);
    $log->execute();
    $conn->commit();
    $inTransaction = false;
    caz_json(['success'=>true,'message'=>'Delivery address updated.']);
} catch (InvalidArgumentException | JsonException $e) {
    if ($inTransaction) $conn->rollback();
    caz_json(['success'=>false,'message'=>$e->getMessage()], 422);
} catch (Throwable $e) {
    if ($inTransaction) $conn->rollback();
    error_log('CAZ delivery address: ' . $e->getMessage());
    caz_json(['success'=>false,'message'=>'The delivery address could not be saved. Please try again.'], 500);
}

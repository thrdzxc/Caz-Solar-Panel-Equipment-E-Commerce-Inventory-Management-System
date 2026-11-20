<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';
require_once __DIR__ . '/../includes/delivery_location.php';
require_once __DIR__ . '/../includes/identity_verification.php';
caz_require_user(false,true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') caz_json(['status'=>'error','message'=>'Use POST.'],405);
caz_check_csrf();
$inTransaction = false;
try {
    $data = json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('Submit a valid order.');
    $quantities = caz_order_quantities($data['items'] ?? null);
    $deliveryLocation = caz_validate_delivery_location($data['delivery_location'] ?? null);
    $shippingAddress = trim(is_string($data['shipping_address'] ?? null) ? $data['shipping_address'] : '');
    if ($shippingAddress === '' || mb_strlen($shippingAddress)>1000) {
        throw new InvalidArgumentException('Choose a shipping address, up to 1,000 characters.');
    }
    // DDL must run before the transaction to avoid implicit commits.
    caz_promotions_schema($conn);
    caz_locations_schema($conn);
    caz_identity_schema($conn);
    $conn->begin_transaction();
    $inTransaction = true;
    $userId = (int)$_SESSION['user_id'];
    // Cart edits and checkout use the same user lock before locking products.
    $userLock = $conn->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $userLock->bind_param('i', $userId);
    $userLock->execute();
    $userLock->get_result()->fetch_assoc();
    $verification = caz_identity_details($conn,$userId,true);
    if ($verification && $verification['status'] !== 'approved') {
        throw new InvalidArgumentException('Your account needs ID approval before ordering. Open Account verification to view the status or submit a clearer photo.');
    }
    // Product rows lock in ID order. Browser-supplied prices are never trusted.
    $quote = caz_quote_order($conn,$quantities,true);
    $userId = (int)$_SESSION['user_id'];
    $totalAmount = $quote['total_amount'];
    $stmt = $conn->prepare("INSERT INTO orders (user_id,total_amount,order_date,status,shipping_address) VALUES (?,?,NOW(),'pending',?)");
    $stmt->bind_param('ids',$userId,$totalAmount,$shippingAddress);
    $stmt->execute();
    $orderId = (int)$conn->insert_id;
    caz_save_order_location($conn, $orderId, $deliveryLocation);
    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)');
    $stockStmt = $conn->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
    $inventoryStmt = $conn->prepare('UPDATE inventory SET stock_out=stock_out+?,current_stock=? WHERE product_id=?');
    $cartStmt = $conn->prepare('DELETE FROM cart_items WHERE user_id=? AND product_id=? AND quantity<=?');
    $cartRemainderStmt = $conn->prepare('UPDATE cart_items SET quantity=quantity-? WHERE user_id=? AND product_id=?');
    $promotionStmt = $conn->prepare('INSERT INTO caz_order_promotions (order_id,product_id,promotion_id,title,benefit,minimum_quantity,original_unit_price,final_unit_price) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($quote['items'] as $item) {
        $pid = $item['id']; $quantity = $item['quantity']; $price = $item['price'];
        $itemStmt->bind_param('iiid',$orderId,$pid,$quantity,$price);
        $itemStmt->execute();
        $stockStmt->bind_param('iii',$quantity,$pid,$quantity);
        $stockStmt->execute();
        if ($stockStmt->affected_rows !== 1) throw new InvalidArgumentException('Stock changed. Please review your cart and try again.');
        $newStock = $item['stock']-$quantity;
        $inventoryStmt->bind_param('iii',$quantity,$newStock,$pid);
        $inventoryStmt->execute();
        if ($item['promotion']) {
            $promo = $item['promotion'];
            $promotionStmt->bind_param('iiissidd',$orderId,$pid,$promo['id'],$promo['title'],$promo['benefit'],$promo['minimum_quantity'],$promo['original_unit_price'],$price);
            $promotionStmt->execute();
        }
        // Preserve unselected products and quantities not included in this purchase.
        $cartStmt->bind_param('iii',$userId,$pid,$quantity);
        $cartStmt->execute();
        $cartRemainderStmt->bind_param('iii',$quantity,$userId,$pid);
        $cartRemainderStmt->execute();
    }
    $activity = 'Order Placed - Order #' . $orderId . ' - Total: P' . number_format($totalAmount,2,'.','');
    $logStmt = $conn->prepare('INSERT INTO customer_logs (user_id,activity,date) VALUES (?,?,NOW())');
    $logStmt->bind_param('is',$userId,$activity);
    $logStmt->execute();
    $notification = 'Your order #CZ-' . $orderId . ' was placed successfully. Awaiting admin approval.';
    $notificationStmt = $conn->prepare('INSERT INTO notifications (user_id,order_id,message,is_read,created_at) VALUES (?,?,?,0,NOW())');
    $notificationStmt->bind_param('iis', $userId, $orderId, $notification);
    $notificationStmt->execute();
    $conn->commit();
    $inTransaction = false;
    caz_json(['status'=>'success','success'=>true,'message'=>'Order placed successfully','order_id'=>$orderId,'total_amount'=>$totalAmount,'items'=>$quote['items']]);
} catch (InvalidArgumentException | JsonException $e) {
    if ($inTransaction) $conn->rollback();
    caz_json(['status'=>'error','success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    if ($inTransaction) $conn->rollback();
    error_log('CAZ checkout: ' . $e->getMessage());
    caz_json(['status'=>'error','success'=>false,'message'=>'The order could not be saved. Please try again.'],500);
}

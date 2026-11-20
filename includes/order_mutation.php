<?php
/** Apply one admin status change and its accounting effects as one transaction. */
function caz_admin_update_order(mysqli $conn, int $orderId, string $nextStatus): bool {
    if ($orderId < 1 || !in_array($nextStatus, ['pending', 'approved', 'shipped', 'canceled'], true)) {
        throw new InvalidArgumentException('Choose a valid order status.');
    }
    $conn->begin_transaction();
    try {
        // Customer cancellation locks this same order before returning stock.
        $stmt = $conn->prepare('SELECT user_id,status,total_amount FROM orders WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) throw new InvalidArgumentException('This order no longer exists.');
        $current = strtolower($order['status']);
        if ($current === $nextStatus) {
            $conn->commit();
            return false;
        }
        if (in_array($current, ['shipped', 'canceled'], true)) {
            throw new DomainException('This order is already ' . $current . ' and cannot be changed.');
        }
        if (!in_array($current, ['pending', 'approved'], true) || ($current === 'approved' && $nextStatus === 'pending')) {
            throw new DomainException('This status change is unavailable. Refresh the order and try again.');
        }
        $stmt = $conn->prepare('UPDATE orders SET status=? WHERE id=?');
        $stmt->bind_param('si', $nextStatus, $orderId);
        $stmt->execute();
        $stmt->close();
        if ($nextStatus === 'canceled') {
            $stmt = $conn->prepare('SELECT product_id,SUM(quantity) AS quantity FROM order_items WHERE order_id=? GROUP BY product_id ORDER BY product_id');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $productStmt = $conn->prepare('UPDATE products SET stock=stock+? WHERE id=?');
            $inventoryStmt = $conn->prepare('UPDATE inventory i JOIN products p ON p.id=i.product_id SET i.current_stock=p.stock,i.stock_out=GREATEST(0,i.stock_out-?) WHERE i.product_id=?');
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                if ($quantity < 1) throw new RuntimeException('Invalid stored order quantity.');
                $productStmt->bind_param('ii', $quantity, $productId);
                $productStmt->execute();
                $inventoryStmt->bind_param('ii', $quantity, $productId);
                $inventoryStmt->execute();
            }
            $productStmt->close();
            $inventoryStmt->close();
        }
        if ($nextStatus === 'shipped') {
            $total = (string)$order['total_amount'];
            $stmt = $conn->prepare('INSERT INTO sales (order_id,total_sales,date) SELECT ?,?,NOW() WHERE NOT EXISTS (SELECT 1 FROM sales WHERE order_id=?)');
            $stmt->bind_param('isi', $orderId, $total, $orderId);
            $stmt->execute();
            $stmt->close();
        }
        $customerId = (int)$order['user_id'];
        $activity = 'Order #' . $orderId . ' status changed to ' . $nextStatus;
        $stmt = $conn->prepare('INSERT INTO customer_logs (user_id,activity,date) VALUES (?,?,NOW())');
        $stmt->bind_param('is', $customerId, $activity);
        $stmt->execute();
        $stmt->close();
        $messages = [
            'approved' => 'Your order #CZ-' . $orderId . ' has been approved! We are now preparing your items.',
            'shipped' => 'Your order #CZ-' . $orderId . ' has been shipped! Your items are on the way.',
            'canceled' => 'Your order #CZ-' . $orderId . ' has been canceled. Please contact support if you have questions.'
        ];
        if (isset($messages[$nextStatus])) {
            $message = $messages[$nextStatus];
            $stmt = $conn->prepare('INSERT INTO notifications (user_id,order_id,message) VALUES (?,?,?)');
            $stmt->bind_param('iis', $customerId, $orderId, $message);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

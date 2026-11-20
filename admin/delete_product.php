<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';

// Opening a saved link must never delete a product.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: inventory.php'); exit; }
caz_check_csrf();
$transaction = false;
try {
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    if ($id === false || $id === null) throw new InvalidArgumentException('Choose a valid product to remove.');
    caz_promotions_schema($conn);
    $conn->begin_transaction();
    $transaction = true;
    // Checkout also locks the product first, preventing deletion during an order.
    $statement = $conn->prepare('SELECT id FROM products WHERE id=? FOR UPDATE');
    $statement->bind_param('i', $id);
    $statement->execute();
    if (!$statement->get_result()->fetch_assoc()) throw new InvalidArgumentException('That product has already been removed.');
    $statement = $conn->prepare('SELECT id FROM order_items WHERE product_id=? LIMIT 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    if ($statement->get_result()->fetch_assoc()) throw new InvalidArgumentException('This product is part of an order and must remain in the order history. To stop new purchases, edit the product and set its stock to 0.');
    foreach (['cart_items', 'caz_promotions', 'product_images', 'inventory'] as $table) {
        $statement = $conn->prepare('DELETE FROM ' . $table . ' WHERE product_id=?');
        $statement->bind_param('i', $id);
        $statement->execute();
    }
    $statement = $conn->prepare('DELETE FROM products WHERE id=?');
    $statement->bind_param('i', $id);
    $statement->execute();
    $conn->commit();
    $transaction = false;
    // Keep original files: another product may share the same photograph.
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Product removed. Its cart entries and promotions have also been removed.'];
} catch (Throwable $error) {
    if ($transaction) $conn->rollback();
    if (!($error instanceof InvalidArgumentException)) error_log('CAZ delete product: ' . $error->getMessage());
    $_SESSION['msg'] = ['type' => 'error', 'text' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'The product could not be removed. No changes were saved. Please try again.'];
}
header('Location: inventory.php', true, 303);
exit;

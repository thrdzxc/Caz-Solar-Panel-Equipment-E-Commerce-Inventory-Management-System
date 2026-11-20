<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/product_mutation.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: inventory.php'); exit; }
caz_check_csrf();
$transaction = false;
$savedImage = null;
$id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
try {
    if ($id === false || $id === null) throw new InvalidArgumentException('Choose a valid product to edit.');
    $data = caz_product_fields($_POST, false);
    $upload = $_FILES['p_image'] ?? [];
    if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $savedImage = caz_store_main_product_image($upload);
        $image = $savedImage['name'];
    } else {
        $image = caz_existing_product_image($_POST['image'] ?? null);
    }
    $conn->begin_transaction();
    $transaction = true;
    // Checkout locks the product first too, so stock edits cannot race an order.
    $statement = $conn->prepare('SELECT image FROM products WHERE id=? FOR UPDATE');
    $statement->bind_param('i', $id);
    $statement->execute();
    $existing = $statement->get_result()->fetch_assoc();
    if (!$existing) throw new InvalidArgumentException('That product no longer exists.');
    $statement = $conn->prepare('UPDATE products SET product_name=?,brand=?,category=?,description=?,stock=?,price=?,image=?,type=?,spec=? WHERE id=?');
    $statement->bind_param('ssssissssi', $data['name'], $data['brand'], $data['category'], $data['description'], $data['stock'], $data['price'], $image, $data['type'], $data['spec'], $id);
    $statement->execute();
    caz_sync_product_inventory($conn, $id, $data['stock']);
    caz_sync_product_main_image($conn, $id, $existing['image'], $image);
    $conn->commit();
    $transaction = false;
    $savedImage = null;
    unset($_SESSION['caz_product_edit_values']);
    header('Location: product.php?id=' . $id . '&status=success');
} catch (Throwable $error) {
    if ($transaction) $conn->rollback();
    if ($savedImage !== null && is_file($savedImage['path']) && !is_link($savedImage['path'])) unlink($savedImage['path']);
    caz_keep_product_form($_POST, false);
    if (!($error instanceof InvalidArgumentException)) error_log('CAZ update product: ' . $error->getMessage());
    $_SESSION['msg'] = ['type' => 'error', 'text' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'The product could not be updated. Please try again.'];
    header('Location: ' . ($id ? 'edit_product.php?id=' . $id : 'inventory.php'));
}
exit;

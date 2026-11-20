<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/product_mutation.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: addproduct.php'); exit; }
caz_check_csrf();
$transaction = false;
$savedImage = null;
try {
    $data = caz_product_fields($_POST, true);
    $savedImage = caz_store_main_product_image($_FILES['p_image'] ?? []);
    $conn->begin_transaction();
    $transaction = true;
    $statement = $conn->prepare('INSERT INTO products (product_name,brand,category,description,stock,price,image,type,spec) VALUES (?,?,?,?,?,?,?,?,?)');
    $statement->bind_param('ssssissss', $data['name'], $data['brand'], $data['category'], $data['description'], $data['stock'], $data['price'], $savedImage['name'], $data['type'], $data['spec']);
    $statement->execute();
    $id = (int)$conn->insert_id;
    caz_sync_product_inventory($conn, $id, $data['stock']);
    caz_sync_product_main_image($conn, $id, '', $savedImage['name']);
    $conn->commit();
    $transaction = false;
    $savedImage = null;
    unset($_SESSION['caz_product_add_values']);
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Product saved successfully!'];
    header('Location: inventory.php');
} catch (Throwable $error) {
    if ($transaction) $conn->rollback();
    if ($savedImage !== null && is_file($savedImage['path']) && !is_link($savedImage['path'])) unlink($savedImage['path']);
    caz_keep_product_form($_POST, true);
    if (!($error instanceof InvalidArgumentException)) error_log('CAZ add product: ' . $error->getMessage());
    $_SESSION['msg'] = ['type' => 'error', 'text' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'The product could not be saved. Please try again.'];
    header('Location: addproduct.php');
}
exit;

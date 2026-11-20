<?php
require_once __DIR__ . '/../includes/admin_guard.php';
include "../db_conn.php";

$fixed_images = 0;
$fixed_inventory = 0;

// Get all products
$products = mysqli_query($conn, "SELECT * FROM products");

while ($product = mysqli_fetch_assoc($products)) {
    $pid   = $product['id'];
    $img   = mysqli_real_escape_string($conn, $product['image']);
    $stock = (int)$product['stock'];

    // --- Fix product_images ---
    $check_img = mysqli_query($conn, "SELECT id FROM product_images WHERE product_id = '$pid'");
    if (mysqli_num_rows($check_img) === 0) {
        $img_path = "../../images/" . $img; // adjust if your path differs
        mysqli_query($conn, "INSERT INTO product_images (product_id, image_name, image_path)
                             VALUES ('$pid', '$img', '$img_path')");
        $fixed_images++;
    }

    // --- Fix inventory ---
    $check_inv = mysqli_query($conn, "SELECT id FROM inventory WHERE product_id = '$pid'");
    if (mysqli_num_rows($check_inv) === 0) {
        mysqli_query($conn, "INSERT INTO inventory (product_id, stock_in, stock_out, current_stock)
                             VALUES ('$pid', '$stock', 0, '$stock')");
        $fixed_inventory++;
    }
}

echo "<h2>✅ Fix Complete!</h2>";
echo "<p>product_images rows added: <b>$fixed_images</b></p>";
echo "<p>inventory rows added: <b>$fixed_inventory</b></p>";
echo "<p>Products that already had records were skipped.</p>";
echo "<br><a href='inventory.php'>← Go back to Inventory</a>";
?>
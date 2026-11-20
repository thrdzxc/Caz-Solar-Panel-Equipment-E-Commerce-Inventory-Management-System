<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/product_details.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "../db_conn.php"; 

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = mysqli_query($conn, "SELECT * FROM products WHERE id = '$id'");
    $product = mysqli_fetch_assoc($result);

    if (!$product) { header("Location: inventory.php"); exit(); }
} else { header("Location: inventory.php"); exit(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/product-details.css?v=20260919-details">
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/admin-glass-details.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/admin-glass-details.css'); ?>">
</head>
<body class="caz-admin caz-themed caz-admin-product-details">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<main class="product-wrapper">
    <div class="caz-details-toolbar">
        <a href="inventory.php" class="caz-details-back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to inventory
        </a>
    </div>

    <div class="product-detail-box">

        <div class="upload-section">
            <h2 class="caz-details-image-heading">Product image</h2>
            <div class="image-placeholder">
                <img src="../images/<?php echo htmlspecialchars($product['image']); ?>"
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
            </div>
            <dl class="caz-details-filename"><dt>Image filename</dt><dd><?php echo htmlspecialchars($product['image']); ?></dd></dl>
        </div>

        <div class="info-section">
            <div class="caz-details-heading">
                <span class="caz-details-eyebrow">Inventory overview</span>
                <h1 class="form-title">Product Details</h1>
            </div>
            <dl class="caz-details-facts">
                <div class="caz-details-name"><dt>Product name</dt><dd><?php echo htmlspecialchars($product['product_name']); ?></dd></div>
                <div><dt>Brand</dt><dd><?php echo htmlspecialchars($product['brand']); ?></dd></div>
                <div><dt>Category</dt><dd><?php echo htmlspecialchars($product['category']); ?></dd></div>
                <div><dt>Type</dt><dd><?php echo htmlspecialchars($product['type'] ?? ''); ?></dd></div>
                <div><dt>Specification</dt><dd><?php echo htmlspecialchars($product['spec'] ?? ''); ?></dd></div>
                <div><dt>Price</dt><dd class="caz-details-price">₱<?php echo number_format((float)$product['price'], 2); ?></dd></div>
                <div><dt>Stock</dt><dd><?php echo htmlspecialchars((string)$product['stock']); ?></dd></div>
            </dl>

            <!-- Description -->
            <section class="caz-product-information" aria-labelledby="customer-details-preview">
                <h2 id="customer-details-preview">Customer details preview</h2>
                <?php echo caz_render_product_facts($product); ?>
                <h3>Features &amp; product details</h3>
                <?php echo caz_render_product_description($product['description'] ?? null); ?>
            </section>

            <div class="form-btns">
                <a class="btn-submit" href="edit_product.php?id=<?php echo (int)$product['id']; ?>"><i class="fas fa-pen" aria-hidden="true"></i> Edit product</a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        cazAlert.success('Product updated successfully!');
    <?php endif; ?>
</script>

</body>
</html>

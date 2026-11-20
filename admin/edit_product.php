<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/feature_bootstrap.php'; 

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = mysqli_query($conn, "SELECT * FROM products WHERE id = '$id'");
    $product = mysqli_fetch_assoc($result);

    if (!$product) { header("Location: inventory.php"); exit(); }
} else { header("Location: inventory.php"); exit(); }
$product_notice = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);
$product_saved_image = $product['image'];
$product_values = $_SESSION['caz_product_edit_values'] ?? [];
unset($_SESSION['caz_product_edit_values']);
if (is_array($product_values) && (string)($product_values['product_id'] ?? '') === (string)$id) {
    foreach (['product_name','brand','category','description','stock','price','type','spec','image'] as $field) {
        if (isset($product_values[$field]) && is_string($product_values[$field])) $product[$field] = $product_values[$field];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/product-details.css?v=20260919-details">
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/admin-product-editor.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/admin-product-editor.css'); ?>">
<link rel="stylesheet" href="../css/product-gallery.css?v=20260930-gallery">
</head>
<body class="caz-admin caz-themed caz-product-editor-page">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<main class="product-wrapper">
    <?php if (is_array($product_notice) && !empty($product_notice['text'])): ?>
        <div class="caz-editor-notice" role="alert"><?php echo htmlspecialchars((string)$product_notice['text'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="caz-editor-toolbar">
        <a href="product.php?id=<?php echo $id; ?>" class="caz-editor-back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to product
        </a>
    </div>

    <form action="update_product.php" method="POST" enctype="multipart/form-data" class="product-detail-box" id="caz-product-edit-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(caz_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

        <div class="upload-section">
            <h2 class="caz-editor-image-title">Product image</h2>
            <div class="image-placeholder">
                <img id="editor-main-preview" src="../images/<?php echo htmlspecialchars($product_saved_image); ?>"
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
            </div>
            <div class="input-group">
                <label for="editor-image">Image filename</label>
                <input id="editor-image" type="text" name="image" value="<?php echo htmlspecialchars($product['image']); ?>" aria-describedby="editor-image-help">
            </div>
            <p class="caz-editor-image-note" id="editor-image-help">Use the name of an existing image in your images folder, including its extension.</p>
            <div class="input-group">
                <label for="editor-replacement-photo">Or upload a replacement photo</label>
                <input type="file" id="editor-replacement-photo" name="p_image" accept="image/jpeg,image/png,image/webp" aria-describedby="editor-replacement-help">
            </div>
            <p class="caz-editor-image-note" id="editor-replacement-help">Optional JPG, PNG or WEBP up to 8 MB. An uploaded photo replaces the filename above; transparent PNGs keep their clear background.</p>
            <a class="caz-gallery-editor-link" href="product_gallery.php?id=<?php echo $id; ?>">Manage product photos</a>
        </div>

        <div class="info-section">
            <header class="caz-editor-heading">
                <h1 class="form-title">Edit product details</h1>
                <p>Keep the image, price, stock and product information up to date.</p>
            </header>

            <!-- Product Name -->
            <div class="input-group">
                <label for="editor-name">Product name</label>
                <input id="editor-name" type="text" name="product_name"
                       value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
            </div>

            <!-- Brand + Category -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-brand">Brand</label>
                    <input id="editor-brand" type="text" name="brand"
                           value="<?php echo htmlspecialchars($product['brand']); ?>">
                </div>
                <div class="input-group">
                    <label for="editor-category">Category</label>
                    <select id="editor-category" name="category">
                        <option value="CCTV"  <?php if ($product['category'] === 'CCTV')  echo 'selected'; ?>>CCTV</option>
                        <option value="Solar" <?php if ($product['category'] === 'Solar') echo 'selected'; ?>>SOLAR</option>
                    </select>
                </div>
            </div>

            <!-- Type + Spec (new) -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-type">Product type</label>
                    <input id="editor-type" type="text" name="type" aria-describedby="editor-type-help"
                           value="<?php echo htmlspecialchars($product['type'] ?? ''); ?>"
                           placeholder="e.g. Dome">
                    <small id="editor-type-help">e.g. Dome, Bullet or Monocrystalline</small>
                </div>
                <div class="input-group">
                    <label for="editor-spec">Resolution / power</label>
                    <input id="editor-spec" type="text" name="spec" aria-describedby="editor-spec-help"
                           value="<?php echo htmlspecialchars($product['spec'] ?? ''); ?>"
                           placeholder="e.g. 4MP">
                    <small id="editor-spec-help">e.g. 2MP, 5MP, 400W or 550W</small>
                </div>
            </div>

            <!-- Price + Stock -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-price">Price (₱)</label>
                    <input id="editor-price" type="number" step="0.01" min="0" name="price"
                           value="<?php echo htmlspecialchars((string)$product['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="input-group">
                    <label for="editor-stock">Stock</label>
                    <input id="editor-stock" type="number" min="0" step="1" name="stock"
                           value="<?php echo htmlspecialchars((string)$product['stock'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <!-- Description -->
            <div class="input-group">
                <label for="product-details-input">Product details — one detail per line</label>
                <textarea id="product-details-input" class="caz-product-editor" name="description" rows="6" aria-describedby="product-details-help"
                          placeholder="Model number: ...&#10;Key features: ...&#10;Compatibility: ...&#10;Included items: ...&#10;Installation requirements: ...&#10;Warranty: ..."><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                <div class="caz-product-editor-note" id="product-details-help">
                    <strong>Each line becomes a customer-facing bullet.</strong> Add confirmed model, dimensions,
                    connection or electrical requirements, included accessories, installation terms and warranty.
                    For CCTV, include lens, night vision and recorder compatibility; for solar equipment, include
                    panel, battery and charging information where applicable. Use verified information only.
                    Brand, type, specification, price and availability appear automatically.
                </div>
            </div>

            <div class="form-btns">
                <a href="product.php?id=<?php echo $id; ?>" class="caz-editor-cancel">Cancel</a>
                <button type="submit" name="update" class="btn-submit"><i class="fas fa-check" aria-hidden="true"></i> Save changes</button>
            </div>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
    (() => {
        const input = document.getElementById('editor-replacement-photo');
        const image = document.getElementById('editor-main-preview');
        const original = image.src;
        let preview = '';
        input.addEventListener('change', () => {
            if (preview) URL.revokeObjectURL(preview);
            preview = '';
            input.setCustomValidity('');
            const file = input.files[0];
            if (!file) { image.src = original; return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
                input.setCustomValidity('Choose a JPG, PNG or WEBP photo up to 8 MB.');
                input.reportValidity(); image.src = original; return;
            }
            preview = URL.createObjectURL(file); image.src = preview;
        });
        image.addEventListener('error', () => {
            if (!preview) return;
            URL.revokeObjectURL(preview); preview = '';
            input.setCustomValidity('This photo cannot be opened. Please choose another image.');
            image.src = original;
        });
    })();
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
        cazAlert.success('Product updated successfully!');
    <?php endif; ?>
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
$product_notice = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);
$product_values = $_SESSION['caz_product_add_values'] ?? [];
unset($_SESSION['caz_product_add_values']);
if (!is_array($product_values)) $product_values = [];
function caz_add_value(string $key, string $default = ''): string {
    global $product_values;
    return htmlspecialchars(is_string($product_values[$key] ?? null) ? $product_values[$key] : $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - Caz Admin</title>
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
    <div class="caz-editor-toolbar">
        <a href="inventory.php" class="caz-editor-back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to inventory
        </a>
    </div>

    <form action="save_product.php" method="POST" enctype="multipart/form-data" class="product-detail-box" id="caz-product-edit-form">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(caz_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

        <div class="upload-section">
            <h2 class="caz-editor-image-title">Product image</h2>
            <button type="button" class="image-placeholder caz-image-picker" id="previewBox" aria-label="Choose a product image">
                <span class="caz-image-picker-empty"><i class="fas fa-image" aria-hidden="true"></i><span>Choose or drop a product photo</span></span>
                <img id="previewImg" alt="New product image preview" hidden>
            </button>
            <div class="input-group">
                <label for="p_image_input">Upload product image</label>
                <input type="file" id="p_image_input" name="p_image" accept="image/jpeg,image/png,image/webp" required aria-describedby="editor-image-help">
            </div>
            <p class="caz-editor-image-note" id="editor-image-help">Choose a clear JPG, PNG or WEBP image up to 8 MB. A transparent PNG works well with the store background. You can add other product views after saving.</p>
            <p id="editor-image-status" class="caz-editor-image-status" role="status" aria-live="polite"></p>
        </div>

        <div class="info-section">
            <header class="caz-editor-heading">
                <h1 class="form-title">Add product details</h1>
                <p>Add the image, price, stock and information customers will see.</p>
            </header>

            <!-- Product Name -->
            <div class="input-group">
                <label for="editor-name">Product name</label>
                <input id="editor-name" type="text" name="p_name"
                       value="<?php echo caz_add_value('p_name'); ?>" required>
            </div>

            <!-- Brand + Category -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-brand">Brand</label>
                    <input id="editor-brand" type="text" name="p_brand"
                           value="<?php echo caz_add_value('p_brand'); ?>">
                </div>
                <div class="input-group">
                    <label for="editor-category">Category</label>
                    <select id="editor-category" name="p_category">
                        <option value="CCTV" <?php if (($product_values['p_category'] ?? 'CCTV') === 'CCTV') echo 'selected'; ?>>CCTV</option>
                        <option value="Solar" <?php if (($product_values['p_category'] ?? '') === 'Solar') echo 'selected'; ?>>SOLAR</option>
                    </select>
                </div>
            </div>

            <!-- Type + Spec (new) -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-type">Product type</label>
                    <input id="editor-type" type="text" name="p_type" aria-describedby="editor-type-help"
                           value="<?php echo caz_add_value('p_type'); ?>"
                           placeholder="e.g. Dome">
                    <small id="editor-type-help">e.g. Dome, Bullet or Monocrystalline</small>
                </div>
                <div class="input-group">
                    <label for="editor-spec">Resolution / power</label>
                    <input id="editor-spec" type="text" name="p_spec" aria-describedby="editor-spec-help"
                           value="<?php echo caz_add_value('p_spec'); ?>"
                           placeholder="e.g. 4MP">
                    <small id="editor-spec-help">e.g. 2MP, 5MP, 400W or 550W</small>
                </div>
            </div>

            <!-- Price + Stock -->
            <div class="input-row">
                <div class="input-group">
                    <label for="editor-price">Price (₱)</label>
                    <input id="editor-price" type="number" step="0.01" min="0" name="p_price"
                           value="<?php echo caz_add_value('p_price'); ?>" required>
                </div>
                <div class="input-group">
                    <label for="editor-stock">Stock</label>
                    <input id="editor-stock" type="number" min="0" step="1" name="p_stock"
                           value="<?php echo caz_add_value('p_stock'); ?>" required>
                </div>
            </div>

            <!-- Description -->
            <div class="input-group">
                <label for="product-details-input">Product details — one detail per line</label>
                <textarea id="product-details-input" class="caz-product-editor" name="p_description" rows="6" aria-describedby="product-details-help"
                          placeholder="Model number: ...&#10;Key features: ...&#10;Compatibility: ...&#10;Included items: ...&#10;Installation requirements: ...&#10;Warranty: ..."><?php echo caz_add_value('p_description'); ?></textarea>
                <div class="caz-product-editor-note" id="product-details-help">
                    <strong>Each line becomes a customer-facing bullet.</strong> Add confirmed model, dimensions,
                    connection or electrical requirements, included accessories, installation terms and warranty.
                    For CCTV, include lens, night vision and recorder compatibility; for solar equipment, include
                    panel, battery and charging information where applicable. Use verified information only.
                    Brand, type, specification, price and availability appear automatically.
                </div>
            </div>

            <div class="form-btns">
                <a href="inventory.php" class="caz-editor-cancel">Cancel</a>
                <button type="submit" name="submit_product" class="btn-submit"><i class="fas fa-plus" aria-hidden="true"></i> Save product</button>
            </div>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
(function () {
    const notice = <?php echo json_encode($product_notice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (notice && typeof notice.text === 'string') {
        if (notice.type === 'success') cazAlert.success(notice.text); else cazAlert.error(notice.text);
    }
    const form = document.getElementById('caz-product-edit-form');
    const input = document.getElementById('p_image_input');
    const box = document.getElementById('previewBox');
    const image = document.getElementById('previewImg');
    const status = document.getElementById('editor-image-status');
    let previewUrl = '';
    function showPreview() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        image.hidden = true;
        image.removeAttribute('src');
        box.classList.remove('has-image');
        status.textContent = '';
        input.setCustomValidity('');
        const file = input.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
            status.textContent = 'Choose a JPG, PNG or WEBP image up to 8 MB.';
            input.value = '';
            input.setCustomValidity(status.textContent);
            return;
        }
        previewUrl = URL.createObjectURL(file);
        image.src = previewUrl;
        image.hidden = false;
        box.classList.add('has-image');
        status.textContent = file.name;
    }
    image.addEventListener('error', function () {
        if (!image.getAttribute('src')) return;
        image.hidden = true;
        box.classList.remove('has-image');
        input.value = '';
        status.textContent = 'This picture could not be opened. Choose another JPG, PNG or WEBP image.';
        input.setCustomValidity(status.textContent);
    });
    box.addEventListener('click', () => input.click());
    input.addEventListener('change', showPreview);
    box.addEventListener('dragover', event => { event.preventDefault(); box.classList.add('drag-over'); });
    box.addEventListener('dragleave', () => box.classList.remove('drag-over'));
    box.addEventListener('drop', event => {
        event.preventDefault(); box.classList.remove('drag-over');
        if (!event.dataTransfer.files.length) return;
        const selection = new DataTransfer();
        selection.items.add(event.dataTransfer.files[0]);
        input.files = selection.files;
        showPreview();
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const name = document.getElementById('editor-name').value.trim();
        cazAlert.confirm('Save ' + name + ' to the inventory? Customers will see this product in its category.', function () {
            form.querySelector('button[type="submit"]').disabled = true;
            form.submit();
        });
    });
}());
</script>
</body>
</html>

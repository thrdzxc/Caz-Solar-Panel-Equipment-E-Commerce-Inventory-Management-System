<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/product_gallery.php';
caz_require_user(true);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: inventory.php'); exit; }
$statement = $conn->prepare('SELECT id, product_name, image FROM products WHERE id=?');
$statement->bind_param('i', $id);
$statement->execute();
$product = $statement->get_result()->fetch_assoc();
if (!$product) { http_response_code(404); exit('Product not found.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    caz_check_csrf();
    try {
        if (($_POST['action'] ?? '') === 'upload') {
            if (($_POST['exact_product'] ?? '') !== '1') throw new InvalidArgumentException('Confirm this photo shows the exact product being sold.');
            if (!is_string($_POST['label'] ?? null) || !is_array($_FILES['photo'] ?? null)) throw new InvalidArgumentException('Choose a photo and add its label.');
            caz_gallery_upload($id, $_FILES['photo'], $_POST['label']);
        } elseif (($_POST['action'] ?? '') === 'remove' && is_string($_POST['file'] ?? null)) {
            caz_gallery_remove($id, $_POST['file']);
        } else { throw new InvalidArgumentException('Choose a valid photo action.'); }
        $_SESSION['gallery_saved_' . $id] = true;
        header('Location: product_gallery.php?id=' . $id);
        exit;
    } catch (InvalidArgumentException $exception) {
        http_response_code(422); $error = $exception->getMessage();
    } catch (RuntimeException $exception) {
        error_log('CAZ gallery upload: ' . $exception->getMessage());
        http_response_code(503); $error = 'The photo could not be saved. Please try again or contact the site administrator.';
    }
}
$photos = caz_product_gallery($id);
$saved = !empty($_SESSION['gallery_saved_' . $id]);
unset($_SESSION['gallery_saved_' . $id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product photos — <?php echo caz_h($product['product_name']); ?></title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
    <link rel="stylesheet" href="../css/experience.css?v=20260921-location">
    <link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
    <link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
    <link rel="stylesheet" href="../css/product-gallery.css?v=20260930-gallery">
    <script defer src="../js/refined.js?v=20260910-release"></script>
    <script defer src="../js/experience.js?v=20260926-teal"></script>
</head>
<body class="caz-admin caz-themed caz-gallery-page">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php include __DIR__ . '/../includes/navigation.php'; ?>
<main class="caz-gallery-manager">
    <a class="caz-gallery-back" href="edit_product.php?id=<?php echo $id; ?>">← Back to product editor</a>
    <section class="caz-gallery-panel">
        <h1>Product photos</h1>
        <p class="caz-gallery-subtitle"><?php echo caz_h($product['product_name']); ?></p>
        <p>Add genuine views of this exact product, such as its side, back or connectors. These appear beside the main catalog image.</p>
        <?php if ($saved): ?><p class="caz-gallery-feedback" role="status">Product photos updated.</p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="caz-gallery-error" role="alert"><?php echo caz_h($error); ?></p><?php endif; ?>
        <div class="caz-gallery-admin-grid">
            <figure class="caz-gallery-card">
                <img src="../images/<?php echo caz_h($product['image']); ?>" alt="<?php echo caz_h($product['product_name']); ?> — main catalog photo">
                <figcaption>Main catalog photo</figcaption>
            </figure>
            <?php foreach ($photos as $photo): ?>
            <figure class="caz-gallery-card">
                <img src="<?php echo caz_h(caz_gallery_url($id, $photo)); ?>" alt="<?php echo caz_h($photo['label']); ?>" loading="lazy">
                <figcaption><?php echo caz_h($photo['label']); ?></figcaption>
                <form method="post" action="product_gallery.php?id=<?php echo $id; ?>">
                    <input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="file" value="<?php echo caz_h($photo['file']); ?>">
                    <button class="caz-gallery-remove" type="submit">Remove photo<span class="caz-sr-only">: <?php echo caz_h($photo['label']); ?></span></button>
                </form>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php if (count($photos) < CAZ_GALLERY_MAX_PHOTOS): ?>
        <form class="caz-gallery-upload" method="post" enctype="multipart/form-data" action="product_gallery.php?id=<?php echo $id; ?>">
            <input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo CAZ_GALLERY_UPLOAD_BYTES; ?>">
            <h2>Add a product photo</h2>
            <p id="gallery-photo-help">JPEG, PNG or WebP, up to 8 MB. Transparent PNG and WebP photos keep their clear background. Up to eight additional photos per product.</p>
            <label for="gallery-photo">Photo</label>
            <input id="gallery-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="gallery-photo-help" required>
            <label for="gallery-label">View label</label>
            <input id="gallery-label" name="label" type="text" maxlength="100" placeholder="e.g. Side view or Rear connectors" required>
            <label class="caz-gallery-confirm"><input name="exact_product" type="checkbox" value="1" required><span>This photo shows the exact product being sold.</span></label>
            <button class="caz-gallery-save" type="submit">Add photo</button>
        </form>
        <?php else: ?><p>All eight additional photo slots are in use. Remove a photo to add another.</p><?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
</body></html>

<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';
caz_require_user(true);
caz_promotions_schema($conn);
$cazAdmin = true;
$error = '';
$form = ['id'=>0,'product_id'=>'','title'=>'','description'=>'','benefit'=>'','minimum_quantity'=>3,
    'special_price'=>'','image'=>'','start_date'=>'','end_date'=>'','is_active'=>0];

function caz_promo_date($value): ?string {
    $value = trim((string)$value);
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Choose a valid date.');
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    caz_check_csrf();
    $uploaded = null;
    try {
        $action = (string)($_POST['action'] ?? '');
        $id = filter_var($_POST['id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($id === false) throw new InvalidArgumentException('Invalid promotion.');
        if (in_array($action,['archive','activate'],true)) {
            if ($id<1) throw new InvalidArgumentException('Choose a promotion first.');
            $active = $action === 'activate' ? 1 : 0;
            $stmt = $conn->prepare('UPDATE caz_promotions SET is_active=? WHERE id=?');
            $stmt->bind_param('ii',$active,$id);
            $stmt->execute();
            header('Location: promotions.php?saved=1'); exit;
        }
        if ($action !== 'save') throw new InvalidArgumentException('Invalid promotion action.');
        foreach (array_keys($form) as $key) {
            if ($key !== 'image') $form[$key] = is_scalar($_POST[$key] ?? null) ? $_POST[$key] : '';
        }
        $productId = filter_var($_POST['product_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $quantity = filter_var($_POST['minimum_quantity'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
        if (!$productId || !$quantity) throw new InvalidArgumentException('Select a product and a positive whole-number minimum quantity.');
        $title = trim((string)$form['title']);
        $description = trim((string)$form['description']);
        $benefit = trim((string)$form['benefit']);
        if ($title === '' || mb_strlen($title)>140) throw new InvalidArgumentException('Enter a promotion title, up to 140 characters.');
        if ($benefit === '' || mb_strlen($benefit)>240) throw new InvalidArgumentException('Describe the included benefit, up to 240 characters.');
        if (mb_strlen($description)>4000) throw new InvalidArgumentException('Keep the description within 4,000 characters.');
        $productStmt = $conn->prepare('SELECT id,price FROM products WHERE id=?');
        $productStmt->bind_param('i',$productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        if (!$product) throw new InvalidArgumentException('The selected product is no longer available.');
        $priceInput = trim((string)$form['special_price']);
        $specialPrice = null;
        if ($priceInput !== '') {
            if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/D',$priceInput)) throw new InvalidArgumentException('Enter a valid price with up to two decimal places.');
            $specialPrice = (float)$priceInput;
            if ($specialPrice<=0 || $specialPrice>(float)$product['price']) throw new InvalidArgumentException('The offer price must be above zero and no higher than the product price.');
        }
        $startDate = caz_promo_date($form['start_date']);
        $endDate = caz_promo_date($form['end_date']);
        if ($startDate && $endDate && $endDate<$startDate) throw new InvalidArgumentException('The end date must be on or after the start date.');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $existingImage = null;
        if ($id) {
            $existingStmt = $conn->prepare('SELECT image FROM caz_promotions WHERE id=?');
            $existingStmt->bind_param('i',$id);
            $existingStmt->execute();
            $existing = $existingStmt->get_result()->fetch_assoc();
            if (!$existing) throw new InvalidArgumentException('This promotion no longer exists.');
            $existingImage = $existing['image'];
        }
        $image = isset($_POST['use_product_image']) ? null : $existingImage;
        $file = $_FILES['promo_image'] ?? null;
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size']>3*1024*1024) throw new InvalidArgumentException('Upload an image smaller than 3 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $dimensions = @getimagesize($file['tmp_name']);
            if (!isset($extensions[$mime]) || !$dimensions || $dimensions[0]>6000 || $dimensions[1]>6000 || $dimensions[0]<1 || $dimensions[1]<1) {
                throw new InvalidArgumentException('Choose a JPG, PNG, or WebP image up to 6,000 pixels wide and high.');
            }
            $folder = __DIR__ . '/../images/promotions';
            if (!is_dir($folder) && !mkdir($folder,0755,true)) throw new RuntimeException('Unable to create image folder.');
            $image = 'promotions/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            $uploaded = __DIR__ . '/../images/' . $image;
            if (!move_uploaded_file($file['tmp_name'],$uploaded)) throw new RuntimeException('Unable to save image.');
        }
        if ($id) {
            $stmt = $conn->prepare('UPDATE caz_promotions SET product_id=?,title=?,description=?,benefit=?,minimum_quantity=?,special_price=?,image=?,start_date=?,end_date=?,is_active=? WHERE id=?');
            $stmt->bind_param('isssidsssii',$productId,$title,$description,$benefit,$quantity,$specialPrice,$image,$startDate,$endDate,$active,$id);
        } else {
            $adminId = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare('INSERT INTO caz_promotions (product_id,title,description,benefit,minimum_quantity,special_price,image,start_date,end_date,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isssidsssii',$productId,$title,$description,$benefit,$quantity,$specialPrice,$image,$startDate,$endDate,$active,$adminId);
        }
        $stmt->execute();
        header('Location: promotions.php?saved=1'); exit;
    } catch (Throwable $e) {
        if ($uploaded && is_file($uploaded)) unlink($uploaded);
        if ($e instanceof InvalidArgumentException) $error = $e->getMessage();
        else { error_log('CAZ promotion save: ' . $e->getMessage()); $error = 'The promotion could not be saved. Please try again.'; }
    }
} elseif (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM caz_promotions WHERE id=?');
    $stmt->bind_param('i',$editId);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc() ?: $form;
}
$products = $conn->query('SELECT id,product_name,category,price,image FROM products ORDER BY category,product_name')->fetch_all(MYSQLI_ASSOC);
$offers = $conn->query("SELECT cp.*,p.product_name,p.price AS original_price,p.image AS product_image,p.category,
    CASE WHEN cp.is_active=0 THEN 'Draft / paused' WHEN p.id IS NULL THEN 'Product removed'
    WHEN cp.start_date>CURRENT_DATE THEN 'Scheduled' WHEN cp.end_date<CURRENT_DATE THEN 'Expired' ELSE 'Live' END AS display_status
    FROM caz_promotions cp LEFT JOIN products p ON p.id=cp.product_id ORDER BY cp.updated_at DESC,cp.id DESC")->fetch_all(MYSQLI_ASSOC);
$liveCount = count(array_filter($offers,fn($offer)=>$offer['display_status']==='Live'));
?>
<!DOCTYPE html>
<html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Promotions · CAZ Admin</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release"><link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
    <link rel="stylesheet" href="../css/experience.css?v=20260921-location"><link rel="stylesheet" href="../css/promotions.css?v=20260910-release">
    <script defer src="../js/promotions.js?v=20260910-release"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script defer src="../js/experience.js?v=20260926-teal"></script><link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/admin-glass-details.css?v=<?= (int)filemtime(__DIR__ . '/../css/admin-glass-details.css') ?>">
</head><body class="caz-admin caz-promotions-page caz-themed caz-admin-promotions">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php require __DIR__ . '/../includes/navigation.php'; ?>
<main class="promo-workspace">
    <header class="promo-page-heading"><div><span class="promo-eyebrow">Grow your business</span><h1>Promotions</h1><p>Create thoughtful offers for your CCTV and solar customers.</p></div><a class="promo-button promo-button-secondary" href="../pages/promotions.php">View customer page ↗</a></header>
    <?php if (isset($_GET['saved'])): ?><div class="promo-notice" role="status">Promotion updated successfully.</div><?php endif; ?>
    <?php if ($error): ?><div class="promo-notice promo-error" role="alert"><?= caz_h($error) ?></div><?php endif; ?>
    <div class="promo-admin-layout">
        <section class="promo-editor" id="promotion-editor" aria-labelledby="editor-heading">
            <div class="promo-editor-heading"><span class="promo-step">✦</span><div><h2 id="editor-heading"><?= $form['id'] ? 'Edit promotion' : 'Create an offer' ?></h2><p>Show customers what makes this offer special.</p></div></div>
            <form method="post" enctype="multipart/form-data" class="promo-form">
                <input type="hidden" name="csrf" value="<?= caz_h(caz_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                <label for="promo-product">Product<select required name="product_id" id="promo-product"><option value="">Choose a product</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>" data-price="<?= caz_h($product['price']) ?>" data-image="<?= caz_h(caz_promo_image_url($product['image'])) ?>" <?= (int)$form['product_id']===(int)$product['id'] ? 'selected' : '' ?>><?= caz_h($product['category'].' · '.$product['product_name']) ?> — ₱<?= number_format((float)$product['price'],2) ?></option><?php endforeach; ?></select></label>
                <div class="promo-product-preview" id="promo-product-preview" hidden><img id="promo-preview-image" src="../images/cazlogo.png" alt="Selected product"><div><span>Regular unit price</span><strong id="promo-preview-price"></strong><small>Your product photo is used automatically.</small></div></div>
                <label for="promo-title">Offer title<input id="promo-title" name="title" maxlength="140" value="<?= caz_h($form['title']) ?>" placeholder="e.g. More security, installation included" required></label>
                <label for="promo-benefit">Included benefit<input id="promo-benefit" name="benefit" maxlength="240" value="<?= caz_h($form['benefit']) ?>" placeholder="e.g. Free installation when you buy 3" required></label>
                <label for="promo-description">Details &amp; conditions<textarea id="promo-description" name="description" maxlength="4000" rows="3" placeholder="Describe the installation coverage, inclusions, and any other conditions."><?= caz_h($form['description']) ?></textarea></label>
                <div class="promo-form-row"><label for="promo-quantity"><span>Minimum units</span><input id="promo-quantity" type="number" name="minimum_quantity" min="1" max="100000" step="1" required value="<?= caz_h($form['minimum_quantity']) ?>"></label><label for="promo-price"><span>Offer price per unit <span class="promo-optional">optional</span></span><input id="promo-price" type="number" name="special_price" min="0.01" step="0.01" value="<?= caz_h($form['special_price']) ?>" placeholder="Keep regular price"></label></div>
                <p class="promo-help">The offer applies to every unit of this product once the minimum is met. Leave the price blank for a benefit such as free installation. Offers do not stack; the lowest eligible price applies.</p>
                <div class="promo-form-row"><label for="promo-start"><span>Starts <span class="promo-optional">optional</span></span><input id="promo-start" name="start_date" type="date" value="<?= caz_h($form['start_date']) ?>"></label><label for="promo-end"><span>Ends <span class="promo-optional">optional</span></span><input id="promo-end" name="end_date" type="date" value="<?= caz_h($form['end_date']) ?>"></label></div>
                <label for="promo-image"><span>Custom offer image <span class="promo-optional">optional</span></span><input id="promo-image" name="promo_image" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WebP. Maximum 3 MB. Use a clear product or installation photo.</small></label>
                <?php if (!empty($form['image'])): ?><label class="promo-checkbox"><input type="checkbox" name="use_product_image" value="1"> Restore the product's original image</label><?php endif; ?>
                <label class="promo-checkbox promo-publish"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>><span>Publish this promotion<small>Customers can see it during its scheduled dates.</small></span></label>
                <div class="promo-actions"><button class="promo-button" type="submit">Save promotion</button><?php if ($form['id']): ?><a class="promo-button promo-button-secondary" href="promotions.php">Create new</a><?php endif; ?></div>
            </form>
        </section>
        <section class="promo-offers-panel" aria-labelledby="offers-heading">
            <div class="promo-list-heading"><div><h2 id="offers-heading">Your offers</h2><p><?= count($offers) ?> total · <?= $liveCount ?> live</p></div><span class="promo-label">Easy to manage</span></div>
            <?php if (!$offers): ?><div class="promo-empty"><span class="promo-empty-icon">✦</span><h3>Your first promotion starts here</h3><p>Choose a product, set a minimum quantity, and describe the benefit. Save it as a draft or publish when you're ready.</p></div><?php endif; ?>
            <div class="promo-admin-offers"><?php foreach ($offers as $offer): ?>
                <article class="promo-manage-card"><img src="<?= caz_h(caz_promo_image_url($offer['image'] ?: $offer['product_image'])) ?>" alt="<?= caz_h($offer['product_name'] ?? 'Promotion') ?>" loading="lazy"><div class="promo-manage-content"><span class="promo-status <?= $offer['display_status']==='Live' ? 'promo-status-live' : '' ?>"><?= caz_h($offer['display_status']) ?></span><h3><?= caz_h($offer['title']) ?></h3><p><?= caz_h($offer['product_name'] ?? 'Product removed') ?></p><strong><?= caz_h($offer['benefit']) ?></strong><p class="promo-help">Buy <?= (int)$offer['minimum_quantity'] ?> or more · <?= $offer['special_price'] !== null ? '₱'.number_format((float)$offer['special_price'],2).' / unit' : 'Regular product price' ?></p><div class="promo-actions"><a class="promo-button promo-button-secondary" href="promotions.php?edit=<?= (int)$offer['id'] ?>#promotion-editor">Edit offer</a><form method="post"><input type="hidden" name="csrf" value="<?= caz_h(caz_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$offer['id'] ?>"><input type="hidden" name="action" value="<?= $offer['is_active'] ? 'archive' : 'activate' ?>"><button type="submit" class="promo-text-button"><?= $offer['is_active'] ? 'Pause' : 'Publish' ?></button></form></div></div></article>
            <?php endforeach; ?></div>
        </section>
    </div>
</main><?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
</body></html>

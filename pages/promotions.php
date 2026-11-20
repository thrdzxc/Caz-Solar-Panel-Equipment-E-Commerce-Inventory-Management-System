<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';
// Promotions are public; shopping and account endpoints still require login.
$promoCustomer = !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'customer';
$promoLogin = '../login.php?role=customer&next=' . rawurlencode('pages/promotions.php');
header('Cache-Control: private, no-store, max-age=0');
caz_promotions_schema($conn);
$cazAdmin = false;
$offers = $conn->query("SELECT cp.*,p.product_name,p.price AS original_price,p.image AS product_image,p.category,p.stock,
    LEAST(p.price,COALESCE(cp.special_price,p.price)) AS unit_price
    FROM caz_promotions cp JOIN products p ON p.id=cp.product_id WHERE cp.is_active=1
    AND (cp.start_date IS NULL OR cp.start_date<=CURRENT_DATE)
    AND (cp.end_date IS NULL OR cp.end_date>=CURRENT_DATE)
    ORDER BY cp.updated_at DESC,cp.id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Promotions · CAZ Solar &amp; CCTV</title>
    <link rel="stylesheet" href="../css/style.css?v=20260910-release"><link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
    <link rel="stylesheet" href="../css/experience.css?v=20260921-location"><link rel="stylesheet" href="../css/promotions.css?v=20260910-release">
    <script defer src="../js/promotions.js?v=20260910-release"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script defer src="../js/experience.js?v=20260926-teal"></script><link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/promotion-access.css?v=20260930-layout">
<link rel="stylesheet" href="../css/promotion-assistant.css?v=<?= filemtime(__DIR__ . '/../css/promotion-assistant.css') ?>">
<script defer src="../js/promotion-assistant.js?v=<?= filemtime(__DIR__ . '/../js/promotion-assistant.js') ?>"></script>
</head><body class="caz-promotions-page caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php if ($promoCustomer): ?>
<?php require __DIR__ . '/../includes/navigation.php'; ?>
<?php else: ?>
<header class="promo-public-header">
    <a class="promo-public-brand" href="../portal.php"><img src="../images/cazlogo.png" alt="CAZ Solar and CCTV"><span>CAZ<small>Solar &amp; CCTV</small></span></a>
    <nav aria-label="Visitor navigation"><a href="../portal.php">Choose a portal</a><a class="promo-public-login" href="<?= caz_h($promoLogin) ?>">Customer login</a></nav>
</header>
<?php endif; ?>
<main class="promo-workspace">
    <section class="promo-hero"><div><span class="promo-eyebrow">A little extra, from CAZ</span><h1>Great equipment.<br>A better deal.</h1><p>Discover offers for a brighter, safer home. Eligible promotions apply automatically at checkout. Browse freely; sign in when you are ready to shop.</p><div class="promo-actions"><a class="promo-button" href="<?= caz_h($promoCustomer ? 'cctv.php' : $promoLogin) ?>">Explore CCTV →</a><a class="promo-button promo-button-secondary" href="<?= caz_h($promoCustomer ? 'solar.php' : $promoLogin) ?>">Discover solar →</a></div></div><div class="promo-hero-art" aria-hidden="true"><span class="promo-art-sun">☀</span><span class="promo-art-mark">CAZ</span><span class="promo-art-caption">Energy. Security. More value.</span></div></section>
    <div class="promo-catalog-heading"><div><span class="promo-eyebrow">Made for your next upgrade</span><h2>Current promotions</h2></div><div class="promo-category-tabs" role="group" aria-label="Filter promotions"><button type="button" class="active" data-promo-filter="all" aria-pressed="true">All offers</button><button type="button" data-promo-filter="cctv" aria-pressed="false">CCTV</button><button type="button" data-promo-filter="solar" aria-pressed="false">SOLAR</button></div></div>
    <div class="promo-grid" id="promo-grid"><?php foreach ($offers as $offer): $saving=(float)$offer['original_price']-(float)$offer['unit_price']; ?>
        <article class="promo-card" data-promo-category="<?= caz_h(strtolower($offer['category'])) ?>"><div class="promo-card-image"><img src="<?= caz_h(caz_promo_image_url($offer['image'] ?: $offer['product_image'])) ?>" alt="<?= caz_h($offer['product_name']) ?>" loading="lazy"><span class="promo-card-badge"><?= caz_h($offer['category']) ?> offer</span></div><div class="promo-card-body"><span class="promo-quantity-label">Buy <?= (int)$offer['minimum_quantity'] ?> or more</span><h3><?= caz_h($offer['title']) ?></h3><p class="promo-product-name"><?= caz_h($offer['product_name']) ?></p><div class="promo-benefit">✦ <span><?= caz_h($offer['benefit']) ?></span></div><?php if ($offer['description']): ?><p class="promo-description"><?= nl2br(caz_h($offer['description'])) ?></p><?php endif; ?><div class="promo-price"><strong>₱<?= number_format((float)$offer['unit_price'],2) ?></strong><span>/ unit</span><?php if ($saving>0): ?><del>₱<?= number_format((float)$offer['original_price'],2) ?></del><?php endif; ?></div><?php if ($saving>0): ?><p class="promo-saving">Save ₱<?= number_format($saving,2) ?> on each eligible unit</p><?php endif; ?><p class="promo-help">Minimum <?= (int)$offer['minimum_quantity'] ?> units of this product in one order.<?= $offer['end_date'] ? ' Valid through '.caz_h(date('M j, Y',strtotime($offer['end_date']))).'.' : '' ?></p><?php if ((int)$offer['stock']<(int)$offer['minimum_quantity']): ?><p class="promo-stock-note">Current stock is below this offer's minimum. Message us about availability.</p><?php endif; ?><div class="promo-card-actions"><a class="promo-button" href="<?= caz_h($promoCustomer ? 'product.php?id=' . (int)$offer['product_id'] : $promoLogin) ?>"><?= $promoCustomer ? 'View product →' : 'Sign in to shop →' ?></a><a class="promo-link" href="<?= caz_h($promoCustomer ? 'messages.php' : '../login.php?role=customer&next=pages%2Fmessages.php') ?>">Ask about this offer</a></div></div></article>
    <?php endforeach; ?></div>
    <div class="promo-empty" id="promo-no-offers" <?= $offers ? 'hidden' : '' ?>><span class="promo-empty-icon">✦</span><h3>Good things are on the way</h3><p>There are no active offers in this category right now. Explore our products or message our team for help choosing your setup.</p><a class="promo-button promo-button-secondary" href="<?= caz_h($promoCustomer ? 'messages.php' : '../login.php?role=customer&next=pages%2Fmessages.php') ?>">Message CAZ</a></div>
    <p class="promo-terms">Promotions apply to the listed product only. Offers do not stack: the lowest eligible unit price applies; if prices are equal, the most recently created offer applies. Installation and other service benefits are recorded with your order for our team to arrange.</p>
</main>
<?php require __DIR__ . '/../includes/promotion-assistant-widget.php'; ?>
</body></html>

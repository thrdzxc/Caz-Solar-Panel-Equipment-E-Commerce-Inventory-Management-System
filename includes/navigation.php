<?php
require_once __DIR__ . '/feature_bootstrap.php';
require_once __DIR__ . '/navigation_icons.php';
$cazAdmin = $cazAdmin ?? (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false);
$cazCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
$cazCartCount = 0;
$cazPhotoUrl = null;
$cazVerification = null;
if (!$cazAdmin && !empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/identity_verification.php';
    caz_identity_schema($conn);
    $cazVerification = caz_identity_details($conn, (int)$_SESSION['user_id']);
    require_once __DIR__ . '/profile_photo.php';
    $cazPhotoVersion = caz_profile_photo_version($conn, (int)$_SESSION['user_id']);
    if ($cazPhotoVersion !== null) $cazPhotoUrl = 'profile_photo.php?v=' . rawurlencode($cazPhotoVersion);
    $cazCartUser = (int)$_SESSION['user_id'];
    $cazCartStmt = $conn->prepare('SELECT COALESCE(SUM(ci.quantity),0) FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.user_id=?');
    $cazCartStmt->bind_param('i', $cazCartUser);
    $cazCartStmt->execute();
    $cazCartCount = (int)$cazCartStmt->get_result()->fetch_row()[0];
}
$cazNavItems = $cazAdmin
    ? ['index.php'=>'Overview','inventory.php'=>'Inventory','orders.php'=>'Orders','logs.php'=>'Activity','sales.php'=>'Sales','promotions.php'=>'Promotions','messages.php'=>'Messages','store_location.php'=>'Store location','verifications.php'=>'Verification']
    : ['index.php'=>'Home','cctv.php'=>'CCTV','solar.php'=>'Solar','about.php'=>'About','contact.php'=>'Contact','messages.php'=>'Messages'];
if (!$cazAdmin && $cazVerification) $cazNavItems['verification.php'] = 'Verification';
?>
<script>window.CAZ_CSRF = <?php echo json_encode(caz_csrf_token()); ?>; window.CAZ_USER_ID = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;</script>
<?php if (!empty($_SESSION['user_id'])): ?>
<script>window.CAZ_MESSAGE_STATUS = <?php echo json_encode(['endpoint'=>$cazAdmin ? '../admin/messages_api.php' : '../pages/messages_api.php','viewerId'=>(int)$_SESSION['user_id'],'admin'=>(bool)$cazAdmin], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<link rel="stylesheet" href="../css/message-notifications.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/message-notifications.css'); ?>"><script src="../js/message-notifications.js?v=<?php echo (int)filemtime(__DIR__ . '/../js/message-notifications.js'); ?>" defer></script>
<script>window.CAZ_ORDER_STATUS = <?= json_encode(['endpoint'=>$cazAdmin ? '../admin/order_status.php' : '../pages/order_status.php','viewerId'=>(int)$_SESSION['user_id'],'admin'=>(bool)$cazAdmin], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<link rel="stylesheet" href="../css/order-notifications.css?v=<?= (int)filemtime(__DIR__ . '/../css/order-notifications.css') ?>"><script src="../js/order-notifications.js?v=<?= (int)filemtime(__DIR__ . '/../js/order-notifications.js') ?>" defer></script>
<?php endif; ?>
<?php if ($cazAdmin && !empty($_SESSION['user_id'])): ?>
<script src="../js/verification-notifications.js?v=<?= (int)filemtime(__DIR__ . '/../js/verification-notifications.js') ?>" defer></script>
<?php endif; ?>
<?php if (!$cazAdmin): ?><script src="../js/cart-status.js?v=20260920-shopping" defer></script><link rel="stylesheet" href="../css/delivery-location.css?v=20260921-location"><script src="../js/delivery-location.js?v=20260921-location" defer></script><?php endif; ?>
<?php if (!$cazAdmin): ?>
<script>window.CAZ_PROFILE_PHOTO = <?php echo json_encode(['endpoint'=>'profile_photo.php','photo_url'=>$cazPhotoUrl], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<link rel="stylesheet" href="../css/profile-photo.css?v=20260922-photo"><script src="../js/profile-photo.js?v=<?php echo (int)filemtime(__DIR__ . '/../js/profile-photo.js'); ?>" defer></script>
<?php endif; ?>
<?php if (!$cazAdmin): ?><script src="../js/customer-navigation.js?v=<?php echo (int)filemtime(__DIR__ . '/../js/customer-navigation.js'); ?>" defer></script><?php endif; ?>
<link rel="stylesheet" href="../css/sidebar.css?v=<?= (int)filemtime(__DIR__ . '/../css/sidebar.css') ?>">
<script>document.body.classList.add('caz-sidebar-layout');try{if(localStorage.getItem('caz-sidebar-collapsed')==='1')document.body.classList.add('caz-sidebar-collapsed')}catch(e){}</script>
<script src="../js/sidebar.js?v=<?= (int)filemtime(__DIR__ . '/../js/sidebar.js') ?>" defer></script>
<div class="caz-mobile-bar"><button type="button" class="caz-menu-toggle" aria-label="Open navigation" aria-controls="caz-sidebar" aria-expanded="false"><?= caz_navigation_icon('Menu') ?></button><a href="index.php"><img src="../images/cazlogo.png" alt=""><span>CAZ <small><?= $cazAdmin ? 'Management' : 'Solar & CCTV' ?></small></span></a></div>
<div class="caz-sidebar-backdrop" hidden></div>
<nav id="caz-sidebar" class="navbar caz-navigation caz-sidebar <?= $cazAdmin ? 'caz-admin-navigation' : 'caz-customer-navigation' ?>" aria-label="<?= $cazAdmin ? 'Admin' : 'Main' ?> navigation">
  <div class="caz-sidebar-top">
    <a class="caz-brand" href="index.php" title="CAZ <?= $cazAdmin ? 'Management' : 'Solar & CCTV' ?>"><img src="../images/cazlogo.png" alt="CAZ"><span class="caz-sidebar-label">CAZ<small><?= $cazAdmin ? 'Management' : 'Solar & CCTV' ?></small></span></a>
    <button type="button" class="caz-sidebar-collapse" aria-label="Collapse navigation" aria-expanded="true" aria-controls="caz-sidebar" title="Collapse navigation"><?= caz_navigation_icon('Collapse') ?></button>
  </div>
  <div class="caz-sidebar-section caz-sidebar-label"><?= $cazAdmin ? 'WORKSPACE' : 'EXPLORE THE STORE' ?></div>
  <div class="caz-nav-links">
    <?php foreach ($cazNavItems as $url=>$label): ?>
    <a href="<?= $url ?>" title="<?= caz_h($label) ?>" <?= $cazCurrent === $url ? 'aria-current="page"' : '' ?> class="caz-sidebar-link<?= $url === 'messages.php' || ($cazAdmin && $url === 'verifications.php') ? ' caz-messages-nav-link' : '' ?>" <?= $url === 'messages.php' ? 'data-message-link' : '' ?> <?= $cazAdmin && $url === 'verifications.php' ? 'data-verification-link' : '' ?> <?= $cazAdmin && $url === 'orders.php' ? 'data-order-link' : '' ?>>
      <?= caz_navigation_icon($label) ?><span class="caz-sidebar-label"><?= $label === 'Solar' ? 'SOLAR' : caz_h($label) ?></span>
      <?php if ($url === 'messages.php' || ($cazAdmin && $url === 'verifications.php')): $isVerification = $url === 'verifications.php'; ?>
      <span class="caz-message-badge" <?= $isVerification ? 'data-verification-badge' : 'data-message-unread' ?> hidden aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 2.5a1.5 1.5 0 0 1 3 0M4 6a4 4 0 0 1 8 0v3l1.5 2.5h-11L4 9V6Zm2.25 7a1.8 1.8 0 0 0 3.5 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><span <?= $isVerification ? 'data-verification-count' : 'data-message-unread-count' ?>>0</span></span>
      <span class="caz-message-announcement" <?= $isVerification ? 'data-verification-announcement' : 'data-message-announcement' ?> role="status" aria-live="polite" aria-atomic="true"></span>
      <?php endif; ?>
      <?php if ($cazAdmin && $url === 'orders.php'): ?>
      <span class="caz-message-badge" data-order-badge hidden aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 2.5a1.5 1.5 0 0 1 3 0M4 6a4 4 0 0 1 8 0v3l1.5 2.5h-11L4 9V6Zm2.25 7a1.8 1.8 0 0 0 3.5 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><span data-order-count>0</span></span><span class="caz-message-announcement" data-order-announcement role="status" aria-live="polite" aria-atomic="true"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="caz-nav-actions">
    <?php if (!$cazAdmin): ?>
      <?php if (in_array($cazCurrent, ['cctv.php','solar.php'], true)): ?>
      <button type="button" class="nav-action" title="Search products" data-caz-product-search aria-label="Search products" aria-controls="search-bar-overlay" aria-expanded="false" onclick="cazSidebarAction('search')"><?= caz_navigation_icon('Search') ?><span class="caz-sidebar-label">Search products</span></button>
      <?php else: ?>
      <a class="nav-action" href="cctv.php?search=1" title="Search products" aria-label="Search products"><?= caz_navigation_icon('Search') ?><span class="caz-sidebar-label">Search products</span></a>
      <?php endif; ?>
      <button type="button" class="nav-action nav-cart-action" data-order-link title="Cart & orders" aria-label="Shopping cart, <?= $cazCartCount ?> items" onclick="cazSidebarAction('cart')"><?= caz_navigation_icon('Cart') ?><span class="caz-sidebar-label">Cart & orders</span><span class="cart-count-badge" data-cart-count aria-live="polite" aria-atomic="true"><?= $cazCartCount ?></span><span class="caz-message-badge" data-order-badge hidden aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6.5 2.5a1.5 1.5 0 0 1 3 0M4 6a4 4 0 0 1 8 0v3l1.5 2.5h-11L4 9V6Zm2.25 7a1.8 1.8 0 0 0 3.5 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><span data-order-count>0</span></span><span class="caz-message-announcement" data-order-announcement role="status" aria-live="polite" aria-atomic="true"></span></button>
    <?php endif; ?>
    <button type="button" class="nav-action caz-sidebar-account" title="Your profile" aria-label="Your profile" onclick="cazSidebarAction('profile')"><span class="caz-sidebar-avatar" <?= !$cazAdmin ? 'data-account-avatar' : '' ?> aria-hidden="true"><?php if ($cazPhotoUrl): ?><img class="account-photo" src="<?= caz_h($cazPhotoUrl) ?>" alt=""><?php else: ?><?= caz_navigation_icon('Account') ?><?php endif; ?></span><span class="caz-sidebar-label">Account<small data-account-name><?= caz_h($_SESSION['user_name'] ?? ($cazAdmin ? 'Administrator' : 'Your profile')) ?></small></span></button>
  </div>
</nav>
<?php if (!$cazAdmin && $cazVerification && $cazVerification['status'] !== 'approved'): ?>
<link rel="stylesheet" href="../css/identity-verification.css?v=20260929">
<aside class="verification-banner">Account verification: <?= caz_h($cazVerification['status']) ?>. Admin approval is needed before ordering. <a href="verification.php">View status or replace ID photo</a></aside>
<?php endif; ?>
<?php if (!$cazAdmin && !empty($_SESSION['user_id'])): ?>
<link rel="stylesheet" href="../css/promotion-access.css?v=20260930-layout">
<a class="caz-promotion-launcher" href="promotions.php" <?= $cazCurrent === 'promotions.php' ? 'aria-current="page"' : '' ?> aria-label="View current CAZ promotions"><span aria-hidden="true">✦</span> Promotions</a>
<?php endif; ?>

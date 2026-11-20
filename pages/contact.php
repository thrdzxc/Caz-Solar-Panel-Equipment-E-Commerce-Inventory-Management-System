<?php
session_start();
include "../db_conn.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../includes/store_location.php';
$store = caz_store_location($conn);
$storeDirections = caz_store_directions($store);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="../css/style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="../css/store-location.css?v=20260926-store">
<script defer src="../assets/vendor/leaflet/leaflet.js"></script>
<script defer src="../js/store-location.js?v=20260926-store"></script>
</head>
<body class="caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<!-- NAVBAR -->
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<!-- CONTACT PAGE -->
<main class="contact-container">
    
    <section class="contact-intro">
        <h1 class="michroma">Contact Us</h1>
        <div class="intro-line"></div>
        <p class="intro-text">
            Have a question about CCTV, solar power or installation? Send our team a message or visit us in Santiago City.
        </p>
        <a class="btn" href="messages.php">Message our team &rarr;</a>
    </section>

    <div class="contact-content-layout">
        
        <div class="contact-links-panel">
            <h2 class="michroma sub-panel-title">Let’s connect</h2>
            
            <a href="<?php echo caz_h($storeDirections); ?>" target="_blank" rel="noopener noreferrer" class="contact-link-item" aria-label="Get directions to the CAZ store in Google Maps">
                <i class="fas fa-map-marker-alt"></i>
                <div class="link-details">
                    <span class="link-label">Location</span>
                    <span class="link-value"><?php echo caz_h($store['address']); ?></span>
                </div>
            </a>

            <a href="https://www.facebook.com" target="_blank" class="contact-link-item">
                <i class="fab fa-facebook-f"></i>
                <div class="link-details">
                    <span class="link-label">Facebook Page</span>
                    <span class="link-value">Caz Solar Panel and Equipment Store</span>
                </div>
            </a>

            <a href="tel:+639157939856" class="contact-link-item">
                <i class="fas fa-mobile-alt"></i>
                <div class="link-details">
                    <span class="link-label">Mobile</span>
                    <span class="link-value">0915-793-9856</span>
                </div>
            </a>

        </div>

        <div class="contact-map-panel" id="map-frame" data-store-config="<?php echo caz_h(json_encode($store + ['admin'=>false,'directions'=>$storeDirections])); ?>">
            <h2 class="michroma sub-panel-title">Visit our store</h2>
            <div class="store-visit-card">
                <p class="store-visit-address"><?php echo caz_h($store['address']); ?></p>
                <?php if (!caz_store_has_pin($store)): ?><p class="store-pin-pending">The store’s exact entrance pin is awaiting admin confirmation. The map shows the Balintocatoc area; directions currently use the written address.</p><?php endif; ?>
                <div id="store-map" class="store-map" role="region" aria-label="CAZ store location map"><p class="store-map-fallback">Loading map… You can open directions below at any time.</p></div>
                <a class="store-directions" href="<?php echo caz_h($storeDirections); ?>" target="_blank" rel="noopener noreferrer"><?php echo caz_store_has_pin($store) ? 'Get directions to our store' : 'Directions to the listed address'; ?> &rarr;</a>
                <p class="store-map-note">Google Maps lets you choose your starting point and shows the route, distance and travel time.</p>
                <p class="store-map-status" role="status" aria-live="polite"></p>
            </div>
        </div>

    </div>
</main>

<!-- CART OVERLAY -->
<div class="cart-backdrop" id="cart-backdrop" onclick="toggleCart()"></div>
<div class="cart-overlay" id="cart-overlay">
    <div class="cart-header"><div class="drawer-title-row"><h2>Your cart &amp; orders</h2><button class="drawer-close" type="button" aria-label="Close cart" onclick="toggleCart()">&times;</button></div>
        <div class="cart-tabs">
            <button type="button" class="tab active" aria-pressed="true" onclick="switchTab('cart')">CCTV & Solar</button>
            <button type="button" class="tab" aria-pressed="false" onclick="switchTab('pending')">Pending</button>
            <button type="button" class="tab" aria-pressed="false" onclick="switchTab('approved')">Approved</button>
            <button type="button" class="tab" aria-pressed="false" onclick="switchTab('shipped')">Shipped</button>
            <button type="button" class="tab" aria-pressed="false" onclick="switchTab('canceled')">Canceled</button>
        </div>
    </div>
    <div class="cart-body" id="cart-body"></div>
    <div class="cart-footer" id="cart-footer">
        <div class="footer-top">
            <div class="select-all">
                <input type="checkbox" id="selectAll" onchange="cart.toggleSelectAll(this.checked); renderCart();">
                <label for="selectAll">Select All</label>
            </div>
            <div class="total-price" id="total-price">₱0</div>
        </div>
        <button class="checkout-btn" onclick="checkout()">
            <span>Check Out</span>
            <span id="checkout-count">(0)</span>
        </button>
    </div>
</div>

<!-- PROFILE OVERLAY -->
<div class="profile-backdrop" id="profile-backdrop" onclick="toggleProfile()"></div>
<div id="profile-panel" class="profile-overlay"><div class="drawer-title-row"><h2>Your account</h2><button class="drawer-close" type="button" aria-label="Close profile" onclick="toggleProfile()">&times;</button></div>

    <div class="profile-icon-wrap">
        <div class="profile-avatar"><i class="fas fa-user-circle"></i></div>
        <div class="profile-customer-id">Customer ID: <strong>#<?php echo $_SESSION['user_id'] ?? ''; ?></strong></div>
    </div>

    <div class="profile-field">
        <label>Full Name:</label>
        <span class="profile-val" id="txt-user"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
        <input class="profile-inp" type="text" id="inp-user" maxlength="100" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
    </div>

    <div class="profile-field">
        <label>Email:</label>
        <span class="profile-val" id="txt-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></span>
        <input class="profile-inp" type="email" id="inp-email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
    </div>

    <div class="profile-field">
        <label>Contact Number:</label>
        <span class="profile-val" id="txt-contact"><?php echo htmlspecialchars($_SESSION['user_contact'] ?? ''); ?></span>
        <input class="profile-inp" type="tel" id="inp-contact" inputmode="numeric" pattern="09[0-9]{9}" oninput="this.value=this.value.replace(/[^0-9]/g,'')" value="<?php echo htmlspecialchars($_SESSION['user_contact'] ?? ''); ?>" maxlength="11">
    </div>

    <div class="profile-field">
        <label>Address:</label>
        <span class="profile-val" id="txt-addr"><?php echo htmlspecialchars($_SESSION['user_address'] ?? ''); ?></span>
    </div>

    <!-- Other Addresses -->
    <div class="profile-field">
        <label>Other Addresses:</label>
        <div id="saved-addresses-list"></div>
        <!-- shown only in edit mode, styled like btn-black -->
        <button id="edit-addresses-btn" onclick="showAddressModal()"
    style="display:none;margin-top:6px;background:none;border:none;padding:0;
    color:#e53935;font-family:'Space Grotesk',sans-serif;font-size:14px;
    font-weight:500;text-transform:uppercase;letter-spacing:0.5px;
    cursor:pointer;text-align:left;transition:color 0.2s;"
    onmouseover="this.style.color='#ff5252'"
    onmouseout="this.style.color='#e53935'">
    Edit Addresses
</button>
    </div>

    <div id="profile-msg" style="font-size:13px;margin-bottom:10px;display:none;"></div>

    <div class="button-row">
        <button class="btn-black" id="edit-save-btn" onclick="enableEdit()">EDIT</button>
        <button class="btn-red" id="save-logout-btn" onclick="handleLogout()">LOGOUT</button>
    </div>

</div>

<!-- SCRIPTS -->

<script src="../js/caz-alert.js?v=20260921-location"></script>

<script>
    window.userProfile = {
        id:      "<?php echo $_SESSION['user_id']      ?? ''; ?>",
        name:    "<?php echo htmlspecialchars($_SESSION['user_name']    ?? ''); ?>",
        email:   "<?php echo htmlspecialchars($_SESSION['user_email']   ?? ''); ?>",
        contact: "<?php echo htmlspecialchars($_SESSION['user_contact'] ?? ''); ?>",
        address: "<?php echo htmlspecialchars($_SESSION['user_address'] ?? ''); ?>"
    };
</script>

<script src="../js/cart.js?v=20260920-shopping"></script>
<script src="../js/ui-components.js?v=20260920-shopping"></script>
<script src="../js/ui-handlers.js?v=20261013-account"></script>

<script>
    window.addEventListener('scroll', () => {
  document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 50);
});
</script>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <h2><span class="text-red">Caz</span> Solar & Equipments</h2>
            <p>Your trusted source for solar panels, CCTV systems, and security equipment in the Philippines.</p>
        </div>
        
        <div class="footer-links-group">
            <div class="footer-col">
                <h3>PRODUCTS</h3>
                <ul>
                    <li><a href="solar.php">Solar Panels</a></li>
                    <li><a href="cctv.php">CCTV Cameras</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>COMPANY</h3>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; 2026 Caz Solar & Equipments Store. All rights reserved.</p>
    </div>
</footer>

</body>
</html>

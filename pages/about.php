<?php
session_start();
include "../db_conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="../css/style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
</head>
<body class="caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<!-- NAVBAR -->
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<!-- FULL SCREEN ABOUT -->
<main class="about-container">
    <section class="about-intro">
        <h1 class="michroma">What is Caz?</h1>
        <div class="intro-line"></div>
        <p class="intro-text">
            A business providing solar energy solutions and CCTV installation services to help homes and businesses become more energy-efficient and secure. We offer high-quality solar panel systems to reduce electricity costs and reliable CCTV systems to monitor and protect properties.
        </p>
    </section>

    <!-- BRANDS SECTION (moved here, above services) -->
    <div class="brands-section">
        <div class="brands-title">Authorized Hardware Vendors</div>
        <div class="brands-grid">
            <div class="brand-card">
                <img src="../images/hikvision.png" alt="Hikvision">
            </div>
            <div class="brand-card brand-card-contrast">
                <img src="../images/dahua.png" alt="Dahua">
            </div>
            <div class="brand-card">
                <img src="../images/bosca.png" alt="Bosca">
            </div>
            <div class="brand-card brand-card-contrast">
                <img src="../images/hp.png" alt="HP">
            </div>
        </div>
    </div>

    <section class="services-section">
        <h2 class="michroma">Our Services</h2>
        
        <div class="service-block">
            <div class="service-header">
                <h3 class="michroma">Monitoring</h3>
            </div>
            <div class="full-image-grid">
                <div class="caz-about-image-frame"><img src="../images/monitor1.jpg" alt="CCTV monitoring cameras installed on a utility pole" width="1538" height="2048" loading="lazy" decoding="async"></div>
                <div class="caz-about-image-frame"><img src="../images/monitor2.jpg" alt="Technician configuring CCTV equipment" width="1536" height="2048" loading="lazy" decoding="async"></div>
            </div>
        </div>

        <div class="service-block">
            <div class="service-header">
                <h3 class="michroma">Installations</h3>
            </div>
            <div class="full-image-grid">
                <div class="caz-about-image-frame"><img src="../images/install1.jpg" alt="Technician installing equipment on a building" width="1538" height="2048" loading="lazy" decoding="async"></div>
                <div class="caz-about-image-frame"><img src="../images/install2.jpg" alt="Field technicians installing CCTV equipment" width="1538" height="2048" loading="lazy" decoding="async"></div>
            </div>
        </div>
    </section>
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

<script> window.addEventListener('scroll', () => {
  document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 50);
});
</script>

<script>
    window.userProfile = {
        id:      "<?php echo $_SESSION['user_id']      ?? ''; ?>",
        name:    "<?php echo htmlspecialchars($_SESSION['user_name']    ?? ''); ?>",
        email:   "<?php echo htmlspecialchars($_SESSION['user_email']   ?? ''); ?>",
        contact: "<?php echo htmlspecialchars($_SESSION['user_contact'] ?? ''); ?>",
        address: "<?php echo htmlspecialchars($_SESSION['user_address'] ?? ''); ?>"
    };
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

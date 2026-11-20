<?php
include "../db_conn.php";
require_once __DIR__ . '/../includes/product_details.php';
require_once __DIR__ . '/../includes/product_gallery.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id      = $_SESSION['user_id'];
$user_name    = $_SESSION['user_name'];
$user_address = $_SESSION['user_address'] ?? '';

$productId = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 1;

// FIXED: joined with inventory to get current_stock
$query = "SELECT * FROM products WHERE id = '$productId'";
$result = mysqli_query($conn, $query);
$p      = mysqli_fetch_assoc($result);

if (!$p) { header("Location: cctv.php"); exit(); }

$is_out = (int)$p['stock'] <= 0;
$gallery = [['url' => '../images/' . $p['image'], 'label' => 'Main view']];
foreach (caz_product_gallery((int)$p['id']) as $photo) {
    $gallery[] = ['url' => caz_gallery_url((int)$p['id'], $photo), 'label' => $photo['label']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($p['product_name']); ?> - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="../css/style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/product-details.css?v=<?php echo filemtime(__DIR__ . '/../css/product-details.css'); ?>">
<script defer src="../js/product-gallery.js?v=<?php echo filemtime(__DIR__ . '/../js/product-gallery.js'); ?>"></script>
</head>
<body class="caz-themed caz-product-page">
<?php require __DIR__ . '/../includes/background.php'; ?>

<!-- NAVBAR -->
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<!-- PRODUCT DETAIL -->
<section class="product-detail">
    <div class="product-detail-toolbar">
        <button type="button" class="back-btn" onclick="goBack()" title="Back to products">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to products
        </button>
    </div>
    <div class="product-box">
        <div class="product-left<?php echo count($gallery) === 1 ? ' product-left-single' : ''; ?>" data-product-gallery>

            <!-- FIXED: out of stock badge on product image -->
            <?php if ($is_out): ?>
            <div class="product-stock-badge">
                Out of Stock
            </div>
            <?php endif; ?>

            <div class="product-image-frame">
                <img id="product-img"
                     src="../images/<?php echo htmlspecialchars($p['image']); ?>"
                     alt="<?php echo htmlspecialchars($p['product_name']); ?>"
                     style="<?php echo $is_out ? 'opacity:0.4; filter:grayscale(60%);' : ''; ?>">
            </div>
            <?php if (count($gallery) > 1): ?>
            <p class="product-gallery-caption" data-gallery-caption aria-live="polite">Main view</p>
            <div class="product-gallery-thumbnails" role="group" aria-label="Product photos">
                <?php foreach ($gallery as $index => $photo): ?>
                <button type="button" class="product-gallery-thumbnail" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        aria-label="Show <?php echo htmlspecialchars($photo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-gallery-src="<?php echo htmlspecialchars($photo['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-gallery-alt="<?php echo htmlspecialchars($p['product_name'] . ' — ' . $photo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-gallery-label="<?php echo htmlspecialchars($photo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <img src="<?php echo htmlspecialchars($photo['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                    <span class="product-gallery-label"><?php echo htmlspecialchars($photo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="product-right">
            <span class="product-meta-tag">Product details</span>
            <h1 id="product-title"><?php echo htmlspecialchars($p['product_name']); ?></h1>
            
            <p id="product-price">₱<?php echo number_format($p['price'], 2); ?></p>
            
            <div class="product-divider"></div>
            
            <section class="caz-product-information" aria-labelledby="product-facts-heading">
                <h2 id="product-facts-heading">At a glance</h2>
                <?php echo caz_render_product_facts($p); ?>
            </section>

            <div class="interaction-row">
                <div class="product-quantity-row">
                    <span class="product-quantity-label" id="product-quantity-label">Quantity</span>
                    <div class="quantity-picker" role="group" aria-labelledby="product-quantity-label">
                        <button type="button" aria-label="Decrease quantity" onclick="changeQty(-1)" <?php echo $is_out ? 'disabled' : ''; ?>>−</button>
                        <span id="qty" aria-live="polite">1</span>
                        <button type="button" aria-label="Increase quantity" onclick="changeQty(1)" <?php echo $is_out ? 'disabled' : ''; ?>>+</button>
                    </div>
                </div>

                <?php if ($is_out): ?>
                    <button class="add-to-cart-btn" disabled style="opacity:0.4; cursor:not-allowed;">
                        <i class="fas fa-shopping-cart"></i> Out of Stock
                    </button>
                <?php else: ?>
                    <button onclick="addToCart()" class="add-to-cart-btn">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                    <button onclick="buyNow()" class="add-to-cart-btn buy-now-btn">
                        <i class="fas fa-bolt"></i> Buy Now
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <section class="caz-product-information caz-product-features" aria-labelledby="product-features-heading">
            <h2 id="product-features-heading">Features &amp; product details</h2>
            <div id="product-desc"><?php echo caz_render_product_description($p['description'] ?? null); ?></div>
        </section>
    </div>
</section>

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

<!-- LOAD JS FILES FIRST -->
<script src="../js/cart.js?v=20260920-shopping"></script>
<script src="../js/ui-commponents.js?v=20261013-account"></script>
<script src="../js/ui-handlers.js?v=20261013-account"></script>

<!-- THEN define functions that use cart -->
<script>
    const currentProduct = {
        id:       <?php echo $p['id']; ?>,
        title:    <?php echo json_encode($p['product_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        price:    <?php echo $p['price']; ?>,
        img:      <?php echo json_encode($p['image'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        category: <?php echo json_encode($p['category'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        stock:    <?php echo (int)$p['stock']; ?>
    };

    let currentQty = 1;

    function changeQty(delta) {
        const maxQty = currentProduct.stock || 1;
        currentQty = Math.min(maxQty, Math.max(1, currentQty + delta));
        document.getElementById('qty').innerText = currentQty;
        if (delta > 0 && currentQty === maxQty) {
            cazAlert.warning('Only ' + maxQty + ' unit(s) available in stock!');
        }
    }

    async function addToCart() {
        const maxQty = currentProduct.stock || 1;
        if (currentQty > maxQty) {
            cazAlert.warning('Only ' + maxQty + ' unit(s) available in stock!');
            return;
        }
        const button = document.querySelector('.add-to-cart-btn');
        if (button) button.disabled = true;
        const saved = await cart.addItem(currentProduct, currentQty);
        if (button) button.disabled = false;
        if (saved === false) return;
        cazAlert.success('Saved in your cart!');
        currentQty = 1;
        document.getElementById('qty').innerText = '1';
        renderCart();
    }

    function buyNow() {
        const items = [{ 
            id:    currentProduct.id,
            title: currentProduct.title,
            image: currentProduct.img,
            qty:   currentQty,
            price: currentProduct.price,
            stock: currentProduct.stock
        }];
        const total = currentProduct.price * currentQty;

        loadSavedAddresses(() => {
            const defaultAddr = window.userProfile?.address || '';
            const allAddresses = [{ label: 'Default', address: defaultAddr }, ...savedAddresses].filter(a => a.address);

            cazAlert.checkoutModal(items, total, (shippingAddress, updatedItems, deliveryLocation) => {
                const finalItems = (updatedItems || items).map(item => ({
                    id:       item.id,
                    title:    item.title,
                    quantity: item.qty,
                    price:    item.price
                }));
                fetch('place_order.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
                    body:    JSON.stringify({ 
                        items: finalItems,
                        shipping_address: shippingAddress,
                        delivery_location: deliveryLocation
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.CAZCartStatus.checkoutComplete(data);
                        if (!document.getElementById('cart-overlay')?.classList.contains('active')) toggleCart();
                        switchTab('pending');
                    } else {
                        cazAlert.error('Order failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(e => cazAlert.error('Error: ' + e.message));
            }, allAddresses);
        });
    }

    function goBack() {
        window.history.back();
    }

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

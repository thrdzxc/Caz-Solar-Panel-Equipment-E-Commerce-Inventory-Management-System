<?php
session_start();
include "../db_conn.php";
require_once __DIR__ . '/../includes/product_details.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id          = $_SESSION['user_id'];
$user_name        = $_SESSION['user_name'];
$user_address     = $_SESSION['user_address'] ?? '';
$current_category = (basename($_SERVER['PHP_SELF']) == 'solar.php') ? 'Solar' : 'CCTV';

// ── Dynamic filters from DB ─────────────────────────────────────
$brand_query  = "SELECT DISTINCT brand FROM products WHERE category = '$current_category' AND brand != '' ORDER BY brand ASC";
$brand_result = mysqli_query($conn, $brand_query);
$brands = [];
while ($b = mysqli_fetch_assoc($brand_result)) $brands[] = $b['brand'];

$type_query  = "SELECT DISTINCT type FROM products WHERE category = '$current_category' AND type != '' ORDER BY type ASC";
$type_result = mysqli_query($conn, $type_query);
$types = [];
while ($t = mysqli_fetch_assoc($type_result)) $types[] = $t['type'];

$spec_query  = "SELECT DISTINCT spec FROM products WHERE category = '$current_category' AND spec != '' ORDER BY spec ASC";
$spec_result = mysqli_query($conn, $spec_query);
$specs = [];
while ($s = mysqli_fetch_assoc($spec_result)) $specs[] = $s['spec'];

// FIXED: use products.stock directly, no inventory join needed
$product_query  = "SELECT * FROM products
                   WHERE category = '$current_category'
                   ORDER BY product_name ASC";
$product_result = mysqli_query($conn, $product_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_category; ?> - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="../css/style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;500;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 10px 0 30px;
        }

        .filter-select {
            appearance: none;
            -webkit-appearance: none;
            background: #0a0a0a url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23555'/%3E%3C/svg%3E") no-repeat right 12px center;
            border: 1px solid #222;
            padding: 9px 32px 9px 14px;
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            color: #aaa;
            cursor: pointer;
            min-width: 130px;
            transition: border-color .2s, color .2s;
            border-radius: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .filter-select:focus  { outline: none; border-color: #e53935; color: #fff; }
        .filter-select:hover  { border-color: #444; color: #fff; }

        .filter-reset {
            background: none;
            border: 1px solid #222;
            padding: 9px 16px;
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            cursor: pointer;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all .2s;
            border-radius: 0;
        }
        .filter-reset:hover { border-color: #e53935; color: #e53935; }

        .product-card.hidden { display: none; }

        /* ── Out of Stock ── */
        .product-card { position: relative; }

        .out-of-stock-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #e53935;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            z-index: 2;
            pointer-events: none;
        }

        .product-card.out-of-stock img         { opacity: 0.4; filter: grayscale(60%); }
        .product-card.out-of-stock .product-name { color: #555; }

        .cart-icon-disabled {
            color: #333 !important;
            cursor: not-allowed !important;
            pointer-events: none;
        }
    </style>
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/product-details.css?v=20260919-details">
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/catalog-filters.css?v=20260926-teal">
<script defer src="../js/catalog-filters.js?v=20260924-final"></script>
</head>
<body class="caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<!-- NAVBAR -->
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<!-- SEARCH BAR -->
<div class="search-bar-overlay" id="search-bar-overlay" role="search" aria-label="Search products">
    <i class="fas fa-search" aria-hidden="true"></i>
    <input type="search" id="productSearch" oninput="applyFilters()" placeholder="Search products..." aria-label="Search products" autocomplete="off">
    <button type="button" class="caz-search-close" onclick="toggleSearch()" aria-label="Close search"><i class="fas fa-times" aria-hidden="true"></i></button>
</div>

<main class="cctv-page">

    <p class="catalog-eyebrow">CAZ / Energy &amp; security</p>
    <h1 class="product-page-title"><?php echo $current_category; ?></h1>
    <p class="catalog-description">Find the right equipment for a safer, smarter space. Explore our products below.</p>

    <!-- ── Filter Dropdowns — all dynamic from DB ── -->
    <section class="catalog-filters" aria-label="Product filters">
    <div class="catalog-filters-heading"><h2>Find your perfect fit</h2><span class="catalog-filter-count" role="status" aria-live="polite"></span></div>
    <div class="filter-row">

        <div class="filter-field"><label for="filterType">Product type</label><select id="filterType" class="filter-select" onchange="applyFilters()">
            <option value="">All Types</option>
            <?php foreach ($types as $type): ?>
                <option value="<?php echo strtolower(htmlspecialchars($type)); ?>">
                    <?php echo htmlspecialchars($type); ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="filter-field"><label for="filterSpec">Power / watts</label><select id="filterSpec" class="filter-select" onchange="applyFilters()">
            <option value=""><?php echo $current_category === 'CCTV' ? 'All MP' : 'All Watts'; ?></option>
            <?php foreach ($specs as $spec): ?>
                <option value="<?php echo strtolower(htmlspecialchars($spec)); ?>">
                    <?php echo htmlspecialchars($spec); ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="filter-field"><label for="filterBrand">Brand</label><select id="filterBrand" class="filter-select" onchange="applyFilters()">
            <option value="">All Brands</option>
            <?php foreach ($brands as $brand): ?>
                <option value="<?php echo strtolower(htmlspecialchars($brand)); ?>">
                    <?php echo htmlspecialchars($brand); ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="filter-field"><label for="filterStock">Availability</label><select id="filterStock" class="filter-select" onchange="applyFilters()">
            <option value="">All Stock</option>
            <option value="instock">In Stock</option>
            <option value="outofstock">Out of Stock</option>
        </select></div>

        <button class="filter-reset" onclick="resetFilters()">Clear filters</button>
    </div>

    </section>
    <p id="catalog-filter-empty" class="catalog-empty" hidden>No products match these filters. Try another option or clear the filters.</p>
    <!-- ── Product Grid ── -->
    <div class="product-grid" id="product-grid">
        <?php
        if ($product_result && mysqli_num_rows($product_result) > 0) {
            while ($row = mysqli_fetch_assoc($product_result)) {
                $id           = $row['id'];
                $name         = addslashes($row['product_name']);
                $img          = $row['image'];
                $price        = $row['price'];
                $nameLC       = strtolower($row['product_name']);
                $brand        = strtolower($row['brand']);
                $type         = strtolower($row['type']  ?? '');
                $spec         = strtolower($row['spec']  ?? '');
                // FIXED: read from products.stock directly
                $stock        = (int)$row['stock'];
                $is_out       = $stock <= 0;
                $card_class   = 'product-card' . ($is_out ? ' out-of-stock' : '');
                $stock_status = $is_out ? 'outofstock' : 'instock';

                echo '
                <div class="' . $card_class . '"
                     data-name="'  . $nameLC      . '"
                     data-brand="' . $brand        . '"
                     data-type="'  . $type         . '"
                     data-spec="'  . $spec         . '"
                     data-stock="' . $stock_status . '">';

                if ($is_out) {
                    echo '<div class="out-of-stock-badge">Out of Stock</div>';
                }

                echo '
                    <a href="product.php?id=' . $id . '&from=' . strtolower($current_category) . '" class="product-link">
                        <span class="caz-product-image-frame"><img src="../images/' . $img . '" alt="' . htmlspecialchars($row['product_name']) . '"></span>
                        <h3 class="product-name">' . htmlspecialchars($row['product_name']) . '</h3>
                        <div class="stock-count" style="font-size:13px;color:' . ($stock <= 3 ? '#e53935' : '#555') . ';font-family:\'JetBrains Mono\',monospace;margin-top:4px;letter-spacing:0.5px;">'
                    . ($is_out ? 'Out of Stock' : $stock . ' in stock') .
                    '</div>
                    </a>' . caz_render_product_facts($row, true) . '
                    <a class="caz-product-details-link" href="product.php?id=' . (int)$id . '&amp;from=solar" aria-label="View details for ' . htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') . '">View full details <span aria-hidden="true">→</span></a>
                    <div class="product-bottom">
                        <span class="price">₱' . number_format($price, 2) . '</span>';

                if ($is_out) {
                    echo '<i class="fas fa-shopping-cart cart-icon-disabled" title="Out of Stock"></i>';
                } else {
                    echo '<i class="fas fa-shopping-cart" onclick="quickAdd(' . $id . ', \'' . $name . '\', ' . $price . ', \'' . $img . '\', \'' . $current_category . '\', ' . $stock . ')" title="Add to Cart"></i>';
                }

                echo '
                    </div>
                </div>';
            }
        } else {
            echo "<p class='mono' style='grid-column:1/-1;text-align:center;color:#444;'>NO PRODUCTS FOUND</p>";
        }
        ?>
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
            class="btn-red"
            style="display:none;margin-top:6px;width:70%;">
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

<script src="../js/cart.js?v=20260920-shopping"></script>
<script src="../js/ui-commponents.js?v=20261013-account"></script>
<script src="../js/ui-handlers.js?v=20261013-account"></script>

<script>
    function quickAdd(productId, productName, productPrice, productImg, productCategory, productStock) {
    cazAlert.cartModal({
        id:       productId,
        title:    productName,
        price:    productPrice,
        image:    productImg,
        category: productCategory,
        stock:    productStock
    });
}

    function toggleSearch() {
        const bar = document.getElementById('search-bar-overlay');
        bar.classList.toggle('active');
        if (bar.classList.contains('active')) {
            document.getElementById('productSearch').focus();
        } else {
            document.getElementById('productSearch').value = '';
            applyFilters();
            document.querySelector('[data-caz-product-search]')?.focus();
        }
    }

    function applyFilters() {
        const search = (document.getElementById('productSearch')?.value || '').toLowerCase().trim();
        const type   = (document.getElementById('filterType')?.value   || '').toLowerCase();
        const spec   = (document.getElementById('filterSpec')?.value   || '').toLowerCase();
        const brand  = (document.getElementById('filterBrand')?.value  || '').toLowerCase();
        const stock  = (document.getElementById('filterStock')?.value  || '').toLowerCase();

        document.querySelectorAll('.product-card').forEach(card => {
            const matchSearch = !search || (card.dataset.name  || '').includes(search);
            const matchType   = !type   || (card.dataset.type  || '').includes(type);
            const matchSpec   = !spec   || (card.dataset.spec  || '').includes(spec);
            const matchBrand  = !brand  || (card.dataset.brand || '').includes(brand);
            const matchStock  = !stock  || (card.dataset.stock || '') === stock;

            card.classList.toggle('hidden',
                !(matchSearch && matchType && matchSpec && matchBrand && matchStock)
            );
        });
    }

    function resetFilters() {
        ['filterType', 'filterSpec', 'filterBrand', 'filterStock'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const search = document.getElementById('productSearch');
        if (search) search.value = '';
        applyFilters();
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

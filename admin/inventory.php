<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/feature_bootstrap.php'; 

// ── Dynamic filters from DB ─────────────────────────────────────
function get_distinct($conn, $col, $category) {
    $result = mysqli_query($conn, "SELECT DISTINCT `$col` FROM products WHERE category = '$category' AND `$col` != '' ORDER BY `$col` ASC");
    $vals = [];
    while ($r = mysqli_fetch_assoc($result)) $vals[] = $r[$col];
    return $vals;
}

$cctv_brands  = get_distinct($conn, 'brand', 'CCTV');
$cctv_types   = get_distinct($conn, 'type',  'CCTV');
$cctv_specs   = get_distinct($conn, 'spec',  'CCTV');

$solar_brands = get_distinct($conn, 'brand', 'Solar');
$solar_types  = get_distinct($conn, 'type',  'Solar');
$solar_specs  = get_distinct($conn, 'spec',  'Solar');

// Fetch products from database
$cctv_result  = mysqli_query($conn, "SELECT * FROM products WHERE category = 'CCTV'  ORDER BY id DESC");
$solar_result = mysqli_query($conn, "SELECT * FROM products WHERE category = 'Solar' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .inventory-delete-form { margin:0; display:flex; }
        body.caz-admin .inventory-delete-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:40px; padding:9px 13px; border:1px solid #ed98aa77; border-radius:9px; background:rgba(180,42,68,.14); color:#ffc4d0; font:inherit; font-size:14px; cursor:pointer; transition:background .2s,transform .2s; }
        body.caz-admin .inventory-delete-button:hover { background:rgba(190,56,80,.28); transform:translateY(-1px); }
        body.caz-admin .inventory-delete-button:focus-visible { outline:2px solid #9ee9e7; outline-offset:3px; }
        /* ── Out of Stock Badge ── */
        .product-item { position: relative; }

        .oos-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #e53935;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 3px 8px;
            z-index: 2;
            pointer-events: none;
        }

        .product-item.out-of-stock img {
            opacity: 0.4;
            filter: grayscale(60%);
        }

        .product-item.out-of-stock h3 {
            color: #888;
        }

        .stock-zero {
            color: #e53935;
            font-weight: bold;
        }

        .product-item.hidden { display: none !important; }

        /* ── Filter Row ── */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0 14px;
            align-items: center;
        }

        .filter-select {
            appearance: none;
            -webkit-appearance: none;
            background: #0a0a0a url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23555'/%3E%3C/svg%3E") no-repeat right 10px center;
            border: 1px solid #222;
            padding: 7px 28px 7px 12px;
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            color: #aaa;
            cursor: pointer;
            min-width: 110px;
            transition: border-color .2s, color .2s;
            border-radius: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .filter-select:focus { outline: none; border-color: #e53935; color: #fff; }
        .filter-select:hover { border-color: #444; color: #fff; }

        .filter-reset {
            background: none;
            border: 1px solid #222;
            padding: 7px 13px;
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            cursor: pointer;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all .2s;
            border-radius: 0;
        }
        .filter-reset:hover { border-color: #e53935; color: #e53935; }
    </style>
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/inventory-controls.css?v=20260911">
<script defer src="../js/inventory-controls.js?v=20260911"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
</head>
<body class="caz-admin caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<?php include __DIR__ . '/../includes/navigation.php'; ?>

    <div class="inventory-wrapper"><div class="caz-page-heading"><div><span class="caz-eyebrow">Store management</span><h1>Inventory</h1><p>Manage your CCTV and solar collection.</p></div></div>
        
        <!-- ══════════════════ CCTV SECTION ══════════════════ -->
        <div class="inventory-section">
            <div class="section-header">
                <div class="header-main">
                    <h2>CCTV</h2>
                    <div class="header-controls">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="cctvSearch" oninput="applyInventoryFilters('cctv')" placeholder="Search CCTV..." aria-label="Search CCTV products">
                        </div>
                        <button class="btn-add-header" onclick="window.location.href='addproduct.php'">Add Product</button>
                    </div>
                </div>

                <!-- ── CCTV Filter Row ── -->
                <div class="filter-row">
                    <select id="cctv-filterType" class="filter-select" onchange="applyInventoryFilters('cctv')">
                        <option value="">All Types</option>
                        <?php foreach ($cctv_types as $t): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($t)); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="cctv-filterSpec" class="filter-select" onchange="applyInventoryFilters('cctv')">
                        <option value="">All MP</option>
                        <?php foreach ($cctv_specs as $s): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($s)); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="cctv-filterBrand" class="filter-select" onchange="applyInventoryFilters('cctv')">
                        <option value="">All Brands</option>
                        <?php foreach ($cctv_brands as $b): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($b)); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="cctv-filterStock" class="filter-select" onchange="applyInventoryFilters('cctv')">
                        <option value="">All Stock</option>
                        <option value="instock">In Stock</option>
                        <option value="outofstock">Out of Stock</option>
                    </select>

                    <button class="filter-reset" onclick="resetInventoryFilters('cctv')">✕ Clear</button>
                </div>
            </div>

            <div class="inventory-container scroll-mode" id="cctv-container">
                <?php if (mysqli_num_rows($cctv_result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($cctv_result)):
                        $is_out       = (int)$row['stock'] <= 0;
                        $stock_status = $is_out ? 'outofstock' : 'instock';
                        $type         = strtolower($row['type']  ?? '');
                        $spec         = strtolower($row['spec']  ?? '');
                        $brand        = strtolower($row['brand'] ?? '');
                        $nameLC       = strtolower($row['product_name']);
                    ?>
                        <div class="product-item <?php echo $is_out ? 'out-of-stock' : ''; ?>"
                             data-name="<?php echo caz_h($nameLC); ?>"
                             data-brand="<?php echo caz_h($brand); ?>"
                             data-type="<?php echo caz_h($type); ?>"
                             data-spec="<?php echo caz_h($spec); ?>"
                             data-stock="<?php echo $stock_status; ?>"
                             onclick="location.href='product.php?id=<?php echo $row['id']; ?>'"
                             style="cursor:pointer;">

                            <?php if ($is_out): ?>
                                <div class="oos-badge">Out of Stock</div>
                            <?php endif; ?>

                            <div class="caz-product-image-frame"><img src="../images/<?php echo caz_h($row['image']); ?>" alt="Product"></div>
                            <div class="product-details">
                                <h3><?php echo caz_h($row['product_name']); ?></h3>
                                <p>Brand: <?php echo caz_h($row['brand']); ?></p>
                                <p>Price: ₱<?php echo number_format($row['price'], 2); ?></p>
                                <p>Stock: <span class="<?php echo $is_out ? 'stock-zero' : ''; ?>">
                                    <?php echo $row['stock']; ?>
                                </span></p>
                                <div class="inventory-card-actions"><a href="edit_product.php?id=<?php echo $row['id']; ?>" onclick="event.stopPropagation()"><i class="fas fa-pen" aria-hidden="true"></i> Edit product</a><form class="inventory-delete-form" method="post" action="delete_product.php" onclick="event.stopPropagation()" onsubmit="return confirmInventoryDelete(event, this)">
                                    <input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" class="inventory-delete-button"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                                </form></div>

                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-msg">No CCTV products added.</p>
                <?php endif; ?>
            </div>

            <div class="section-footer">
                <button class="view-toggle-btn" onclick="toggleView('cctv-container', this)">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>

        <!-- ══════════════════ SOLAR SECTION ══════════════════ -->
        <div class="inventory-section">
            <div class="section-header">
                <div class="header-main">
                    <h2>SOLAR</h2>
                    <div class="header-controls">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="solarSearch" oninput="applyInventoryFilters('solar')" placeholder="Search Solar..." aria-label="Search solar products">
                        </div>
                        <button class="btn-add-header" onclick="window.location.href='addproduct.php'">Add Product</button>
                    </div>
                </div>

                <!-- ── Solar Filter Row ── -->
                <div class="filter-row">
                    <select id="solar-filterType" class="filter-select" onchange="applyInventoryFilters('solar')">
                        <option value="">All Types</option>
                        <?php foreach ($solar_types as $t): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($t)); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="solar-filterSpec" class="filter-select" onchange="applyInventoryFilters('solar')">
                        <option value="">All Watts</option>
                        <?php foreach ($solar_specs as $s): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($s)); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="solar-filterBrand" class="filter-select" onchange="applyInventoryFilters('solar')">
                        <option value="">All Brands</option>
                        <?php foreach ($solar_brands as $b): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($b)); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="solar-filterStock" class="filter-select" onchange="applyInventoryFilters('solar')">
                        <option value="">All Stock</option>
                        <option value="instock">In Stock</option>
                        <option value="outofstock">Out of Stock</option>
                    </select>

                    <button class="filter-reset" onclick="resetInventoryFilters('solar')">✕ Clear</button>
                </div>
            </div>

            <div class="inventory-container scroll-mode" id="solar-container">
                <?php if (mysqli_num_rows($solar_result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($solar_result)):
                        $is_out       = (int)$row['stock'] <= 0;
                        $stock_status = $is_out ? 'outofstock' : 'instock';
                        $type         = strtolower($row['type']  ?? '');
                        $spec         = strtolower($row['spec']  ?? '');
                        $brand        = strtolower($row['brand'] ?? '');
                        $nameLC       = strtolower($row['product_name']);
                    ?>
                        <div class="product-item <?php echo $is_out ? 'out-of-stock' : ''; ?>"
                             data-name="<?php echo caz_h($nameLC); ?>"
                             data-brand="<?php echo caz_h($brand); ?>"
                             data-type="<?php echo caz_h($type); ?>"
                             data-spec="<?php echo caz_h($spec); ?>"
                             data-stock="<?php echo $stock_status; ?>"
                             onclick="location.href='product.php?id=<?php echo $row['id']; ?>'"
                             style="cursor:pointer;">

                            <?php if ($is_out): ?>
                                <div class="oos-badge">Out of Stock</div>
                            <?php endif; ?>

                            <div class="caz-product-image-frame"><img src="../images/<?php echo caz_h($row['image']); ?>" alt="Product"></div>
                            <div class="product-details">
                                <h3><?php echo caz_h($row['product_name']); ?></h3>
                                <p>Brand: <?php echo caz_h($row['brand']); ?></p>
                                <p>Price: ₱<?php echo number_format($row['price'], 2); ?></p>
                                <p>Stock: <span class="<?php echo $is_out ? 'stock-zero' : ''; ?>">
                                    <?php echo $row['stock']; ?>
                                </span></p>
                                <div class="inventory-card-actions"><a href="edit_product.php?id=<?php echo $row['id']; ?>" onclick="event.stopPropagation()"><i class="fas fa-pen" aria-hidden="true"></i> Edit product</a><form class="inventory-delete-form" method="post" action="delete_product.php" onclick="event.stopPropagation()" onsubmit="return confirmInventoryDelete(event, this)">
                                    <input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" class="inventory-delete-button"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                                </form></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-msg">No Solar products added.</p>
                <?php endif; ?>
            </div>

            <div class="section-footer">
                <button class="view-toggle-btn" onclick="toggleView('solar-container', this)">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
    <?php if (!empty($_SESSION['msg'])): ?>
        cazAlert[<?php echo json_encode(in_array($_SESSION['msg']['type'] ?? '', ['success','error','warning','info'], true) ? $_SESSION['msg']['type'] : 'info'); ?>](<?php echo json_encode((string)($_SESSION['msg']['text'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>);
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>
</script>

<script>

function confirmInventoryDelete(event, form) {
    event.preventDefault();
    event.stopPropagation();
    const submit = () => HTMLFormElement.prototype.submit.call(form);
    if (typeof cazAlert !== 'undefined') cazAlert.confirm('Remove this product and its related offers and cart entries? Products already in an order are kept for order history.', submit);
    else if (window.confirm('Remove this product and its related offers and cart entries?')) submit();
    return false;
}

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

/**
 * applyInventoryFilters(section)
 * section = 'cctv' | 'solar'
 * Reads the search input + 4 dropdowns for that section,
 * then shows/hides .product-item cards using data-* attributes.
 */
function applyInventoryFilters(section) {
    const containerId = section + '-container';
    const search = (document.getElementById(section + 'Search')?.value || '').toLowerCase().trim();
    const type   = (document.getElementById(section + '-filterType')?.value  || '').toLowerCase();
    const spec   = (document.getElementById(section + '-filterSpec')?.value  || '').toLowerCase();
    const brand  = (document.getElementById(section + '-filterBrand')?.value || '').toLowerCase();
    const stock  = (document.getElementById(section + '-filterStock')?.value || '').toLowerCase();

    document.querySelectorAll('#' + containerId + ' .product-item').forEach(card => {
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

function resetInventoryFilters(section) {
    ['filterType', 'filterSpec', 'filterBrand', 'filterStock'].forEach(id => {
        const el = document.getElementById(section + '-' + id);
        if (el) {
            el.value = '';
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    const search = document.getElementById(section + 'Search');
    if (search) search.value = '';
    applyInventoryFilters(section);
}

function filterInventory(containerId, value) {
    // legacy — kept for safety, delegates to applyInventoryFilters
    const section = containerId.replace('-container', '');
    applyInventoryFilters(section);
}

function toggleView(containerId, btn) {
    const container = document.getElementById(containerId);
    container.classList.toggle('scroll-mode');
    container.classList.toggle('grid-mode');
}
</script>

</body>
</html>

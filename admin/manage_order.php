<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/order_mutation.php';

$order_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$order_id) { header('Location: orders.php'); exit; }
$form_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals(caz_csrf_token(), $csrf)) {
        http_response_code(403);
        $form_error = 'Your form expired. Refresh this page and try again.';
    } else {
        try {
            $new_status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            caz_admin_update_order($conn, $order_id, $new_status);
            header('Location: orders.php?success=1', true, 303);
            exit;
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $form_error = $e->getMessage();
        } catch (DomainException $e) {
            http_response_code(409);
            $form_error = $e->getMessage();
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('CAZ admin order update: ' . $e->getMessage());
            $form_error = 'The order could not be updated. No changes were saved. Please try again.';
        }
    }
}

$query = "SELECT
            o.id, o.user_id, o.order_date, o.total_amount, o.status,
            COALESCE(u.name, 'Deleted User')                        AS customer_name,
            COALESCE(o.shipping_address, u.address, 'N/A')          AS address,
            COALESCE(u.email, 'N/A')                                AS email,
            COALESCE(u.contact_number, 'N/A')                       AS contact_number
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          WHERE o.id = '$order_id'";

$result = mysqli_query($conn, $query);
$order  = mysqli_fetch_assoc($result);
if (!$order) { header("Location: orders.php"); exit(); }
require_once __DIR__ . '/../includes/identity_verification.php';
caz_identity_schema($conn);
$order_verification = caz_identity_details($conn, (int)$order['user_id']);

require_once __DIR__ . '/../includes/delivery_location.php';
caz_locations_schema($conn);
$location_stmt = $conn->prepare('SELECT latitude, longitude FROM caz_order_locations WHERE order_id = ? LIMIT 1');
$location_stmt->bind_param('i', $order_id);
$location_stmt->execute();
$delivery_location = $location_stmt->get_result()->fetch_assoc();
$location_stmt->close();
$delivery_destination = null;
if ($delivery_location) {
    $latitude = (float)$delivery_location['latitude'];
    $longitude = (float)$delivery_location['longitude'];
    if (is_finite($latitude) && is_finite($longitude) && abs($latitude) <= 90 && abs($longitude) <= 180) {
        $delivery_destination = number_format($latitude, 7, '.', '') . ',' . number_format($longitude, 7, '.', '');
    }
}
$delivery_address = trim((string)$order['address']);
$directions_destination = $delivery_destination ?? ($delivery_address !== 'N/A' ? $delivery_address : '');
$delivery_directions_url = 'https://www.google.com/maps/dir/?' . http_build_query([
    'api' => '1', 'destination' => $directions_destination,
    'travelmode' => 'driving', 'dir_action' => 'navigate'
], '', '&', PHP_QUERY_RFC3986);
$delivery_map_url = 'https://www.google.com/maps/search/?' . http_build_query([
    'api' => '1', 'query' => $delivery_destination
], '', '&', PHP_QUERY_RFC3986);

require_once __DIR__ . '/../includes/promotions.php';
caz_promotions_schema($conn);
$items_q = "SELECT oi.quantity, COALESCE(p.product_name, 'Unavailable product') AS product_name, oi.price, p.image,
            cop.title AS promotion_title,cop.benefit AS promotion_benefit
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN caz_order_promotions cop ON cop.order_id=oi.order_id AND cop.product_id=oi.product_id
            WHERE oi.order_id = '$order_id'";
$items_r = mysqli_query($conn, $items_q);
$items   = [];
while ($row = mysqli_fetch_assoc($items_r)) { $items[] = $row; }

$status = strtolower($order['status']);
$is_locked = ($status === 'shipped' || $status === 'canceled');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Order - Caz Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Michroma&family=Space+Grotesk:wght@400;500;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0a0a0a;
            color: #fff;
            font-family: 'Space Grotesk', sans-serif;
            overflow-y: auto;
        }

        .navbar {
            position: fixed;
            top: 0; width: 100%;
            z-index: 9999;
            background: #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            border-bottom: 1px solid #1a1a1a;
            box-sizing: border-box;
        }
        .nav-left { display: flex; align-items: center; gap: 20px; }
        .navbar a { color: #fff; text-decoration: none; font-size: 16px; letter-spacing: 0.5px; }
        .navbar a:hover, .navbar a.active { color: #e53935; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .nav-right i { cursor: pointer; font-size: 16px; color: #fff; }
        .nav-right i:hover { color: #e53935; }

        .back-btn {
            position: fixed;
            top: 72px; right: 20px;
            z-index: 1000;
            width: 36px; height: 36px;
            background: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
        }
        .back-btn:hover { background: #e53935; border-color: #e53935; }

        .manage-wrapper {
            display: flex;
            min-height: calc(100vh - 55px);
            margin-top: 55px;
        }

        /* ── LEFT PANEL ── */
        .order-left {
            width: 40%;
            background: #050505;
            border-right: 1px solid #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            gap: 16px;
        }

        .order-icon-box {
            width: 100%;
            max-width: 320px;
            aspect-ratio: 1;
            background: #0a0a0a;
            border: 1px solid #1a1a1a;
            overflow: hidden;
            position: relative;
        }
        .order-icon-box.single img {
            width: 100%; height: 100%;
            object-fit: cover; display: block;
        }
        .order-icon-box.fallback {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 10px;
        }
        .order-icon-box.fallback i    { font-size: 64px; color: #1a1a1a; }
        .order-icon-box.fallback span { font-family:'JetBrains Mono',monospace; font-size:12px; color:#333; letter-spacing:1px; }
        .order-icon-box.multi {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 2px;
        }
        .order-icon-box.multi .img-cell { overflow: hidden; position: relative; }
        .order-icon-box.multi .img-cell img { width:100%; height:100%; object-fit:cover; display:block; }
        .order-icon-box.multi .img-cell.more-overlay::after {
            content: attr(data-more);
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.65);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Michroma', sans-serif;
            font-size: 20px; color: #fff;
        }

        .order-id-label {
            font-family: 'Michroma', sans-serif;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #fff;
        }
        .order-date-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px; color: #555; letter-spacing: 1px;
        }

        .status-badge { padding: 5px 16px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-family: 'JetBrains Mono', monospace; }
        .status-badge.pending  { background: rgba(255,152,0,0.05);  color:#ff9800; border:1px solid rgba(255,152,0,0.3); }
        .status-badge.approved { background: rgba(76,175,80,0.05);  color:#4caf50; border:1px solid rgba(76,175,80,0.3); }
        .status-badge.shipped  { background: rgba(33,150,243,0.05); color:#2196f3; border:1px solid rgba(33,150,243,0.3); }
        .status-badge.canceled { background: rgba(244,67,54,0.05);  color:#f44336; border:1px solid rgba(244,67,54,0.3); }

        /* ── RIGHT PANEL ── */
        .order-right {
            flex: 1;
            padding: 50px 50px 60px;
            display: flex;
            flex-direction: column;
            gap: 0;
            overflow-y: auto;
        }

        .section-title {
            font-family: 'Michroma', sans-serif;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #fff;
            margin-bottom: 30px;
        }

        .field-label {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #aaa;
            margin-bottom: 8px;
        }

        .field-value {
            width: 100%;
            background: #020202;
            border: 1px solid #1a1a1a;
            padding: 12px 15px;
            color: #888;
            font-size: 14px;
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 20px;
            cursor: default;
        }

        .field-row { display: flex; gap: 20px; }
        .field-row .field-group { flex: 1; }
        .field-group { display: flex; flex-direction: column; }

        /* ── ITEMS LIST ── */
        .items-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }

        .item-card {
            display: flex; align-items: center; gap: 14px;
            background: #020202; border: 1px solid #1a1a1a;
            padding: 10px 14px; transition: border-color 0.2s;
        }
        .item-card:hover { border-color: #2a2a2a; }

        .item-thumb {
            width: 52px; height: 52px; flex-shrink: 0;
            background: #0d0d0d; border: 1px solid #1a1a1a;
            overflow: hidden; display: flex;
            align-items: center; justify-content: center;
        }
        .item-thumb img { width:100%; height:100%; object-fit:cover; }
        .item-thumb i   { font-size: 20px; color: #222; }

        .item-info { flex: 1; min-width: 0; }
        .item-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 13px; font-weight: 500; color: #ccc;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .item-price { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #555; margin-top: 3px; }

        .item-qty {
            font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #888;
            background: #0d0d0d; border: 1px solid #1a1a1a;
            padding: 4px 10px; flex-shrink: 0;
        }
        .item-subtotal {
            font-family: 'JetBrains Mono', monospace; font-size: 12px;
            color: #e53935; flex-shrink: 0; min-width: 80px; text-align: right;
        }

        /* ── STATUS SELECT ── */
        .status-select {
            width: 100%; padding: 12px 15px;
            background: #020202; color: #fff;
            border: 1px solid #1a1a1a;
            font-size: 14px; font-family: 'Space Grotesk', sans-serif;
            margin-bottom: 14px; appearance: none;
            cursor: pointer; outline: none; transition: border-color 0.3s;
        }
        .status-select:focus { border-color: #e53935; }
        .status-select:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .update-btn {
            width: 100%; padding: 15px 20px;
            background: #e53935; color: #fff; border: none;
            font-size: 13px; font-weight: bold; cursor: pointer;
            text-transform: uppercase; letter-spacing: 2px;
            font-family: 'Space Grotesk', sans-serif;
            transition: background 0.2s;
        }
        .update-btn:hover { background: #ff3333; }

        /* ── LOCKED NOTICE ── */
        .locked-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-left: 3px solid #555;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #555;
            margin-top: 4px;
        }
        .locked-notice.shipped { border-left-color: #2196f3; color: #2196f3; }
        .locked-notice.canceled { border-left-color: #f44336; color: #f44336; }
        .locked-notice i { font-size: 14px; flex-shrink: 0; }

        /* ── PROFILE OVERLAY ── */
        .profile-backdrop {
            position: fixed; top:0; left:0;
            width:100%; height:100%;
            background: rgba(0,0,0,0.7);
            display: none; z-index: 999;
        }
        .profile-backdrop.active { display: block; }

        .profile-overlay {
            position: fixed;
            top: 0; right: -400px;
            width: 320px; height: 100%;
            background: #0a0a0a;
            border-left: 1px solid #1a1a1a;
            padding: 40px 30px;
            transition: 0.3s ease-in-out;
            z-index: 10001;
            overflow-y: auto;
        }
        .profile-overlay.active { right: 0; }

        .profile-overlay h1 {
            font-family: 'Michroma', sans-serif;
            font-size: 20px; margin-bottom: 20px; color: #fff;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .profile-field { margin-bottom: 20px; }
        .profile-field label {
            display: block; color: #555;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 6px;
        }
        .profile-field span {
            display: block; font-size: 14px; color: #fff;
            background: #050505; border: 1px solid #141414;
            padding: 10px 14px;
        }

        .btn-red {
            width: 100%; height: 46px;
            background: transparent; color: #e53935;
            border: 1px solid #1a1a1a;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.5px;
            cursor: pointer; transition: all 0.2s ease;
            margin-top: 10px;
        }
        .btn-red:hover { background: #e53935; border-color: #e53935; color: #fff; }
    </style>
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping"><script defer src="../js/refined.js?v=20260910-release"></script><link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/order-details.css?v=20260911-order-cards">
<link rel="stylesheet" href="../css/delivery-location.css?v=20260921-location">
<script defer src="../js/delivery-location.js?v=20260921-location"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../css/admin-order.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/admin-order.css'); ?>">
</head>
<body class="caz-admin caz-order-details caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php include __DIR__ . '/../includes/navigation.php'; ?>

<main class="order-page">
<a href="orders.php" class="back-btn" title="Back to Orders">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to orders
</a>

<?php if ($form_error !== ''): ?>
<div class="order-form-error" role="alert"><?php echo caz_h($form_error); ?></div>
<?php endif; ?>
<form method="POST" id="caz-order-manage-form" onsubmit="return confirmUpdate(event)">
<input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
<div class="manage-wrapper">

    <!-- LEFT: image panel -->
    <div class="order-left">
        <?php
        $imgs      = array_values(array_filter(array_column($items, 'image')));
        $img_count = count($imgs);

        if ($img_count === 0): ?>
            <div class="order-icon-box fallback">
                <i class="fas fa-box-open"></i>
                <span>ORDER #CZ-<?php echo $order['id']; ?></span>
            </div>
        <?php elseif ($img_count === 1): ?>
            <div class="order-icon-box single">
                <img src="../images/<?php echo htmlspecialchars($imgs[0]); ?>" alt="Product"
                     onerror="this.parentElement.classList.add('fallback');this.parentElement.innerHTML='<i class=\'fas fa-box-open\' aria-label=\'Product image unavailable\'></i>'">
            </div>
        <?php else: ?>
            <div class="order-icon-box multi">
                <?php
                $show = min($img_count, 4);
                for ($i = 0; $i < $show; $i++):
                    $is_last = ($i === 3 && $img_count > 4);
                    $extra   = $img_count - 4;
                ?>
                <div class="img-cell <?php echo $is_last ? 'more-overlay' : ''; ?>"
                     <?php echo $is_last ? 'data-more="+' . $extra . '"' : ''; ?>>
                    <img src="../images/<?php echo htmlspecialchars($imgs[$i]); ?>"
                         alt="Product" onerror="this.style.display='none'">
                </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="order-id-label">Order #CZ-<?php echo $order['id']; ?></div>
        <div class="order-date-label"><?php echo date('M d, Y — H:i', strtotime($order['order_date'])); ?></div>
        <span class="status-badge <?php echo $status; ?>"><?php echo $order['status']; ?></span>
    </div>

    <!-- RIGHT: info fields -->
    <div class="order-right">
        <h1 class="section-title">Order information</h1>

        <?php if ($order_verification): ?>
        <div class="field-group">
            <div class="field-label">Order verification contact · <?php echo htmlspecialchars($order_verification['status']); ?></div>
            <div class="field-value"><?php echo htmlspecialchars($order_verification['contact_name']); ?> · <?php echo htmlspecialchars($order_verification['contact_phone']); ?></div>
        </div>
        <?php endif; ?>
        <!-- Customer Name + Customer ID -->
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">Customer Name</div>
                <div class="field-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
            </div>
            <div class="field-group">
                <div class="field-label">Customer ID</div>
                <div class="field-value">#<?php echo htmlspecialchars($order['user_id']); ?></div>
            </div>
        </div>

        <!-- Email + Contact -->
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">Email</div>
                <div class="field-value"><?php echo htmlspecialchars($order['email']); ?></div>
            </div>
            <div class="field-group">
                <div class="field-label">Contact Number</div>
                <div class="field-value"><?php echo htmlspecialchars($order['contact_number']); ?></div>
            </div>
        </div>

        <!-- Address + Total -->
        <div class="field-row">
            <div class="field-group">
                <div class="field-label">Address</div>
                <div class="field-value"><?php echo htmlspecialchars($order['address']); ?></div>
            </div>
            <div class="field-group">
                <div class="field-label">Total Amount</div>
                <div class="field-value order-total">₱<?php echo number_format($order['total_amount'], 2); ?></div>
            </div>
        </div>

        <section class="delivery-location-card" aria-labelledby="delivery-location-heading">
            <div class="delivery-location-heading">
                <span class="delivery-location-icon" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                <div>
                    <h2 id="delivery-location-heading">Delivery location</h2>
                    <p class="delivery-location-help"><?php echo $delivery_destination !== null ? 'The delivery pin selected by the customer for this order.' : 'No exact delivery pin was saved for this order.'; ?></p>
                </div>
            </div>
            <?php if ($delivery_destination !== null): ?>
                <div class="delivery-map-preview" data-latitude="<?php echo htmlspecialchars((string)$latitude, ENT_QUOTES, 'UTF-8'); ?>" data-longitude="<?php echo htmlspecialchars((string)$longitude, ENT_QUOTES, 'UTF-8'); ?>" role="region" aria-label="Customer delivery location map">
                    <p class="delivery-location-help">Loading the delivery map… You can also open the saved pin in Google Maps below.</p>
                </div>
                <p class="delivery-location-coordinates"><strong>Saved coordinates</strong><span><?php echo htmlspecialchars(str_replace(',', ', ', $delivery_destination), ENT_QUOTES, 'UTF-8'); ?></span></p>
                <div class="delivery-location-actions">
                    <a class="delivery-location-link delivery-location-link-primary" href="<?php echo htmlspecialchars($delivery_directions_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-route" aria-hidden="true"></i> Get directions in Google Maps <span class="delivery-external-label">↗</span></a>
                    <a class="delivery-location-link" href="<?php echo htmlspecialchars($delivery_map_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View saved pin <span class="delivery-external-label">↗</span></a>
                </div>
                <p class="delivery-location-help delivery-directions-note">Google Maps opens in a new tab or its app. Allow location access there to use your current position as the starting point, or enter a starting address.</p>
            <?php else: ?>
                <div class="delivery-location-empty"><i class="fas fa-map-location-dot" aria-hidden="true"></i><p>Use the written delivery address below for this order. An address search may be approximate.</p></div>
                <?php if ($directions_destination !== ''): ?>
                <p class="delivery-location-address"><?php echo htmlspecialchars($delivery_address, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="delivery-location-actions"><a class="delivery-location-link" href="<?php echo htmlspecialchars($delivery_directions_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-route" aria-hidden="true"></i> Directions using written address <span class="delivery-external-label">↗</span></a></div>
                <?php else: ?>
                <p class="delivery-location-help">No delivery address is available. Contact the customer before arranging delivery.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Items -->
        <div class="field-group">
            <div class="field-label">
                Items Ordered
                <span class="order-items-count">(<?php echo count($items); ?>)</span>
            </div>
            <div class="items-list">
                <?php foreach ($items as $item):
                    $subtotal = $item['quantity'] * $item['price'];
                ?>
                <div class="item-card">
                    <div class="item-thumb">
                        <?php if (!empty($item['image'])): ?>
                            <img src="../images/<?php echo htmlspecialchars($item['image']); ?>"
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                 onerror="this.parentElement.innerHTML='<i class=\'fas fa-box\' style=\'font-size:20px;color:#222;\'></i>'">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="item-price">₱<?php echo number_format($item['price'], 2); ?> / unit</div>
                        <?php if (!empty($item['promotion_benefit'])): ?>
                        <div class="order-promotion-benefit">
                            <strong><?php echo htmlspecialchars($item['promotion_title'],ENT_QUOTES,'UTF-8'); ?></strong><br>
                            <?php echo htmlspecialchars($item['promotion_benefit'],ENT_QUOTES,'UTF-8'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-qty">x<?php echo (int)$item['quantity']; ?></div>
                    <div class="item-subtotal">₱<?php echo number_format($subtotal, 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Update Status -->
        <div class="field-group">
            <label class="field-label" for="order-status">Update status</label>

            <?php if ($is_locked): ?>
                <!-- Show locked dropdown (read-only) -->
                <select id="order-status" class="status-select" disabled>
                    <option selected><?php echo ucfirst($status); ?></option>
                </select>
                <div class="locked-notice <?php echo $status; ?>">
                    <i class="fas fa-lock"></i>
                    <?php if ($status === 'shipped'): ?>
                        This order has already been shipped and cannot be modified.
                    <?php elseif ($status === 'canceled'): ?>
                        This order has been canceled and cannot be modified.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <select id="order-status" name="status" class="status-select" required>
                    <?php if ($status === 'pending'): ?><option value="pending" selected>Pending</option><?php endif; ?>
                    <option value="approved" <?php echo $status==='approved' ? 'selected':''; ?>>Approved</option>
                    <option value="shipped"  <?php echo $status==='shipped'  ? 'selected':''; ?>>Shipped</option>
                    <option value="canceled" <?php echo $status==='canceled' ? 'selected':''; ?>>Canceled</option>
                </select>
                <button type="submit" class="update-btn">
                    <i class="fas fa-check" style="margin-right:8px;"></i>Update Status
                </button>
            <?php endif; ?>
        </div>

    </div>
</div>
</form>
</main>

<!-- PROFILE -->
<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const preview = document.querySelector('.delivery-map-preview[data-latitude]');
    if (preview && window.CAZDeliveryMap) {
        Promise.resolve(window.CAZDeliveryMap.mountPreview(preview, {
            latitude: Number(preview.dataset.latitude),
            longitude: Number(preview.dataset.longitude)
        })).catch(function () {
            preview.textContent = 'The map preview is unavailable. Open the saved pin or directions in Google Maps below.';
        });
    }
});



function confirmUpdate(e) {
    e.preventDefault();
    const select = document.querySelector('select[name="status"]');
    if (!select) return false; // locked, do nothing
    const newStatus = select.options[select.selectedIndex].text;
    const orderId   = '<?php echo $order["id"]; ?>';
    cazAlert.confirm(
        'Update Order #CZ-' + orderId + ' to "' + newStatus + '"? This will be logged.',
        () => { document.getElementById('caz-order-manage-form').submit(); }
    );
}
</script>

<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
    <?php if (isset($_GET['success'])): ?>
        cazAlert.success('Order status updated successfully!');
    <?php endif; ?>
</script>

</body>
</html>


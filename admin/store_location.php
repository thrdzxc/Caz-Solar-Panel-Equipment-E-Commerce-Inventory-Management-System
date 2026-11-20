<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(true);
require_once __DIR__ . '/../includes/store_location.php';
$store = caz_store_location($conn);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Store location - Caz Admin</title>
<link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping"><link rel="stylesheet" href="../css/experience.css?v=20260921-location">
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal"><link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css"><link rel="stylesheet" href="../css/store-location.css?v=20260926-store">
<script defer src="../assets/vendor/leaflet/leaflet.js"></script><script defer src="../js/store-location.js?v=20260926-store"></script>
<script defer src="../js/experience.js?v=20260926-teal"></script>
</head><body class="caz-admin caz-themed">
<?php require __DIR__ . '/../includes/background.php'; require __DIR__ . '/../includes/navigation.php'; ?>
<main class="store-settings" data-store-config="<?php echo caz_h(json_encode($store + ['admin'=>true,'endpoint'=>'store_location_api.php','csrf'=>caz_csrf_token()])); ?>">
<header class="caz-page-heading"><div><p class="caz-eyebrow">CAZ / Store settings</p><h1>Help customers find your store</h1><p>Place the pin at your entrance, then save it to publish directions on the Contact page.</p></div></header>
<form class="store-editor" id="store-location-form" action="store_location_api.php" method="post">
<section class="store-map-card"><div class="store-map-heading"><h2>Store entrance</h2><span class="store-pin-state"><?php echo caz_store_has_pin($store) ? 'Published pin' : 'Pin not set'; ?></span></div>
<div id="store-map" class="store-map" role="region" tabindex="0" aria-label="Store map: click to place a pin, then drag to adjust"><p class="store-map-fallback">Loading map… You can also enter coordinates in the form.</p></div>
<div class="store-map-tools"><button type="button" data-pin-center>Place pin at map center</button><button type="button" data-pin-gps>Use my current location</button></div>
<p class="store-map-note">The starting view shows the Balintocatoc area only. Click your store entrance, drag the pin, or enter coordinates. No location is published until you save.</p>
<p class="store-map-status" role="status" aria-live="polite"></p></section>
<section class="store-form-card"><h2>Location details</h2><label for="store-address">Business address</label><textarea id="store-address" name="address" maxlength="255" required rows="3"><?php echo caz_h($store['address']); ?></textarea>
<div class="store-coordinates"><div><label for="store-latitude">Latitude</label><input id="store-latitude" name="latitude" type="number" step="any" min="-90" max="90" required value="<?php echo caz_h($store['latitude'] ?? ''); ?>"></div><div><label for="store-longitude">Longitude</label><input id="store-longitude" name="longitude" type="number" step="any" min="-180" max="180" required value="<?php echo caz_h($store['longitude'] ?? ''); ?>"></div></div>
<input type="hidden" name="csrf" value="<?php echo caz_h(caz_csrf_token()); ?>">
<p class="store-map-note">You can copy coordinates from a dropped Google Maps pin. Confirm that the pin is at the correct entrance before saving.</p>
<button class="store-save" type="submit">Save store location</button><p class="store-save-status" role="status" aria-live="polite"></p>
<a class="store-directions" data-store-directions href="<?php echo caz_h(caz_store_directions($store)); ?>" target="_blank" rel="noopener noreferrer"><?php echo caz_store_has_pin($store) ? 'Preview published directions' : 'Find the listed address in Google Maps'; ?></a>
</section></form></main>
<?php require __DIR__ . '/../includes/admin_profile.php'; ?>
<script src="../js/caz-alert.js?v=20260921-location"></script>
</body></html>

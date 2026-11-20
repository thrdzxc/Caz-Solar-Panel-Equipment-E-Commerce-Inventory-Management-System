<?php
// Shared account drawer; include once on authenticated admin pages.
$cazAccountName = htmlspecialchars((string)($_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="../css/admin-account.css?v=20260927-location">
<div class="profile-backdrop admin-account-backdrop" id="profile-backdrop" onclick="toggleProfile()" aria-hidden="true"></div>
<aside id="profile-panel" class="profile-overlay admin-account-panel" role="dialog" aria-modal="true" aria-labelledby="admin-account-title" aria-hidden="true" inert>
    <div class="drawer-title-row admin-account-heading">
        <h2 id="admin-account-title">Your account</h2>
        <button class="drawer-close" type="button" aria-label="Close account" onclick="toggleProfile()">&times;</button>
    </div>
    <div class="admin-account-identity">
        <img src="../images/cazlogo.png" alt="CAZ Solar and CCTV" width="72" height="60">
        <div><span class="admin-account-label">Signed in as</span><strong class="admin-account-name"><?php echo $cazAccountName; ?></strong><span class="admin-account-role">Administrator</span></div>
    </div>
    <div class="admin-account-links" aria-label="Account shortcuts">
        <a href="store_location.php"><span><strong>Store location</strong><small>Set the pin customers use for directions</small></span><span aria-hidden="true">&rarr;</span></a>
        <a href="index.php"><span><strong>Management overview</strong><small>Return to your store dashboard</small></span><span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="admin-account-footer">
        <button type="button" class="admin-account-dismiss" onclick="toggleProfile()">Close</button>
        <button type="button" class="admin-account-logout" onclick="cazAdminLogout()">Log out</button>
    </div>
</aside>
<script defer src="../js/admin-account.js?v=20260927-location-ready"></script>

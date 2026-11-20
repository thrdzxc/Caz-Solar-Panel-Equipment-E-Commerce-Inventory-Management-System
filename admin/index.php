<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php?role=admin"); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Caz Solar & CCTV</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
</head>
<body class="caz-admin caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<?php include __DIR__ . '/../includes/navigation.php'; ?>

<main class="caz-dashboard"><div class="caz-dashboard-intro"><div><span class="caz-eyebrow">CAZ / Management</span><h1>Your store, at a glance.</h1><p>Everything you need to manage products and take care of customers.</p></div><img src="../images/cazlogo.png" alt="CAZ"></div><div class="caz-management-grid"><a class="caz-management-card" href="inventory.php"><i class="fas fa-box-open" aria-hidden="true"></i><h2>Inventory</h2><p>Organize products, images, pricing and stock.</p><span>Open inventory &rarr;</span></a><a class="caz-management-card" href="orders.php"><i class="fas fa-shopping-bag" aria-hidden="true"></i><h2>Orders</h2><p>Review purchases and manage their progress.</p><span>Open orders &rarr;</span></a><a class="caz-management-card" href="logs.php"><i class="fas fa-list-check" aria-hidden="true"></i><h2>Activity</h2><p>Keep track of customer and account activity.</p><span>Open activity &rarr;</span></a><a class="caz-management-card" href="sales.php"><i class="fas fa-chart-line" aria-hidden="true"></i><h2>Sales</h2><p>View performance and export your reports.</p><span>Open sales &rarr;</span></a><a class="caz-management-card" href="promotions.php"><i class="fas fa-tags" aria-hidden="true"></i><h2>Promotions</h2><p>Create offers and installation benefits.</p><span>Open promotions &rarr;</span></a><a class="caz-management-card" href="messages.php"><i class="fas fa-comments" aria-hidden="true"></i><h2>Messages</h2><p>Read customer questions and send helpful replies.</p><span>Open messages &rarr;</span></a></div></main>
<!-- PROFILE -->
<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script src="../js/caz-alert.js?v=20260921-location"></script>

<script>

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

</script>
</body>
</html>
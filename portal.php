<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caz Store - Portal</title>
    <link rel="stylesheet" href="css/style.css?v=20260910-release">
<link rel="stylesheet" href="css/refined.css?v=20260920-shopping">
<script defer src="js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="css/experience.css?v=20260921-location"><script defer src="js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="css/portal-layout.css?v=20260926-teal">
<link rel="stylesheet" href="css/glass-panels.css?v=20260926-teal">
</head>
<body class="portal-body caz-themed caz-portal">
<?php require __DIR__ . '/includes/background.php'; ?>

    <main class="portal-container" aria-labelledby="portal-title">
        <div class="main-logo">
            <img src="images/cazlogo.png" alt="Caz Solar Panel and Equipment Store">
        </div>
        <div class="portal-intro">
            <p class="portal-eyebrow">Solar energy &amp; smart security</p>
            <h1 id="portal-title">Welcome to CAZ</h1>
            <p>Choose your space to get started.</p>
        </div>
        <nav class="portal-buttons" aria-label="Choose your portal">
            <a href="login.php?role=customer" class="portal-btn portal-customer">Customer</a>
            <a href="login.php?role=admin" class="portal-btn portal-admin">Admin</a>
            <a href="pages/promotions.php" class="portal-btn portal-admin">View promotions</a>
        </nav>
    </main>

</body>
</html>

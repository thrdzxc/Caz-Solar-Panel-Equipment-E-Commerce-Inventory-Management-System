<?php
require_once __DIR__ . '/includes/session.php';
$loginRole = ($_GET['role'] ?? '') === 'admin' ? 'admin' : 'customer';
$loginNext = caz_safe_login_next($_GET['next'] ?? '', $loginRole);
caz_start_portal_session($loginRole === 'admin');
header('Cache-Control: no-store, max-age=0');
if ($loginRole === 'customer') require_once __DIR__ . '/includes/captcha.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Caz Store</title>
    <link rel="stylesheet" href="css/style.css?v=20260910-release">
<link rel="stylesheet" href="css/refined.css?v=20260920-shopping">
<script defer src="js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="css/experience.css?v=20260921-location"><script defer src="js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="css/captcha.css?v=<?= filemtime(__DIR__.'/css/captcha.css') ?>">
<link rel="stylesheet" href="css/promotion-access.css?v=20260929-public">
<script defer src="js/captcha.js?v=20260925-teal"></script>
</head>
<body class="portal-body caz-themed">
<?php require __DIR__ . '/includes/background.php'; ?>

    <div class="login-container">

        <div class="login-card">
            <h1 class="auth-heading"><?php echo ($_GET['role'] ?? 'customer') === 'admin' ? 'Admin login' : 'Customer login'; ?></h1>
            <form action="login_check.php" method="POST">

                <?php if (isset($_GET['error'])) { ?>
                    <p style="color: #ff5252; background: rgba(255,0,0,0.1); padding: 10px; border-radius: 4px; text-align: center; font-size: 14px; margin-bottom: 15px;">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </p>
                <?php } ?>

                <?php if (isset($_GET['success'])) { ?>
                    <p style="color: #4BB543; background: rgba(0,255,0,0.1); padding: 10px; border-radius: 4px; text-align: center; font-size: 14px; margin-bottom: 15px;">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </p>
                <?php } ?>

                <!-- ── ADDED: carry the role through the form so login_check.php knows which portal ── -->
                <input type="hidden" name="role" value="<?php echo $loginRole; ?>">
                <input type="hidden" name="next" value="<?php echo htmlspecialchars($loginNext, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="input-group">
                    <label for="login-username">Username:</label>
                    <input id="login-username" type="text" name="username" placeholder="Enter Username" autocomplete="username" required>
                </div>

                <div class="input-group">
                    <label for="login-password">Password:</label>
                    <input id="login-password" type="password" name="password" placeholder="Enter Password" autocomplete="current-password" required>
                </div>

                <?php if ($loginRole === 'customer') caz_captcha_form('login'); ?>

                <div class="login-action">
                    <button type="submit" class="login-btn">Login</button>
                </div>
            </form>
            <div class="login-promotions">
                <a class="login-promotion-button" href="pages/promotions.php">View promotions</a>
                <p>Explore current CAZ offers. No login needed.</p>
            </div>
        </div>

        <div class="register-link"><a href="portal.php">Choose Customer or Admin portal</a></div>
        <div class="register-link">
            <p>Don't have an account?</p>
            <a href="register.php">Register Now</a>
        </div>
    </div>

</body>
</html>

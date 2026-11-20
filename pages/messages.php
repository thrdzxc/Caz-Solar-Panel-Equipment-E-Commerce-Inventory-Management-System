<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user();
if (($_SESSION['user_role'] ?? '') === 'admin') { header('Location: ../admin/messages.php'); exit; }
require_once __DIR__ . '/../includes/messages.php';
$cazAdmin = false;
?>
<!DOCTYPE html>
<html lang="en" class="caz-messages-html"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Messages | CAZ Solar & CCTV</title>
<link rel="stylesheet" href="../css/style.css?v=20260910-release"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><link rel="stylesheet" href="../css/refined.css?v=20260920-shopping"><link rel="stylesheet" href="../css/experience.css?v=20260921-location"><link rel="stylesheet" href="../css/messages.css?v=20260927-profile-avatars"><script defer src="../js/messages.js?v=<?php echo (int)filemtime(__DIR__ . '/../js/messages.js'); ?>"></script>
<script defer src="../js/experience.js?v=20260926-teal"></script><link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal"><link rel="stylesheet" href="../css/message-glass.css?v=20260926-teal">
<link rel="stylesheet" href="../css/message-layout.css?v=<?php echo (int)filemtime(__DIR__ . '/../css/message-layout.css'); ?>">
</head><body class="caz-customer caz-messages-body caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>
<?php require __DIR__ . '/../includes/navigation.php'; caz_messages_view(false); ?>
<script>
// Keep the promotions shortcut within the heading so it never covers the composer.
(() => {
    const shortcut = document.querySelector('body > .caz-promotion-launcher');
    const heading = document.querySelector('.messages-heading');
    if (shortcut && heading) heading.append(shortcut);
})();
</script>
</body></html>

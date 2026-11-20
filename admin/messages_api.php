<?php
// Keep admin polling and sends on the admin session, even with a customer tab open.
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(true, true);
require __DIR__ . '/../pages/messages_api.php';

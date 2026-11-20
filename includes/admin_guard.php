<?php
require_once __DIR__ . '/session.php';
caz_start_portal_session(true);
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    caz_redirect_to_login(true);
}

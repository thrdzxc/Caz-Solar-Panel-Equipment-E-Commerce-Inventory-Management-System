<?php
// Password recovery is intentionally unavailable. Old bookmarks return to login.
header('Cache-Control: no-store');
header('Location: login.php?role=customer', true, 303);
exit;

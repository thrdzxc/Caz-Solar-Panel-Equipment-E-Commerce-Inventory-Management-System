<?php
// A separate admin route keeps customer and administrator sessions independent.
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(true, true);
require_once __DIR__ . '/../includes/profile_photo.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    caz_json(['success'=>false,'message'=>'Customer photos are read-only here.'],405);
}
$customerId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if ($customerId === false) caz_json(['success'=>false,'message'=>'Choose a valid customer.'],422);
session_write_close();
try {
    caz_profile_photo_response($conn, $customerId);
} catch (Throwable $error) {
    error_log('CAZ admin customer photo: ' . $error->getMessage());
    caz_json(['success'=>false,'message'=>'The customer photo could not be loaded. Please try again.'],500);
}

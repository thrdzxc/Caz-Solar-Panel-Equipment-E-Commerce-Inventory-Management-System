<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(true, true);
require_once __DIR__ . '/../includes/store_location.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') caz_json(['success'=>false,'message'=>'Use the store location form to save a pin.'],405);
caz_check_csrf();
try {
    $store = caz_store_validate($_POST);
} catch (InvalidArgumentException $error) { caz_json(['success'=>false,'message'=>$error->getMessage()],422); }
try {
    caz_store_schema($conn);
    $user = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('INSERT INTO caz_store_location (id,address,latitude,longitude,updated_by) VALUES (1,?,?,?,?) ON DUPLICATE KEY UPDATE address=VALUES(address),latitude=VALUES(latitude),longitude=VALUES(longitude),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
    $stmt->bind_param('sddi',$store['address'],$store['latitude'],$store['longitude'],$user);
    $stmt->execute();
    caz_json(['success'=>true,'message'=>'Store location saved. Customers can now get directions to this pin.','location'=>$store,'directions'=>caz_store_directions($store)]);
} catch (Throwable $error) {
    error_log('CAZ store location save failed: ' . $error->getMessage());
    caz_json(['success'=>false,'message'=>'The location could not be saved. Please try again.'],500);
}

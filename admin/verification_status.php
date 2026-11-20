<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(true, true);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    caz_json(['ok'=>false, 'message'=>'Use GET for verification updates.'], 405);
}
session_write_close();
require_once __DIR__ . '/../includes/identity_verification.php';
try {
    caz_identity_schema($conn);
    // Only a count and opaque revision leave this endpoint, never ID or contact data.
    $rows = $conn->query("SELECT v.user_id,v.status,v.version FROM caz_identity_verifications v JOIN users u ON u.id=v.user_id WHERE u.role<>'admin' ORDER BY v.user_id")->fetch_all(MYSQLI_ASSOC);
    $pending = count(array_filter($rows, static fn($row) => $row['status'] === 'pending'));
    caz_json(['ok'=>true, 'pending'=>$pending, 'revision'=>hash('sha256', json_encode($rows))]);
} catch (Throwable $e) {
    error_log('CAZ verification notification status unavailable.');
    caz_json(['ok'=>false, 'message'=>'Verification updates are temporarily unavailable.'], 503);
}

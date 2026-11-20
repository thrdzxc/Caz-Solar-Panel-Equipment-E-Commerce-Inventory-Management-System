<?php
// This retired endpoint must never read or change account credentials.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success'=>false, 'message'=>'Password reset is not available in this system.']);
exit;

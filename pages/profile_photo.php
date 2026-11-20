<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/profile_photo.php';
caz_require_user(false, true);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    caz_json(['success'=>false,'message'=>'Use GET or POST for profile photos.'],405);
}
if (($_SESSION['user_role'] ?? '') === 'admin') caz_json(['success'=>false,'message'=>'A customer account is required.'],403);
$userId = (int)$_SESSION['user_id'];
if ($method === 'POST') caz_check_csrf();
session_write_close();
try {
    $user = $conn->prepare("SELECT id FROM users WHERE id=? AND role <> 'admin'");
    $user->bind_param('i',$userId);$user->execute();
    $exists = $user->get_result()->fetch_assoc();$user->close();
    if (!$exists) caz_json(['success'=>false,'message'=>'Please sign in to a customer account.'],401);
    caz_profile_photos_schema($conn);
    if ($method === 'GET') {
        caz_profile_photo_response($conn, $userId);
    }
    $action=$_POST['action'] ?? 'upload';
    if ($action === 'remove') {
        $stmt=$conn->prepare('DELETE FROM caz_profile_photos WHERE user_id=?');
        $stmt->bind_param('i',$userId);$stmt->execute();$stmt->close();
        caz_json(['success'=>true,'photo_url'=>null]);
    }
    if ($action !== 'upload') caz_json(['success'=>false,'message'=>'Unknown photo action.'],422);
    $file=$_FILES['photo'] ?? null;
    if (!is_array($file)||!isset($file['error'])||is_array($file['error'])) caz_json(['success'=>false,'message'=>'Choose one profile photo.'],422);
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $tooLarge=in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true);
        caz_json(['success'=>false,'message'=>$tooLarge?'The photo is too large. Choose a smaller image.':'The upload did not finish. Choose the photo again.'],$tooLarge?413:422);
    }
    if (!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])) caz_json(['success'=>false,'message'=>'A valid photo upload is required.'],422);
    $size=filesize($file['tmp_name']);
    // The UI resizes originals (up to 2 MB) to 640px before sending them.
    // Keep each stored photo below this XAMPP installation's 1 MB SQL packet limit.
    if ($size===false||$size<1||$size>768*1024) caz_json(['success'=>false,'message'=>'Use the photo picker to resize this image, or choose a photo under 768 KB.'],413);
    $info=@getimagesize($file['tmp_name']);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=[IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_PNG=>'image/png',IMAGETYPE_WEBP=>'image/webp'];
    if (!$info||!isset($allowed[$info[2]])||$allowed[$info[2]]!==$mime) caz_json(['success'=>false,'message'=>'Choose a valid JPG, PNG or WebP image.'],422);
    if ($info[0]<1||$info[1]<1||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>20000000) caz_json(['success'=>false,'message'=>'This image is too large in dimensions. Choose a smaller photo.'],422);
    $bytes=file_get_contents($file['tmp_name']);
    if ($bytes===false||strlen($bytes)!==$size) throw new RuntimeException('Unable to read uploaded photo');
    $version=bin2hex(random_bytes(16));
    $stmt=$conn->prepare('INSERT INTO caz_profile_photos (user_id,mime_type,image_data,version) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE mime_type=VALUES(mime_type),image_data=VALUES(image_data),version=VALUES(version),updated_at=CURRENT_TIMESTAMP');
    $stmt->bind_param('isss',$userId,$mime,$bytes,$version);$stmt->execute();$stmt->close();
    caz_json(['success'=>true,'photo_url'=>'profile_photo.php?v='.$version]);
} catch (Throwable $error) {
    error_log('CAZ profile photo: '.$error->getMessage());
    caz_json(['success'=>false,'message'=>'Your photo could not be saved or loaded. Please try again.'],500);
}

<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/account_validation.php';
caz_require_user(false, true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    caz_json(['success'=>false, 'message'=>'Use POST to update your profile.'], 405);
}
if (($_SESSION['user_role'] ?? '') === 'admin') caz_json(['success'=>false, 'message'=>'Use a customer account to edit this profile.'], 403);
caz_check_csrf();
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) caz_json(['success'=>false, 'message'=>'Your profile details could not be read.'], 422);
foreach (['password','current_password','new_password'] as $field) {
    if (array_key_exists($field, $data)) caz_json(['success'=>false, 'message'=>'Password changes are not available in this system.'], 422);
}
$values = [];
foreach (['name','address','email','contact'] as $field) {
    if (!is_string($data[$field] ?? null)) caz_json(['success'=>false, 'message'=>'Complete your name, email, contact number and address.'], 422);
    $values[$field] = trim($data[$field]);
    if (!mb_check_encoding($values[$field], 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $values[$field])) {
        caz_json(['success'=>false, 'message'=>'Use readable text for your profile details.'], 422);
    }
}
if ($values['address'] === '' || strlen($values['address']) > 65535) caz_json(['success'=>false, 'message'=>'Enter your shipping address (up to 65,535 bytes).'], 422);
if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 191) caz_json(['success'=>false, 'message'=>'Enter a valid email address, up to 191 characters.'], 422);
$userId = (int)$_SESSION['user_id'];
$transaction = false;
try {
    $conn->begin_transaction(); $transaction = true;
    $current = $conn->prepare("SELECT name,contact_number FROM users WHERE id=? AND role <> 'admin' FOR UPDATE");
    $current->bind_param('i', $userId); $current->execute();
    $user = $current->get_result()->fetch_assoc();
    if (!$user) throw new InvalidArgumentException('Your customer account could not be found. Please sign in again.');
    // Preserve old names/numbers when only an address or email is edited.
    if ($values['name'] !== $user['name'] && !caz_account_name_valid($values['name'])) {
        throw new InvalidArgumentException('Your name must contain letters and spaces only, up to 100 characters.');
    }
    if ($values['contact'] !== $user['contact_number'] && !caz_account_phone_valid($values['contact'])) {
        throw new InvalidArgumentException('Enter exactly 11 digits for your contact number, starting with 09.');
    }
    $duplicate = $conn->prepare('SELECT id FROM users WHERE id<>? AND (name=? OR email=?) LIMIT 1');
    $duplicate->bind_param('iss', $userId, $values['name'], $values['email']); $duplicate->execute();
    if ($duplicate->get_result()->fetch_assoc()) throw new InvalidArgumentException('That username or email is already used by another account.');
    $statement = $conn->prepare('UPDATE users SET name=?,address=?,email=?,contact_number=? WHERE id=?');
    $statement->bind_param('ssssi', $values['name'], $values['address'], $values['email'], $values['contact'], $userId);
    $statement->execute();
    $log = $conn->prepare("INSERT INTO customer_logs (user_id,activity,date) VALUES (?,'Profile details updated',NOW())");
    $log->bind_param('i', $userId); $log->execute();
    $conn->commit(); $transaction = false;
    $_SESSION['user_name'] = $values['name'];
    $_SESSION['user_address'] = $values['address'];
    $_SESSION['user_email'] = $values['email'];
    $_SESSION['user_contact'] = $values['contact'];
    caz_json(['success'=>true, 'message'=>'Profile updated.']);
} catch (Throwable $error) {
    if ($transaction) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    if ($error instanceof InvalidArgumentException) caz_json(['success'=>false, 'message'=>$error->getMessage()], 422);
    if ((int)$error->getCode() === 1062) caz_json(['success'=>false, 'message'=>'That username or email is already used by another account.'], 422);
    error_log('CAZ profile update failed (code ' . (int)$error->getCode() . ').');
    caz_json(['success'=>false, 'message'=>'Your profile could not be saved. Please try again.'], 500);
}
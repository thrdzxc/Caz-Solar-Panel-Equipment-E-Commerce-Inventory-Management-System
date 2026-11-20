<?php
require_once __DIR__ . '/includes/session.php';
caz_start_portal_session(false);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: register.php'); exit;
}

// Keep ordinary form values after an error, but never passwords or answers.
$fields = ['name', 'email', 'contact_number', 'address', 'address_house', 'address_zip',
    'address_street', 'address_barangay', 'address_city', 'address_province',
    'id_type','contact_person_name','contact_person_phone','contact_person_relationship'];
$values = [];
foreach ($fields as $field) $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
$_SESSION['caz_registration_values'] = $values;
function caz_registration_error(string $message): void {
    header('Location: register.php?' . http_build_query(['error' => $message])); exit;
}

require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/account_validation.php';
$captchaError = caz_captcha_verify('register', $_POST['captcha_id'] ?? null, $_POST['captcha_answer'] ?? null);
if ($captchaError !== null) caz_registration_error($captchaError);

$name = $values['name'];
$email = $values['email'];
$contact = $values['contact_number'];
$pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$address = $values['address'];
if ($values['address_street'] !== '') {
    foreach (['address_zip', 'address_street', 'address_barangay', 'address_city', 'address_province'] as $field) {
        if ($values[$field] === '') caz_registration_error('Please complete all required shipping address fields.');
    }
    $parts = array_filter([$values['address_house'], $values['address_street'], 'Brgy. ' . $values['address_barangay'],
        $values['address_city'], $values['address_province'], $values['address_zip']], static fn($part) => $part !== '');
    $address = implode(', ', $parts);
}
if ($name === '' || $pass === '' || $address === '') caz_registration_error('Enter your name, password, and shipping address.');
if (!caz_account_name_valid($name)) caz_registration_error('Your username / full name must contain letters and spaces only, up to 100 characters.');
if (($passwordError = caz_account_password_error($pass)) !== null) caz_registration_error($passwordError);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) caz_registration_error('Enter a valid email address.');
if (!caz_account_phone_valid($contact)) caz_registration_error('Enter exactly 11 digits for your contact number, starting with 09.');
require_once __DIR__ . '/includes/identity_verification.php';
try {
    $identityContact = caz_identity_contact($_POST);
    $identityImage = caz_identity_upload($_FILES['identity_document'] ?? [], $_POST['id_type'] ?? null);
} catch (InvalidArgumentException $error) { caz_registration_error($error->getMessage()); }

// Invalid human verification never reaches authentication or the database.
require __DIR__ . '/db_conn.php';
$transaction=false;
try {
$existing = $conn->prepare('SELECT name, email FROM users WHERE name = ? OR email = ? LIMIT 1');
$existing->bind_param('ss', $name, $email);
$existing->execute();
$user = $existing->get_result()->fetch_assoc();
if ($user) caz_registration_error(strcasecmp($user['name'], $name) === 0 ? 'Username is already taken.' : 'Email is already registered.');
    caz_identity_schema($conn);
    $conn->begin_transaction(); $transaction=true;
    $hashed = password_hash($pass, PASSWORD_BCRYPT);
    $statement = $conn->prepare("INSERT INTO users (name, email, contact_number, password, address, role) VALUES (?, ?, ?, ?, ?, 'customer')");
    $statement->bind_param('sssss', $name, $email, $contact, $hashed, $address);
    $statement->execute();
    caz_identity_save($conn, (int)$conn->insert_id, $identityImage, $identityContact);
    $conn->commit(); $transaction=false;
} catch (Throwable $error) {
    if ($transaction) caz_identity_rollback($conn);
    error_log('CAZ registration could not be saved (code '.(int)$error->getCode().').');
    caz_registration_error('We could not save your registration. Please try again.');
}
unset($_SESSION['caz_registration_values']);
header('Location: login.php?' . http_build_query(['role' => 'customer', 'success' => 'Account created. Sign in to browse while an administrator reviews your ID. Ordering opens after approval.']));
exit;

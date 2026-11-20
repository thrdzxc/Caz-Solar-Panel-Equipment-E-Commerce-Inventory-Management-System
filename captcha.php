<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session.php';
caz_start_portal_session(false);
require_once __DIR__ . '/includes/captcha.php';
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['token'] ?? null;
    $purpose = $_POST['purpose'] ?? null;
    if (!is_string($token) || !isset($_SESSION['caz_captcha_refresh']) || !hash_equals($_SESSION['caz_captcha_refresh'], $token)
        || !is_string($purpose) || !in_array($purpose, ['login', 'register'], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Please reload this page before requesting a new code.']);
        exit;
    }
    $oldId = $_POST['previous_id'] ?? null;
    if (is_string($oldId)) unset($_SESSION['caz_captcha_challenges'][$oldId]);
    $challenge = caz_captcha_issue($purpose);
    echo json_encode(['ok' => true, 'id' => $challenge['id'], 'image' => 'captcha.php?id=' . $challenge['id']]);
    exit;
}

$id = $_GET['id'] ?? null;
$challenge = is_string($id) ? ($_SESSION['caz_captcha_challenges'][$id] ?? null) : null;
if (!is_array($challenge) || ($challenge['expires'] ?? 0) < time()) {
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'This code has expired. Choose New code.';
    exit;
}
$answer = $challenge['answer'];
session_write_close();
header('Content-Type: image/png');
echo caz_captcha_png($answer);

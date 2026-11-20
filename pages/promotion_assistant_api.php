<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotion-assistant.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    caz_json(['success'=>false, 'message'=>'Use GET to view questions or POST to ask one.'], 405);
}
if ($method === 'GET') caz_json(['success'=>true] + caz_assistant_state());
caz_check_csrf();
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    caz_json(['success'=>false, 'message'=>'Send a JSON question request.'], 415);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw) > 4096) caz_json(['success'=>false, 'message'=>'The question request is too large.'], 413);
try { $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
catch (JsonException $error) { caz_json(['success'=>false, 'message'=>'The question request is invalid.'], 400); }
if (!is_array($input) || !is_string($input['question_id'] ?? null)
    || !array_key_exists($input['question_id'], caz_assistant_questions())) {
    caz_json(['success'=>false, 'message'=>'Choose one of the 10 suggested questions.'], 422);
}
$requestId = $input['request_id'] ?? null;
if (!is_string($requestId) || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $requestId)) {
    caz_json(['success'=>false, 'message'=>'The request identifier is invalid.'], 422);
}
$id = $input['question_id'];
// PHP's session lock serializes concurrent tabs. A retried request never spends a second answer.
$requests = $_SESSION['caz_public_assistant']['requests'] ?? [];
if (isset($requests[$requestId])) {
    if ($requests[$requestId] !== $id) caz_json(['success'=>false, 'message'=>'This request identifier was already used.'], 409);
    caz_json(['success'=>true] + caz_assistant_state());
}
if (count($_SESSION['caz_public_assistant']['history'] ?? []) >= 10) {
    caz_json(['success'=>false, 'message'=>'You have used all 10 answers for this session. You can still message the admin.'] + caz_assistant_state(), 429);
}
try {
    $answer = caz_assistant_answer($id, $conn);
    $_SESSION['caz_public_assistant']['history'][] = ['questionId'=>$id, 'question'=>caz_assistant_questions()[$id], 'answer'=>$answer];
    $_SESSION['caz_public_assistant']['requests'][$requestId] = $id;
    caz_json(['success'=>true] + caz_assistant_state());
} catch (Throwable $error) {
    error_log('CAZ public assistant unavailable: ' . $error->getMessage());
    caz_json(['success'=>false, 'message'=>'The store information could not be loaded. Please try again; this answer was not counted.'], 503);
}

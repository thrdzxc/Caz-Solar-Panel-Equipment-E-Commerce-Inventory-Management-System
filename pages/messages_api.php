<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
caz_require_user(false, true);
require_once __DIR__ . '/../includes/messages.php';

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$viewerId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'inbox');
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); caz_json(['ok'=>false,'error'=>'Method not allowed.'],405); }
if ($method === 'POST') caz_check_csrf();
if (in_array($action, ['send','read'], true) && $method !== 'POST') caz_json(['ok'=>false,'error'=>'Use POST for this action.'],405);
if (in_array($action, ['status','inbox','thread','customers'], true) && $method !== 'GET') caz_json(['ok'=>false,'error'=>'Use GET for this action.'],405);
// Polling must not hold the portal session lock while photos or other tabs load.
session_write_close();

try {
    caz_messages_schema($conn);
    if ($action === 'status') {
        caz_json(['ok'=>true,'unread_total'=>caz_message_unread_count($conn, $isAdmin, $viewerId)]);
    }
    caz_profile_photos_schema($conn);
    if ($action === 'customers') {
        if (!$isAdmin) caz_json(['ok'=>false,'error'=>'Admin access required.'],403);
        $q = '%' . substr(trim((string)($_GET['q'] ?? '')), 0, 100) . '%';
        $customers = caz_message_query($conn, "SELECT u.id, u.name, p.version AS photo_version FROM users u LEFT JOIN caz_profile_photos p ON p.user_id=u.id WHERE u.role <> 'admin' AND u.name LIKE ? ORDER BY u.name LIMIT 30", 's', [$q])->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($customers as &$customer) {
            $customer['photo_url'] = caz_profile_photo_url((int)$customer['id'], $customer['photo_version'], true);
            unset($customer['photo_version']);
        }
        unset($customer);
        caz_json(['ok'=>true,'customers'=>$customers]);
    }
    if ($action === 'inbox') {
        $opposite = $isAdmin ? 'customer' : 'admin';
        $sql = "SELECT c.id, c.customer_id, u.name, p.version AS photo_version, m.body AS last_body, m.sent_at AS last_sent_at,
            (SELECT COUNT(*) FROM caz_messages unread WHERE unread.conversation_id = c.id AND unread.sender_role = ? AND unread.read_at IS NULL) AS unread
            FROM caz_conversations c JOIN users u ON u.id = c.customer_id
            LEFT JOIN caz_profile_photos p ON p.user_id = c.customer_id
            LEFT JOIN caz_messages m ON m.id = (SELECT MAX(last_message.id) FROM caz_messages last_message WHERE last_message.conversation_id = c.id)";
        $types = 's'; $params = [$opposite];
        if (!$isAdmin) { $sql .= ' WHERE c.customer_id = ?'; $types .= 'i'; $params[] = $viewerId; }
        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC';
        $rows = caz_message_query($conn, $sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
        $total = 0;
        foreach ($rows as &$row) {
            $row['id']=(int)$row['id']; $row['customer_id']=(int)$row['customer_id']; $row['unread']=(int)$row['unread']; $total += $row['unread'];
            $row['photo_url'] = caz_profile_photo_url($row['customer_id'], $row['photo_version'], $isAdmin);
            unset($row['photo_version']);
        }
        unset($row);
        caz_json(['ok'=>true,'conversations'=>$rows,'unread_total'=>$total]);
    }
    if (!in_array($action, ['thread','send','read'], true)) caz_json(['ok'=>false,'error'=>'Unknown action.'],400);
    $input = $method === 'POST' ? $_POST : $_GET;
    $customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : ($isAdmin ? 0 : $viewerId);
    $customer = caz_message_customer($conn, $customerId, $isAdmin, $viewerId);
    $room = caz_message_room($conn, $customerId);
    $roomId = $room ? (int)$room['id'] : 0;

    if ($action === 'thread') {
        $messages = []; $hasEarlier = false; $lastReadId = 0;
        if ($roomId) {
            $before = max(0, (int)($_GET['before'] ?? 0));
            $after = max(0, (int)($_GET['after'] ?? 0));
            $sql = 'SELECT m.id, m.body, m.sender_role, m.sender_id, m.sent_at, m.read_at, u.name AS sender_name FROM caz_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.conversation_id = ?';
            $params = [$roomId]; $types = 'i';
            if ($before) { $sql .= ' AND m.id < ?'; $types .= 'i'; $params[] = $before; }
            elseif ($after) { $sql .= ' AND m.id > ?'; $types .= 'i'; $params[] = $after; }
            $sql .= $after && !$before ? ' ORDER BY m.id ASC LIMIT 60' : ' ORDER BY m.id DESC LIMIT 60';
            $messages = caz_message_query($conn,$sql,$types,$params)->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!$after || $before) $messages = array_reverse($messages);
            foreach ($messages as &$message) { $message['id']=(int)$message['id']; $message['sender_id']=(int)$message['sender_id']; }
            unset($message);
            if ($messages) {
                $firstId = (int)$messages[0]['id'];
                $hasEarlier = (bool)caz_message_query($conn,'SELECT id FROM caz_messages WHERE conversation_id = ? AND id < ? LIMIT 1','ii',[$roomId,$firstId])->get_result()->fetch_assoc();
            }
            $ownRole = $isAdmin ? 'admin' : 'customer';
            $lastReadId = (int)caz_message_query($conn,'SELECT COALESCE(MAX(id),0) AS last_read_id FROM caz_messages WHERE conversation_id = ? AND sender_role = ? AND read_at IS NOT NULL','is',[$roomId,$ownRole])->get_result()->fetch_assoc()['last_read_id'];
        }
        caz_json(['ok'=>true,'conversation'=>['id'=>$roomId,'customer_id'=>$customerId,'name'=>$customer['name'],'photo_url'=>$customer['photo_url']],'messages'=>$messages,'has_earlier'=>$hasEarlier,'last_read_id'=>$lastReadId]);
    }
    if ($action === 'read') {
        $lastId = max(0,(int)($_POST['last_id'] ?? 0));
        if ($roomId && $lastId) {
            $opposite = $isAdmin ? 'customer' : 'admin';
            caz_message_query($conn,'UPDATE caz_messages SET read_at = CURRENT_TIMESTAMP WHERE conversation_id = ? AND id <= ? AND sender_role = ? AND read_at IS NULL','iis',[$roomId,$lastId,$opposite]);
        }
        caz_json(['ok'=>true,
            'unread_total'=>caz_message_unread_count($conn, $isAdmin, $viewerId),
            'conversation_unread'=>caz_message_unread_count($conn, $isAdmin, $viewerId, $customerId)]);
    }
    $body = trim((string)($_POST['body'] ?? ''));
    $nonce = (string)($_POST['nonce'] ?? '');
    if ($body === '' || !preg_match('//u',$body) || preg_match_all('/./us',$body) > 4000 || strlen($body) > 16000) caz_json(['ok'=>false,'error'=>'Write a message between 1 and 4,000 characters.'],422);
    if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$nonce)) caz_json(['ok'=>false,'error'=>'Refresh the page before sending this message.'],422);
    $conn->begin_transaction();
    try {
        caz_message_query($conn,'INSERT INTO caz_conversations (customer_id) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)','i',[$customerId]);
        $roomId = (int)$conn->insert_id;
        // Lock the conversation so duplicate retries and ordered updates stay consistent.
        caz_message_query($conn,'SELECT id FROM caz_conversations WHERE id = ? FOR UPDATE','i',[$roomId]);
        $existing = caz_message_query($conn,'SELECT id, conversation_id, body FROM caz_messages WHERE sender_id = ? AND client_nonce = ?','is',[$viewerId,$nonce])->get_result()->fetch_assoc();
        if ($existing) {
            if ((int)$existing['conversation_id'] !== $roomId || $existing['body'] !== $body) {
                $conn->rollback(); caz_json(['ok'=>false,'error'=>'This message request has already been used. Please try again.'],409);
            }
            $messageId = (int)$existing['id'];
        } else {
            $role = $isAdmin ? 'admin' : 'customer';
            $stmt = caz_message_query($conn,'INSERT INTO caz_messages (conversation_id,sender_id,sender_role,body,client_nonce) VALUES (?,?,?,?,?)','iisss',[$roomId,$viewerId,$role,$body,$nonce]);
            $messageId = (int)$stmt->insert_id;
            caz_message_query($conn,'UPDATE caz_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?','i',[$roomId]);
        }
        $conn->commit();
        caz_json(['ok'=>true,'message_id'=>$messageId,'conversation_id'=>$roomId]);
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
} catch (Throwable $error) {
    error_log('CAZ messaging: ' . $error->getMessage());
    caz_json(['ok'=>false,'error'=>'Messages are temporarily unavailable. Please try again.'],500);
}

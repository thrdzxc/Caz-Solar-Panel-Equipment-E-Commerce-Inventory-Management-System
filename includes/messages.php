<?php
require_once __DIR__ . '/profile_photo.php';
/** Additive messaging storage. Existing accounts and orders are never modified. */
function caz_messages_schema(mysqli $conn): void {
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+00:00'");
    $conn->query("CREATE TABLE IF NOT EXISTS caz_conversations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY customer_conversation (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS caz_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT UNSIGNED NOT NULL,
        sender_id INT NOT NULL,
        sender_role VARCHAR(12) NOT NULL,
        body TEXT NOT NULL,
        client_nonce VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY sender_nonce (sender_id, client_nonce),
        KEY conversation_messages (conversation_id, id),
        KEY unread_messages (conversation_id, sender_role, read_at),
        CONSTRAINT caz_message_conversation FOREIGN KEY (conversation_id) REFERENCES caz_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function caz_message_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

/** Count incoming unread messages without exposing message bodies or changing read state. */
function caz_message_unread_count(mysqli $conn, bool $isAdmin, int $viewerId, ?int $customerId = null): int {
    $sql = 'SELECT COUNT(*) FROM caz_messages m JOIN caz_conversations c ON c.id=m.conversation_id WHERE m.sender_role=? AND m.read_at IS NULL';
    $types = 's';
    $params = [$isAdmin ? 'customer' : 'admin'];
    if (!$isAdmin || $customerId !== null) {
        $sql .= ' AND c.customer_id=?';
        $types .= 'i';
        $params[] = $isAdmin ? $customerId : $viewerId;
    }
    return (int)caz_message_query($conn, $sql, $types, $params)->get_result()->fetch_row()[0];
}

function caz_message_customer(mysqli $conn, int $customerId, bool $isAdmin, int $viewerId): array {
    if ($customerId < 1 || (!$isAdmin && $customerId !== $viewerId)) {
        caz_json(['ok'=>false, 'error'=>'You cannot access this conversation.'], 403);
    }
    $row = caz_message_query($conn, "SELECT u.id, u.name, p.version AS photo_version FROM users u LEFT JOIN caz_profile_photos p ON p.user_id=u.id WHERE u.id = ? AND u.role <> 'admin'", 'i', [$customerId])->get_result()->fetch_assoc();
    if (!$row) caz_json(['ok'=>false, 'error'=>'This customer is not available.'], 404);
    $row['photo_url'] = caz_profile_photo_url($customerId, $row['photo_version'], $isAdmin);
    unset($row['photo_version']);
    return $row;
}

function caz_message_room(mysqli $conn, int $customerId): ?array {
    return caz_message_query($conn, 'SELECT id, customer_id FROM caz_conversations WHERE customer_id = ?', 'i', [$customerId])->get_result()->fetch_assoc();
}

function caz_messages_view(bool $isAdmin): void {
    $config = ['admin'=>$isAdmin, 'viewerId'=>(int)$_SESSION['user_id'], 'csrf'=>caz_csrf_token(), 'endpoint'=>$isAdmin ? '../admin/messages_api.php' : '../pages/messages_api.php'];
    ?>
    <main class="messages-page" id="messages-app" data-config="<?php echo caz_h(json_encode($config)); ?>">
      <header class="messages-heading">
        <div><p class="messages-eyebrow">CAZ / CUSTOMER CARE</p><h1><?php echo $isAdmin ? 'Customer messages' : 'Let’s talk about your project'; ?></h1>
        <p><?php echo $isAdmin ? 'Keep every customer conversation in one organized place.' : 'Get help with products, promotions, installation, or your order.'; ?></p></div>
        <span class="messages-heading-tag"><span aria-hidden="true">●</span> <?php echo $isAdmin ? 'Shared admin inbox' : 'Direct to Caz admin'; ?></span>
      </header>
      <div class="messages-shell <?php echo $isAdmin ? 'messages-admin' : 'messages-customer'; ?>">
        <aside class="messages-sidebar" aria-label="<?php echo $isAdmin ? 'Conversations' : 'Customer care'; ?>">
          <?php if ($isAdmin): ?>
          <div class="messages-sidebar-title"><h2>Inbox <span id="messages-unread-total" class="messages-count" hidden>0</span></h2><button id="messages-new" class="messages-icon-button" type="button" aria-label="Start a new conversation" title="New conversation">＋</button></div>
          <label class="messages-search-label" for="messages-search">Find a conversation</label>
          <input id="messages-search" type="search" placeholder="Search customer or message…" autocomplete="off">
          <div id="messages-recipient-picker" hidden>
            <div class="messages-picker-heading"><strong>New conversation</strong><button class="messages-text-button" id="messages-picker-close" type="button">Close</button></div>
            <label class="messages-search-label" for="messages-recipient-search">Find a customer</label>
            <input id="messages-recipient-search" type="search" placeholder="Customer name…" autocomplete="off">
            <div id="messages-recipient-list" class="messages-recipient-list"></div>
          </div>
          <div id="messages-inbox" class="messages-inbox"><p class="messages-hint">Loading conversations…</p></div>
          <?php else: ?>
          <div class="messages-support-mark messages-brand-avatar" aria-hidden="true"><img src="../images/cazlogo.png" alt=""></div>
          <h2>Caz admin</h2><p class="messages-support-copy">A little guidance goes a long way. Send us a message and our admin will reply here.</p>
          <div class="messages-service-note"><strong>How can we help?</strong><p>Product recommendations<br>Installation & promotions<br>Orders & after-sales support</p></div>
          <div class="messages-quick-topics"><span>Start with a question</span><button type="button" data-message-topic="Hi! Can you help me choose a CCTV system?">Choosing CCTV <span aria-hidden="true">↗</span></button><button type="button" data-message-topic="Hi! I’d like to ask about solar products and installation.">Solar & installation <span aria-hidden="true">↗</span></button><button type="button" data-message-topic="Hi! Can you tell me about your current promotions?">Current promotions <span aria-hidden="true">↗</span></button></div>
          <?php endif; ?>
        </aside>
        <section class="messages-conversation" aria-label="Message conversation">
          <header class="messages-thread-heading"><span id="messages-avatar" class="messages-avatar<?php if (!$isAdmin) echo ' messages-brand-avatar'; ?>" aria-hidden="true"><?php if ($isAdmin): ?>C<?php else: ?><img src="../images/cazlogo.png" alt=""><?php endif; ?></span><div><h2 id="messages-thread-name"><?php echo $isAdmin ? 'Your customer conversations' : 'Caz admin'; ?></h2><p id="messages-thread-subtitle"><?php echo $isAdmin ? 'Select a conversation to get started' : 'Your messages stay here for your next visit'; ?></p></div><span id="messages-connection" class="messages-connection" role="status">Connecting…</span></header>
          <div class="messages-history" id="messages-history" role="log" aria-label="Messages" aria-live="polite" aria-relevant="additions"><button id="messages-load-earlier" class="messages-earlier" type="button" hidden>Load earlier messages</button><div id="messages-items"><div class="messages-empty"><span aria-hidden="true">✉</span><h3><?php echo $isAdmin ? 'A helpful reply starts here' : 'Welcome to your CAZ conversation'; ?></h3><p><?php echo $isAdmin ? 'Choose a customer from the inbox, or start a new conversation.' : 'Ask a question below. Our team will see it in their inbox.'; ?></p></div></div></div>
          <p id="messages-error" class="messages-error" role="alert" hidden></p>
          <form id="messages-compose" class="messages-compose">
            <label for="messages-body">Your message</label>
            <div class="messages-compose-row"><textarea id="messages-body" name="body" rows="2" maxlength="4000" placeholder="Write your message…" required <?php if ($isAdmin) echo 'disabled'; ?>></textarea><button id="messages-send" type="submit" <?php if ($isAdmin) echo 'disabled'; ?>><span>Send</span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg></button></div>
            <div class="messages-compose-help"><span>Enter to send · Shift + Enter for a new line</span><span id="messages-character-count">0 / 4,000</span></div>
          </form>
        </section>
      </div>
    </main>
    <?php
}

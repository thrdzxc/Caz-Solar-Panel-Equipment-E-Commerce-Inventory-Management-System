<?php
/** Profile photos stay with accounts in the existing database. */
function caz_profile_photos_schema(mysqli $conn): void {
    static $ready = [];
    $key = spl_object_id($conn);
    if (isset($ready[$key])) return;
    $conn->query("CREATE TABLE IF NOT EXISTS caz_profile_photos (
        user_id INT NOT NULL PRIMARY KEY,
        mime_type VARCHAR(32) NOT NULL,
        image_data MEDIUMBLOB NOT NULL,
        version CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[$key] = true;
}

function caz_profile_photo_version(mysqli $conn, int $userId): ?string {
    caz_profile_photos_schema($conn);
    $stmt = $conn->prepare('SELECT version FROM caz_profile_photos WHERE user_id=?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['version'] : null;
}

/** Versioned metadata only: never place the photo bytes in a messages response. */
function caz_profile_photo_url(int $userId, ?string $version, bool $admin): ?string {
    if ($userId < 1 || $version === null || !preg_match('/^[a-f0-9]{32}$/D', $version)) return null;
    return $admin
        ? '../admin/customer_photo.php?user_id=' . $userId . '&v=' . $version
        : '../pages/profile_photo.php?v=' . $version;
}

/** Call only after authorizing the viewer and choosing the permitted customer ID. */
function caz_profile_photo_response(mysqli $conn, int $userId): void {
    caz_profile_photos_schema($conn);
    $stmt = $conn->prepare("SELECT p.mime_type, p.image_data FROM caz_profile_photos p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND u.role <> 'admin'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $photo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$photo) caz_json(['success'=>false,'message'=>'No profile photo has been saved.'],404);
    if (!in_array($photo['mime_type'], ['image/jpeg','image/png','image/webp'], true)) throw new RuntimeException('Unexpected stored photo format');
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Length: ' . strlen($photo['image_data']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    echo $photo['image_data'];
    exit;
}

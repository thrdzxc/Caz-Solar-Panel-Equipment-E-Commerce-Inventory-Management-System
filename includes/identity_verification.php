<?php
/** Private, manually reviewed ID submissions. Never expose image bytes in API metadata. */
function caz_identity_schema(mysqli $conn): void {
    static $ready = [];
    if (isset($ready[spl_object_id($conn)])) return;
    $conn->query("CREATE TABLE IF NOT EXISTS caz_identity_verifications (
        user_id INT NOT NULL PRIMARY KEY,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        id_type VARCHAR(50) NOT NULL, mime_type VARCHAR(32) NOT NULL,
        image_data MEDIUMBLOB NULL, version CHAR(32) NOT NULL,
        contact_name VARCHAR(120) NOT NULL, contact_phone VARCHAR(20) NOT NULL,
        contact_relationship VARCHAR(80) NOT NULL DEFAULT '',
        review_note VARCHAR(1000) NOT NULL DEFAULT '', reviewed_by INT NULL,
        submitted_at DATETIME NOT NULL, reviewed_at DATETIME NULL,
        INDEX verification_status (status,submitted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[spl_object_id($conn)] = true;
}
function caz_identity_details(mysqli $conn, int $userId, bool $lock = false): ?array {
    $stmt = $conn->prepare('SELECT user_id,status,id_type,version,contact_name,contact_phone,contact_relationship,review_note,submitted_at,reviewed_at FROM caz_identity_verifications WHERE user_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $userId); $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
function caz_identity_contact(array $values): array {
    require_once __DIR__ . '/account_validation.php';
    $name = trim(is_string($values['contact_person_name'] ?? null) ? $values['contact_person_name'] : '');
    $phone = trim(is_string($values['contact_person_phone'] ?? null) ? $values['contact_person_phone'] : '');
    $relationship = trim(is_string($values['contact_person_relationship'] ?? null) ? $values['contact_person_relationship'] : '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new InvalidArgumentException('Enter the contact person’s full name (2–120 characters).');
    if (!caz_account_phone_valid($phone)) throw new InvalidArgumentException('Enter exactly 11 digits for the contact person’s mobile number, starting with 09.');
    if (mb_strlen($relationship) > 80) throw new InvalidArgumentException('Keep the relationship below 80 characters.');
    if (($values['identity_consent'] ?? '') !== '1') throw new InvalidArgumentException('Please confirm that you have permission to submit these details for verification.');
    return ['name'=>$name, 'phone'=>$phone, 'relationship'=>$relationship];
}
function caz_identity_types(): array {
    return ["Driver's license", 'National ID (PhilSys)', 'Passport', 'UMID', 'Postal ID', 'PRC ID', 'Senior citizen ID', 'PWD ID', 'Government-issued photo ID', 'Student ID', 'Other photo ID'];
}
function caz_identity_rollback(mysqli $conn): void {
    try { $conn->rollback(); } catch (Throwable $ignored) { /* A lost connection already rolls back its transaction. */ }
}
function caz_identity_upload(array $file, $type): array {
    if (!is_string($type) || !in_array($type, caz_identity_types(), true)) throw new InvalidArgumentException('Choose the type of photo ID.');
    $uploadError=$file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) throw new InvalidArgumentException('The ID photo is too large. Choose an image up to 8 MB.');
    if ($uploadError===UPLOAD_ERR_PARTIAL) throw new InvalidArgumentException('The ID photo upload was interrupted. Please select the photo and try again.');
    if ($uploadError !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Upload a clear JPG, PNG or WebP photo of your valid ID, up to 8 MB.');
    $size = filesize($file['tmp_name']);
    if ($size < 100 || $size > 8 * 1024 * 1024) throw new InvalidArgumentException('The ID image must be no larger than 8 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $dimensions = @getimagesize($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true) || !$dimensions || ($dimensions['mime'] ?? '') !== $mime) throw new InvalidArgumentException('Only real JPG, PNG or WebP images are accepted.');
    [$width,$height] = $dimensions;
    if (min($width,$height) < 500 || max($width,$height) < 800) throw new InvalidArgumentException('This photo is too small to review. Upload a clearer image at least 800 × 500 pixels (either orientation).');
    if ($width * $height > 24000000) throw new InvalidArgumentException('The image is too large. Use a photo below 24 megapixels and 8 MB.');
    require_once __DIR__.'/image_processing.php';
    try { $image=caz_process_image($file['tmp_name'],true); }
    catch (RuntimeException $error) { error_log('CAZ local ID image processor unavailable.'); throw new InvalidArgumentException('We could not check this photo right now. Please try again or contact the store.'); }
    return ['type'=>$type, 'mime'=>$image['mime'], 'bytes'=>$image['bytes'], 'version'=>bin2hex(random_bytes(16))];
}
/** Caller holds the user's row lock and a transaction. */
function caz_identity_save(mysqli $conn, int $userId, array $image, array $contact): void {
    $packet=(int)$conn->query('SELECT @@max_allowed_packet')->fetch_row()[0];
    if (strlen($image['bytes'])+65536 > $packet) throw new RuntimeException('Database image transfer limit is too small.');
    $stmt = $conn->prepare("INSERT INTO caz_identity_verifications (user_id,id_type,mime_type,image_data,version,contact_name,contact_phone,contact_relationship,submitted_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status='pending',id_type=VALUES(id_type),mime_type=VALUES(mime_type),image_data=VALUES(image_data),version=VALUES(version),contact_name=VALUES(contact_name),contact_phone=VALUES(contact_phone),contact_relationship=VALUES(contact_relationship),review_note='',reviewed_by=NULL,submitted_at=NOW(),reviewed_at=NULL");
    $stmt->bind_param('isssssss', $userId,$image['type'],$image['mime'],$image['bytes'],$image['version'],$contact['name'],$contact['phone'],$contact['relationship']);
    $stmt->execute();
}
function caz_identity_image_response(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare('SELECT mime_type,image_data FROM caz_identity_verifications WHERE user_id=?');
    $stmt->bind_param('i', $userId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
    if (!$row || $row['image_data'] === null) { http_response_code(404); exit('No ID image is available. Approved ID images are removed after review.'); }
    if (!in_array($row['mime_type'], ['image/jpeg','image/png','image/webp'], true)) { http_response_code(404); exit; }
    header('Content-Type: '.$row['mime_type']); header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Content-Disposition: inline; filename="verification-image"');
    echo $row['image_data']; exit;
}
function caz_identity_form_fields(array $values = []): void {
    $value = static fn($key) => htmlspecialchars((string)($values[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <fieldset class="identity-upload full-width">
      <legend>Account verification</legend>
      <p>Upload your own valid photo ID. Keep all edges visible and the name, photo and dates readable, with no blur or glare. An administrator will review it before you can place orders.</p>
      <div class="identity-fields">
        <div><label for="identity-type">ID type</label><select id="identity-type" name="id_type" required><option value="">Choose ID type</option><?php foreach (caz_identity_types() as $type): ?><option <?= ($values['id_type']??'')===$type?'selected':'' ?>><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></div>
        <div><label for="identity-file">Clear ID photo</label><input id="identity-file" type="file" name="identity_document" accept="image/jpeg,image/png,image/webp" required aria-describedby="identity-file-help"><small id="identity-file-help">JPG, PNG or WebP · up to 8 MB · at least 800 × 500 px. Obvious blur, darkness and glare are checked automatically; the admin reviews the ID itself.</small></div>
        <figure id="identity-preview" hidden><img alt="Preview of the ID photo you selected"><figcaption>Check the name, photo and expiry date. Choose another file if anything is unclear.</figcaption></figure>
        <div><label for="contact-person-name">Contact person for order verification</label><input id="contact-person-name" name="contact_person_name" maxlength="120" minlength="2" value="<?= $value('contact_person_name') ?>" placeholder="Full name" required></div>
        <div><label for="contact-person-phone">Contact person’s mobile number</label><input id="contact-person-phone" type="tel" name="contact_person_phone" pattern="09[0-9]{9}" maxlength="11" minlength="11" inputmode="numeric" data-account-phone value="<?= $value('contact_person_phone') ?>" placeholder="09XXXXXXXXX" required aria-describedby="contact-person-phone-help"><small id="contact-person-phone-help">Exactly 11 digits, starting with 09.</small></div>
        <div class="full-width"><label for="contact-person-relationship">Relationship (optional)</label><input id="contact-person-relationship" name="contact_person_relationship" maxlength="80" value="<?= $value('contact_person_relationship') ?>" placeholder="e.g. Self, parent or spouse"></div>
      </div>
      <p>Only you and authorized administrators can view your ID. Its photo is removed after approval; your verification status and contact details are kept for order support.</p>
      <label class="identity-consent"><input type="checkbox" name="identity_consent" value="1" required><span>I confirm these details are accurate and I have permission to share them for account and order verification.</span></label>
    </fieldset>
    <?php
}

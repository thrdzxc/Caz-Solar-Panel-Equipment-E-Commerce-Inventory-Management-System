<?php
/** Product-specific photographs. Only server-generated JPEG/PNG names are published. */
const CAZ_GALLERY_MAX_PHOTOS = 8;
const CAZ_GALLERY_UPLOAD_BYTES = 8 * 1024 * 1024;

function caz_gallery_directory(int $productId, bool $create = false): ?string {
    if ($productId < 1) throw new InvalidArgumentException('Invalid product.');
    $images = realpath(__DIR__ . '/../images');
    if ($images === false) throw new RuntimeException('Product image storage is unavailable.');
    $root = $images . DIRECTORY_SEPARATOR . 'product-gallery';
    $directory = $root . DIRECTORY_SEPARATOR . $productId;
    foreach ([$root, $directory] as $path) {
        if (is_link($path)) throw new RuntimeException('Product image storage is unavailable.');
        if (!is_dir($path)) {
            if (!$create) return null;
            if (!@mkdir($path, 0755) && !is_dir($path)) throw new RuntimeException('Product image storage is not writable.');
        }
        $resolved = realpath($path);
        if ($resolved === false || strcasecmp($resolved, $path) !== 0) throw new RuntimeException('Product image storage is unavailable.');
    }
    return $directory;
}

function caz_gallery_read_entries(string $directory): array {
    $path = $directory . '/gallery.json';
    if (!file_exists($path)) return [];
    if (is_link($path) || !is_file($path) || filesize($path) > 65536) throw new RuntimeException('Product photos could not be read.');
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || !array_is_list($data) || count($data) > CAZ_GALLERY_MAX_PHOTOS) throw new RuntimeException('Product photos could not be read.');
    $entries = [];
    foreach ($data as $entry) {
        if (!is_array($entry) || !is_string($entry['file'] ?? null) || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png)$/D', $entry['file'])) continue;
        $path = $directory . '/' . $entry['file'];
        if (!is_file($path) || is_link($path)) continue;
        $entries[] = ['file' => $entry['file'], 'label' => is_string($entry['label'] ?? null) ? $entry['label'] : 'Product photo'];
    }
    return $entries;
}

function caz_gallery_lock(string $directory, int $operation) {
    $path = $directory . '/gallery.lock';
    if (is_link($path)) throw new RuntimeException('Product photo storage is unavailable.');
    $lock = @fopen($path, 'c');
    if (!$lock || !flock($lock, $operation)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Product photos are busy. Please try again.');
    }
    return $lock;
}

function caz_product_gallery(int $productId): array {
    $lock = null;
    try {
        $directory = caz_gallery_directory($productId);
        if ($directory === null) return [];
        $lock = caz_gallery_lock($directory, LOCK_SH);
        return caz_gallery_read_entries($directory);
    } catch (RuntimeException $error) {
        error_log('CAZ product gallery: ' . $error->getMessage());
        return [];
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function caz_gallery_save_entries(string $directory, array $entries): void {
    $temporary = $directory . '/gallery-' . bin2hex(random_bytes(12)) . '.tmp';
    $json = json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json) || !@rename($temporary, $directory . '/gallery.json')) {
            throw new RuntimeException('Product photos could not be saved.');
        }
    } finally {
        if (is_file($temporary) && !is_link($temporary)) unlink($temporary);
    }
}

function caz_gallery_upload(int $productId, array $upload, string $label): void {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Choose a JPEG, PNG or WebP photo up to 8 MB.');
    $temporary = $upload['tmp_name'] ?? '';
    if (!is_string($temporary) || !is_uploaded_file($temporary) || !is_file($temporary)) throw new InvalidArgumentException('Choose a valid uploaded photo.');
    $size = filesize($temporary);
    if ($size === false || $size < 1 || $size > CAZ_GALLERY_UPLOAD_BYTES) throw new InvalidArgumentException('Each photo must be 8 MB or smaller.');
    $label = trim($label);
    if (!preg_match('/^[^\p{C}]{1,100}$/uD', $label)) throw new InvalidArgumentException('Add a photo label of up to 100 characters, such as Side view.');
    require_once __DIR__ . '/gallery_image_processing.php';
    $image = caz_process_gallery_image($temporary);
    if (!in_array($image['mime'] ?? '', ['image/jpeg', 'image/png'], true) || !is_string($image['bytes'] ?? null) || strlen($image['bytes']) > 1500000) throw new RuntimeException('Photo processing could not finish. Please try another photo.');
    $directory = caz_gallery_directory($productId, true);
    $lock = caz_gallery_lock($directory, LOCK_EX);
    $path = null;
    try {
        $entries = caz_gallery_read_entries($directory);
        if (count($entries) >= CAZ_GALLERY_MAX_PHOTOS) throw new InvalidArgumentException('This product already has eight additional photos. Remove one before adding another.');
        $file = bin2hex(random_bytes(16)) . ($image['mime'] === 'image/png' ? '.png' : '.jpg');
        $path = $directory . '/' . $file;
        $stream = @fopen($path, 'xb');
        if (!$stream) throw new RuntimeException('Product image storage is not writable.');
        try { $written = fwrite($stream, $image['bytes']); } finally { fclose($stream); }
        if ($written !== strlen($image['bytes'])) throw new RuntimeException('The photo could not be saved.');
        $entries[] = ['file' => $file, 'label' => $label];
        caz_gallery_save_entries($directory, $entries);
        $path = null;
    } finally {
        if ($path !== null && is_file($path) && !is_link($path)) unlink($path);
        flock($lock, LOCK_UN); fclose($lock);
    }
}

function caz_gallery_remove(int $productId, string $file): void {
    if (!preg_match('/^[a-f0-9]{32}\.(?:jpg|png)$/D', $file)) throw new InvalidArgumentException('Choose a valid product photo.');
    $directory = caz_gallery_directory($productId);
    if ($directory === null) throw new InvalidArgumentException('That photo no longer exists.');
    $lock = caz_gallery_lock($directory, LOCK_EX);
    try {
        $entries = caz_gallery_read_entries($directory);
        $remaining = array_values(array_filter($entries, static fn(array $entry): bool => $entry['file'] !== $file));
        if (count($remaining) === count($entries)) throw new InvalidArgumentException('That photo no longer exists.');
        caz_gallery_save_entries($directory, $remaining);
        $path = $directory . '/' . $file;
        // The id and random basename are validated; no submitted path is used.
        if (is_file($path) && !is_link($path) && !@unlink($path)) error_log('CAZ gallery: could not remove an unlisted product image.');
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function caz_gallery_url(int $productId, array $entry): string {
    return '../images/product-gallery/' . $productId . '/' . rawurlencode($entry['file']);
}

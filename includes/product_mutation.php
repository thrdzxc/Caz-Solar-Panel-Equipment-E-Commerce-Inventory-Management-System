<?php
/** Shared validation for the Add/Edit Product forms. */
function caz_keep_product_form(array $input, bool $adding): void {
    $fields = $adding ? ['p_name','p_brand','p_category','p_description','p_stock','p_price','p_type','p_spec'] : ['product_id','product_name','brand','category','description','stock','price','type','spec','image'];
    $values = [];
    foreach ($fields as $field) if (isset($input[$field]) && is_string($input[$field]) && strlen($input[$field]) <= 65535) $values[$field] = $input[$field];
    $_SESSION[$adding ? 'caz_product_add_values' : 'caz_product_edit_values'] = $values;
}

function caz_product_text($value, string $label, int $maximum, bool $required = false): string {
    if (!is_string($value)) throw new InvalidArgumentException('Enter a valid ' . strtolower($label) . '.');
    $value = trim($value);
    if (!preg_match('//u', $value) || preg_match('/[\x00-\x1F\x7F]/u', $value) || mb_strlen($value, 'UTF-8') > $maximum || ($required && $value === '')) {
        throw new InvalidArgumentException($label . ' must be ' . ($required ? '1 to ' : 'no more than ') . $maximum . ' characters.');
    }
    return $value;
}

function caz_product_fields(array $input, bool $adding): array {
    $prefix = $adding ? 'p_' : '';
    $nameKey = $adding ? 'p_name' : 'product_name';
    $data = [
        'name' => caz_product_text($input[$nameKey] ?? '', 'Product name', 255, true),
        'brand' => caz_product_text($input[$prefix . 'brand'] ?? '', 'Brand', 100),
        'type' => caz_product_text($input[$prefix . 'type'] ?? '', 'Product type', 100),
        'spec' => caz_product_text($input[$prefix . 'spec'] ?? '', 'Resolution / power', 100)
    ];
    $category = $input[$prefix . 'category'] ?? null;
    if (!is_string($category) || !in_array($category, ['CCTV', 'Solar'], true)) throw new InvalidArgumentException('Choose CCTV or SOLAR as the category.');
    $data['category'] = $category;
    $description = $input[$prefix . 'description'] ?? '';
    if (!is_string($description) || !preg_match('//u', $description) || strlen($description) > 65535 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description)) throw new InvalidArgumentException('Enter product details of no more than 65,535 bytes.');
    $data['description'] = $description;
    $stock = $input[$prefix . 'stock'] ?? null;
    if (!is_string($stock) || !preg_match('/^\d{1,10}$/D', $stock) || (float)$stock > 2147483647) throw new InvalidArgumentException('Stock must be a whole number from 0 to 2,147,483,647.');
    $data['stock'] = (int)$stock;
    $price = $input[$prefix . 'price'] ?? null;
    if (!is_string($price) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $price)) throw new InvalidArgumentException('Price must be a positive amount or zero, with no more than two decimal places.');
    $data['price'] = $price;
    return $data;
}

function caz_existing_product_image($value): string {
    if (!is_string($value)) throw new InvalidArgumentException('Enter an existing product image filename.');
    $name = trim($value);
    // Existing catalog names may contain spaces. Paths, active content and links are never accepted.
    if (strlen($name) > 255 || !preg_match('/^[^\\\\\/\x00-\x1F<>:"|?*]+\.(?:jpe?g|png|webp|gif)$/iD', $name) || str_contains($name, '..')) throw new InvalidArgumentException('Use an image filename from the images folder, without a folder path.');
    $root = realpath(__DIR__ . '/../images');
    $path = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . $name);
    if ($path === false || !is_file($path) || is_link($root . DIRECTORY_SEPARATOR . $name) || strcasecmp(dirname($path), $root) !== 0) throw new InvalidArgumentException('That image was not found. Use an existing image filename from the images folder.');
    $info = @getimagesize($path);
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) throw new InvalidArgumentException('That file is not a supported product image.');
    return basename($path);
}

function caz_store_main_product_image(array $upload): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Choose a JPG, PNG or WEBP product photo up to 8 MB.');
    $temporary = $upload['tmp_name'] ?? '';
    if (!is_string($temporary) || !is_uploaded_file($temporary)) throw new InvalidArgumentException('Choose a valid uploaded product photo.');
    $size = filesize($temporary);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) throw new InvalidArgumentException('The product photo must be 8 MB or smaller.');
    require_once __DIR__ . '/gallery_image_processing.php';
    $image = caz_process_gallery_image($temporary);
    $root = realpath(__DIR__ . '/../images');
    if ($root === false || !is_dir($root)) throw new RuntimeException('Product image storage is unavailable.');
    $name = 'product_' . bin2hex(random_bytes(16)) . ($image['mime'] === 'image/png' ? '.png' : '.jpg');
    $path = $root . DIRECTORY_SEPARATOR . $name;
    $stream = @fopen($path, 'xb');
    if (!$stream) throw new RuntimeException('Product image storage is not writable.');
    try { $written = fwrite($stream, $image['bytes']); } finally { fclose($stream); }
    if ($written !== strlen($image['bytes'])) {
        unlink($path);
        throw new RuntimeException('The product photo could not be saved.');
    }
    return ['name' => $name, 'path' => $path];
}

function caz_sync_product_inventory(mysqli $db, int $id, int $stock): void {
    $statement = $db->prepare('SELECT stock_out FROM inventory WHERE product_id=? FOR UPDATE');
    $statement->bind_param('i', $id);
    $statement->execute();
    $existing = $statement->get_result()->fetch_assoc();
    $stockOut = $existing ? max(0, (int)$existing['stock_out']) : 0;
    $stockIn = $stock + $stockOut;
    if ($stockIn > 2147483647) throw new InvalidArgumentException('Stock is too large for this product. Enter a smaller available quantity.');
    if ($existing) {
        // A correction changes available stock while preserving fulfilled/reserved units.
        $statement = $db->prepare('UPDATE inventory SET stock_in=?,current_stock=? WHERE product_id=?');
        $statement->bind_param('iii', $stockIn, $stock, $id);
    } else {
        $statement = $db->prepare('INSERT INTO inventory (product_id,stock_in,stock_out,current_stock) VALUES (?,?,0,?)');
        $statement->bind_param('iii', $id, $stock, $stock);
    }
    $statement->execute();
}

function caz_sync_product_main_image(mysqli $db, int $id, string $oldName, string $name): void {
    $path = '../images/' . $name;
    $statement = $db->prepare('SELECT id FROM product_images WHERE product_id=? AND image_name=? FOR UPDATE');
    $statement->bind_param('is', $id, $oldName);
    $statement->execute();
    $previous = $statement->get_result()->fetch_assoc();
    if ($previous) {
        $statement = $db->prepare('UPDATE product_images SET image_name=?,image_path=? WHERE product_id=? AND image_name=?');
        $statement->bind_param('ssis', $name, $path, $id, $oldName);
    } else {
        $statement = $db->prepare('INSERT INTO product_images (product_id,image_name,image_path) VALUES (?,?,?)');
        $statement->bind_param('iss', $id, $name, $path);
    }
    $statement->execute();
}

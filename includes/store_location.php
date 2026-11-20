<?php
/** A store pin is published only after an authenticated admin chooses it. */
function caz_store_schema(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS caz_store_location (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        address VARCHAR(255) NOT NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function caz_store_location(mysqli $conn): array {
    caz_store_schema($conn);
    $row = $conn->query('SELECT address,latitude,longitude,updated_at FROM caz_store_location WHERE id=1')->fetch_assoc();
    return $row ? ['address'=>$row['address'], 'latitude'=>$row['latitude'] === null ? null : (float)$row['latitude'],
        'longitude'=>$row['longitude'] === null ? null : (float)$row['longitude'], 'updated_at'=>$row['updated_at']]
        : ['address'=>'P-7 Mendoza Street, Balintocatoc, Santiago City, Isabela, Philippines', 'latitude'=>null, 'longitude'=>null, 'updated_at'=>null];
}
function caz_store_has_pin(array $store): bool { return $store['latitude'] !== null && $store['longitude'] !== null; }
function caz_store_directions(array $store): string {
    $destination = caz_store_has_pin($store) ? $store['latitude'] . ',' . $store['longitude'] : $store['address'];
    return 'https://www.google.com/maps/dir/?' . http_build_query(['api'=>1,'destination'=>$destination,'dir_action'=>'navigate'], '', '&', PHP_QUERY_RFC3986);
}
function caz_store_validate(array $data): array {
    $address = $data['address'] ?? null;
    if (!is_string($address) || trim($address) === '' || strlen($address) > 255) throw new InvalidArgumentException('Enter a store address of up to 255 characters.');
    $lat = $data['latitude'] ?? null; $lng = $data['longitude'] ?? null;
    if (!is_string($lat) && !is_int($lat) && !is_float($lat)) throw new InvalidArgumentException('Choose a store pin before saving.');
    if (!is_string($lng) && !is_int($lng) && !is_float($lng)) throw new InvalidArgumentException('Choose a store pin before saving.');
    if (!is_numeric($lat) || !is_numeric($lng) || !is_finite((float)$lat) || !is_finite((float)$lng)
        || (float)$lat < -90 || (float)$lat > 90 || (float)$lng < -180 || (float)$lng > 180) throw new InvalidArgumentException('Enter valid latitude and longitude coordinates.');
    return ['address'=>trim($address),'latitude'=>round((float)$lat,7),'longitude'=>round((float)$lng,7)];
}

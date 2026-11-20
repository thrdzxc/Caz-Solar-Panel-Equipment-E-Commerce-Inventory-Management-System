<?php
/** Additive location storage. Run schema setup before starting a transaction. */
function caz_locations_schema(mysqli $conn): void {
    static $ready = [];
    $key = spl_object_id($conn);
    if (isset($ready[$key])) return;
    $conn->query("CREATE TABLE IF NOT EXISTS caz_order_locations (
        order_id INT NOT NULL PRIMARY KEY,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[$key] = true;
}

/** A missing pin is null; coordinates are never inferred from an address. */
function caz_validate_delivery_location($location): ?array {
    if ($location === null) return null;
    if (!is_array($location) || !array_key_exists('latitude', $location) || !array_key_exists('longitude', $location)) {
        throw new InvalidArgumentException('Choose a complete delivery pin with latitude and longitude.');
    }
    $latitude = $location['latitude'];
    $longitude = $location['longitude'];
    if ((!is_int($latitude) && !is_float($latitude)) || (!is_int($longitude) && !is_float($longitude)) ||
        !is_finite((float)$latitude) || !is_finite((float)$longitude) ||
        $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('Choose a valid delivery pin on the map.');
    }
    return ['latitude' => (float)$latitude, 'longitude' => (float)$longitude];
}

/** Save or clear a pin within the same transaction as its order/address. */
function caz_save_order_location(mysqli $conn, int $orderId, ?array $location): void {
    $location = caz_validate_delivery_location($location);
    if ($orderId < 1) throw new InvalidArgumentException('Choose a valid order.');
    if ($location === null) {
        $stmt = $conn->prepare('DELETE FROM caz_order_locations WHERE order_id=?');
        $stmt->bind_param('i', $orderId);
    } else {
        $stmt = $conn->prepare('INSERT INTO caz_order_locations (order_id,latitude,longitude) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),updated_at=CURRENT_TIMESTAMP');
        $stmt->bind_param('idd', $orderId, $location['latitude'], $location['longitude']);
    }
    $stmt->execute();
    $stmt->close();
}

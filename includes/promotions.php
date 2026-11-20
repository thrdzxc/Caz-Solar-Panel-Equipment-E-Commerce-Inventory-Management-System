<?php
/** Additive promotion storage. Call before beginning an order transaction. */
function caz_promotions_schema(mysqli $conn): void {
    static $ready = [];
    $key = spl_object_id($conn);
    if (isset($ready[$key])) return;
    $conn->query("CREATE TABLE IF NOT EXISTS caz_promotions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        title VARCHAR(140) NOT NULL,
        description TEXT NOT NULL,
        benefit VARCHAR(240) NOT NULL,
        minimum_quantity INT UNSIGNED NOT NULL DEFAULT 1,
        special_price DECIMAL(12,2) NULL,
        image VARCHAR(255) NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_promo_product (product_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS caz_order_promotions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        promotion_id INT UNSIGNED NOT NULL,
        title VARCHAR(140) NOT NULL,
        benefit VARCHAR(240) NOT NULL,
        minimum_quantity INT UNSIGNED NOT NULL,
        original_unit_price DECIMAL(12,2) NOT NULL,
        final_unit_price DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_order_product (order_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready[$key] = true;
}

/** Exactly one offer applies to a product line: lowest price, then newest offer. */
function caz_promotion_for_product(mysqli $conn, int $productId, int $quantity): ?array {
    caz_promotions_schema($conn);
    if ($productId < 1 || $quantity < 1) return null;
    $stmt = $conn->prepare("SELECT cp.*, p.price AS original_unit_price,
        LEAST(p.price, COALESCE(cp.special_price,p.price)) AS unit_price,
        COALESCE(NULLIF(cp.image,''),p.image) AS offer_image
        FROM caz_promotions cp JOIN products p ON p.id=cp.product_id
        WHERE cp.product_id=? AND cp.minimum_quantity<=? AND cp.is_active=1
        AND (cp.start_date IS NULL OR cp.start_date<=CURRENT_DATE)
        AND (cp.end_date IS NULL OR cp.end_date>=CURRENT_DATE)
        ORDER BY unit_price ASC, cp.id DESC LIMIT 1");
    $stmt->bind_param('ii', $productId, $quantity);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    return [
        'id'=>(int)$row['id'], 'title'=>$row['title'], 'benefit'=>$row['benefit'],
        'minimum_quantity'=>(int)$row['minimum_quantity'],
        'unit_price'=>(float)$row['unit_price'],
        'original_unit_price'=>(float)$row['original_unit_price'],
        'image'=>$row['offer_image'] ?? ''
    ];
}

/** Validate and aggregate duplicate product lines before checking thresholds or stock. */
function caz_order_quantities($items): array {
    if (!is_array($items) || !$items || count($items)>100) {
        throw new InvalidArgumentException('Choose between 1 and 100 products.');
    }
    $quantities = [];
    foreach ($items as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Invalid product selection.');
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $qty = filter_var($item['quantity'] ?? ($item['qty'] ?? null), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100000]]);
        if ($id === false || $qty === false || $id === null || $qty === null) {
            throw new InvalidArgumentException('Product quantities must be positive whole numbers.');
        }
        $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
        if ($quantities[$id]>100000) throw new InvalidArgumentException('The requested quantity is too large.');
    }
    ksort($quantities, SORT_NUMERIC);
    return $quantities;
}

/** Read authoritative product prices. $lock=true is only used inside checkout transactions. */
function caz_quote_order(mysqli $conn, array $quantities, bool $lock=false): array {
    caz_promotions_schema($conn);
    $items = [];
    $totalCents = 0;
    $stmt = $conn->prepare('SELECT id,product_name,price,stock,image,category FROM products WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
    foreach ($quantities as $id=>$quantity) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!$p) throw new InvalidArgumentException('A selected product is no longer available.');
        if ((int)$p['stock']<$quantity) {
            throw new InvalidArgumentException($p['product_name'] . ': only ' . max(0,(int)$p['stock']) . ' unit(s) available.');
        }
        $promotion = caz_promotion_for_product($conn,(int)$id,(int)$quantity);
        $unitCents = (int)round(100*($promotion ? $promotion['unit_price'] : (float)$p['price']));
        if ($unitCents<0) throw new RuntimeException('Invalid stored product price.');
        $totalCents += $unitCents*$quantity;
        if ($totalCents>99999999999) throw new InvalidArgumentException('Order total is too large.');
        $items[] = ['id'=>(int)$id,'quantity'=>(int)$quantity,'price'=>$unitCents/100,
            'original_price'=>(float)$p['price'],'title'=>$p['product_name'],'image'=>$p['image'],
            'stock'=>(int)$p['stock'],'category'=>$p['category'],'promotion'=>$promotion];
    }
    $stmt->close();
    return ['items'=>$items, 'total_amount'=>$totalCents/100];
}

function caz_promo_image_url($image): string {
    $value = str_replace('\\','/',trim((string)$image));
    if ($value==='' || strpos($value,'..')!==false || !preg_match('~^[a-zA-Z0-9_ ./()-]+\.(png|jpe?g|webp|gif)$~i',$value)) {
        return '../images/cazlogo.png';
    }
    return '../images/' . implode('/',array_map('rawurlencode',explode('/',$value)));
}

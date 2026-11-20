<?php
/** Product facts come directly from the saved catalog; unknown specifications stay omitted. */
function caz_product_facts(array $product, bool $compact = false): array
{
    $facts = [];
    foreach (['brand' => 'Brand', 'category' => 'Category', 'type' => 'Product type'] as $key => $label) {
        $value = trim((string)($product[$key] ?? ''));
        if ($value !== '' && !($compact && $key === 'category')) {
            $facts[$label] = $value;
        }
    }
    $spec = trim((string)($product['spec'] ?? ''));
    if ($spec !== '') {
        $label = 'Specification';
        if (preg_match('/^\d+(?:\.\d+)?\s*MP$/i', $spec)) $label = 'Resolution';
        elseif (preg_match('/^\d+(?:\.\d+)?\s*W$/i', $spec)) $label = 'Listed power';
        $facts[$label] = $spec;
    }
    if (!$compact) {
        if (isset($product['price'])) $facts['Price per unit'] = '₱' . number_format((float)$product['price'], 2);
        if (isset($product['stock'])) {
            $stock = max(0, (int)$product['stock']);
            $facts['Availability'] = $stock > 0 ? $stock . ' ' . ($stock === 1 ? 'unit' : 'units') . ' in stock' : 'Currently out of stock';
        }
    }
    return $facts;
}

/** Admins can enter a paragraph or one fact per line, with or without bullet markers. */
function caz_product_description_points(?string $description): array
{
    $lines = preg_split('/\R|[•●]/u', trim($description ?? '')) ?: [];
    $points = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/^\s*(?:[-*–]\s+|\d+[.)]\s+)/u', '', $line) ?? $line);
        if ($line !== '' && !in_array($line, $points, true)) $points[] = $line;
    }
    return $points;
}

function caz_render_product_facts(array $product, bool $compact = false): string
{
    $facts = caz_product_facts($product, $compact);
    if (!$facts) return '';
    $html = '<ul class="caz-product-facts' . ($compact ? ' caz-product-facts-compact' : '') . '">';
    foreach ($facts as $label => $value) {
        $html .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    return $html . '</ul>';
}

function caz_render_product_description(?string $description): string
{
    $points = caz_product_description_points($description);
    if (!$points) return '<p class="caz-product-empty">Additional product details have not been provided yet.</p>';
    $html = '<ul class="caz-product-description">';
    foreach ($points as $point) $html .= '<li>' . htmlspecialchars($point, ENT_QUOTES, 'UTF-8') . '</li>';
    return $html . '</ul>';
}

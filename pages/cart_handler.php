<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';

if (empty($_SESSION['user_id'])) {
    caz_json(['status'=>'error', 'success'=>false, 'message'=>'Please sign in to save your cart.'], 401);
}

$userId = (int)$_SESSION['user_id'];
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function caz_cart_items(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare('SELECT ci.id, ci.product_id, ci.quantity, p.product_name, p.price, p.image, p.category, p.stock
        FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.user_id=? ORDER BY ci.id');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $count = 0;
    foreach ($items as &$item) {
        foreach (['id','product_id','quantity','stock'] as $key) $item[$key] = (int)$item[$key];
        $item['price'] = (float)$item['price'];
        $count += max(0, $item['quantity']);
    }
    unset($item);
    return ['items'=>$items, 'count'=>$count];
}

function caz_cart_integer($value, string $label, bool $positive = true): int {
    if ((!is_int($value) && !is_string($value)) ||
        !preg_match($positive ? '/^[1-9][0-9]*$/D' : '/^-?[1-9][0-9]*$/D', (string)$value) ||
        filter_var($value, FILTER_VALIDATE_INT) === false ||
        abs((float)$value) > 2147483647) {
        throw new InvalidArgumentException($label . ' must be a ' . ($positive ? 'positive ' : 'non-zero ') . 'whole number.');
    }
    return (int)$value;
}

if ($action === 'get' && $method !== 'GET') {
    header('Allow: GET');
    caz_json(['status'=>'error', 'success'=>false, 'message'=>'Use GET to view your cart.'], 405);
}
if ($action !== 'get' && !in_array($action, ['add','update','remove','clear'], true)) {
    caz_json(['status'=>'error', 'success'=>false, 'message'=>'Unknown cart action.'], 400);
}
if ($action !== 'get' && $method !== 'POST') {
    header('Allow: POST');
    caz_json(['status'=>'error', 'success'=>false, 'message'=>'Use POST to change your cart.'], 405);
}
if ($action !== 'get') caz_check_csrf();

$inTransaction = false;
try {
    if ($action === 'get') {
        caz_json(['status'=>'success', 'success'=>true] + caz_cart_items($conn, $userId));
    }
    $data = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('Send a valid cart request.');

    $productId = $action === 'add' ? caz_cart_integer($data['product_id'] ?? null, 'Product') : null;
    $itemId = in_array($action, ['update','remove'], true) ? caz_cart_integer($data['item_id'] ?? null, 'Cart item') : null;
    $quantity = $action === 'add' ? caz_cart_integer($data['quantity'] ?? 1, 'Quantity') : null;
    $delta = null;
    if ($action === 'update') {
        if (array_key_exists('delta', $data) && array_key_exists('quantity', $data)) {
            throw new InvalidArgumentException('Choose either a quantity or a quantity change.');
        }
        if (array_key_exists('delta', $data)) $delta = caz_cart_integer($data['delta'], 'Quantity change', false);
        else $quantity = caz_cart_integer($data['quantity'] ?? null, 'Quantity');
    }

    $conn->begin_transaction();
    $inTransaction = true;
    // Cart edits and checkout lock the account before product rows, including separate browsers.
    $stmt = $conn->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) throw new RuntimeException('Please sign in again.', 401);
    $stmt->close();

    if ($action === 'clear') {
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE user_id=?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $message = 'Your cart is now empty.';
    } elseif ($action === 'remove') {
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $itemId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) throw new RuntimeException('This item is no longer in your cart. Refresh your cart.', 404);
        $message = 'Item removed from your cart.';
    } else {
        if ($action === 'update') {
            $stmt = $conn->prepare('SELECT product_id, quantity FROM cart_items WHERE id=? AND user_id=?');
            $stmt->bind_param('ii', $itemId, $userId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            if (!$existing) throw new RuntimeException('This item is no longer in your cart. Refresh your cart.', 404);
            $productId = (int)$existing['product_id'];
            if ($delta !== null) $quantity = (int)$existing['quantity'] + $delta;
            if ($quantity < 1 || $quantity > 2147483647) throw new InvalidArgumentException('Quantity must be at least 1. Use Remove to delete the item.');
        }
        $stmt = $conn->prepare('SELECT stock FROM products WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) throw new RuntimeException('This product is no longer available.', 404);
        $stock = (int)$product['stock'];

        if ($action === 'add') {
            $stmt = $conn->prepare('SELECT id, quantity FROM cart_items WHERE user_id=? AND product_id=? FOR UPDATE');
            $stmt->bind_param('ii', $userId, $productId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            if ($existing) {
                $itemId = (int)$existing['id'];
                $quantity += max(0, (int)$existing['quantity']);
            }
        }
        // Stock may fall after a cart was saved. Let customers reduce that quantity
        // step by step; additions and increases must always fit available stock.
        $isDecrease = $action === 'update' && $quantity < (int)$existing['quantity'];
        if ($stock < 1 && !$isDecrease) throw new InvalidArgumentException('This product is currently out of stock.');
        if ($quantity > $stock && !$isDecrease) throw new InvalidArgumentException('Only ' . $stock . ' item' . ($stock === 1 ? ' is' : 's are') . ' available. Please lower the quantity.');

        if ($itemId !== null) {
            $stmt = $conn->prepare('UPDATE cart_items SET quantity=? WHERE id=? AND user_id=?');
            $stmt->bind_param('iii', $quantity, $itemId, $userId);
        } else {
            $stmt = $conn->prepare('INSERT INTO cart (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)');
            $stmt->bind_param('iii', $userId, $productId, $quantity);
        }
        $stmt->execute();
        if ($itemId === null) $itemId = (int)$conn->insert_id;
        $message = $action === 'add' ? 'Item added to your saved cart.' : 'Cart quantity updated.';
    }
    $stmt->close();
    $snapshot = caz_cart_items($conn, $userId);
    $conn->commit();
    $inTransaction = false;
    $response = ['status'=>'success', 'success'=>true, 'message'=>$message] + $snapshot;
    if ($action === 'add') $response['cart_item_id'] = $itemId;
    caz_json($response);
} catch (InvalidArgumentException | JsonException $e) {
    if ($inTransaction) $conn->rollback();
    caz_json(['status'=>'error', 'success'=>false, 'message'=>$e->getMessage()], 422);
} catch (Throwable $e) {
    if ($inTransaction) $conn->rollback();
    $status = in_array($e->getCode(), [401,404], true) ? $e->getCode() : 500;
    if ($status === 500) error_log('CAZ cart: ' . $e->getMessage());
    caz_json(['status'=>'error', 'success'=>false, 'message'=>$status === 500 ? 'Your cart could not be saved. Please try again.' : $e->getMessage()], $status);
}

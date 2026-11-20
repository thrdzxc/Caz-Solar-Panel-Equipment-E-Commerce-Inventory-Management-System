<?php
require_once __DIR__ . '/../includes/feature_bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';
caz_require_user(false, true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') caz_json(['success'=>false,'message'=>'Use POST.'],405);
caz_check_csrf();
try {
    $data = json_decode(file_get_contents('php://input'), true, 64, JSON_THROW_ON_ERROR);
    $quantities = caz_order_quantities($data['items'] ?? null);
    $quote = caz_quote_order($conn,$quantities);
    caz_json(['success'=>true,'status'=>'success'] + $quote);
} catch (InvalidArgumentException | JsonException $e) {
    caz_json(['success'=>false,'status'=>'error','message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('CAZ promotion quote: ' . $e->getMessage());
    caz_json(['success'=>false,'status'=>'error','message'=>'Unable to refresh prices. Please try again.'],500);
}

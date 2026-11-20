<?php
declare(strict_types=1);

/** Prepared public store guidance; no customer records or generated answers. */
function caz_assistant_questions(): array {
    return [
        'business' => 'What does CAZ offer?',
        'products' => 'How do I choose a CCTV or solar product?',
        'offers' => 'What promotions are available now?',
        'promotion_rules' => 'How do I qualify for a promotion?',
        'installation' => 'Does CAZ provide installation services?',
        'ordering' => 'How can I place an order?',
        'verification' => 'What do I need to register and get verified?',
        'delivery' => 'How do I set my delivery location?',
        'location' => 'Where is the store and how do I get directions?',
        'admin' => 'How can I talk to the CAZ admin?',
    ];
}

function caz_assistant_answer(string $id, mysqli $conn): string {
    switch ($id) {
        case 'business':
            return 'CAZ Solar & Equipments provides solar energy solutions, CCTV equipment and installation services for homes and businesses. The system lets you browse products and promotions, place orders and contact the store team.';
        case 'products':
            return 'After signing in, open CCTV or SOLAR. Filter by product type, resolution or watts, brand and availability, then open View full details to compare specifications, price and stock. For a setup suited to your property, use Message admin and describe your needs; product suitability should be confirmed with the team.';
        case 'offers':
            $offers = $conn->query("SELECT cp.title,cp.benefit,cp.minimum_quantity,p.product_name,
                LEAST(p.price,COALESCE(cp.special_price,p.price)) AS unit_price
                FROM caz_promotions cp JOIN products p ON p.id=cp.product_id
                WHERE cp.is_active=1 AND (cp.start_date IS NULL OR cp.start_date<=CURRENT_DATE)
                AND (cp.end_date IS NULL OR cp.end_date>=CURRENT_DATE)
                ORDER BY cp.updated_at DESC,cp.id DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
            if (!$offers) return 'There are no active promotions right now. This public page shows the offers published by the admin. You can still sign in to browse products or ask the admin about upcoming offers.';
            $lines = [];
            foreach (array_slice($offers, 0, 3) as $offer) {
                $lines[] = $offer['title'] . ' — ' . $offer['product_name'] . ': minimum ' . (int)$offer['minimum_quantity']
                    . ' units, ₱' . number_format((float)$offer['unit_price'], 2) . ' per unit. Included benefit: ' . $offer['benefit'] . '.';
            }
            return "Here are current offers from the store:\n\n" . implode("\n\n", $lines)
                . (count($offers) > 3 ? "\n\nMore offers are listed on this page." : '')
                . "\n\nRead each offer's conditions and check stock before ordering. Offers and availability may change.";
        case 'promotion_rules':
            return 'Buy at least the minimum quantity of the listed product in one order while the offer is active. Eligible offers apply automatically at checkout. Offers do not stack: the lowest eligible unit price applies; equal prices use the newest offer. Installation benefits are recorded with your order for the team to arrange. Read the offer card for its specific conditions.';
        case 'installation':
            return 'CAZ provides CCTV installation and solar energy solutions. Some promotions include installation when their minimum purchase and stated conditions are met. Free installation is not included with every purchase. Message the admin to confirm site coverage, scheduling and any additional charges before ordering.';
        case 'ordering':
            return 'Sign in, open a product, choose a quantity and select Add to Cart or Buy Now. Review your selected items and delivery details at checkout, then submit the order. The system checks stock and account eligibility. You can follow its progress under Cart & orders. Newly registered accounts need admin verification before they can place orders.';
        case 'verification':
            return 'Create a customer account with your contact details, a contact person and a clear valid photo ID. Accepted options include a driver’s license, National ID (PhilSys), passport and other supported photo IDs. Use JPG, PNG or WebP up to 8 MB, at least 800 × 500 pixels in either orientation. Enter the case-sensitive CAPTCHA. Images with obvious quality problems are rejected; admin review is still needed to approve the account. If asked for a new photo, replace it under Verification.';
        case 'delivery':
            return 'Add or update your delivery address in Account, then select the saved address during checkout. You can also choose an accurate delivery pin. If you allow location access, the map can suggest your position; check it before saving. Ask the admin to confirm delivery coverage, fees and timing for your location; the chatbot cannot promise a delivery date.';
        case 'location':
            require_once __DIR__ . '/store_location.php';
            $store = caz_store_location($conn);
            return 'Store address: ' . $store['address'] . '. After signing in, open Contact and choose Get directions in Google Maps. '
                . (caz_store_has_pin($store)
                    ? 'Directions use the entrance pin saved by the admin. Choose your starting point in Google Maps for the route, distance and travel time.'
                    : 'An exact entrance pin has not been published yet, so directions use the written address. Please confirm the entrance with the admin before visiting.');
        case 'admin':
            return 'Choose Message admin below. Visitors must log in to a customer account first, then the system opens Messages. Signed-in customers go directly to their conversation. Write your question and press Send to contact the team. These prepared chatbot answers are separate from admin messages and do not send anything to the admin automatically.';
        default:
            throw new InvalidArgumentException('Choose one of the suggested questions.');
    }
}

function caz_assistant_state(): array {
    $history = $_SESSION['caz_public_assistant']['history'] ?? [];
    $customer = !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'customer';
    $questions = [];
    foreach (caz_assistant_questions() as $id => $question) $questions[] = ['id'=>$id, 'question'=>$question];
    return ['csrf'=>caz_csrf_token(), 'limit'=>10, 'used'=>count($history), 'remaining'=>max(0, 10-count($history)),
        'questions'=>$questions, 'history'=>$history,
        'handoff'=>['url'=>$customer ? 'messages.php' : '../login.php?role=customer&next=pages%2Fmessages.php',
            'label'=>$customer ? 'Message admin' : 'Log in to message admin', 'requiresLogin'=>!$customer]];
}

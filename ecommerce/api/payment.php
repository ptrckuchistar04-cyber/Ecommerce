<?php
/**
 * Xendit Invoice creation endpoint.
 * POST from checkout.php; creates order, calls Xendit, redirects user to Xendit invoice URL.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('../login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../checkout.php'); exit; }
csrfCheck();

$uid = currentUserId();

// Load cart
$cart = getCartItems($uid);
if (empty($cart)) { header('Location: ../cart.php'); exit; }

$total = 0; foreach ($cart as $c) $total += (float)$c['reservation_fee'];

// ---- Idempotency: reuse a recent unpaid invoice instead of creating a duplicate ----
// If the user already has a pending order for the same total created in the last hour,
// just send them back to that existing Xendit invoice (handles refresh / double-click).
$dupe = db()->prepare(
   "SELECT order_number FROM transactions
    WHERE user_id = ? AND status = 'pending' AND total_reservation_fee = ?
      AND xendit_invoice_url IS NOT NULL
      AND created_at >= (NOW() - INTERVAL 1 HOUR)
    ORDER BY created_at DESC LIMIT 1");
$dupe->execute([$uid, $total]);
if ($existingOrder = $dupe->fetchColumn()) {
    header('Location: ../pay.php?order=' . urlencode($existingOrder));
    exit;
}

$orderNumber = generateOrderNumber();

// Fetch user (for invoice payer info)
$ustmt = db()->prepare("SELECT full_name, email, phone FROM users WHERE id=?");
$ustmt->execute([$uid]); $user = $ustmt->fetch();

try {
    db()->beginTransaction();

    // ---- Race-condition guard: make sure every cart item is still available ----
    // Lock the listing rows so a concurrent checkout can't grab the same item.
    $ids = array_map(fn($c) => (int)$c['id'], $cart);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $chk = db()->prepare("SELECT id, title, status FROM listings WHERE id IN ($ph) FOR UPDATE");
    $chk->execute($ids);
    foreach ($chk->fetchAll() as $row) {
        if ($row['status'] !== 'available') {
            db()->rollBack();
            $_SESSION['flash_error'] = '"' . $row['title'] . '" is no longer available. Please remove it from your cart.';
            header('Location: ../cart.php');
            exit;
        }
    }

    // Create transaction
    $tx = db()->prepare(
       "INSERT INTO transactions (user_id, order_number, total_reservation_fee, status)
        VALUES (?,?,?, 'pending')");
    $tx->execute([$uid, $orderNumber, $total]);
    $txId = (int)db()->lastInsertId();

    $item = db()->prepare(
       "INSERT INTO transaction_items (transaction_id, listing_id, reservation_fee) VALUES (?,?,?)");
    foreach ($cart as $c) $item->execute([$txId, (int)$c['id'], (float)$c['reservation_fee']]);

    // ---- Call Xendit Invoice API ----
    $payload = [
        'external_id'      => $orderNumber,
        'amount'           => $total,
        'description'      => SITE_NAME . ' reservation (' . count($cart) . ' item' . (count($cart)>1?'s':'') . ')',
        'payer_email'      => $user['email'] ?? null,
        'customer'         => [
            'given_names' => $user['full_name'] ?? 'Customer',
            'email'       => $user['email'] ?? '',
            'mobile_number' => $user['phone'] ?? '',
        ],
        'success_redirect_url' => XENDIT_SUCCESS_URL . '?order=' . $orderNumber,
        'failure_redirect_url' => XENDIT_FAILURE_URL,
        'currency'         => 'PHP',
        'items' => array_map(function($c) {
            return [
                'name'     => $c['title'],
                'quantity' => 1,
                'price'    => (float)$c['reservation_fee'],
                'category' => $c['type'] === 'property' ? 'Real Estate' : 'Vehicle',
            ];
        }, $cart),
    ];

    $ch = curl_init(XENDIT_API_BASE . '/v2/invoices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(XENDIT_SECRET_KEY . ':'),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($code < 200 || $code >= 300 || !$resp) {
        throw new RuntimeException("Xendit error (HTTP $code): " . ($cerr ?: $resp));
    }
    $data = json_decode($resp, true);
    if (empty($data['invoice_url']) || empty($data['id'])) {
        throw new RuntimeException("Xendit response invalid: " . $resp);
    }

    // Save invoice details
    $upd = db()->prepare("UPDATE transactions SET xendit_invoice_id=?, xendit_invoice_url=? WHERE id=?");
    $upd->execute([$data['id'], $data['invoice_url'], $txId]);

    // Log
    $log = db()->prepare("INSERT INTO payment_logs (transaction_id, invoice_id, status, payload) VALUES (?,?,?,?)");
    $log->execute([$txId, $data['id'], 'INVOICE_CREATED', $resp]);

    // NOTE: cart is intentionally NOT cleared here. We only clear it once payment
    // is confirmed (see xendit_webhook.php). This way an abandoned/failed payment
    // leaves the cart intact so the customer can retry.

    db()->commit();

    // Send the buyer to our QR payment page (which shows the QR + a "Pay on Xendit"
    // button and polls for confirmation). This works without a public webhook URL.
    header('Location: ../pay.php?order=' . urlencode($orderNumber));
    exit;

} catch (Throwable $e) {
    db()->rollBack();
    error_log('Payment error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not start payment. Please try again or contact support.';
    header('Location: ../checkout.php');
    exit;
}

<?php
/**
 * Creates a Xendit invoice for a seller's promo fee on their own approved listing.
 * POST from promo.php → creates invoice → redirects to promo.php?listing=ID (QR page).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('../login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../profile.php'); exit; }
csrfCheck();

if (!hasPromoFields()) {
    $_SESSION['flash_error'] = 'Promo feature not available — apply database_PATCH_v4.sql first.';
    header('Location: ../profile.php'); exit;
}

$uid = currentUserId();
$lid = (int)($_POST['listing_id'] ?? 0);

// The listing must belong to this seller and be available + not already promoted.
$stmt = db()->prepare("SELECT * FROM listings WHERE id=? AND seller_id=?");
$stmt->execute([$lid, $uid]);
$listing = $stmt->fetch();
if (!$listing) { $_SESSION['flash_error'] = 'Listing not found or not yours.'; header('Location: ../profile.php'); exit; }
if ($listing['is_promo']) { header('Location: ../profile.php'); exit; }

// Reuse a recent pending promo invoice if one exists (idempotency).
$dupe = db()->prepare(
   "SELECT id FROM promo_payments
    WHERE listing_id=? AND seller_id=? AND status='pending' AND xendit_invoice_url IS NOT NULL
      AND created_at >= (NOW() - INTERVAL 1 HOUR) ORDER BY created_at DESC LIMIT 1");
$dupe->execute([$lid, $uid]);
if ($dupe->fetchColumn()) { header('Location: ../promo.php?listing=' . $lid); exit; }

$fee = promoFeeFor($listing);
$orderNumber = 'PROMO-' . strtoupper(bin2hex(random_bytes(5)));

$ustmt = db()->prepare("SELECT full_name, email, phone FROM users WHERE id=?");
$ustmt->execute([$uid]); $user = $ustmt->fetch();

try {
    db()->beginTransaction();

    $ins = db()->prepare(
       "INSERT INTO promo_payments (listing_id, seller_id, order_number, amount, status)
        VALUES (?,?,?,?, 'pending')");
    $ins->execute([$lid, $uid, $orderNumber, $fee]);
    $pid = (int)db()->lastInsertId();

    // Mark the listing as awaiting promo payment.
    db()->prepare("UPDATE listings SET promo_status='pending_payment', promo_fee=? WHERE id=?")->execute([$fee, $lid]);

    $payload = [
        'external_id' => $orderNumber,
        'amount'      => $fee,
        'description' => SITE_NAME . ' promo fee — ' . $listing['title'],
        'payer_email' => $user['email'] ?? null,
        'customer'    => [
            'given_names'   => $user['full_name'] ?? 'Seller',
            'email'         => $user['email'] ?? '',
            'mobile_number' => $user['phone'] ?? '',
        ],
        'success_redirect_url' => SITE_URL . 'promo.php?listing=' . $lid,
        'failure_redirect_url' => SITE_URL . 'promo.php?listing=' . $lid . '&status=failed',
        'currency' => 'PHP',
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

    if ($code < 200 || $code >= 300 || !$resp) throw new RuntimeException("Xendit error (HTTP $code): " . ($cerr ?: $resp));
    $data = json_decode($resp, true);
    if (empty($data['invoice_url']) || empty($data['id'])) throw new RuntimeException("Xendit invalid: " . $resp);

    db()->prepare("UPDATE promo_payments SET xendit_invoice_id=?, xendit_invoice_url=? WHERE id=?")
        ->execute([$data['id'], $data['invoice_url'], $pid]);

    db()->commit();
    header('Location: ../promo.php?listing=' . $lid);
    exit;

} catch (Throwable $e) {
    db()->rollBack();
    error_log('Promo payment error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not start the promo payment. Please try again.';
    header('Location: ../profile.php');
    exit;
}

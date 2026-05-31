<?php
/**
 * Xendit Webhook receiver.
 * Configure URL in Xendit dashboard: https://your-domain/ecommerce/xendit_webhook.php
 * And set the X-CALLBACK-TOKEN to the value in config.php (XENDIT_CALLBACK_TOKEN).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$headers = function_exists('getallheaders') ? getallheaders() : [];
$received = $headers['X-Callback-Token'] ?? $headers['x-callback-token'] ?? ($_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '');

if (!hash_equals(XENDIT_CALLBACK_TOKEN, $received)) {
    http_response_code(403);
    error_log("Xendit webhook unauthorized. Received: '$received'");
    die('Unauthorized');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { http_response_code(400); die('Invalid JSON'); }

$externalId = $data['external_id'] ?? null;
$status     = $data['status'] ?? null;
$invoiceId  = $data['id'] ?? null;

if (!$externalId || !$status) { http_response_code(400); die('Missing fields'); }

try {
    db()->beginTransaction();

    $stmt = db()->prepare("SELECT id, status FROM transactions WHERE order_number=?");
    $stmt->execute([$externalId]);
    $tx = $stmt->fetch();

    if ($tx) {
        if ($status === 'PAID') {
            db()->prepare("UPDATE transactions SET status='paid', updated_at=NOW() WHERE id=?")->execute([$tx['id']]);
            db()->prepare(
              "UPDATE listings l JOIN transaction_items ti ON l.id=ti.listing_id
               SET l.status='reserved', l.updated_at=NOW()
               WHERE ti.transaction_id=?")->execute([$tx['id']]);
        } elseif (in_array($status, ['EXPIRED','FAILED'], true)) {
            db()->prepare("UPDATE transactions SET status='".($status==='EXPIRED'?'expired':'cancelled')."', updated_at=NOW() WHERE id=?")
                ->execute([$tx['id']]);
        }
        db()->prepare("INSERT INTO payment_logs (transaction_id, invoice_id, status, payload) VALUES (?,?,?,?)")
            ->execute([$tx['id'], $invoiceId, $status, $raw]);
    } else {
        // Log even if order missing
        db()->prepare("INSERT INTO payment_logs (transaction_id, invoice_id, status, payload) VALUES (NULL,?,?,?)")
            ->execute([$invoiceId, $status, $raw]);
    }

    db()->commit();
    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    db()->rollBack();
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error';
}

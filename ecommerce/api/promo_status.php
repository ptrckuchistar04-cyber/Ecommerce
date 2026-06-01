<?php
/** Returns a promo payment's status, reconciling with Xendit if still pending. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) jsonOut(['status' => 'unauthorized'], 401);
if (!hasPromoFields()) jsonOut(['status' => 'unavailable']);

$uid = currentUserId();
$lid = (int)($_GET['listing'] ?? 0);

$pp = db()->prepare("SELECT * FROM promo_payments WHERE listing_id=? AND seller_id=? ORDER BY created_at DESC LIMIT 1");
$pp->execute([$lid, $uid]);
$promo = $pp->fetch();
if (!$promo) jsonOut(['status' => 'none']);

jsonOut(['status' => syncPromoWithXendit($promo)]);

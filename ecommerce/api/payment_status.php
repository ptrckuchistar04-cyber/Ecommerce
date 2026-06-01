<?php
/**
 * Returns the current status of an order, reconciling with Xendit if still
 * pending. Used by pay.php to auto-confirm payment without a public webhook.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) jsonOut(['status' => 'unauthorized'], 401);

$num = $_GET['order'] ?? '';
if ($num === '') jsonOut(['status' => 'error', 'message' => 'missing order'], 400);

$stmt = db()->prepare("SELECT * FROM transactions WHERE order_number=? AND user_id=?");
$stmt->execute([$num, currentUserId()]);
$order = $stmt->fetch();
if (!$order) jsonOut(['status' => 'error', 'message' => 'not found'], 404);

$status = syncTransactionWithXendit($order);
jsonOut(['status' => $status, 'order' => $num]);

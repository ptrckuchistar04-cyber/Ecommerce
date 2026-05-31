<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$num = $_GET['order'] ?? '';
if (!$num) { header('Location: index.php'); exit; }

$stmt = db()->prepare("SELECT * FROM transactions WHERE order_number=? AND user_id=?");
$stmt->execute([$num, currentUserId()]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit; }

$items = db()->prepare(
  "SELECT ti.reservation_fee, l.title, l.type FROM transaction_items ti
   JOIN listings l ON l.id = ti.listing_id WHERE ti.transaction_id=?");
$items->execute([$order['id']]);
$rows = $items->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto px-6 py-16 text-center">
  <div class="text-7xl mb-4 animate-float"><?= $order['status']==='paid'?'✅':'⏳' ?></div>
  <h1 class="font-display text-3xl font-bold text-navy"><?= $order['status']==='paid' ? 'Reservation Confirmed!' : 'Awaiting Payment' ?></h1>
  <p class="text-ink/60 mt-1">Order #<?= e($order['order_number']) ?></p>

  <div class="bg-white rounded-3xl shadow-deep p-6 mt-8 text-left">
    <div class="flex justify-between text-sm mb-3">
      <span class="text-ink/70">Date</span>
      <span class="font-semibold"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
    </div>
    <div class="flex justify-between text-sm mb-3">
      <span class="text-ink/70">Status</span>
      <span class="chip bg-emerald-100 text-emerald-700"><?= ucfirst($order['status']) ?></span>
    </div>
    <hr class="my-3">
    <?php foreach ($rows as $r): ?>
      <div class="flex justify-between py-2">
        <div>
          <div class="font-semibold text-navy"><?= e($r['title']) ?></div>
          <div class="text-xs text-ink/60"><?= ucfirst($r['type']) ?></div>
        </div>
        <div class="font-bold text-orange"><?= money($r['reservation_fee']) ?></div>
      </div>
    <?php endforeach; ?>
    <hr class="my-3">
    <div class="flex justify-between font-bold text-lg"><span>Total</span><span class="text-orange"><?= money($order['total_reservation_fee']) ?></span></div>
  </div>

  <div class="mt-6 flex gap-3 justify-center">
    <a href="listing.php" class="btn btn-primary">Continue Browsing</a>
    <a href="orders.php" class="btn btn-ghost">My Orders →</a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

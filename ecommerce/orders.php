<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
if (isAdmin()) { header('Location: admin.php'); exit; }

$stmt = db()->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([currentUserId()]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy mb-6">📋 My Orders</h1>

  <?php if (empty($orders)): ?>
    <div class="bg-white rounded-3xl shadow-md p-16 text-center">
      <div class="text-6xl mb-3">📭</div>
      <h3 class="text-xl font-bold text-navy">No orders yet</h3>
      <p class="text-ink/60 mb-5">Start browsing to make your first reservation.</p>
      <a href="listing.php" class="btn btn-primary">Browse Listings</a>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($orders as $o):
        $color = ['paid'=>'emerald','pending'=>'amber','processing'=>'blue','completed'=>'emerald','cancelled'=>'rose','expired'=>'slate'][$o['status']] ?? 'slate';
      ?>
        <div class="bg-white rounded-2xl shadow-md p-5 flex flex-wrap items-center gap-4 justify-between">
          <div>
            <div class="font-bold text-navy">Order #<?= e($o['order_number']) ?></div>
            <div class="text-xs text-ink/60"><?= date('F j, Y g:i A', strtotime($o['created_at'])) ?></div>
          </div>
          <div class="font-bold text-orange text-lg"><?= money($o['total_reservation_fee']) ?></div>
          <span class="chip bg-<?= $color ?>-100 text-<?= $color ?>-700"><?= ucfirst($o['status']) ?></span>
          <?php if ($o['status']==='pending' && !empty($o['xendit_invoice_url'])): ?>
            <a href="<?= e($o['xendit_invoice_url']) ?>" class="btn btn-primary text-sm">Pay Now</a>
          <?php else: ?>
            <a href="order-success.php?order=<?= e($o['order_number']) ?>" class="btn btn-ghost text-sm">View</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

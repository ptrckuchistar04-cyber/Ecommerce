<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
if (isAdmin()) { header('Location: admin.php'); exit; }

$items = getCartItems(currentUserId());
if (empty($items)) { header('Location: cart.php'); exit; }
$total = 0; foreach ($items as $i) $total += (float)$i['reservation_fee'];

$pageTitle = 'Checkout — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy mb-6">💳 Checkout</h1>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>
  <?php if (($_GET['status'] ?? '')==='failed'): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl mb-4">Your last payment was not completed. You can try again below.</div>
  <?php endif; ?>

  <div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="font-display font-bold text-navy mb-4">Order Summary</h2>
      <?php foreach ($items as $it): ?>
        <div class="flex justify-between py-2 border-b last:border-0">
          <div>
            <div class="font-semibold text-navy"><?= e($it['title']) ?></div>
            <div class="text-xs text-ink/60"><?= ucfirst($it['type']) ?></div>
          </div>
          <div class="font-bold text-orange"><?= money($it['reservation_fee']) ?></div>
        </div>
      <?php endforeach; ?>
      <div class="flex justify-between font-bold text-lg mt-4 pt-4 border-t-2 border-navy/10">
        <span>Total</span><span class="text-orange"><?= money($total) ?></span>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-deep p-6">
      <h2 class="font-display font-bold text-navy mb-4">Payment</h2>
      <div class="bg-slate-50 rounded-xl p-4 mb-5 text-sm">
        <div class="flex items-center gap-2 mb-1"><span class="text-2xl">💰</span><span class="font-semibold text-navy">Pay securely with Xendit</span></div>
        <p class="text-ink/70">You'll get a <b>QR code to scan</b> (or a button to open Xendit's secure page) to pay via GCash, GrabPay, Maya, cards, online banking, or 7-Eleven.</p>
      </div>

      <form method="POST" action="api/payment.php">
        <?= csrfField() ?>
        <button class="btn btn-primary w-full justify-center text-lg py-4">
          Generate QR & Pay <?= money($total) ?> →
        </button>
      </form>

      <p class="text-xs text-ink/50 mt-4 text-center">By proceeding, you agree to our reservation terms. Reservation fees are <b>non-refundable</b> after 7 days.</p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

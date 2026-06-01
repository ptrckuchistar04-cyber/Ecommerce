<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$num = $_GET['order'] ?? '';
if (!$num) { header('Location: cart.php'); exit; }

$stmt = db()->prepare("SELECT * FROM transactions WHERE order_number=? AND user_id=?");
$stmt->execute([$num, currentUserId()]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit; }

// If somehow already paid, jump straight to the success page.
$status = syncTransactionWithXendit($order);
if ($status === 'paid' || $status === 'completed') {
    header('Location: order-success.php?order=' . urlencode($num));
    exit;
}

$invoiceUrl = $order['xendit_invoice_url'] ?? '';
// Build a scannable QR image of the Xendit invoice URL (hosted generator → always scans).
$qrSrc = $invoiceUrl
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=' . urlencode($invoiceUrl)
    : '';

$pageTitle = 'Scan to Pay — On The Line';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl mx-auto px-6 py-12">
  <div class="bg-white rounded-3xl shadow-deep p-8 text-center">
    <span class="chip bg-orange/15 text-orange border border-orange/30">TEST PAYMENT</span>
    <h1 class="font-display text-3xl font-bold text-navy mt-3">Scan to Pay</h1>
    <p class="text-ink/60 mt-1">Order #<?= e($order['order_number']) ?></p>
    <div class="text-3xl font-bold text-orange mt-2"><?= money($order['total_reservation_fee']) ?></div>

    <?php if ($qrSrc): ?>
      <div class="mt-6 inline-block p-4 bg-white rounded-2xl border-2 border-navy/10 shadow-md">
        <img src="<?= e($qrSrc) ?>" alt="Payment QR code" width="280" height="280"
             class="w-[280px] h-[280px] object-contain"
             onerror="this.parentElement.innerHTML='<p class=&quot;text-sm text-ink/60 p-8&quot;>QR needs internet. Use the button below instead.</p>'">
      </div>
      <p class="text-sm text-ink/70 mt-4">
        📱 Scan with your phone camera, or tap the button to open Xendit's secure checkout.
      </p>
    <?php else: ?>
      <p class="text-rose-600 mt-6">No invoice URL found for this order. Please try checkout again.</p>
    <?php endif; ?>

    <?php if ($invoiceUrl): ?>
      <a href="<?= e($invoiceUrl) ?>" target="_blank" rel="noopener"
         class="btn btn-primary w-full justify-center mt-6 text-lg py-4">
        💳 Pay on Xendit →
      </a>
    <?php endif; ?>

    <div id="payStatus" class="mt-6 text-sm text-ink/60">
      <span class="inline-flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
        Waiting for payment… this page checks automatically.
      </span>
    </div>

    <div class="mt-4 flex gap-3 justify-center">
      <button id="checkNow" class="btn btn-ghost text-sm">Check now</button>
      <a href="orders.php" class="btn btn-ghost text-sm">View my orders</a>
    </div>

    <p class="text-xs text-ink/40 mt-6">
      Tip: in Xendit test mode you can complete the payment with the simulated methods on the checkout page.
    </p>
  </div>
</div>

<script>
(function () {
  const order = <?= json_encode($order['order_number']) ?>;
  const statusEl = document.getElementById('payStatus');
  let tries = 0;

  async function check() {
    tries++;
    try {
      const r = await fetch('api/payment_status.php?order=' + encodeURIComponent(order), { cache: 'no-store' });
      const j = await r.json();
      if (j.status === 'paid' || j.status === 'completed') {
        statusEl.innerHTML = '<span class="text-emerald-600 font-semibold">✅ Payment complete! Redirecting…</span>';
        window.location.href = 'order-success.php?order=' + encodeURIComponent(order);
        return true;
      }
      if (j.status === 'expired' || j.status === 'cancelled') {
        statusEl.innerHTML = '<span class="text-rose-600 font-semibold">This invoice ' + j.status + '. Please checkout again.</span>';
        return true;
      }
    } catch (e) { /* keep polling */ }
    return false;
  }

  // Poll every 4s for ~10 minutes.
  const timer = setInterval(async () => {
    if (await check() || tries > 150) clearInterval(timer);
  }, 4000);

  document.getElementById('checkNow')?.addEventListener('click', check);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

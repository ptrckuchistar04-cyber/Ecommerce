<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

if (!hasPromoFields()) {
    $_SESSION['flash_error'] = 'Promo feature not available yet (run database_PATCH_v4.sql).';
    header('Location: profile.php'); exit;
}

$uid = currentUserId();
$lid = (int)($_GET['listing'] ?? 0);

$stmt = db()->prepare("SELECT * FROM listings WHERE id=? AND seller_id=?");
$stmt->execute([$lid, $uid]);
$listing = $stmt->fetch();
if (!$listing) { header('Location: profile.php'); exit; }

// Find the latest promo payment for this listing (if any).
$pp = db()->prepare("SELECT * FROM promo_payments WHERE listing_id=? AND seller_id=? ORDER BY created_at DESC LIMIT 1");
$pp->execute([$lid, $uid]);
$promo = $pp->fetch();

// Reconcile with Xendit if there's a pending invoice.
$promoStatus = $promo ? syncPromoWithXendit($promo) : 'none';

$fee = promoFeeFor($listing);
$invoiceUrl = $promo['xendit_invoice_url'] ?? '';
$qrSrc = $invoiceUrl
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=' . urlencode($invoiceUrl)
    : '';

$pageTitle = 'Promote Listing — On The Line';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl mx-auto px-6 py-12">
  <div class="bg-white rounded-3xl shadow-deep p-8 text-center">
    <span class="chip bg-orange/15 text-orange border border-orange/30">⭐ PROMOTE LISTING</span>
    <h1 class="font-display text-3xl font-bold text-navy mt-3">Feature "<?= e($listing['title']) ?>"</h1>
    <p class="text-ink/60 mt-1">Promo fee: <span class="font-bold text-orange"><?= money($fee) ?></span></p>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl my-4"><?= e($_SESSION['flash_error']) ?></div>
      <?php unset($_SESSION['flash_error']); endif; ?>

    <?php if ($listing['is_promo']): ?>
      <div class="mt-6 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-6">
        <div class="text-4xl mb-2">⭐</div>
        <div class="font-bold">This listing is already featured!</div>
      </div>
      <a href="profile.php" class="btn btn-ghost mt-6">← Back to profile</a>

    <?php elseif ($promoStatus === 'paid'): ?>
      <div class="mt-6 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-6">
        <div class="text-4xl mb-2">✅</div>
        <div class="font-bold">Promo fee paid!</div>
        <p class="text-sm mt-1">Our admin has been notified and will activate your promo shortly.</p>
      </div>
      <a href="profile.php" class="btn btn-ghost mt-6">← Back to profile</a>

    <?php elseif ($invoiceUrl && $promoStatus === 'pending'): ?>
      <!-- QR + pay button + polling -->
      <div class="mt-6 inline-block p-4 bg-white rounded-2xl border-2 border-navy/10 shadow-md">
        <img src="<?= e($qrSrc) ?>" alt="Promo payment QR" width="280" height="280"
             class="w-[280px] h-[280px] object-contain"
             onerror="this.parentElement.innerHTML='<p class=&quot;text-sm text-ink/60 p-8&quot;>QR needs internet. Use the button below.</p>'">
      </div>
      <p class="text-sm text-ink/70 mt-4">📱 Scan to pay, or open Xendit's secure page.</p>
      <a href="<?= e($invoiceUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary w-full justify-center mt-5 text-lg py-4">💳 Pay <?= money($fee) ?> on Xendit →</a>

      <div id="promoStatus" class="mt-5 text-sm text-ink/60">
        <span class="inline-flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Waiting for payment…</span>
      </div>
      <button id="checkNow" class="btn btn-ghost text-sm mt-3">Check now</button>

      <script>
      (function () {
        const listing = <?= (int)$lid ?>;
        const el = document.getElementById('promoStatus');
        let tries = 0;
        async function check() {
          tries++;
          try {
            const r = await fetch('api/promo_status.php?listing=' + listing, { cache:'no-store' });
            const j = await r.json();
            if (j.status === 'paid') { el.innerHTML = '<span class="text-emerald-600 font-semibold">✅ Paid! Reloading…</span>'; location.reload(); return true; }
            if (j.status === 'expired' || j.status === 'cancelled') { el.innerHTML = '<span class="text-rose-600 font-semibold">Invoice ' + j.status + '. Please try again.</span>'; return true; }
          } catch(e){}
          return false;
        }
        const t = setInterval(async()=>{ if (await check() || tries>150) clearInterval(t); }, 4000);
        document.getElementById('checkNow')?.addEventListener('click', check);
      })();
      </script>

    <?php else: ?>
      <!-- Start a new promo payment -->
      <div class="mt-6 bg-slate-50 rounded-xl p-4 text-sm text-ink/70">
        Featuring your listing puts it on the homepage carousel. Pay a one-time fee of <b><?= money($fee) ?></b>;
        once paid, our admin activates the promo.
      </div>
      <form method="POST" action="api/promo_payment.php" class="mt-5">
        <?= csrfField() ?>
        <input type="hidden" name="listing_id" value="<?= (int)$lid ?>">
        <button class="btn btn-primary w-full justify-center text-lg py-4">Generate QR & Pay <?= money($fee) ?> →</button>
      </form>
      <a href="profile.php" class="btn btn-ghost mt-3">← Back to profile</a>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

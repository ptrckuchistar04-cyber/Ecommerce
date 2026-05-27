<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
if (isAdmin()) { header('Location: admin.php'); exit; }

$items = getCartItems(currentUserId());
$total = 0; foreach ($items as $i) $total += (float)$i['reservation_fee'];

$pageTitle = 'My Cart — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy mb-6">🛒 Reservation Cart</h1>

  <?php if (!empty($_SESSION['flash_ok'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_ok']) ?></div>
    <?php unset($_SESSION['flash_ok']); endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

  <?php if (empty($items)): ?>
    <div class="bg-white rounded-3xl shadow-md p-16 text-center">
      <div class="text-6xl mb-3">🛒</div>
      <h3 class="text-xl font-bold text-navy mb-2">Your cart is empty</h3>
      <p class="text-ink/60 mb-5">Browse listings and add items to your reservation.</p>
      <a href="listing.php" class="btn btn-primary">Browse Listings</a>
    </div>
  <?php else: ?>
    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-3">
        <?php foreach ($items as $it):
          $img = listingImg($it['main_image'], $it['type']); ?>
          <div class="bg-white rounded-2xl shadow-md p-4 flex gap-4 items-center">
            <div class="w-24 h-20 rounded-xl overflow-hidden bg-slate-100 shrink-0">
              <img src="<?= e($img) ?>" alt="" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 min-w-0">
              <a href="listing.php?id=<?= (int)$it['id'] ?>" class="font-bold text-navy hover:text-orange line-clamp-1"><?= e($it['title']) ?></a>
              <div class="text-xs text-ink/60">
                <?= $it['type']==='property'?'🏠':'🚗' ?>
                <?php if ($it['type']==='property'): ?>
                  <?= e($it['square_meters']) ?>sqm • <?= e($it['bedrooms']) ?> beds
                <?php else: ?>
                  <?= e($it['make'].' '.$it['model']) ?> • <?= e($it['year']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-right">
              <div class="font-bold text-orange"><?= money($it['reservation_fee']) ?></div>
              <div class="text-xs text-ink/60">Reservation fee</div>
            </div>
            <form method="POST" action="api/cart.php?action=remove" onsubmit="return confirm('Remove this item?')">
              <?= csrfField() ?>
              <input type="hidden" name="cart_id" value="<?= (int)$it['cart_id'] ?>">
              <button class="text-rose-500 hover:bg-rose-50 rounded-lg p-2">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <aside class="bg-white rounded-2xl shadow-deep p-6 h-fit sticky top-24">
        <h2 class="font-display font-bold text-navy text-lg mb-4">Summary</h2>
        <div class="flex justify-between text-sm py-1"><span class="text-ink/70">Items</span><span class="font-semibold"><?= count($items) ?></span></div>
        <div class="flex justify-between text-sm py-1"><span class="text-ink/70">Subtotal</span><span class="font-semibold"><?= money($total) ?></span></div>
        <div class="border-t my-3"></div>
        <div class="flex justify-between font-bold text-lg"><span>Total</span><span class="text-orange"><?= money($total) ?></span></div>
        <a href="checkout.php" class="btn btn-primary w-full justify-center mt-5">Proceed to Checkout →</a>
        <a href="listing.php" class="btn btn-ghost w-full justify-center mt-2">Continue Browsing</a>
      </aside>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

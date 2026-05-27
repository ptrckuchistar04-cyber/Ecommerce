<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['clear'])) { clearCompare(); header('Location: compare.php'); exit; }
if (isset($_GET['remove'])) { removeCompare((int)$_GET['remove']); header('Location: compare.php'); exit; }

$items = getCompareItems();
$pageTitle = 'Compare — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-6 py-10">
  <div class="flex justify-between items-center mb-6">
    <h1 class="font-display text-3xl font-bold text-navy">⇆ Compare Listings</h1>
    <?php if (!empty($items)): ?>
      <a href="compare.php?clear=1" class="btn btn-ghost text-sm">Clear all</a>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
    <div class="bg-white rounded-3xl shadow-md p-16 text-center">
      <div class="text-6xl mb-3">⇆</div>
      <h3 class="text-xl font-bold text-navy">Nothing to compare yet</h3>
      <p class="text-ink/60 mb-5">Browse listings and click the <b>⇆ Compare</b> button on any card to add it here (up to <?= MAX_COMPARE_ITEMS ?>).</p>
      <a href="listing.php" class="btn btn-primary">Browse Listings</a>
    </div>
  <?php else: ?>

  <!-- Cards row -->
  <div class="grid gap-4" style="grid-template-columns: 180px repeat(<?= count($items) ?>, minmax(220px,1fr));">
    <div></div>
    <?php foreach ($items as $it): ?>
      <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="aspect-[4/3]"><img src="<?= e(listingImg($it['main_image'], $it['type'])) ?>" class="w-full h-full object-cover"></div>
        <div class="p-3">
          <div class="font-bold text-navy text-sm line-clamp-2"><?= e($it['title']) ?></div>
          <a href="compare.php?remove=<?= (int)$it['id'] ?>" class="text-xs text-rose-500 hover:underline">Remove</a>
        </div>
      </div>
    <?php endforeach; ?>

    <?php
    $rows = [
      ['Type',  fn($i) => $i['type']==='property'?'🏠 Property':'🚗 Vehicle'],
      ['Price', fn($i) => money($i['price'])],
      ['Reserve', fn($i) => money($i['reservation_fee'])],
      ['Location/Make', fn($i) => $i['type']==='property' ? ($i['location'] ?: '—') : ($i['make'].' '.$i['model'])],
      ['Area / Year',   fn($i) => $i['type']==='property' ? ($i['square_meters'].' sqm') : $i['year']],
      ['Bedrooms / Mileage', fn($i) => $i['type']==='property' ? $i['bedrooms'] : number_format((int)$i['mileage']).' km'],
      ['Bathrooms / Transmission', fn($i) => $i['type']==='property' ? $i['bathrooms'] : ucfirst((string)$i['transmission'])],
    ];
    foreach ($rows as [$label, $fn]): ?>
      <div class="font-semibold text-ink/70 self-center"><?= $label ?></div>
      <?php foreach ($items as $it): ?>
        <div class="bg-white rounded-xl shadow-sm p-3 text-sm text-navy"><?= e((string)$fn($it)) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

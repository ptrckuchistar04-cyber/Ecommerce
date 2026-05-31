<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $listing = getListing($id);
    if (!$listing) { header('Location: listing.php'); exit; }
    $amenities = getListingAmenities($id);
    $images = getListingImages($id);
    $pageTitle = $listing['title'] . ' — On The Line';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="max-w-7xl mx-auto px-6 py-10">
      <nav class="text-sm text-ink/60 mb-6">
        <a href="index.php" class="hover:text-orange">Home</a> /
        <a href="listing.php?type=<?= e($listing['type']) ?>" class="hover:text-orange"><?= ucfirst($listing['type']) ?></a> /
        <span class="text-navy font-semibold"><?= e($listing['title']) ?></span>
      </nav>

      <?php if (!empty($_SESSION['flash_ok'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_ok']) ?></div>
        <?php unset($_SESSION['flash_ok']); endif; ?>
      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); endif; ?>

      <div class="grid lg:grid-cols-2 gap-10">
        <!-- Image gallery -->
        <div data-reveal>
          <?php $allImgs = array_merge(
            $listing['main_image'] ? [$listing['main_image']] : [],
            $images ?: []
          ); $allImgs = array_unique($allImgs);
          if (!empty($allImgs)): ?>
            <div class="rounded-3xl overflow-hidden shadow-deep aspect-[4/3] bg-white mb-3">
              <img id="mainImg" src="<?= e($allImgs[0]) ?>" alt="<?= e($listing['title']) ?>" class="w-full h-full object-cover transition">
            </div>
            <?php if (count($allImgs) > 1): ?>
            <div class="flex gap-2 overflow-x-auto pb-2">
              <?php foreach ($allImgs as $idx => $img): ?>
                <img src="<?= e($img) ?>" onclick="document.getElementById('mainImg').src=this.src" class="w-20 h-16 rounded-xl object-cover border-2 cursor-pointer hover:border-orange transition <?= $idx===0?'border-orange':'border-transparent' ?>">
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="rounded-3xl overflow-hidden shadow-deep aspect-[4/3] bg-white">
              <img src="<?= e(listingImg($listing['main_image'], $listing['type'])) ?>" alt="<?= e($listing['title']) ?>" class="w-full h-full object-cover">
            </div>
          <?php endif; ?>
        </div>

        <div data-reveal>
          <span class="chip bg-navy text-white"><?= $listing['type']==='property'?'🏠 Property':'🚗 Vehicle' ?></span>
          <?php if ($listing['status'] !== 'available'): ?>
            <span class="chip bg-rose-100 text-rose-700 ml-2"><?= ucfirst($listing['status']) ?></span>
          <?php endif; ?>
          <h1 class="font-display text-3xl sm:text-4xl font-bold text-navy mt-3"><?= e($listing['title']) ?></h1>

          <div class="mt-5 flex items-baseline gap-3">
            <span class="text-3xl font-bold text-orange"><?= money($listing['price']) ?></span>
            <span class="text-ink/60">Reserve for <?= money($listing['reservation_fee']) ?></span>
          </div>

          <p class="mt-5 text-ink/80 leading-relaxed"><?= nl2br(e($listing['description'])) ?></p>

          <!-- Property details -->
          <?php if ($listing['type']==='property'): ?>
            <div class="mt-7 grid sm:grid-cols-2 gap-3 text-sm">
              <div class="glass rounded-xl p-3"><b>Type:</b> <?= e(ucfirst($listing['property_type'] ?? 'N/A')) ?></div>
              <div class="glass rounded-xl p-3"><b>Floor Area:</b> <?= e($listing['square_meters'] ?? '—') ?> sqm</div>
              <?php if (!empty($listing['lot_area'])): ?>
                <div class="glass rounded-xl p-3"><b>Lot Area:</b> <?= e($listing['lot_area']) ?> sqm</div>
              <?php endif; ?>
              <div class="glass rounded-xl p-3"><b>Bedrooms:</b> <?= e($listing['bedrooms'] ?? '—') ?></div>
              <div class="glass rounded-xl p-3"><b>Bathrooms:</b> <?= e($listing['bathrooms'] ?? '—') ?></div>
              <div class="glass rounded-xl p-3"><b>Floors:</b> <?= e($listing['floors'] ?? 1) ?></div>
              <?php if (!empty($listing['parking_slots'])): ?>
                <div class="glass rounded-xl p-3"><b>Parking:</b> <?= (int)$listing['parking_slots'] ?> slot(s)</div>
              <?php endif; ?>
              <?php if (!empty($listing['year_built'])): ?>
                <div class="glass rounded-xl p-3"><b>Year Built:</b> <?= e($listing['year_built']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['location'])): ?>
                <div class="glass rounded-xl p-3"><b>Location:</b> <?= e($listing['location']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['furnishing']) && $listing['furnishing'] !== 'unfurnished'): ?>
                <div class="glass rounded-xl p-3"><b>Furnishing:</b> <?= e(ucfirst($listing['furnishing'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['is_mortgaged'])): ?>
                <div class="glass rounded-xl p-3 bg-amber-50 border border-amber-200">
                  <b>🏦 Mortgaged</b>
                  <?php if (!empty($listing['monthly_amortization'])): ?>
                    <br>Amortization: <?= money($listing['monthly_amortization']) ?>/mo
                  <?php endif; ?>
                  <?php if (!empty($listing['mortgage_bank'])): ?>
                    <br>Bank: <?= e($listing['mortgage_bank']) ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

          <!-- Vehicle details -->
          <?php else: ?>
            <div class="mt-7 grid sm:grid-cols-2 gap-3 text-sm">
              <div class="glass rounded-xl p-3"><b>Make/Model:</b> <?= e(trim(($listing['make']??'').' '.($listing['model']??''))) ?></div>
              <div class="glass rounded-xl p-3"><b>Year:</b> <?= e($listing['year'] ?? '—') ?></div>
              <div class="glass rounded-xl p-3"><b>Mileage:</b> <?= number_format((int)($listing['mileage'] ?? 0)) ?> km</div>
              <div class="glass rounded-xl p-3"><b>Transmission:</b> <?= e(ucfirst($listing['transmission'] ?? '—')) ?></div>
              <div class="glass rounded-xl p-3"><b>Fuel:</b> <?= e($listing['fuel_type'] ?? '—') ?></div>
              <?php if (!empty($listing['color'])): ?>
                <div class="glass rounded-xl p-3"><b>Color:</b> <?= e($listing['color']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['engine_type'])): ?>
                <div class="glass rounded-xl p-3"><b>Engine:</b> <?= e($listing['engine_type']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['condition'])): ?>
                <div class="glass rounded-xl p-3"><b>Condition:</b> <?= e(ucfirst($listing['condition'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['modifications'])): ?>
                <div class="glass rounded-xl p-3 sm:col-span-2"><b>Modifications:</b> <?= e($listing['modifications']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['vin'])): ?>
                <div class="glass rounded-xl p-3"><b>VIN:</b> <?= e($listing['vin']) ?></div>
              <?php endif; ?>
              <?php if (!empty($listing['plate_number'])): ?>
                <div class="glass rounded-xl p-3"><b>Plate:</b> <?= e($listing['plate_number']) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($amenities)): ?>
          <div class="mt-6">
            <h3 class="font-display font-bold text-navy mb-2">Amenities</h3>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($amenities as $a): ?>
                <span class="chip bg-white border border-navy/10 text-navy">✓ <?= e($a) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="mt-8 flex flex-wrap gap-3">
            <?php if (isLoggedIn() && !isAdmin() && $listing['status']==='available'): ?>
              <form method="POST" action="api/cart.php?action=add">
                <?= csrfField() ?>
                <input type="hidden" name="listing_id" value="<?= (int)$listing['id'] ?>">
                <button class="btn btn-primary">Reserve Now — <?= money($listing['reservation_fee']) ?></button>
              </form>
              <button onclick="addToCompare(<?= (int)$listing['id'] ?>)" class="btn btn-ghost">⇆ Add to Compare</button>
            <?php elseif (!isLoggedIn()): ?>
              <a href="login.php?return=<?= urlencode('listing.php?id=' . (int)$listing['id']) ?>" class="btn btn-primary">Login to Reserve</a>
              <button onclick="addToCompare(<?= (int)$listing['id'] ?>)" class="btn btn-ghost">⇆ Add to Compare (guest OK)</button>
            <?php elseif (isAdmin()): ?>
              <a href="admin.php?tab=listings" class="btn btn-dark">Manage in Admin →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// --- List mode ---
$type   = $_GET['type']   ?? null;
$search = trim($_GET['search'] ?? '');
$items  = getListings($type, $search, 60);
$pageTitle = 'Browse Listings — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-6 py-10">

  <?php if (!empty($_SESSION['flash_ok'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_ok']) ?></div>
    <?php unset($_SESSION['flash_ok']); endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); endif; ?>

  <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
      <h1 class="font-display text-3xl sm:text-4xl font-bold text-navy">
        <?php if ($type==='property'): ?>🏠 Properties
        <?php elseif ($type==='vehicle'): ?>🚗 Vehicles
        <?php else: ?>All Listings <?php endif; ?>
      </h1>
      <p class="text-ink/60">Showing <?= count($items) ?> result<?= count($items)===1?'':'s' ?><?= $search?' for \"'.e($search).'\"':'' ?></p>
    </div>

    <div class="flex gap-2">
      <a href="listing.php"               class="chip <?= !$type?'bg-orange text-white':'bg-white border border-navy/10 text-navy' ?>">All</a>
      <a href="listing.php?type=property" class="chip <?= $type==='property'?'bg-orange text-white':'bg-white border border-navy/10 text-navy' ?>">🏠 Properties</a>
      <a href="listing.php?type=vehicle"  class="chip <?= $type==='vehicle'?'bg-orange text-white':'bg-white border border-navy/10 text-navy' ?>">🚗 Vehicles</a>
    </div>
  </div>

  <?php if (empty($items)): ?>
    <div class="text-center bg-white rounded-3xl p-16 shadow-md">
      <div class="text-6xl mb-3">🔎</div>
      <h3 class="font-bold text-navy text-xl">No listings found</h3>
      <p class="text-ink/60">Try a different search or category.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($items as $p):
        $img = listingImg($p['main_image'], $p['type']);
      ?>
        <div data-tilt data-reveal class="card-3d bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-deep group">
          <a href="listing.php?id=<?= (int)$p['id'] ?>" class="block relative aspect-[4/3] overflow-hidden">
            <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
            <span class="absolute top-3 left-3 chip bg-navy text-white"><?= $p['type']==='property'?'🏠':'🚗' ?> <?= ucfirst($p['type']) ?></span>
            <?php if (!empty($p['is_promo'])): ?><span class="absolute top-3 right-3 chip bg-orange text-white">PROMO</span><?php endif; ?>
          </a>
          <div class="p-5">
            <a href="listing.php?id=<?= (int)$p['id'] ?>" class="font-display font-bold text-navy text-lg hover:text-orange transition line-clamp-1"><?= e($p['title']) ?></a>
            <div class="text-sm text-ink/60 mt-1">
              <?php if ($p['type']==='property'): ?>
                📐 <?= e($p['square_meters'] ?? '?') ?>sqm • 🛏 <?= e($p['bedrooms'] ?? 0) ?> • 🛁 <?= e($p['bathrooms'] ?? 0) ?>
                <?php if (!empty($p['is_mortgaged'])): ?> • 🏦<?php endif; ?>
              <?php else: ?>
                <?= e(trim(($p['make']??'').' '.($p['model']??''))) ?> • <?= e($p['year'] ?? '') ?> • <?= number_format((int)($p['mileage'] ?? 0)) ?>km
              <?php endif; ?>
            </div>
            <div class="mt-4 flex items-end justify-between">
              <div>
                <div class="font-bold text-navy text-xl"><?= money($p['price']) ?></div>
                <div class="text-xs text-ink/60">Reserve <?= money($p['reservation_fee']) ?></div>
              </div>
              <div class="flex gap-1">
                <button onclick="addToCompare(<?= (int)$p['id'] ?>)" title="Compare" class="btn btn-ghost text-xs px-2 py-1">⇆</button>
                <?php if (isLoggedIn() && !isAdmin()): ?>
                  <form method="POST" action="api/cart.php?action=add" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="listing_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-primary text-xs px-3 py-1" title="Reserve now">+ Reserve</button>
                  </form>
                <?php else: ?>
                  <a href="listing.php?id=<?= (int)$p['id'] ?>" class="btn btn-primary text-xs px-3 py-1">View →</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

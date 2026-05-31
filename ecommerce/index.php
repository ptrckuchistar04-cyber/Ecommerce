<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$promos = getPromoListings(8);
// Fallback: if no promos flagged, show all available
if (empty($promos)) $promos = getListings(null, '', 8);

$pageTitle = 'On The Line — Find Your Perfect Match';
include __DIR__ . '/includes/header.php';
?>

<!-- ============== HERO with big LOGO ============== -->
<section class="relative overflow-hidden bg-gradient-to-br from-navy via-navy2 to-ink text-white">
  <!-- decorative background -->
  <div class="absolute inset-0 opacity-40 pointer-events-none" style="background:
    radial-gradient(900px 480px at 18% 10%, rgba(255,140,0,.40), transparent 60%),
    radial-gradient(700px 360px at 82% 90%, rgba(255,255,255,.16), transparent 60%);"></div>
  <!-- subtle grid -->
  <div class="absolute inset-0 opacity-[0.06] pointer-events-none"
       style="background-image:linear-gradient(rgba(255,255,255,.6) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.6) 1px,transparent 1px);background-size:48px 48px;"></div>

  <div class="relative max-w-7xl mx-auto px-6 pt-10 pb-24 text-center">
    <!-- BIG LOGO -->
    <div id="heroLogo" class="inline-block animate-float will-change-transform" style="transform-origin: 50% 30%;">
      <div class="logo-box hero-logo mx-auto">
        <img src="assets/images/logo.png" alt="On The Line — Garage & Gate" class="logo-img"
             onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22330%22 height=%22330%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22%23191970%22 rx=%2224%22/><text x=%2250%25%22 y=%2255%25%22 font-size=%2290%22 font-family=%22Arial%22 fill=%22white%22 text-anchor=%22middle%22 font-weight=%22bold%22>OL</text></svg>'">
      </div>
    </div>

    <h1 class="font-display text-4xl sm:text-6xl font-bold leading-tight mt-6">
      Find Your <span class="text-orange">Perfect Match</span>
    </h1>
    <p class="mt-3 text-white/80 text-lg max-w-2xl mx-auto">
      Premium real estate and certified pre-owned vehicles — secure your reservation in minutes.
    </p>

    <form method="GET" action="listing.php" class="mt-8 max-w-2xl mx-auto flex glass rounded-2xl p-2 shadow-deep">
      <input type="text" name="search" placeholder="Search by title, make, location…"
             class="flex-1 bg-transparent px-4 py-3 outline-none text-ink placeholder-ink/50">
      <button class="btn btn-primary">🔍 Search</button>
    </form>

    <div class="mt-8 text-white/70 text-sm flex flex-col items-center gap-1 animate-bounce">
      <span>Scroll to explore</span>
      <span class="text-2xl">↓</span>
    </div>
  </div>
</section>

<!-- ============== LAZY SUSAN (appears as you scroll) ============== -->
<section class="bg-gradient-to-b from-ink to-navy text-white py-16">
  <div class="max-w-7xl mx-auto px-4">
    <div class="text-center mb-8" data-reveal>
      <span class="chip bg-orange/20 text-orange border border-orange/40 mb-3 inline-block">✨ PROMO LISTINGS</span>
      <h2 class="font-display text-3xl sm:text-4xl font-bold">Featured This Week</h2>
      <p class="text-white/70 mt-2">Spin the carousel — drag, click arrows, or just watch.</p>
    </div>

    <div data-lazy-susan class="select-none mx-auto" style="max-width:1100px;">
      <!-- Stage with perspective; height grows to fit cards -->
      <div data-ls-stage class="relative mx-auto"
           style="height: 520px; perspective: 1600px; perspective-origin: 50% 45%;">
        <!-- Ring -->
        <div data-ls-ring class="absolute left-0 right-0 top-0 bottom-0"
             style="transform-style: preserve-3d; transition: none; transform: translateZ(-400px);">
          <?php foreach ($promos as $p):
            $img  = listingImg($p['main_image'] ?? null, $p['type']);
            $sub  = $p['type']==='property'
                  ? e($p['location'] ?? 'Property')
                  : e(trim(($p['make'] ?? '').' '.($p['model'] ?? '')).' • '.($p['year'] ?? ''));
            $icon = $p['type']==='property' ? '🏠' : '🚗';
          ?>
          <a href="listing.php?id=<?= (int)$p['id'] ?>" data-ls-card
             class="absolute block rounded-3xl overflow-hidden shadow-deep transition-all duration-300 ring-0 ring-orange"
             style="backface-visibility:hidden;">
            <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
            <div class="absolute top-3 left-3">
              <span class="chip bg-orange text-white"><?= $icon ?> <?= ucfirst($p['type']) ?></span>
            </div>
            <div class="absolute top-3 right-3">
              <span class="chip bg-white/95 text-navy font-bold">PROMO</span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
              <h3 class="font-display font-bold text-xl mb-1 leading-tight line-clamp-2"><?= e($p['title']) ?></h3>
              <p class="text-white/85 text-sm mb-3 line-clamp-1"><?= $sub ?></p>
              <div class="flex items-end justify-between">
                <div>
                  <div class="text-[10px] text-white/70 uppercase tracking-wide">From</div>
                  <div class="font-bold text-orange text-lg"><?= money($p['price']) ?></div>
                </div>
                <div class="text-right">
                  <div class="text-[10px] text-white/70 uppercase tracking-wide">Reserve</div>
                  <div class="font-semibold"><?= money($p['reservation_fee']) ?></div>
                </div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="flex items-center justify-center gap-4 mt-6">
        <button data-ls-prev class="btn btn-ghost">‹ Prev</button>
        <span class="text-white/60 text-sm hidden sm:inline">Drag to spin • Hover to pause</span>
        <button data-ls-next class="btn btn-ghost">Next ›</button>
      </div>
    </div>
  </div>
</section>

<!-- ============== CATEGORY CTA ============== -->
<section class="max-w-7xl mx-auto px-6 -mt-10 relative z-10">
  <div class="grid sm:grid-cols-3 gap-4">
    <a href="listing.php" data-tilt class="card-3d glass rounded-2xl p-6 shadow-deep flex items-center gap-4 group">
      <div class="text-4xl">🗂️</div>
      <div>
        <div class="font-display font-bold text-navy text-lg group-hover:text-orange transition">All Listings</div>
        <div class="text-sm text-ink/70">Browse the full catalog</div>
      </div>
    </a>
    <a href="listing.php?type=property" data-tilt class="card-3d glass rounded-2xl p-6 shadow-deep flex items-center gap-4 group">
      <div class="text-4xl">🏠</div>
      <div>
        <div class="font-display font-bold text-navy text-lg group-hover:text-orange transition">Properties</div>
        <div class="text-sm text-ink/70">Houses • Condos • Land</div>
      </div>
    </a>
    <a href="listing.php?type=vehicle" data-tilt class="card-3d glass rounded-2xl p-6 shadow-deep flex items-center gap-4 group">
      <div class="text-4xl">🚗</div>
      <div>
        <div class="font-display font-bold text-navy text-lg group-hover:text-orange transition">Vehicles</div>
        <div class="text-sm text-ink/70">SUVs • Sedans • Pickups</div>
      </div>
    </a>
  </div>
</section>

<!-- ============== WHY US ============== -->
<section class="max-w-7xl mx-auto px-6 py-16 grid md:grid-cols-3 gap-6">
  <?php
  $features = [
    ['🔒','Secure Reservations','Lock in your favorite listing with a small reservation fee via Xendit.'],
    ['⇆','Side-by-Side Compare','Compare up to 4 items at once across price, specs, and amenities.'],
    ['⚡','Instant Process','From browsing to reservation in under 3 minutes — no calls, no spam.'],
  ];
  foreach ($features as $f): ?>
  <div data-reveal class="bg-white rounded-2xl p-6 shadow-md hover:shadow-deep transition">
    <div class="text-3xl mb-3"><?= $f[0] ?></div>
    <h3 class="font-display font-bold text-navy mb-1"><?= $f[1] ?></h3>
    <p class="text-sm text-ink/70"><?= $f[2] ?></p>
  </div>
  <?php endforeach; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

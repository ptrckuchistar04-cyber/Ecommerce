<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$tab = $_GET['tab'] ?? 'overview';
$msg = '';

/* ---------- LISTING ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $act = $_POST['act'] ?? '';

    if ($act === 'create_listing') {
        try {
            db()->beginTransaction();
            $stmt = db()->prepare(
              "INSERT INTO listings (admin_id, type, title, description, price, reservation_fee, status, is_promo)
               VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                currentUserId(),
                $_POST['type'],
                trim($_POST['title']),
                trim($_POST['description']),
                (float)$_POST['price'],
                (float)$_POST['reservation_fee'],
                $_POST['status'] ?? 'available',
                isset($_POST['is_promo']) ? 1 : 0,
            ]);
            $lid = (int)db()->lastInsertId();

            if ($_POST['type'] === 'property') {
                $d = db()->prepare(
                  "INSERT INTO property_details
                   (listing_id, property_type, square_meters, lot_area, bedrooms, bathrooms, floors,
                    year_built, location, is_mortgaged, monthly_amortization, mortgage_bank,
                    furnishing, parking_slots)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $d->execute([
                    $lid,
                    $_POST['property_type'] ?? 'house',
                    (float)($_POST['square_meters'] ?? 0),
                    (float)($_POST['lot_area'] ?? 0),
                    (int)($_POST['bedrooms'] ?? 0),
                    (int)($_POST['bathrooms'] ?? 0),
                    (int)($_POST['floors'] ?? 1),
                    !empty($_POST['year_built']) ? (int)$_POST['year_built'] : null,
                    trim($_POST['location'] ?? ''),
                    !empty($_POST['is_mortgaged']) ? 1 : 0,
                    !empty($_POST['monthly_amortization']) ? (float)$_POST['monthly_amortization'] : null,
                    trim($_POST['mortgage_bank'] ?? ''),
                    $_POST['furnishing'] ?? 'unfurnished',
                    (int)($_POST['parking_slots'] ?? 0),
                ]);
            } else {
                $d = db()->prepare(
                  "INSERT INTO vehicle_details
                   (listing_id, make, model, year, mileage, transmission, fuel_type,
                    modifications, vin, color, engine_type, `condition`, plate_number)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $d->execute([
                    $lid,
                    trim($_POST['make'] ?? ''),
                    trim($_POST['model'] ?? ''),
                    (int)($_POST['year'] ?? date('Y')),
                    (int)($_POST['mileage'] ?? 0),
                    $_POST['transmission'] ?? 'automatic',
                    trim($_POST['fuel_type'] ?? ''),
                    trim($_POST['modifications'] ?? ''),
                    trim($_POST['vin'] ?? '') ?: null,
                    trim($_POST['color'] ?? ''),
                    trim($_POST['engine_type'] ?? ''),
                    $_POST['vehicle_condition'] ?? 'used',
                    trim($_POST['plate_number'] ?? ''),
                ]);
            }

            // Handle image upload if present
            if (!empty($_FILES['listing_image']) && $_FILES['listing_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['listing_image'];
                $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                $mime = mime_content_type($file['tmp_name']);
                if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                    $name = 'lst-' . $lid . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                    $dest = UPLOAD_DIR . $name;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $url = 'uploads/products/' . $name;
                        db()->prepare("INSERT INTO images (listing_id, url, sort_order) VALUES (?,?,0)")->execute([$lid, $url]);
                        db()->prepare("UPDATE listings SET main_image=? WHERE id=?")->execute([$url, $lid]);
                    }
                }
            }

            db()->commit();
            $msg = 'Listing created successfully.';
        } catch (Exception $e) { db()->rollBack(); error_log($e->getMessage()); $msg = 'Error: '.$e->getMessage(); }
    }

    if ($act === 'delete_listing') {
        db()->prepare("DELETE FROM listings WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Listing deleted.';
    }

    if ($act === 'toggle_status') {
        $id = (int)$_POST['id'];
        $cur = db()->prepare("SELECT status FROM listings WHERE id=?"); $cur->execute([$id]); $r = $cur->fetch();
        $next = $r['status']==='available' ? 'hidden' : 'available';
        db()->prepare("UPDATE listings SET status=? WHERE id=?")->execute([$next, $id]);
        $msg = "Status changed to $next.";
    }

    if ($act === 'inquiry_status') {
        $inquiryId = (int)$_POST['id'];
        $newStatus = $_POST['status'] ?? '';

        // If approving, auto-create the listing
        if ($newStatus === 'approved' && isset($_POST['auto_create'])) {
            $lid = createListingFromInquiry($inquiryId);
            if ($lid) {
                // Update inquiry status
                db()->prepare("UPDATE sell_inquiries SET status='approved', admin_notes=? WHERE id=?")
                    ->execute([trim($_POST['notes'] ?? ''), $inquiryId]);
                $msg = "✅ Inquiry approved and listing #{$lid} created! <a href='listing.php?id={$lid}' target='_blank' class='underline'>View Listing →</a>";
            } else {
                $msg = 'Error creating listing from inquiry. Check error logs.';
            }
        } else {
            db()->prepare("UPDATE sell_inquiries SET status=?, admin_notes=? WHERE id=?")
                ->execute([$newStatus, trim($_POST['notes'] ?? ''), $inquiryId]);
            $msg = 'Inquiry updated.';
        }
    }

    if ($act === 'upload_images') {
        $lid = (int)($_POST['listing_id'] ?? 0);
        if ($lid > 0 && !empty($_FILES['images'])) {
            $files = $_FILES['images'];
            $count = 0;
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                $mime = mime_content_type($files['tmp_name'][$i]);
                if (!isset($allowed[$mime]) || $files['size'][$i] > 5 * 1024 * 1024) continue;
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                $fname = 'lst-' . $lid . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                $dest = UPLOAD_DIR . $fname;
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $url = 'uploads/products/' . $fname;
                    db()->prepare("INSERT INTO images (listing_id, url, sort_order) VALUES (?,?,?)")
                        ->execute([$lid, $url, $i]);
                    // Set as main if first image and no main exists
                    if ($i === 0) {
                        $check = db()->prepare("SELECT main_image FROM listings WHERE id=?");
                        $check->execute([$lid]);
                        if (!$check->fetchColumn()) {
                            db()->prepare("UPDATE listings SET main_image=? WHERE id=?")->execute([$url, $lid]);
                        }
                    }
                    $count++;
                }
            }
            $msg = "{$count} image(s) uploaded.";
        }
    }
}

/* ---------- DATA ---------- */
$stats = [
  'listings'  => (int)db()->query("SELECT COUNT(*) FROM listings")->fetchColumn(),
  'available' => (int)db()->query("SELECT COUNT(*) FROM listings WHERE status='available'")->fetchColumn(),
  'orders'    => (int)db()->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
  'paid'      => (int)db()->query("SELECT COUNT(*) FROM transactions WHERE status='paid'")->fetchColumn(),
  'users'     => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
  'inquiries' => (int)db()->query("SELECT COUNT(*) FROM sell_inquiries WHERE status='new'")->fetchColumn(),
];
$revenue = (float)db()->query("SELECT COALESCE(SUM(total_reservation_fee),0) FROM transactions WHERE status='paid'")->fetchColumn();

$listings  = db()->query("SELECT id, type, title, price, reservation_fee, status, is_promo, created_at FROM listings ORDER BY created_at DESC LIMIT 100")->fetchAll();
$orders    = db()->query("SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 100")->fetchAll();
$inquiries = db()->query("SELECT i.*, u.full_name, u.email FROM sell_inquiries i JOIN users u ON u.id=i.user_id ORDER BY i.created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'Admin — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy">📊 Admin Dashboard</h1>

  <?php if ($msg): ?><div class="mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?= $msg ?></div><?php endif; ?>

  <!-- Tabs -->
  <div class="mt-6 flex flex-wrap gap-2 border-b">
    <?php foreach (['overview'=>'📈 Overview','listings'=>'🏷️ Listings','orders'=>'💳 Orders','inquiries'=>'💼 Sell Inquiries'] as $k=>$v): ?>
      <a href="?tab=<?= $k ?>" class="px-4 py-2 -mb-px border-b-2 <?= $tab===$k ? 'border-orange text-orange font-bold':'border-transparent text-ink/70 hover:text-navy' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab==='overview'): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
      <?php foreach ([
        ['Total Listings', $stats['listings'], '🏷️','from-orange to-orange2'],
        ['Available',      $stats['available'],'✅','from-emerald-500 to-teal-500'],
        ['Orders',         $stats['orders'],   '💳','from-navy to-navy2'],
        ['Paid Orders',    $stats['paid'],     '💰','from-emerald-600 to-emerald-800'],
        ['Customers',      $stats['users'],    '👥','from-blue-500 to-indigo-500'],
        ['New Inquiries',  $stats['inquiries'],'💼','from-purple-500 to-pink-500'],
        ['Revenue',        money($revenue),    '💵','from-orange to-rose-500'],
      ] as $s): ?>
        <div class="bg-gradient-to-br <?= $s[3] ?> text-white rounded-2xl p-5 shadow-md">
          <div class="text-3xl mb-1"><?= $s[2] ?></div>
          <div class="text-sm opacity-90"><?= $s[0] ?></div>
          <div class="text-2xl font-bold"><?= is_string($s[1])?$s[1]:number_format($s[1]) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($tab==='listings'): ?>
    <!-- Add new with image upload -->
    <details class="mt-6 bg-white rounded-2xl shadow-md p-5">
      <summary class="cursor-pointer font-bold text-navy">➕ Add New Listing</summary>
      <form method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-3 mt-4" id="newL">
        <?= csrfField() ?>
        <input type="hidden" name="act" value="create_listing">

        <select name="type" required onchange="document.getElementById('propF').classList.toggle('hidden', this.value!=='property'); document.getElementById('vehF').classList.toggle('hidden', this.value!=='vehicle');" class="border rounded-lg p-2">
          <option value="">-- Type --</option>
          <option value="property">🏠 Property</option>
          <option value="vehicle">🚗 Vehicle</option>
        </select>
        <input name="title" required placeholder="Title" class="border rounded-lg p-2">
        <textarea name="description" rows="2" placeholder="Description" class="border rounded-lg p-2 sm:col-span-2"></textarea>
        <input type="number" step="0.01" name="price" required placeholder="Price (PHP)" class="border rounded-lg p-2">
        <input type="number" step="0.01" name="reservation_fee" required placeholder="Reservation fee (PHP)" class="border rounded-lg p-2">
        <select name="status" class="border rounded-lg p-2">
          <option value="available">available</option>
          <option value="hidden">hidden</option>
        </select>
        <label class="flex items-center gap-2"><input type="checkbox" name="is_promo"> Show as promo</label>

        <!-- Image upload -->
        <div class="sm:col-span-2">
          <label class="text-sm font-semibold text-navy">Main Image (JPG/PNG/WEBP, max 5MB)</label>
          <input type="file" name="listing_image" accept="image/jpeg,image/png,image/webp" class="border rounded-lg p-2 w-full">
        </div>

        <div id="propF" class="hidden sm:col-span-2 grid sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-lg">
          <select name="property_type" class="border rounded-lg p-2">
            <option value="house">House</option><option value="condo">Condo</option><option value="townhouse">Townhouse</option><option value="land">Land</option>
          </select>
          <input type="number" step="0.01" name="square_meters" placeholder="Floor area (sqm)" class="border rounded-lg p-2">
          <input type="number" step="0.01" name="lot_area" placeholder="Lot area (sqm)" class="border rounded-lg p-2">
          <input type="number" name="bedrooms" placeholder="Bedrooms" class="border rounded-lg p-2">
          <input type="number" name="bathrooms" placeholder="Bathrooms" class="border rounded-lg p-2">
          <input type="number" name="floors" placeholder="Floors" class="border rounded-lg p-2">
          <input type="number" name="parking_slots" placeholder="Parking slots" class="border rounded-lg p-2">
          <input type="number" name="year_built" placeholder="Year built" class="border rounded-lg p-2">
          <input name="location" placeholder="Location" class="border rounded-lg p-2">
          <select name="furnishing" class="border rounded-lg p-2">
            <option value="unfurnished">Unfurnished</option>
            <option value="semi-furnished">Semi-Furnished</option>
            <option value="fully-furnished">Fully Furnished</option>
          </select>
          <label class="flex items-center gap-2 border rounded-lg p-2 bg-white"><input type="checkbox" name="is_mortgaged" value="1"> Mortgaged</label>
          <input type="number" step="0.01" name="monthly_amortization" placeholder="Monthly amort" class="border rounded-lg p-2">
          <input name="mortgage_bank" placeholder="Bank name" class="border rounded-lg p-2">
        </div>

        <div id="vehF" class="hidden sm:col-span-2 grid sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-lg">
          <input name="make" placeholder="Make" class="border rounded-lg p-2">
          <input name="model" placeholder="Model" class="border rounded-lg p-2">
          <input type="number" name="year" placeholder="Year" class="border rounded-lg p-2">
          <input type="number" name="mileage" placeholder="Mileage (km)" class="border rounded-lg p-2">
          <select name="transmission" class="border rounded-lg p-2">
            <option value="automatic">Automatic</option><option value="manual">Manual</option><option value="cvt">CVT</option>
          </select>
          <input name="fuel_type" placeholder="Fuel" class="border rounded-lg p-2">
          <input name="color" placeholder="Color" class="border rounded-lg p-2">
          <input name="engine_type" placeholder="Engine" class="border rounded-lg p-2">
          <select name="vehicle_condition" class="border rounded-lg p-2">
            <option value="brand-new">Brand New</option><option value="used">Used</option><option value="for-parts">For Parts</option>
          </select>
          <input name="vin" placeholder="VIN" class="border rounded-lg p-2">
          <input name="plate_number" placeholder="Plate #" class="border rounded-lg p-2">
          <input name="modifications" placeholder="Modifications" class="border rounded-lg p-2 sm:col-span-2">
        </div>

        <button class="btn btn-primary justify-center sm:col-span-2">Create Listing</button>
      </form>
    </details>

    <!-- Upload images to existing listing -->
    <details class="mt-3 bg-white rounded-2xl shadow-md p-5">
      <summary class="cursor-pointer font-bold text-navy">🖼️ Upload Images to Listing</summary>
      <form method="POST" enctype="multipart/form-data" class="mt-3 flex flex-wrap gap-3 items-end">
        <?= csrfField() ?>
        <input type="hidden" name="act" value="upload_images">
        <div>
          <label class="text-xs text-navy font-semibold">Listing ID</label>
          <input type="number" name="listing_id" required placeholder="Listing #" class="border rounded-lg p-2 w-24">
        </div>
        <div>
          <label class="text-xs text-navy font-semibold">Select Images (multiple)</label>
          <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="border rounded-lg p-2">
        </div>
        <button class="btn btn-dark text-sm">Upload</button>
      </form>
    </details>

    <!-- Listings table -->
    <div class="mt-6 bg-white rounded-2xl shadow-md overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-navy text-white">
          <tr>
            <th class="p-3 text-left">#</th><th class="p-3 text-left">Title</th><th class="p-3 text-left">Type</th>
            <th class="p-3 text-right">Price</th><th class="p-3 text-right">Reserve</th>
            <th class="p-3 text-left">Status</th><th class="p-3 text-center">Promo</th><th class="p-3"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($listings as $l): ?>
            <tr class="border-b hover:bg-slate-50">
              <td class="p-3"><?= (int)$l['id'] ?></td>
              <td class="p-3 font-semibold text-navy"><?= e($l['title']) ?></td>
              <td class="p-3"><?= ucfirst($l['type']) ?></td>
              <td class="p-3 text-right"><?= money($l['price']) ?></td>
              <td class="p-3 text-right text-orange font-semibold"><?= money($l['reservation_fee']) ?></td>
              <td class="p-3"><span class="chip bg-slate-100 text-slate-700"><?= $l['status'] ?></span></td>
              <td class="p-3 text-center"><?= $l['is_promo']?'⭐':'—' ?></td>
              <td class="p-3 flex gap-1">
                <form method="POST"><?= csrfField() ?><input type="hidden" name="act" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="text-xs btn btn-ghost py-1 px-2">Toggle</button></form>
                <form method="POST" onsubmit="return confirm('Delete this listing?')"><?= csrfField() ?><input type="hidden" name="act" value="delete_listing"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="text-xs bg-rose-100 text-rose-600 rounded-lg px-2 py-1">Delete</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='orders'): ?>
    <div class="mt-6 bg-white rounded-2xl shadow-md overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-navy text-white">
          <tr><th class="p-3 text-left">Order #</th><th class="p-3 text-left">Customer</th><th class="p-3 text-right">Amount</th><th class="p-3">Status</th><th class="p-3">Date</th><th class="p-3">Invoice</th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr class="border-b hover:bg-slate-50">
              <td class="p-3 font-mono text-xs"><?= e($o['order_number']) ?></td>
              <td class="p-3"><?= e($o['full_name']) ?><div class="text-xs text-ink/50"><?= e($o['email']) ?></div></td>
              <td class="p-3 text-right text-orange font-bold"><?= money($o['total_reservation_fee']) ?></td>
              <td class="p-3"><span class="chip bg-slate-100"><?= $o['status'] ?></span></td>
              <td class="p-3 text-xs"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
              <td class="p-3"><?php if ($o['xendit_invoice_url']): ?><a href="<?= e($o['xendit_invoice_url']) ?>" target="_blank" class="text-orange text-xs hover:underline">Open ↗</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($tab==='inquiries'): ?>
    <div class="mt-6 space-y-3">
      <?php if (empty($inquiries)): ?><p class="text-ink/60">No inquiries yet.</p><?php endif; ?>
      <?php foreach ($inquiries as $q):
        $inqImages = db()->prepare("SELECT url FROM inquiry_images WHERE inquiry_id=? ORDER BY sort_order");
        $inqImages->execute([$q['id']]);
        $images = $inqImages->fetchAll(PDO::FETCH_COLUMN);
      ?>
        <div class="bg-white rounded-2xl shadow-md p-5">
          <div class="flex justify-between items-start gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="chip bg-navy/10 text-navy"><?= $q['item_type']==='property'?'🏠 Property':'🚗 Vehicle' ?></span>
                <span class="chip <?= $q['status']==='approved'?'bg-emerald-100 text-emerald-700':($q['status']==='rejected'?'bg-rose-100 text-rose-700':'bg-amber-100 text-amber-700') ?>"><?= ucfirst($q['status']) ?></span>
              </div>
              <h3 class="font-bold text-navy text-lg"><?= e($q['title']) ?></h3>
              <p class="text-sm text-ink/70 mt-1"><?= nl2br(e(mb_substr($q['description']??'', 0, 200))) ?></p>

              <!-- Show inquiry images -->
              <?php if ($q['main_image'] || !empty($images)): ?>
              <div class="flex gap-2 mt-3 flex-wrap">
                <?php foreach (array_slice(array_merge($q['main_image'] ? [$q['main_image']] : [], $images), 0, 5) as $img): ?>
                  <img src="<?= e($img) ?>" class="w-16 h-16 rounded-lg object-cover border">
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Detail summary -->
              <div class="text-xs text-ink/60 mt-2 space-y-1">
                <div>From <b><?= e($q['full_name']) ?></b> (<?= e($q['email']) ?>) • Phone: <?= e($q['contact_phone'] ?: '—') ?> • Asking: <b class="text-orange"><?= money($q['asking_price']) ?></b></div>
                <?php if ($q['item_type']==='property'): ?>
                  <div class="text-navy/70">
                    <?= e(ucfirst($q['property_type']??'N/A')) ?> •
                    <?= $q['square_meters'] ? e($q['square_meters']).' sqm' : '' ?> •
                    <?= $q['bedrooms'] ? e($q['bedrooms']).' beds' : '' ?> •
                    <?= $q['bathrooms'] ? e($q['bathrooms']).' baths' : '' ?> •
                    <?= $q['location'] ? e($q['location']) : '' ?>
                    <?= $q['is_mortgaged'] ? '• 🏦 Mortgaged' : '' ?>
                  </div>
                <?php else: ?>
                  <div class="text-navy/70">
                    <?= e($q['make'].' '.$q['model']) ?> •
                    <?= $q['vehicle_year'] ? e($q['vehicle_year']) : '' ?> •
                    <?= $q['mileage'] ? number_format($q['mileage']).' km' : '' ?> •
                    <?= e($q['color'] ?: '') ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <form method="POST" class="flex flex-col gap-1 w-64 shrink-0">
              <?= csrfField() ?>
              <input type="hidden" name="act" value="inquiry_status">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <select name="status" class="border rounded-lg p-1.5 text-sm">
                <?php foreach (['new','reviewing','approved','rejected'] as $s): ?>
                  <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <textarea name="notes" rows="2" placeholder="Admin notes…" class="border rounded-lg p-1.5 text-sm"><?= e($q['admin_notes']) ?></textarea>
              <label class="flex items-center gap-1 text-xs">
                <input type="checkbox" name="auto_create" value="1" checked>
                ✨ Auto-create listing on approve
              </label>
              <button class="btn btn-dark text-xs py-1.5">Save</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

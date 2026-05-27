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
                  "INSERT INTO property_details (listing_id, property_type, square_meters, bedrooms, bathrooms, year_built, location)
                   VALUES (?,?,?,?,?,?,?)");
                $d->execute([
                    $lid,
                    $_POST['property_type'] ?? 'house',
                    (float)($_POST['square_meters'] ?? 0),
                    (int)($_POST['bedrooms'] ?? 0),
                    (int)($_POST['bathrooms'] ?? 0),
                    !empty($_POST['year_built']) ? (int)$_POST['year_built'] : null,
                    trim($_POST['location'] ?? ''),
                ]);
            } else {
                $d = db()->prepare(
                  "INSERT INTO vehicle_details (listing_id, make, model, year, mileage, transmission, fuel_type, modifications, vin)
                   VALUES (?,?,?,?,?,?,?,?,?)");
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
                ]);
            }
            db()->commit();
            $msg = 'Listing created.';
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
        db()->prepare("UPDATE sell_inquiries SET status=?, admin_notes=? WHERE id=?")
            ->execute([$_POST['status'], trim($_POST['notes'] ?? ''), (int)$_POST['id']]);
        $msg = 'Inquiry updated.';
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

  <?php if ($msg): ?><div class="mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>

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
    <!-- Add new -->
    <details class="mt-6 bg-white rounded-2xl shadow-md p-5">
      <summary class="cursor-pointer font-bold text-navy">➕ Add New Listing</summary>
      <form method="POST" class="grid sm:grid-cols-2 gap-3 mt-4" id="newL">
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
        <label class="flex items-center gap-2"><input type="checkbox" name="is_promo"> Show in homepage Lazy Susan (promo)</label>

        <div id="propF" class="hidden sm:col-span-2 grid sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-lg">
          <select name="property_type" class="border rounded-lg p-2">
            <option value="house">House</option><option value="condo">Condo</option><option value="townhouse">Townhouse</option><option value="land">Land</option>
          </select>
          <input type="number" step="0.01" name="square_meters" placeholder="sqm" class="border rounded-lg p-2">
          <input type="number" name="bedrooms" placeholder="Bedrooms" class="border rounded-lg p-2">
          <input type="number" name="bathrooms" placeholder="Bathrooms" class="border rounded-lg p-2">
          <input type="number" name="year_built" placeholder="Year built" class="border rounded-lg p-2">
          <input name="location" placeholder="Location" class="border rounded-lg p-2">
        </div>

        <div id="vehF" class="hidden sm:col-span-2 grid sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-lg">
          <input name="make" placeholder="Make" class="border rounded-lg p-2">
          <input name="model" placeholder="Model" class="border rounded-lg p-2">
          <input type="number" name="year" placeholder="Year" class="border rounded-lg p-2">
          <input type="number" name="mileage" placeholder="Mileage (km)" class="border rounded-lg p-2">
          <select name="transmission" class="border rounded-lg p-2">
            <option value="automatic">automatic</option><option value="manual">manual</option><option value="cvt">CVT</option>
          </select>
          <input name="fuel_type" placeholder="Fuel" class="border rounded-lg p-2">
          <input name="vin" placeholder="VIN" class="border rounded-lg p-2">
          <input name="modifications" placeholder="Modifications" class="border rounded-lg p-2 sm:col-span-2">
        </div>

        <button class="btn btn-primary justify-center sm:col-span-2">Create Listing</button>
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
      <?php foreach ($inquiries as $q): ?>
        <div class="bg-white rounded-2xl shadow-md p-5">
          <div class="flex justify-between items-start gap-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="chip bg-navy/10 text-navy"><?= $q['item_type']==='property'?'🏠 Property':'🚗 Vehicle' ?></span>
                <span class="chip bg-slate-100 text-slate-700"><?= ucfirst($q['status']) ?></span>
              </div>
              <h3 class="font-bold text-navy"><?= e($q['title']) ?></h3>
              <p class="text-sm text-ink/70 mt-1"><?= nl2br(e($q['description'])) ?></p>
              <div class="text-xs text-ink/60 mt-2">
                From <b><?= e($q['full_name']) ?></b> (<?= e($q['email']) ?>) • Phone: <?= e($q['contact_phone'] ?: '—') ?> •
                Asking: <b class="text-orange"><?= money($q['asking_price']) ?></b>
              </div>
            </div>
            <form method="POST" class="flex flex-col gap-1 w-56">
              <?= csrfField() ?>
              <input type="hidden" name="act" value="inquiry_status">
              <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
              <select name="status" class="border rounded-lg p-1.5 text-sm">
                <?php foreach (['new','reviewing','approved','rejected'] as $s): ?>
                  <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <textarea name="notes" rows="2" placeholder="Notes…" class="border rounded-lg p-1.5 text-sm"><?= e($q['admin_notes']) ?></textarea>
              <button class="btn btn-dark text-xs py-1.5">Save</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$uid = currentUserId();
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    csrfCheck();
    $name    = trim($_POST['full_name'] ?? '');
    $phone   = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $brgy    = trim($_POST['address_barangay'] ?? '');
    $city    = trim($_POST['address_city'] ?? '');
    $province= trim($_POST['address_province'] ?? '');
    $region  = trim($_POST['address_region'] ?? '');
    $country = trim($_POST['address_country'] ?? 'Philippines');
    $birthday= trim($_POST['birthday'] ?? '');
    $gender  = $_POST['gender'] ?? 'prefer_not_to_say';

    if (!$name) { $err = 'Name is required.'; }
    elseif ($phone !== '' && (strlen($phone) !== 11 || !str_starts_with($phone, '09'))) {
        $err = 'Phone must be 11 digits starting with 09.';
    } else {
        // Validate birthday: must be 18+
        if ($birthday) {
            $bd = DateTime::createFromFormat('Y-m-d', $birthday);
            $today = new DateTime('today');
            if (!$bd || $bd > $today) {
                $err = 'Birthday cannot be in the future.';
            } else {
                $age = $today->diff($bd)->y;
                if ($age < 18) {
                    $err = "You must be at least 18 years old (you are {$age}).";
                }
            }
        }
        if (!$err) {
            try {
                $stmt = db()->prepare(
                  "UPDATE users SET full_name=?, phone=?,
                                    address_barangay=?, address_city=?, address_province=?, address_region=?, address_country=?,
                                    birthday=?, gender=?
                   WHERE id=?");
                $stmt->execute([$name, $phone, $brgy, $city, $province, $region, $country, $birthday ?: null, $gender, $uid]);
                $_SESSION['user_name'] = $name;
                $ok = 'Profile updated.';
            } catch (Exception $e) { error_log($e->getMessage()); $err = 'Update failed.'; }
        }
    }
}

// ─── SELL INQUIRY ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sell') {
    csrfCheck();
    $type   = $_POST['item_type'] ?? '';
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $price  = (float)($_POST['asking_price'] ?? 0);
    $phone  = trim($_POST['contact_phone'] ?? '');

    if (!in_array($type, ['property','vehicle'], true) || !$title || $price <= 0) {
        $err = 'Please fill all sell-inquiry fields correctly.';
    } else {
        try {
            // Collect all details
            $commonFields = [
                'user_id' => $uid,
                'item_type' => $type,
                'title' => $title,
                'description' => $desc,
                'asking_price' => $price,
                'contact_phone' => $phone,
            ];

            if ($type === 'property') {
                $commonFields['property_type']  = $_POST['property_type'] ?? 'house';
                $commonFields['square_meters']  = (float)($_POST['square_meters'] ?? 0);
                $commonFields['lot_area']       = (float)($_POST['lot_area'] ?? 0);
                $commonFields['bedrooms']       = (int)($_POST['bedrooms'] ?? 0);
                $commonFields['bathrooms']      = (int)($_POST['bathrooms'] ?? 0);
                $commonFields['floors']         = (int)($_POST['floors'] ?? 1);
                $commonFields['year_built']     = !empty($_POST['year_built']) ? (int)$_POST['year_built'] : null;
                $commonFields['location']       = trim($_POST['location'] ?? '');
                $commonFields['is_mortgaged']   = !empty($_POST['is_mortgaged']) ? 1 : 0;
                $commonFields['monthly_amortization'] = !empty($_POST['monthly_amortization']) ? (float)$_POST['monthly_amortization'] : null;
                $commonFields['mortgage_bank']  = trim($_POST['mortgage_bank'] ?? '');
                $commonFields['furnishing']     = $_POST['furnishing'] ?? 'unfurnished';
                $commonFields['parking_slots']  = (int)($_POST['parking_slots'] ?? 0);
            } else {
                $commonFields['make']           = trim($_POST['make'] ?? '');
                $commonFields['model']          = trim($_POST['model'] ?? '');
                $commonFields['vehicle_year']   = (int)($_POST['vehicle_year'] ?? date('Y'));
                $commonFields['mileage']        = (int)($_POST['mileage'] ?? 0);
                $commonFields['transmission']   = $_POST['transmission'] ?? 'automatic';
                $commonFields['fuel_type']      = trim($_POST['fuel_type'] ?? '');
                $commonFields['modifications']  = trim($_POST['modifications'] ?? '');
                $commonFields['vin']            = trim($_POST['vin'] ?? '');
                $commonFields['color']          = trim($_POST['color'] ?? '');
                $commonFields['engine_type']    = trim($_POST['engine_type'] ?? '');
                $commonFields['vehicle_condition'] = $_POST['vehicle_condition'] ?? 'used';
                $commonFields['plate_number']   = trim($_POST['plate_number'] ?? '');
            }

            // Build INSERT query dynamically
            $columns = implode(', ', array_keys($commonFields));
            $placeholders = implode(', ', array_fill(0, count($commonFields), '?'));
            $stmt = db()->prepare("INSERT INTO sell_inquiries ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($commonFields));
            $inquiryId = (int)db()->lastInsertId();

            // Handle image upload
            if (!empty($_FILES['inquiry_image']) && $_FILES['inquiry_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['inquiry_image'];
                $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                $mime = mime_content_type($file['tmp_name']);
                if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/uploads/inquiries/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    $name = 'inq-' . $inquiryId . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                    $dest = $uploadDir . $name;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $url = 'uploads/inquiries/' . $name;
                        // Set as main image
                        db()->prepare("UPDATE sell_inquiries SET main_image=? WHERE id=?")->execute([$url, $inquiryId]);
                        // Also store in inquiry_images
                        db()->prepare("INSERT INTO inquiry_images (inquiry_id, url, sort_order) VALUES (?,?,0)")->execute([$inquiryId, $url]);
                    }
                }
            }

            $ok = 'Your sell inquiry has been submitted with full details! Our admin team will review and list it within 24-48 hours.';
        } catch (Exception $e) {
            error_log('Sell inquiry error: ' . $e->getMessage());
            $err = 'Could not send inquiry. Please try again.';
        }
    }
}

// ─── FETCH USER DATA ────────────────────────────────────────
$stmt = db()->prepare("SELECT id, full_name, email, phone, address_barangay, address_city, address_province, address_region, address_country, birthday, age, gender, role, created_at FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

$inq = db()->prepare("SELECT * FROM sell_inquiries WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$inq->execute([$uid]);
$inquiries = $inq->fetchAll();

// Seller's own approved listings (so they can pay a promo fee). Only when v4 schema exists.
$myListings = [];
if (hasPromoFields()) {
    $ml = db()->prepare(
        "SELECT id, type, title, main_image, status, is_promo, promo_status, price
         FROM listings WHERE seller_id=? ORDER BY created_at DESC LIMIT 50");
    $ml->execute([$uid]);
    $myListings = $ml->fetchAll();
}

$pageTitle = 'My Profile — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy mb-2">My Profile</h1>
  <p class="text-ink/60 mb-6">Member since <?= date('F Y', strtotime($user['created_at'])) ?> • <span class="chip bg-navy/10 text-navy"><?= ucfirst($user['role']) ?></span></p>

  <?php if ($err): ?><div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok):  ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4"><?= e($ok) ?></div><?php endif; ?>

  <?php if (!empty($myListings)): ?>
  <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
    <h2 class="font-display font-bold text-navy text-lg mb-1">🏷️ My Approved Listings</h2>
    <p class="text-sm text-ink/60 mb-4">Boost a listing to the homepage "Featured" carousel by paying a one-time promo fee.</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($myListings as $ml): ?>
        <div class="border border-slate-200 rounded-xl overflow-hidden">
          <div class="aspect-[4/3] bg-slate-100">
            <img src="<?= e(listingImg($ml['main_image'], $ml['type'])) ?>" alt="" class="w-full h-full object-cover">
          </div>
          <div class="p-3">
            <a href="listing.php?id=<?= (int)$ml['id'] ?>" class="font-bold text-navy text-sm line-clamp-1 hover:text-orange"><?= e($ml['title']) ?></a>
            <div class="text-xs text-ink/60 mt-0.5"><?= money($ml['price']) ?> • <span class="capitalize"><?= e($ml['status']) ?></span></div>

            <div class="mt-2">
              <?php if ($ml['is_promo']): ?>
                <span class="chip bg-orange/15 text-orange text-xs">⭐ Featured</span>
              <?php elseif ($ml['promo_status']==='pending_payment'): ?>
                <a href="promo.php?listing=<?= (int)$ml['id'] ?>" class="btn btn-primary text-xs w-full justify-center">Continue Promo Payment →</a>
              <?php elseif ($ml['status']==='available'): ?>
                <a href="promo.php?listing=<?= (int)$ml['id'] ?>" class="btn btn-ghost text-xs w-full justify-center">☆ Promote (₱<?= number_format(promoFeeFor($ml),0) ?>)</a>
              <?php else: ?>
                <span class="text-xs text-ink/40">Not eligible for promo</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid lg:grid-cols-2 gap-6">

    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="font-display font-bold text-navy text-lg mb-4">👤 Personal Info</h2>
      <form method="POST" class="space-y-3">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="profile">

        <div>
          <label class="text-sm font-semibold text-navy">Full Name</label>
          <input name="full_name" value="<?= e($user['full_name']) ?>" required class="w-full bg-slate-50 border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
        </div>
        <div>
          <label class="text-sm font-semibold text-navy">Email (cannot change)</label>
          <input value="<?= e($user['email']) ?>" disabled class="w-full bg-slate-100 border rounded-xl px-4 py-2.5 text-ink/60">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-semibold text-navy">Phone</label>
            <input id="phoneInput" name="phone" inputmode="numeric" maxlength="11" pattern="09\d{9}" value="<?= e($user['phone']) ?>" placeholder="09171234567" class="w-full bg-slate-50 border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
          </div>
          <div>
            <label class="text-sm font-semibold text-navy">Gender</label>
            <select name="gender" class="w-full bg-slate-50 border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
              <?php foreach (['prefer_not_to_say'=>'Prefer not to say','male'=>'Male','female'=>'Female','other'=>'Other'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $user['gender']===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Address -->
        <div class="mt-2">
          <div class="text-sm font-semibold text-navy mb-2">Address</div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-ink/60">Country</label>
              <select name="address_country" id="addrCountry" class="w-full bg-slate-50 border rounded-xl px-3 py-2 focus:ring-2 focus:ring-orange focus:outline-none">
                <option value="Philippines" <?= ($user['address_country']??'Philippines')==='Philippines'?'selected':'' ?>>🇵🇭 Philippines</option>
                <option value="Other" <?= ($user['address_country']??'')==='Other'?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="text-xs text-ink/60">Region</label>
              <select name="address_region" id="addrRegion" class="w-full bg-slate-50 border rounded-xl px-3 py-2 focus:ring-2 focus:ring-orange focus:outline-none">
                <option value="<?= e($user['address_region']) ?>"><?= e($user['address_region'] ?: 'Loading…') ?></option>
              </select>
            </div>
            <div>
              <label class="text-xs text-ink/60">Province</label>
              <select name="address_province" id="addrProvince" class="w-full bg-slate-50 border rounded-xl px-3 py-2 focus:ring-2 focus:ring-orange focus:outline-none">
                <option value="<?= e($user['address_province']) ?>"><?= e($user['address_province'] ?: '—') ?></option>
              </select>
            </div>
            <div>
              <label class="text-xs text-ink/60">City / Municipality</label>
              <select name="address_city" id="addrCity" class="w-full bg-slate-50 border rounded-xl px-3 py-2 focus:ring-2 focus:ring-orange focus:outline-none">
                <option value="<?= e($user['address_city']) ?>"><?= e($user['address_city'] ?: '—') ?></option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="text-xs text-ink/60">Barangay</label>
              <select name="address_barangay" id="addrBrgy" class="w-full bg-slate-50 border rounded-xl px-3 py-2 focus:ring-2 focus:ring-orange focus:outline-none">
                <option value="<?= e($user['address_barangay']) ?>"><?= e($user['address_barangay'] ?: '—') ?></option>
              </select>
            </div>
          </div>
          <p class="text-xs text-ink/50 mt-1">Change Region to refresh the cascade.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-semibold text-navy">Birthday</label>
            <input type="date" id="bday2" name="birthday" value="<?= e($user['birthday']) ?>" max="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
          </div>
          <div>
            <label class="text-sm font-semibold text-navy">Age (auto)</label>
            <input id="age2" readonly value="<?= $user['age'] ? e($user['age']).' years' : '—' ?>" class="w-full bg-slate-100 border rounded-xl px-4 py-2.5 text-ink/70">
          </div>
        </div>
        <button class="btn btn-primary w-full justify-center mt-3">Save Changes</button>
      </form>
    </div>

    <!-- ── ENHANCED SELL FORM ── -->
    <div id="sell" class="bg-gradient-to-br from-navy to-navy2 text-white rounded-2xl shadow-deep p-6">
      <h2 class="font-display font-bold text-orange text-lg mb-2">💼 Sell Your Item</h2>
      <p class="text-white/80 text-sm mb-4">Provide complete details below. Once approved, your listing will appear automatically!</p>

      <form method="POST" enctype="multipart/form-data" class="space-y-3" id="sellForm">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="sell">

        <!-- Basic info -->
        <div class="grid grid-cols-2 gap-3">
          <select name="item_type" id="sellType" required onchange="toggleSellFields()" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-orange">
            <option value="property" class="text-navy">🏠 Property</option>
            <option value="vehicle"  class="text-navy">🚗 Vehicle</option>
          </select>
          <input name="contact_phone" placeholder="Contact # (e.g. 09171234567)" maxlength="11" value="<?= e($user['phone']) ?>" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
        </div>
        <input name="title" required placeholder="Listing title (e.g. 2-bedroom Condo in Pasig)" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
        <div class="grid grid-cols-2 gap-3">
          <input type="number" step="0.01" name="asking_price" required placeholder="Asking price (PHP)" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          <input type="file" name="inquiry_image" accept="image/jpeg,image/png,image/webp" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-orange file:text-white file:text-xs focus:outline-none focus:ring-2 focus:ring-orange">
        </div>
        <textarea name="description" rows="3" placeholder="Describe your item in detail…" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange"></textarea>

        <!-- ═══ PROPERTY FIELDS ═══ -->
        <div id="propertyFields" class="space-y-3">
          <div class="text-sm font-semibold text-orange mt-2">🏠 Property Details</div>
          <div class="grid grid-cols-3 gap-3">
            <select name="property_type" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange">
              <option value="house" class="text-navy">House</option>
              <option value="condo" class="text-navy">Condo</option>
              <option value="townhouse" class="text-navy">Townhouse</option>
              <option value="land" class="text-navy">Land / Lot</option>
            </select>
            <input type="number" step="0.01" name="square_meters" placeholder="Floor area (sqm)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input type="number" step="0.01" name="lot_area" placeholder="Lot area (sqm)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <div class="grid grid-cols-4 gap-3">
            <input type="number" name="bedrooms" placeholder="Bedrooms" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input type="number" name="bathrooms" placeholder="Bathrooms" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input type="number" name="floors" placeholder="Floors" value="1" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input type="number" name="parking_slots" placeholder="Parking slots" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <input type="number" name="year_built" placeholder="Year built (e.g. 2020)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input name="location" placeholder="City / Location" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <div class="grid grid-cols-3 gap-3">
            <select name="furnishing" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange">
              <option value="unfurnished" class="text-navy">Unfurnished</option>
              <option value="semi-furnished" class="text-navy">Semi-Furnished</option>
              <option value="fully-furnished" class="text-navy">Fully Furnished</option>
            </select>
            <label class="flex items-center gap-2 text-sm bg-white/10 border border-white/20 rounded-xl px-3 py-2">
              <input type="checkbox" name="is_mortgaged" value="1" onchange="toggleMortgage()">
              <span>🏦 Mortgaged?</span>
            </label>
            <div></div>
          </div>

          <!-- Mortgage details (hidden unless checked) -->
          <div id="mortgageFields" class="hidden grid grid-cols-2 gap-3 bg-white/5 rounded-xl p-3">
            <input type="number" step="0.01" name="monthly_amortization" placeholder="Monthly amortization (₱)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input name="mortgage_bank" placeholder="Mortgage bank (e.g. BDO, BPI)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
        </div>

        <!-- ═══ VEHICLE FIELDS ═══ -->
        <div id="vehicleFields" class="hidden space-y-3">
          <div class="text-sm font-semibold text-orange mt-2">🚗 Vehicle Details</div>
          <div class="grid grid-cols-3 gap-3">
            <input name="make" placeholder="Make (e.g. Toyota)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input name="model" placeholder="Model (e.g. Fortuner)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input type="number" name="vehicle_year" placeholder="Year (e.g. 2023)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <div class="grid grid-cols-3 gap-3">
            <input type="number" name="mileage" placeholder="Mileage (km)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <select name="transmission" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange">
              <option value="automatic" class="text-navy">Automatic</option>
              <option value="manual" class="text-navy">Manual</option>
              <option value="cvt" class="text-navy">CVT</option>
            </select>
            <input name="fuel_type" placeholder="Fuel (e.g. Gasoline, Diesel)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <div class="grid grid-cols-3 gap-3">
            <input name="color" placeholder="Color" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input name="engine_type" placeholder="Engine (e.g. 2.8L Diesel)" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <select name="vehicle_condition" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange">
              <option value="brand-new" class="text-navy">Brand New</option>
              <option value="used" class="text-navy">Used</option>
              <option value="for-parts" class="text-navy">For Parts</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <input name="vin" placeholder="VIN (17 characters)" maxlength="17" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
            <input name="plate_number" placeholder="Plate number" class="bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
          </div>
          <textarea name="modifications" rows="2" placeholder="Modifications / Upgrades…" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange"></textarea>
        </div>

        <button class="btn btn-primary w-full justify-center mt-3">📤 Submit for Review</button>
      </form>

      <!-- Recent inquiries -->
      <?php if (!empty($inquiries)): ?>
      <div class="mt-6">
        <div class="text-sm font-semibold text-orange mb-2">Your recent inquiries</div>
        <div class="space-y-1 text-sm max-h-60 overflow-y-auto">
          <?php foreach ($inquiries as $q): ?>
            <div class="flex justify-between items-center bg-white/10 rounded-lg px-3 py-2">
              <div class="flex items-center gap-2 min-w-0">
                <?php if ($q['main_image']): ?>
                  <img src="<?= e($q['main_image']) ?>" class="w-8 h-8 rounded-lg object-cover shrink-0">
                <?php else: ?>
                  <span class="text-lg"><?= $q['item_type']==='property'?'🏠':'🚗' ?></span>
                <?php endif; ?>
                <span class="truncate"><?= e($q['title']) ?></span>
              </div>
              <span class="chip shrink-0 <?= $q['status']==='approved'?'bg-emerald-500':($q['status']==='rejected'?'bg-rose-500':'bg-amber-500') ?> text-white"><?= ucfirst($q['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
/* Phone digits only */
const ph = document.getElementById('phoneInput');
ph?.addEventListener('input', () => { ph.value = ph.value.replace(/\D/g,'').slice(0,11); });

/* Age auto-calc (fixed) */
const b = document.getElementById('bday2'), a = document.getElementById('age2');
function calcAge() {
  if (!b.value) { a.value='—'; return; }
  const bd = new Date(b.value+'T00:00:00'), t = new Date();
  if (isNaN(bd) || bd > t) { a.value='—'; return; }
  let n = t.getFullYear() - bd.getFullYear();
  const m = t.getMonth() - bd.getMonth();
  if (m<0||(m===0&&t.getDate()<bd.getDate())) n--;
  a.value = (n >= 0 ? n : 0) + ' years';
}
b?.addEventListener('input', calcAge);
calcAge();

/* Toggle sell form fields */
function toggleSellFields() {
  const t = document.getElementById('sellType').value;
  document.getElementById('propertyFields').classList.toggle('hidden', t !== 'property');
  document.getElementById('vehicleFields').classList.toggle('hidden', t !== 'vehicle');
}
toggleSellFields();

/* Toggle mortgage fields */
function toggleMortgage() {
  document.getElementById('mortgageFields').classList.toggle('hidden', !document.querySelector('[name=is_mortgaged]').checked);
}

/* PSGC cascading address */
const psgc='https://psgc.gitlab.io/api';
const $r=document.getElementById('addrRegion'),$p=document.getElementById('addrProvince'),$c=document.getElementById('addrCity'),$brg=document.getElementById('addrBrgy');
const fill=(sel,items,ph,k='name')=>{sel.innerHTML=`<option value="">${ph}</option>`+items.sort((a,b)=>a[k].localeCompare(b[k])).map(i=>`<option value="${i[k]}" data-code="${i.code}">${i[k]}</option>`).join('');};
const code=sel=>sel.options[sel.selectedIndex]?.dataset.code;

async function loadRegions(){
  try{const r=await fetch(psgc+'/regions/');const d=await r.json();fill($r,d,'Select region');}catch(e){}
}
$r?.addEventListener('change', async ()=>{
  $p.innerHTML='<option>Loading…</option>';$c.innerHTML='<option>—</option>';$brg.innerHTML='<option>—</option>';
  const cd=code($r); if(!cd) return;
  try{
    let r=await fetch(`${psgc}/regions/${cd}/provinces/`);let d=await r.json();
    if(d.length===0){r=await fetch(`${psgc}/regions/${cd}/cities-municipalities/`);d=await r.json();$p.innerHTML='<option>(none)</option>';fill($c,d,'Select city');}
    else{fill($p,d,'Select province');}
  }catch(e){}
});
$p?.addEventListener('change', async ()=>{
  $c.innerHTML='<option>Loading…</option>';$brg.innerHTML='<option>—</option>';
  const cd=code($p); if(!cd) return;
  try{const r=await fetch(`${psgc}/provinces/${cd}/cities-municipalities/`);const d=await r.json();fill($c,d,'Select city');}catch(e){}
});
$c?.addEventListener('change', async ()=>{
  $brg.innerHTML='<option>Loading…</option>';
  const cd=code($c); if(!cd) return;
  try{const r=await fetch(`${psgc}/cities-municipalities/${cd}/barangays/`);const d=await r.json();fill($brg,d,'Select barangay');}catch(e){}
});
loadRegions();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

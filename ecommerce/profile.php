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
            $stmt = db()->prepare(
              "INSERT INTO sell_inquiries (user_id, item_type, title, description, asking_price, contact_phone)
               VALUES (?,?,?,?,?,?)");
            $stmt->execute([$uid, $type, $title, $desc, $price, $phone]);
            $ok = 'Your sell inquiry has been sent to our admin team. We\'ll reach out within 24-48 hours.';
        } catch (Exception $e) { error_log($e->getMessage()); $err = 'Could not send inquiry.'; }
    }
}

$stmt = db()->prepare("SELECT id, full_name, email, phone, address_barangay, address_city, address_province, address_region, address_country, birthday, age, gender, role, created_at FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

$inq = db()->prepare("SELECT * FROM sell_inquiries WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$inq->execute([$uid]);
$inquiries = $inq->fetchAll();

$pageTitle = 'My Profile — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-display text-3xl font-bold text-navy mb-2">My Profile</h1>
  <p class="text-ink/60 mb-6">Member since <?= date('F Y', strtotime($user['created_at'])) ?> • <span class="chip bg-navy/10 text-navy"><?= ucfirst($user['role']) ?></span></p>

  <?php if ($err): ?><div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-4"><?= e($err) ?></div><?php endif; ?>
  <?php if ($ok):  ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-4"><?= e($ok) ?></div><?php endif; ?>

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

    <!-- SELL FORM -->
    <div id="sell" class="bg-gradient-to-br from-navy to-navy2 text-white rounded-2xl shadow-deep p-6">
      <h2 class="font-display font-bold text-orange text-lg mb-2">💼 Want to Sell?</h2>
      <p class="text-white/80 text-sm mb-4">Submit your property or vehicle for review. Our admin will contact you within 24-48 hours to list it on On The Line.</p>
      <form method="POST" class="space-y-3">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="sell">

        <div class="grid grid-cols-2 gap-3">
          <select name="item_type" required class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-orange">
            <option value="property" class="text-navy">🏠 Property</option>
            <option value="vehicle"  class="text-navy">🚗 Vehicle</option>
          </select>
          <input name="contact_phone" placeholder="Best contact #" maxlength="11" value="<?= e($user['phone']) ?>" class="bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
        </div>
        <input name="title" required placeholder="e.g. 2-bedroom Condo in Pasig" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
        <input type="number" step="0.01" name="asking_price" required placeholder="Asking price (PHP)" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange">
        <textarea name="description" rows="4" placeholder="Tell us about it..." class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-2.5 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-orange"></textarea>

        <button class="btn btn-primary w-full justify-center">Send Inquiry to Admin</button>
      </form>

      <?php if (!empty($inquiries)): ?>
      <div class="mt-6">
        <div class="text-sm font-semibold text-orange mb-2">Your recent inquiries</div>
        <div class="space-y-1 text-sm">
          <?php foreach ($inquiries as $q): ?>
            <div class="flex justify-between bg-white/10 rounded-lg px-3 py-2">
              <span><?= e($q['title']) ?></span>
              <span class="chip <?= $q['status']==='approved'?'bg-emerald-500':($q['status']==='rejected'?'bg-rose-500':'bg-amber-500') ?> text-white"><?= ucfirst($q['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
/* Phone */
const ph = document.getElementById('phoneInput');
ph?.addEventListener('input', () => { ph.value = ph.value.replace(/\D/g,'').slice(0,11); });

/* Age */
const b = document.getElementById('bday2'), a = document.getElementById('age2');
function calc() {
  if (!b.value) { a.value='—'; return; }
  const bd = new Date(b.value+'T00:00:00'), t = new Date();
  if (isNaN(bd) || bd > t) { a.value='—'; return; }
  let n = t.getFullYear() - bd.getFullYear();
  const m = t.getMonth() - bd.getMonth();
  if (m<0||(m===0&&t.getDate()<bd.getDate())) n--;
  a.value = n+' years';
}
b?.addEventListener('input', calc);

/* PSGC cascading address (same as register) */
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

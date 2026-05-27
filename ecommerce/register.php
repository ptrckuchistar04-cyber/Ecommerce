<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = preg_replace('/\D+/', '', $_POST['phone'] ?? '');  // digits only
    $brgy     = trim($_POST['address_barangay'] ?? '');
    $city     = trim($_POST['address_city'] ?? '');
    $province = trim($_POST['address_province'] ?? '');
    $region   = trim($_POST['address_region'] ?? '');
    $country  = trim($_POST['address_country'] ?? 'Philippines');
    $birthday = trim($_POST['birthday'] ?? '');
    $gender   = $_POST['gender'] ?? 'prefer_not_to_say';
    $pass     = $_POST['password'] ?? '';
    $cpass    = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$pass || !$birthday) {
        $err = 'Please fill all required fields (marked *).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } elseif ($phone !== '' && (strlen($phone) !== 11 || !str_starts_with($phone, '09'))) {
        $err = 'Phone must be 11 digits starting with 09 (e.g. 09171234567).';
    } elseif ($pass !== $cpass) {
        $err = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        $err = 'Invalid birthday.';
    } else {
        $bd  = DateTime::createFromFormat('Y-m-d', $birthday);
        $today = new DateTime('today');
        if (!$bd || $bd > $today) {
            $err = 'Birthday cannot be in the future.';
        } else {
            $age = $today->diff($bd)->y;
            if ($age < 18) {
                $err = "You must be at least 18 years old to register (you are {$age}).";
            } else {
                try {
                    $check = db()->prepare("SELECT id FROM users WHERE email = ?");
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $err = 'That email is already registered.';
                    } else {
                        $stmt = db()->prepare(
                          "INSERT INTO users
                            (full_name,email,password_hash,phone,
                             address_barangay,address_city,address_province,address_region,address_country,
                             birthday,gender,role)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?, 'customer')");
                        $stmt->execute([
                            $name, $email, hashPassword($pass), $phone,
                            $brgy, $city, $province, $region, $country,
                            $birthday, $gender,
                        ]);
                        $ok = "Welcome to On The Line! You can now <a href='login.php' class='underline text-orange font-bold'>sign in</a>.";
                    }
                } catch (Exception $e) {
                    error_log('Register: ' . $e->getMessage());
                    $err = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Create Account — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto px-6 py-12">
  <div class="bg-white rounded-3xl shadow-deep p-8">
    <h1 class="font-display text-3xl font-bold text-navy">Join <span class="text-orange">On The Line</span></h1>
    <p class="text-ink/60 mt-1">Start reserving your dream property or vehicle today.</p>

    <?php if ($err): ?><div class="mt-5 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?= e($err) ?></div><?php endif; ?>
    <?php if ($ok):  ?><div class="mt-5 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?= $ok ?></div><?php endif; ?>

    <form method="POST" class="mt-6 grid sm:grid-cols-2 gap-4" id="regForm" autocomplete="off">
      <?= csrfField() ?>

      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-navy mb-1">Full Name *</label>
        <input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>

      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Email *</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Phone (11 digits)</label>
        <input id="phoneInput" name="phone" inputmode="numeric" maxlength="11" pattern="09\d{9}" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="09171234567"
               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
        <p class="text-xs text-ink/50 mt-1">Format: 09XXXXXXXXX</p>
      </div>

      <!-- ===== ADDRESS (cascading PSGC dropdowns) ===== -->
      <div class="sm:col-span-2 mt-2">
        <div class="text-sm font-semibold text-navy mb-2">Address</div>
        <div class="grid sm:grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-ink/60">Country</label>
            <select name="address_country" id="addrCountry" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
              <option value="Philippines">🇵🇭 Philippines</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-ink/60">Region</label>
            <select name="address_region" id="addrRegion" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none">
              <option value="">Loading regions…</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-ink/60">Province</label>
            <select name="address_province" id="addrProvince" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none" disabled>
              <option value="">Select region first</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-ink/60">City / Municipality</label>
            <select name="address_city" id="addrCity" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none" disabled>
              <option value="">Select province first</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="text-xs text-ink/60">Barangay</label>
            <select name="address_barangay" id="addrBrgy" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none" disabled>
              <option value="">Select city/municipality first</option>
            </select>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Birthday *</label>
        <input type="date" id="birthday" name="birthday" required value="<?= e($_POST['birthday'] ?? '') ?>" max="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Age (auto)</label>
        <input id="ageDisplay" readonly value="—" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 text-ink/70 cursor-not-allowed">
        <p class="text-xs text-ink/50 mt-1">Calculated from your birthday. Must be 18+.</p>
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-semibold text-navy mb-1">Gender</label>
        <select name="gender" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
          <?php foreach (['prefer_not_to_say'=>'Prefer not to say','male'=>'Male','female'=>'Female','other'=>'Other'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (($_POST['gender'] ?? '')===$k?'selected':'') ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Password *</label>
        <input type="password" name="password" required minlength="8" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Confirm Password *</label>
        <input type="password" name="confirm_password" required minlength="8" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>

      <button class="sm:col-span-2 btn btn-primary justify-center mt-2">Create Account</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink/70">Already have an account? <a href="login.php" class="text-orange font-semibold hover:underline">Sign in</a></p>
  </div>
</div>

<script>
/* ===== Phone: digits only, max 11 ===== */
const ph = document.getElementById('phoneInput');
ph.addEventListener('input', () => {
  ph.value = ph.value.replace(/\D/g,'').slice(0,11);
});

/* ===== Age auto-calc ===== */
const bday = document.getElementById('birthday');
const ageEl = document.getElementById('ageDisplay');
function calcAge() {
  const v = bday.value; if (!v) { ageEl.value = '—'; return; }
  const b = new Date(v + 'T00:00:00');
  const t = new Date();
  if (isNaN(b) || b > t) { ageEl.value = '—'; return; }
  let a = t.getFullYear() - b.getFullYear();
  const m = t.getMonth() - b.getMonth();
  if (m < 0 || (m === 0 && t.getDate() < b.getDate())) a--;
  ageEl.value = a >= 0 ? a + ' years' : '—';
  ageEl.className = (a < 18)
    ? 'w-full bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-rose-700 cursor-not-allowed'
    : 'w-full bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 text-emerald-700 cursor-not-allowed';
}
bday.addEventListener('input', calcAge);
calcAge();

/* ===== PSGC cascading address (Region → Province → City → Barangay) =====
   Uses public PSGC API (https://psgc.gitlab.io/api/) — no API key required. */
const psgc = 'https://psgc.gitlab.io/api';
const $region = document.getElementById('addrRegion');
const $prov   = document.getElementById('addrProvince');
const $city   = document.getElementById('addrCity');
const $brgy   = document.getElementById('addrBrgy');
const $ctry   = document.getElementById('addrCountry');

const fill = (sel, items, placeholder, codeKey='code', nameKey='name') => {
  sel.innerHTML = `<option value="">${placeholder}</option>` +
    items.sort((a,b)=>a[nameKey].localeCompare(b[nameKey]))
         .map(i => `<option value="${i[nameKey]}" data-code="${i[codeKey]}">${i[nameKey]}</option>`).join('');
};
const reset = (sel, msg) => { sel.innerHTML = `<option value="">${msg}</option>`; sel.disabled = true; };
const getCode = sel => sel.options[sel.selectedIndex]?.dataset.code;

async function loadRegions() {
  try {
    const r = await fetch(psgc + '/regions/');
    const data = await r.json();
    fill($region, data, 'Select region');
    $region.disabled = false;
  } catch(e) {
    $region.innerHTML = '<option value="">⚠ Could not load regions — type manually below</option>';
    enableManualFallback();
  }
}
$region.addEventListener('change', async () => {
  reset($prov, 'Loading…'); reset($city, 'Select province first'); reset($brgy, 'Select city first');
  const code = getCode($region); if (!code) return;
  try {
    const r = await fetch(`${psgc}/regions/${code}/provinces/`);
    let data = await r.json();
    if (data.length === 0) {
      // e.g. NCR has no provinces, only cities
      const r2 = await fetch(`${psgc}/regions/${code}/cities-municipalities/`);
      data = await r2.json();
      $prov.innerHTML = '<option value="">(no provinces)</option>'; $prov.disabled = true;
      fill($city, data, 'Select city/municipality'); $city.disabled = false;
    } else {
      fill($prov, data, 'Select province'); $prov.disabled = false;
    }
  } catch(e) { reset($prov, '⚠ Load failed'); }
});
$prov.addEventListener('change', async () => {
  reset($city, 'Loading…'); reset($brgy, 'Select city first');
  const code = getCode($prov); if (!code) return;
  try {
    const r = await fetch(`${psgc}/provinces/${code}/cities-municipalities/`);
    const data = await r.json();
    fill($city, data, 'Select city/municipality'); $city.disabled = false;
  } catch(e) { reset($city, '⚠ Load failed'); }
});
$city.addEventListener('change', async () => {
  reset($brgy, 'Loading…');
  const code = getCode($city); if (!code) return;
  try {
    const r = await fetch(`${psgc}/cities-municipalities/${code}/barangays/`);
    const data = await r.json();
    fill($brgy, data, 'Select barangay'); $brgy.disabled = false;
  } catch(e) { reset($brgy, '⚠ Load failed'); }
});

$ctry.addEventListener('change', () => {
  if ($ctry.value !== 'Philippines') enableManualFallback();
  else { loadRegions(); }
});

function enableManualFallback() {
  // convert all dropdowns to text inputs for non-PH addresses
  [['addrRegion','Region/State'],['addrProvince','Province'],['addrCity','City'],['addrBrgy','Barangay / District']].forEach(([id,ph])=>{
    const old = document.getElementById(id);
    const name = old.name;
    const inp = document.createElement('input');
    inp.id = id; inp.name = name; inp.placeholder = ph;
    inp.className = 'w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-orange focus:outline-none';
    old.parentNode.replaceChild(inp, old);
  });
}

loadRegions();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

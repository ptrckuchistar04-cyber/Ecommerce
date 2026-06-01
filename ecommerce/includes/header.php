<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'On The Line — Real Estate & Vehicle Reservations';
$isHome    = basename($_SERVER['PHP_SELF']) === 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title><?= e($pageTitle) ?></title>

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        navy:'#191970', navy2:'#0f0f4b',
        orange:'#FF8C00', orange2:'#ff7a00',
        silver:'#C0C0C0', ink:'#0b0b2a',
      },
      fontFamily: { sans:['Inter','system-ui','sans-serif'], display:['"Space Grotesk"','system-ui','sans-serif'] },
      boxShadow: {
        glow:'0 10px 40px -10px rgba(255,140,0,.5)',
        deep:'0 30px 60px -15px rgba(25,25,112,.45)',
        logo:'0 0 0 4px #fff, 0 0 0 6px rgba(255,140,0,.55), 0 18px 40px -10px rgba(25,25,112,.55)',
      },
      animation: {
        'fade-up':'fadeUp .6s cubic-bezier(.4,0,.2,1) both',
        'fade-in':'fadeIn .5s ease-out both',
        'float':'float 6s ease-in-out infinite',
      },
      keyframes: {
        fadeUp:{ '0%':{opacity:'0',transform:'translateY(30px)'},'100%':{opacity:'1',transform:'translateY(0)'} },
        fadeIn:{ '0%':{opacity:'0'},'100%':{opacity:'1'} },
        float:{ '0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-14px)'} },
      },
    },
  },
};
</script>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

<style>
  html { scroll-behavior: smooth; }
  body { font-family:'Inter',system-ui,sans-serif; background:#f6f7fb; color:#0b0b2a; }
  .glass { background:rgba(255,255,255,.7); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); }
  .glass-dark { background:rgba(15,15,75,.55); backdrop-filter:blur(14px); -webkit-backdrop-filter:blur(14px); }
  .card-3d { transform-style:preserve-3d; transition:transform .35s cubic-bezier(.2,.7,.3,1.2),box-shadow .3s; }
  .card-3d:hover { transform:translateY(-6px) rotateX(2deg) rotateY(-2deg); }

  .btn { display:inline-flex;align-items:center;gap:.5rem;padding:.7rem 1.25rem;border-radius:.9rem;font-weight:600;transition:.25s;cursor:pointer; }
  .btn-primary { background:linear-gradient(135deg,#FF8C00,#ff5e00); color:#fff; box-shadow:0 8px 24px -8px rgba(255,140,0,.6); }
  .btn-primary:hover { transform:translateY(-2px); box-shadow:0 14px 32px -10px rgba(255,140,0,.75); }
  .btn-ghost { background:rgba(255,255,255,.6); color:#191970; border:1px solid rgba(25,25,112,.15); }
  .btn-ghost:hover { background:#fff; }
  .btn-dark { background:#191970; color:#fff; }
  .btn-dark:hover { background:#0f0f4b; }
  .chip { padding:.35rem .8rem;border-radius:999px;font-size:.78rem;font-weight:600; }

  /* ---- LOGO TREATMENT ---- */
  /* White border + orange glow + subtle navy backing */
  .logo-box {
    background: linear-gradient(135deg, #191970 0%, #0f0f4b 100%);
    padding: 5px;
    border-radius: 18px;
    border: 4px solid #ffffff;
    box-shadow:
      0 0 0 2px rgba(255,140,0,.5),
      0 0 20px rgba(255,140,0,.15),
      0 14px 30px -10px rgba(25,25,112,.55);
    display:inline-flex;align-items:center;justify-content:center;
    transition: box-shadow 0.3s ease;
  }
  .logo-box:hover {
    box-shadow:
      0 0 0 3px rgba(255,140,0,.6),
      0 0 30px rgba(255,140,0,.25),
      0 14px 40px -10px rgba(25,25,112,.65);
  }
  .logo-img { display:block; mix-blend-mode: screen; filter: brightness(1.15) contrast(1.1); }

  /* Nav logo when scrolled / on inner pages = small. Hero logo = big. */
  .nav-logo { width:42px; height:42px; }
  .nav-logo .logo-img { width:36px; height:36px; object-fit:contain; }
  .hero-logo { width:340px; height:340px; transition: all .6s cubic-bezier(.2,.7,.3,1); }
  .hero-logo .logo-img { width:330px; height:330px; object-fit:contain; }
  @media (max-width:640px) {
    .hero-logo { width:230px; height:230px; }
    .hero-logo .logo-img { width:220px; height:220px; }
  }

  main { animation: fadeIn .4s ease-out; }
  @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

  /* dropdown */
  .dropdown { position: relative; }
  .dropdown-menu { position:absolute; right:0; top:calc(100% + 4px); min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition: all .18s ease; }
  .dropdown.open .dropdown-menu, .dropdown.open .dropdown-menu * { visibility: visible; opacity: 1; transform: translateY(0); }
  .dropdown.open .dropdown-menu { opacity:1; visibility:visible; transform:translateY(0); }
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- NAV -->
<nav id="topNav" class="sticky top-0 z-50 glass border-b border-white/40 transition-all">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
    <a href="index.php" class="flex items-center gap-3 group">
      <div class="logo-box nav-logo">
        <img src="assets/images/logo.png" alt="On The Line" class="logo-img"
             onerror="this.onerror=null;this.outerHTML='<div class=&quot;w-9 h-9 grid place-items-center text-white font-bold&quot;>OL</div>'">
      </div>
      <div class="leading-tight hidden sm:block">
        <div class="font-display font-bold text-navy text-lg">ON THE LINE</div>
        <div class="text-[10px] tracking-widest text-orange font-semibold -mt-1">GARAGE &amp; GATE</div>
      </div>
    </a>

    <button id="navToggle" class="md:hidden text-navy text-2xl" aria-label="Menu">☰</button>

    <ul class="hidden md:flex items-center gap-1 text-sm font-medium">
      <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="index.php">Home</a></li>
      <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="listing.php?type=property">Properties</a></li>
      <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="listing.php?type=vehicle">Vehicles</a></li>
      <li>
        <a class="px-3 py-2 rounded-lg hover:bg-white/70 relative" href="compare.php">
          ⇆ Compare
          <span id="compareBadge" class="absolute -top-1 -right-1 bg-orange text-white text-[10px] rounded-full w-5 h-5 grid place-items-center"
                style="display:<?= count(getCompareList())>0?'grid':'none' ?>">
            <?= count(getCompareList()) ?>
          </span>
        </a>
      </li>

      <?php if (isLoggedIn()): ?>
        <?php if (!isAdmin()): ?>
          <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="cart.php">🛒 Cart</a></li>
          <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="orders.php">📋 Orders</a></li>
        <?php else: ?>
          <li><a class="px-3 py-2 rounded-lg hover:bg-white/70" href="admin.php">📊 Admin</a></li>
        <?php endif; ?>

        <li class="dropdown" id="userDrop">
          <button type="button" class="px-3 py-2 rounded-lg hover:bg-white/70 flex items-center gap-1" data-drop-toggle>
            👤 <?= e($_SESSION['user_name']) ?>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <ul class="dropdown-menu glass rounded-xl shadow-deep py-2 border border-white/40">
            <li><a class="block px-4 py-2 hover:bg-orange/10" href="profile.php">My Profile</a></li>
            <?php if (!isAdmin()): ?>
              <li><a class="block px-4 py-2 hover:bg-orange/10 text-orange font-semibold" href="profile.php#sell">+ Sell an Item</a></li>
            <?php endif; ?>
            <li><hr class="my-1 border-navy/10"></li>
            <li><a class="block px-4 py-2 hover:bg-red-50 text-red-600" href="logout.php">Sign Out</a></li>
          </ul>
        </li>
      <?php else: ?>
        <li><a class="btn btn-ghost text-sm" href="login.php">Login</a></li>
        <li><a class="btn btn-primary text-sm" href="register.php">Register</a></li>
      <?php endif; ?>
    </ul>
  </div>

  <!-- Mobile -->
  <ul id="navMobile" class="hidden md:hidden px-4 pb-4 space-y-1 text-sm">
    <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="index.php">Home</a></li>
    <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="listing.php?type=property">Properties</a></li>
    <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="listing.php?type=vehicle">Vehicles</a></li>
    <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="compare.php">⇆ Compare</a></li>
    <?php if (isLoggedIn()): ?>
      <?php if (!isAdmin()): ?>
        <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="cart.php">🛒 Cart</a></li>
        <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="orders.php">📋 Orders</a></li>
      <?php else: ?>
        <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="admin.php">📊 Admin</a></li>
      <?php endif; ?>
      <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="profile.php">My Profile</a></li>
      <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70 text-red-600" href="logout.php">Sign Out</a></li>
    <?php else: ?>
      <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="login.php">Login</a></li>
      <li><a class="block px-3 py-2 rounded-lg hover:bg-white/70" href="register.php">Register</a></li>
    <?php endif; ?>
  </ul>
</nav>

<main class="flex-1">

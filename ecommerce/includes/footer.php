</main>

<footer class="mt-20 bg-gradient-to-br from-navy via-navy2 to-ink text-white/90">
  <div class="max-w-7xl mx-auto px-6 py-14 grid md:grid-cols-4 gap-10">
    <div>
      <div class="flex items-center gap-2 mb-4">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange to-orange2 grid place-items-center text-white font-bold">OL</div>
        <div class="font-display font-bold text-lg">ON THE LINE</div>
      </div>
      <p class="text-sm text-white/70">Premium real estate and certified pre-owned vehicles — all under one roof.</p>
    </div>
    <div>
      <h4 class="font-bold mb-3 text-orange">Browse</h4>
      <ul class="space-y-2 text-sm">
        <li><a href="listing.php?type=property" class="hover:text-orange">Properties</a></li>
        <li><a href="listing.php?type=vehicle"  class="hover:text-orange">Vehicles</a></li>
        <li><a href="compare.php" class="hover:text-orange">Compare</a></li>
      </ul>
    </div>
    <div>
      <h4 class="font-bold mb-3 text-orange">Account</h4>
      <ul class="space-y-2 text-sm">
        <li><a href="login.php" class="hover:text-orange">Sign In</a></li>
        <li><a href="register.php" class="hover:text-orange">Create Account</a></li>
        <li><a href="profile.php" class="hover:text-orange">My Profile</a></li>
      </ul>
    </div>
    <div>
      <h4 class="font-bold mb-3 text-orange">Contact</h4>
      <p class="text-sm">📧 info@ontheline.com</p>
      <p class="text-sm">📱 (123) 456-7890</p>
      <p class="text-sm">📍 Manila, PH</p>
    </div>
  </div>
  <div class="border-t border-white/10 py-5 text-center text-xs text-white/60">
    &copy; <?= date('Y') ?> On The Line. All rights reserved. • Garage &amp; Gate
  </div>
</footer>

<!-- Global toast container -->
<div id="toastRoot" class="fixed top-5 right-5 z-[100] space-y-2"></div>

<script src="assets/js/app.js"></script>
<script>
  // Mobile nav
  const navToggle = document.getElementById('navToggle');
  const navMobile = document.getElementById('navMobile');
  navToggle?.addEventListener('click', () => navMobile.classList.toggle('hidden'));
</script>
</body>
</html>

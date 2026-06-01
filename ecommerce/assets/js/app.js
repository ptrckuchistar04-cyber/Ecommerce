/* =========================================================
   On The Line — Global app.js
   ========================================================= */

/* ---------- Toasts ---------- */
function toast(msg, type='info') {
  const root = document.getElementById('toastRoot');
  if (!root) return alert(msg);
  const el = document.createElement('div');
  const bg = type==='success' ? 'from-emerald-500 to-teal-500'
           : type==='error'   ? 'from-rose-500 to-red-600'
           : 'from-navy to-orange';
  el.className = `bg-gradient-to-r ${bg} text-white px-5 py-3 rounded-xl shadow-lg font-semibold animate-fade-up`;
  el.textContent = msg;
  root.appendChild(el);
  setTimeout(()=>{ el.style.transition='all .3s'; el.style.opacity='0'; el.style.transform='translateX(40px)'; }, 2700);
  setTimeout(()=> el.remove(), 3100);
}

/* ---------- Compare ---------- */
function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}
async function addToCompare(id) {
  try {
    const body = new URLSearchParams({ id: String(id), csrf_token: csrfToken() });
    const r = await fetch('api/compare.php?action=add', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken() },
      body
    });
    const j = await r.json();
    if (j.success) { toast('Added to comparison ⇆', 'success'); refreshCompareBadge(j.count); }
    else toast(j.message || 'Could not add', 'error');
  } catch (e) { toast('Network error', 'error'); }
}
async function refreshCompareBadge(count) {
  const badge = document.getElementById('compareBadge');
  if (!badge) return;
  if (count === undefined) {
    try { const r = await fetch('api/compare.php?action=count'); const j = await r.json(); count = j.count; } catch(e) { return; }
  }
  badge.textContent = count;
  badge.style.display = count > 0 ? 'grid' : 'none';
}

/* ---------- Click dropdown (replaces hover) ---------- */
document.addEventListener('click', (e) => {
  const toggle = e.target.closest('[data-drop-toggle]');
  if (toggle) {
    e.preventDefault();
    const drop = toggle.closest('.dropdown');
    document.querySelectorAll('.dropdown.open').forEach(d => { if (d !== drop) d.classList.remove('open'); });
    drop.classList.toggle('open');
    return;
  }
  // click outside closes
  if (!e.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  }
});

/* ---------- Mobile nav ---------- */
document.addEventListener('DOMContentLoaded', () => {
  const tog = document.getElementById('navToggle');
  const mob = document.getElementById('navMobile');
  tog?.addEventListener('click', () => mob.classList.toggle('hidden'));

  /* 3D tilt */
  document.querySelectorAll('[data-tilt]').forEach(card => {
    card.addEventListener('mousemove', e => {
      const r = card.getBoundingClientRect();
      const rx = ((e.clientY - r.top - r.height/2) / r.height) * -8;
      const ry = ((e.clientX - r.left - r.width/2)  / r.width)  *  8;
      card.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px)`;
    });
    card.addEventListener('mouseleave', () => { card.style.transform = 'perspective(900px) rotateX(0) rotateY(0) translateY(0)'; });
  });

  /* Scroll reveal */
  const io = new IntersectionObserver((entries) => {
    entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('animate-fade-up'); io.unobserve(en.target); } });
  }, { threshold: 0.12 });
  document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
});

/* =========================================================
   3D LAZY SUSAN
   Rebuilt: stable positioning, doesn't overflow, smoother spin
   ========================================================= */
class LazySusan {
  constructor(root) {
    this.root  = root;
    this.stage = root.querySelector('[data-ls-stage]');
    this.ring  = root.querySelector('[data-ls-ring]');
    this.cards = Array.from(root.querySelectorAll('[data-ls-card]'));
    this.n     = this.cards.length;
    if (this.n === 0) return;
    this.step  = 360 / this.n;
    this.angle = 0;
    this.radius = 0;
    this.targetAngle = 0;
    this.auto = true;
    this.dragging = false;
    this.lastX = 0;
    this.cardW = 280;
    this.cardH = 380;
    this.init();
  }
  init() {
    this.layout();
    window.addEventListener('resize', () => this.layout());

    this.root.querySelector('[data-ls-prev]')?.addEventListener('click', () => this.snap(-1));
    this.root.querySelector('[data-ls-next]')?.addEventListener('click', () => this.snap(+1));

    this.stage.addEventListener('pointerdown', e => {
      this.dragging = true; this.lastX = e.clientX; this.auto = false;
      this.stage.setPointerCapture(e.pointerId);
      this.stage.style.cursor = 'grabbing';
    });
    this.stage.addEventListener('pointermove', e => {
      if (!this.dragging) return;
      const dx = e.clientX - this.lastX; this.lastX = e.clientX;
      this.angle += dx * 0.35; this.targetAngle = this.angle; this.render();
    });
    const stop = () => { this.dragging = false; this.stage.style.cursor = 'grab'; };
    this.stage.addEventListener('pointerup', stop);
    this.stage.addEventListener('pointercancel', stop);
    this.stage.style.cursor = 'grab';

    this.root.addEventListener('mouseenter', () => this.auto = false);
    this.root.addEventListener('mouseleave', () => this.auto = true);

    this.loop();
  }
  layout() {
    const w = this.stage.clientWidth || 800;
    // Card sizes responsive
    this.cardW = Math.max(220, Math.min(300, Math.round(w * 0.28)));
    this.cardH = Math.round(this.cardW * 1.35);
    // Radius so cards form a comfortable circle
    this.radius = Math.round(this.cardW * 1.4);

    this.cards.forEach((c, i) => {
      c.style.width  = this.cardW + 'px';
      c.style.height = this.cardH + 'px';
      c.style.left   = '50%';
      c.style.top    = '50%';
      c.style.marginLeft = (-this.cardW/2) + 'px';
      c.style.marginTop  = (-this.cardH/2) + 'px';
      c.style.transform  = `rotateY(${i * this.step}deg) translateZ(${this.radius}px)`;
    });
    this.render();
  }
  snap(dir) {
    this.auto = false;
    this.targetAngle = Math.round((this.angle - dir * this.step) / this.step) * this.step;
  }
  render() {
    this.ring.style.transform = `translateZ(-${this.radius}px) rotateY(${this.angle}deg)`;
    this.cards.forEach((c, i) => {
      const a = ((i * this.step + this.angle) % 360 + 360) % 360;   // 0..360
      const norm = a > 180 ? a - 360 : a;                            // -180..180
      const dist = Math.abs(norm);
      const opacity = dist > 110 ? 0.25 : (1 - dist/180);
      c.style.opacity = opacity.toFixed(2);
      c.style.zIndex = String(1000 - Math.round(dist));
      c.style.pointerEvents = dist < 60 ? 'auto' : 'none';
      c.classList.toggle('is-front', dist < 25);
    });
  }
  loop() {
    let last = performance.now();
    const tick = (t) => {
      const dt = t - last; last = t;
      if (this.auto && !this.dragging) {
        this.angle += dt * 0.012;
        this.targetAngle = this.angle;
      } else if (!this.dragging && Math.abs(this.targetAngle - this.angle) > 0.05) {
        this.angle += (this.targetAngle - this.angle) * 0.12;
      }
      this.render();
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-lazy-susan]').forEach(el => new LazySusan(el));
});

/* =========================================================
   SCROLL-DRIVEN HERO LOGO (homepage only)
   When the user scrolls past the giant logo,
   it animates into the nav.
   ========================================================= */
document.addEventListener('DOMContentLoaded', () => {
  const heroLogo = document.getElementById('heroLogo');
  if (!heroLogo) return;
  const onScroll = () => {
    const y = window.scrollY;
    const t = Math.min(1, y / 320);          // 0..1 over first 320px
    const scale = 1 - 0.65 * t;              // 1 → 0.35
    const op    = 1 - t;
    heroLogo.style.transform = `scale(${scale})`;
    heroLogo.style.opacity = op.toFixed(2);
    heroLogo.style.pointerEvents = op < 0.1 ? 'none' : 'auto';
  };
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
});

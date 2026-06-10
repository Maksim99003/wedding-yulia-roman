/* =====================================================
   Wedding Invitation — main.js
   ===================================================== */

/* ── Фото: проверка + scroll parallax ──────────── */
(function () {
  /* Проверяем загрузку */
  const img = new Image();
  img.onerror = function () {
    const overlay = document.querySelector('.hero__bg-overlay');
    if (overlay) overlay.style.opacity = '0';
  };
  img.src = 'piony.jpg';

  /* Scroll parallax: фон двигается медленнее контента */
  const bg   = document.querySelector('.hero__bg');
  const hero = document.getElementById('hero');
  if (!bg || !hero) return;

  let ticking = false;
  let lastY   = 0;

  function applyParallax() {
    const y = window.scrollY;
    if (y <= hero.offsetHeight + 200) {
      bg.style.transform = `translateY(${y * 0.38}px)`;
    }
    ticking = false;
  }

  window.addEventListener('scroll', function () {
    lastY = window.scrollY;
    if (!ticking) {
      requestAnimationFrame(applyParallax);
      ticking = true;
    }
  }, { passive: true });
})();

/* ── Floral transitions ─────────────────────────── */
(function () {
  const bouquets = [
    '0% 0%','50% 0%','100% 0%',
    '0% 50%','50% 50%','100% 50%',
    '0% 100%','50% 100%','100% 100%'
  ];

  const positions = ['left', 'right'];

  function rnd(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  document.querySelectorAll('.fl-transition').forEach(function (wrapper) {
    const div = document.createElement('div');
    div.className = 'fl-divider fl-divider--' + rnd(positions);
    div.style.backgroundPosition = rnd(bouquets);
    div.setAttribute('aria-hidden', 'true');
    wrapper.appendChild(div);
  });
})();

/* ── Scroll reveal ──────────────────────────────── */
(function () {
  const targets = document.querySelectorAll(
    '.fade-up, .reveal, .reveal-left, .reveal-right, .reveal-up, .fl:not(.fl--hero-tl):not(.fl--hero-br), .fl-divider'
  );

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          io.unobserve(e.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  targets.forEach((el) => io.observe(el));

  /* Hero elements are above the fold — trigger immediately */
  document.querySelectorAll('.hero .fade-up').forEach((el) => {
    requestAnimationFrame(() => el.classList.add('visible'));
  });
})();

/* ── Countdown timer ────────────────────────────── */
(function () {
  const target = new Date('2026-07-25T16:00:00');

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    const now  = new Date();
    const diff = target - now;

    if (diff <= 0) {
      document.getElementById('countdown').innerHTML =
        '<p style="font-family:var(--font-serif);font-size:1.4rem;color:var(--terra)">Сегодня наш день! 🤍</p>';
      return;
    }

    const days  = Math.floor(diff / 864e5);
    const hours = Math.floor((diff % 864e5) / 36e5);
    const mins  = Math.floor((diff % 36e5) / 6e4);
    const secs  = Math.floor((diff % 6e4) / 1e3);

    const el = (id, v) => {
      const node = document.getElementById(id);
      if (node && node.textContent !== pad(v)) {
        node.textContent = pad(v);
        node.parentElement.style.transform = 'scale(1.08)';
        setTimeout(() => { node.parentElement.style.transform = ''; }, 200);
      }
    };

    el('cd-d', days);
    el('cd-h', hours);
    el('cd-m', mins);
    el('cd-s', secs);
  }

  tick();
  setInterval(tick, 1000);
})();

/* ── Falling petals ─────────────────────────────── */
(function () {
  const container = document.getElementById('petals');
  const COLORS = ['#FFF0EE', '#F9DDD8', '#F5C5BE', '#FAEAE7', '#EDD9CF'];
  const N = 28;

  for (let i = 0; i < N; i++) {
    const petal = document.createElement('div');
    const size  = 8 + Math.random() * 14;
    const left  = Math.random() * 100;
    const delay = Math.random() * 12;
    const dur   = 8 + Math.random() * 10;
    const color = COLORS[Math.floor(Math.random() * COLORS.length)];
    const rot   = Math.random() * 360;

    petal.style.cssText = `
      position: absolute;
      top: -${size * 2}px;
      left: ${left}%;
      width: ${size}px;
      height: ${size * 1.5}px;
      background: ${color};
      border-radius: 50% 0 50% 0;
      opacity: ${0.3 + Math.random() * 0.5};
      transform: rotate(${rot}deg);
      animation: petalFall ${dur}s ${delay}s linear infinite;
    `;
    container.appendChild(petal);
  }

  /* Inject keyframes */
  const style = document.createElement('style');
  style.textContent = `
    @keyframes petalFall {
      0%   { transform: translateY(0)   rotate(0deg)   translateX(0);   opacity: .7; }
      25%  { transform: translateY(25vh) rotate(90deg)  translateX(30px);}
      50%  { transform: translateY(50vh) rotate(180deg) translateX(-20px);}
      75%  { transform: translateY(75vh) rotate(270deg) translateX(20px);}
      100% { transform: translateY(105vh) rotate(360deg) translateX(0);  opacity: 0; }
    }
  `;
  document.head.appendChild(style);
})();

/* ── RSVP form ──────────────────────────────────── */
(function () {
  const form    = document.getElementById('rsvpForm');
  const success = document.getElementById('rsvpSuccess');

  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const name = form.querySelector('[name="name"]').value.trim();
    if (!name) {
      form.querySelector('[name="name"]').focus();
      return;
    }

    const attendance = form.querySelector('[name="attendance"]:checked');
    if (!attendance) {
      form.querySelector('[name="attendance"]').parentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    /* Collect data (could POST to a backend here) */
    const data = Object.fromEntries(new FormData(form).entries());
    console.info('RSVP data:', data);

    form.style.transition = 'opacity .4s, transform .4s';
    form.style.opacity = '0';
    form.style.transform = 'scale(.97)';

    setTimeout(() => {
      form.style.display = 'none';
      success.classList.add('show');
      success.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 400);
  });
})();

/* ── Smooth palette tooltip ─────────────────────── */
(function () {
  const swatches = document.querySelectorAll('.pal-swatch');
  swatches.forEach((s) => {
    s.title = s.dataset.name;
  });
})();

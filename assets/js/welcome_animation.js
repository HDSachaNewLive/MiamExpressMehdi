/**
 * Animation de bienvenue — home.php
 * Dépend de window.__WL_CONFIG__ et des libs Three/Vanta chargées avant ce script.
 */
(function () {
  'use strict';

  const cfg = window.__WL_CONFIG__ || { show: false, userId: 0, reason: '' };
  const SOUNDS = {
    circle: 'assets/sounds/welcome_circle.mp3',
    loop: 'assets/sounds/welcome_loop.mp3',
    end: 'assets/sounds/welcome_end.mp3',
    burst: 'assets/sounds/welcome_burst.mp3',
  };
  const BURST_COLORS = ['#4caf50', '#e91e8c', '#2196f3', '#9e9e9e', '#f44336'];
  const VANTA_OPTS = {
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 1205.0,
    minWidth: 200.0,
    scale: 1.0,
    scaleMobile: 1.0,
    color: 0xf6b26b,
    shininess: 60,
    waveHeight: 22,
    waveSpeed: 0.7,
    zoom: 1.1,
  };

  const overlay = document.getElementById('welcome-overlay');
  const circleWrap = document.getElementById('wl-circle-wrap');
  const circleCanvas = document.getElementById('wl-circle');
  const logoWrap = document.getElementById('wl-logo-wrap');
  const burstWrap = document.getElementById('wl-burst-wrap');
  const letters = () => Array.from(document.querySelectorAll('.wl-letter'));

  function getWelcomePeriodKey() {
    const now = new Date();
    const period = new Date(now);
    period.setHours(6, 0, 0, 0);
    if (now < period) {
      period.setDate(period.getDate() - 1);
    }
    return String(period.getTime());
  }

  function wasAlreadyShown() {
    if (!cfg.userId) return false;
    // Pour un nouveau compte, on vérifie le localStorage
    // MAIS si le serveur dit show=true et reason=new_account, on fait confiance au serveur
    // et on ne bloque PAS sur le localStorage (le serveur gère déjà la logique via session)
    if (cfg.reason === 'new_account') {
      // On laisse passer : le serveur a posé la session, il sait ce qu'il fait
      return false;
    }
    // Pour le welcome daily, on vérifie le localStorage pour éviter de rejouer
    // si l'utilisateur rafraîchit la page dans la même journée
    const key = 'foodhub_welcome_' + cfg.userId;
    return localStorage.getItem(key) === getWelcomePeriodKey();
  }

  function markShown() {
    if (!cfg.userId) return;
    if (cfg.reason === 'new_account') {
      // On ne stocke rien en localStorage pour new_account :
      // le serveur gère ça via derniere_connexion (welcome_mark_shown.php)
      fetch('welcome_mark_shown.php', { method: 'POST', credentials: 'same-origin' }).catch(() => {});
      return;
    }
    // Pour daily : stocker en localStorage pour éviter le replay intra-journalier
    localStorage.setItem('foodhub_welcome_' + cfg.userId, getWelcomePeriodKey());
  }

  function wait(ms) {
    return new Promise((r) => setTimeout(r, ms));
  }

  function playSound(src, opts = {}) {
    const audio = new Audio(src);
    audio.volume = opts.volume ?? 0.7;
    const p = audio.play().catch(() => {});
    return { audio, done: p };
  }

  function waitForAudioEnd(audio, minMs, maxMs) {
    return new Promise((resolve) => {
      const start = performance.now();
      let resolved = false;
      const finish = () => {
        if (resolved) return;
        resolved = true;
        audio.pause();
        resolve();
      };
      audio.addEventListener('ended', finish);
      const tick = () => {
        const elapsed = performance.now() - start;
        if (maxMs && elapsed >= maxMs) finish();
        else if (!resolved) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
      if (minMs) {
        setTimeout(() => {
          if (audio.ended || (audio.duration && audio.currentTime >= audio.duration - 0.05)) {
            finish();
          }
        }, minMs);
      }
    });
  }

  function initWelcomeVanta() {
    if (typeof VANTA === 'undefined' || !document.getElementById('wl-bg')) return;
    window.wlVantaEffect = VANTA.WAVES(Object.assign({ el: '#wl-bg' }, VANTA_OPTS));
  }

  function initBodyVanta() {
    if (typeof window.initHomeVanta === 'function') {
      window.initHomeVanta();
      return;
    }
    if (typeof VANTA === 'undefined') return;
    window.vantaEffect = VANTA.WAVES(Object.assign({ el: 'body' }, VANTA_OPTS));
  }

  function destroyWelcomeVanta() {
    if (window.wlVantaEffect) {
      window.wlVantaEffect.destroy();
      window.wlVantaEffect = null;
    }
  }

  function skipAnimation() {
    document.body.classList.remove('wl-pending');
    if (overlay) {
      overlay.style.display = 'none';
      overlay.setAttribute('aria-hidden', 'true');
    }
    const crit = document.getElementById('wl-critical');
    if (crit) crit.remove();
    destroyWelcomeVanta();
    initBodyVanta();
  }

  function revealPage() {
    document.body.classList.add('wl-revealing');
    if (overlay) {
      overlay.style.transition = 'opacity 0.5s ease';
      overlay.style.opacity = '0';
    }
    return wait(500).then(() => {
      document.body.classList.remove('wl-pending', 'wl-revealing');
      if (overlay) overlay.remove();
      const crit = document.getElementById('wl-critical');
      if (crit) crit.remove();
      destroyWelcomeVanta();
      initBodyVanta();
    });
  }

  /* ── Cercle pointillé (canvas) ── */
  const NUM_DASHES = 8;

  function drawDottedCircle(ctx, cx, cy, r, gradRotation, opts = {}) {
    const {
      color = '#ff8c00',
      glow = true,
      dashScale = 1,
      alpha = 1,
      lineWidth = 7,
      useGradient = true,
    } = opts;
    const gap = (Math.PI * 2) / NUM_DASHES;
    const dashArc = gap * 0.52;
    const radius = r * dashScale;

    ctx.save();
    ctx.globalAlpha = alpha;
    ctx.translate(cx, cy);

    if (glow) {
      ctx.shadowBlur = 14;
      ctx.shadowColor = color;
    }

    for (let i = 0; i < NUM_DASHES; i++) {
      const start = i * gap - Math.PI / 2;
      ctx.save();
      ctx.rotate(gradRotation);
      ctx.beginPath();
      ctx.arc(0, 0, radius, start, start + dashArc);
      if (useGradient) {
        let g;
        if (typeof ctx.createConicGradient === 'function') {
          g = ctx.createConicGradient(gradRotation, 0, 0);
          g.addColorStop(0, '#ff8800');
          g.addColorStop(0.35, '#ffcc00');
          g.addColorStop(0.7, '#ff6600');
          g.addColorStop(1, '#ff8800');
        } else {
          g = ctx.createLinearGradient(-radius, -radius, radius, radius);
          g.addColorStop(0, '#ff8800');
          g.addColorStop(0.5, '#ffcc00');
          g.addColorStop(1, '#ff6600');
        }
        ctx.strokeStyle = g;
      } else {
        ctx.strokeStyle = color;
      }
      ctx.lineWidth = lineWidth;
      ctx.lineCap = 'round';
      ctx.stroke();
      ctx.restore();
    }
    ctx.restore();
  }

  function animateCircleGradient(durationMs) {
    const ctx = circleCanvas.getContext('2d');
    const w = circleCanvas.width;
    const h = circleCanvas.height;
    const cx = w / 2;
    const cy = h / 2;
    const r = 44;
    const start = performance.now();

    return new Promise((resolve) => {
      function frame(now) {
        const t = Math.min(1, (now - start) / durationMs);
        ctx.clearRect(0, 0, w, h);
        drawDottedCircle(ctx, cx, cy, r, t * Math.PI * 2, { useGradient: true });
        if (t < 1) requestAnimationFrame(frame);
        else resolve();
      }
      requestAnimationFrame(frame);
    });
  }

  function animateDashBurst(durationMs) {
    const ctx = circleCanvas.getContext('2d');
    const w = circleCanvas.width;
    const h = circleCanvas.height;
    const cx = w / 2;
    const cy = h / 2;
    const r = 44;
    const start = performance.now();

    return new Promise((resolve) => {
      function frame(now) {
        const t = Math.min(1, (now - start) / durationMs);
        const scale = 1 + t * 1.8;
        const alpha = 1 - t;
        ctx.clearRect(0, 0, w, h);
        drawDottedCircle(ctx, cx, cy, r, Math.PI * 2, {
          useGradient: true,
          dashScale: scale,
          alpha,
          glow: alpha > 0.3,
        });
        if (t < 1) requestAnimationFrame(frame);
        else {
          ctx.clearRect(0, 0, w, h);
          resolve();
        }
      }
      requestAnimationFrame(frame);
    });
  }

  /* ── Sauts de lettres ── */
  function easeOutBounce(t) {
    if (t < 1 / 2.75) return 7.5625 * t * t;
    if (t < 2 / 2.75) return 7.5625 * (t -= 1.5 / 2.75) * t + 0.75;
    if (t < 2.5 / 2.75) return 7.5625 * (t -= 2.25 / 2.75) * t + 0.9375;
    return 7.5625 * (t -= 2.625 / 2.75) * t + 0.984375;
  }

  function animateLetterJump(letter, durationMs) {
    const start = performance.now();
    const jumpH = 38 + Math.random() * 18;
    return new Promise((resolve) => {
      function frame(now) {
        const t = Math.min(1, (now - start) / durationMs);
        const bounce = easeOutBounce(t);
        const y = -jumpH * Math.sin(t * Math.PI);
        const rot = t < 0.85 ? t * 360 : 360;
        const glow = Math.max(0, 1 - t * 1.2);
        letter.style.transform = `translateY(${y}px) rotateY(${rot}deg)`;
        letter.style.textShadow = `0 0 ${8 + glow * 20}px rgba(220,50,30,${0.35 + glow * 0.65})`;
        if (t < 1) requestAnimationFrame(frame);
        else {
          letter.style.transform = 'translateY(0) rotateY(0deg)';
          letter.style.textShadow = '0 0 6px rgba(220,50,30,0.35)';
          resolve();
        }
      }
      requestAnimationFrame(frame);
    });
  }

  async function runLetterPass(order, totalMs) {
    const ls = order === 'ltr' ? letters() : letters().slice().reverse();
    const count = Math.max(3, Math.floor(ls.length * (0.45 + Math.random() * 0.35)));
    const picked = new Set();
    while (picked.size < count) {
      picked.add(Math.floor(Math.random() * ls.length));
    }
    const indices = [...picked].sort((a, b) => a - b);
    const stagger = totalMs / (indices.length + 1);
    const tasks = indices.map((idx, i) =>
      wait(i * stagger).then(() => animateLetterJump(ls[idx], 380 + Math.random() * 120))
    );
    await Promise.all(tasks);
    await wait(totalMs - indices.length * stagger);
  }

  async function runLetterLoop(minDurationMs) {
    const loopSound = playSound(SOUNDS.loop);
    const start = performance.now();
    do {
      await runLetterPass('ltr', 1000);
      await wait(1000);
      await runLetterPass('rtl', 1000);
    } while (performance.now() - start < minDurationMs);

    const endSound = playSound(SOUNDS.end);
    await waitForAudioEnd(endSound.audio, 400, 2000);
    loopSound.audio.pause();
  }

  /* ── Burst final (5 cercles) ── */
  function createBurstCircles() {
    burstWrap.innerHTML = '';
    const palettes = [...BURST_COLORS].sort(() => Math.random() - 0.5);
    const canvases = [];
    for (let i = 0; i < 5; i++) {
      const wrap = document.createElement('div');
      wrap.className = 'wl-burst-circle';
      const c = document.createElement('canvas');
      c.width = 56;
      c.height = 56;
      wrap.appendChild(c);
      burstWrap.appendChild(wrap);
      canvases.push({ wrap, canvas: c, color: palettes[i % palettes.length] });
    }
    return canvases;
  }

  function animateBurstCircle(item, spinDurationMs) {
    const ctx = item.canvas.getContext('2d');
    const cx = 28;
    const cy = 28;
    const start = performance.now();

    return new Promise((resolve) => {
      function frame(now) {
        const elapsed = now - start;
        const t = Math.min(1, elapsed / spinDurationMs);
        const spin = elapsed * 0.025;
        const scale = 0.5 + t * 1.6;
        const alpha = 1 - t * 0.95;

        item.wrap.style.opacity = String(Math.min(1, elapsed / 50));
        item.wrap.style.transform = `scale(${scale})`;

        ctx.clearRect(0, 0, 56, 56);
        drawDottedCircle(ctx, cx, cy, 20, spin, {
          color: item.color,
          useGradient: false,
          glow: true,
          alpha,
          lineWidth: 5,
        });

        if (t < 1) requestAnimationFrame(frame);
        else resolve();
      }
      requestAnimationFrame(frame);
    });
  }

  async function runBurst() {
    const items = createBurstCircles();
    await wait(50);
    const burstSound = playSound(SOUNDS.burst);
    await Promise.all(items.map((item) => animateBurstCircle(item, 550)));
    await waitForAudioEnd(burstSound.audio, 200, 1500);
  }

  /* ── Séquence principale ── */
  async function runWelcomeSequence() {
    document.body.classList.add('wl-pending');
    if (overlay) {
      overlay.style.display = 'flex';
      overlay.setAttribute('aria-hidden', 'false');
    }

    initWelcomeVanta();
    await wait(80);

    logoWrap.classList.add('wl-visible');
    await wait(800);

    circleWrap.classList.add('wl-visible');
    await wait(300);

    const circleDuration = 3000;
    const circleSound = playSound(SOUNDS.circle);
    const circleSoundPromise = waitForAudioEnd(circleSound.audio, 2000, 4000);

    await animateCircleGradient(circleDuration);
    circleSound.audio.pause();
    await circleSoundPromise.catch(() => {});

    await animateDashBurst(450);
    circleWrap.classList.remove('wl-visible');
    circleWrap.style.opacity = '0';
    await wait(200);

    await runLetterLoop(4000);

    await runBurst();

    logoWrap.classList.add('wl-exit');
    await wait(700);

    markShown();
    await revealPage();
  }

  function bootstrap() {
    if (!cfg.show || !overlay) {
      skipAnimation();
      return;
    }
    if (wasAlreadyShown()) {
      skipAnimation();
      return;
    }
    runWelcomeSequence().catch(() => skipAnimation());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();

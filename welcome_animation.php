<?php
/**
 * welcome_animation.php
 * Inclure avec require_once AVANT le <!DOCTYPE html> dans home.php.
 * Nécessite : session_start(), $conn (PDO), $_SESSION['user_id'].
 */

$show_welcome   = false;
$welcome_reason = '';
$_wl_uid        = (int)($_SESSION['user_id'] ?? 0);

if (!empty($_SESSION['nouveau_compte'])) {
    $show_welcome   = true;
    $welcome_reason = 'new_account';
    // On ne fait PAS unset ici : le flag est supprimé par welcome_mark_shown.php
    // après que le JS confirme que l'animation a bien été jouée.
    // Cela évite que le flag disparaisse si home.php est chargé sans que le JS s'exécute.
} elseif (!empty($_SESSION['welcome_daily'])) {
    $show_welcome   = true;
    $welcome_reason = 'daily';
    unset($_SESSION['welcome_daily']); // daily : on peut unset directement, le localStorage prend le relais
} elseif ($_wl_uid > 0 && isset($conn)) {
    /* Fallback : compte Google ou session perdue — jamais connecté en BDD */
    $_wl_stmt = $conn->prepare("SELECT date_creation, derniere_connexion FROM users WHERE user_id = ?");
    $_wl_stmt->execute([$_wl_uid]);
    $_wl_row = $_wl_stmt->fetch(PDO::FETCH_ASSOC);
    if ($_wl_row) {
        $last_raw = $_wl_row['derniere_connexion'] ?? null;
        $never_connected = ($last_raw === null || $last_raw === '' || $last_raw === '0000-00-00 00:00:00');
        $created_at = strtotime($_wl_row['date_creation'] ?? '') ?: 0;
        if ($never_connected && $created_at && (time() - $created_at) < 172800) {
            $show_welcome   = true;
            $welcome_reason = 'new_account';
        }
    }
}

function wl_render_head_styles(): void {
    global $show_welcome;
    if (!$show_welcome) {
        return;
    }
    ?>
<style id="wl-critical">
body.wl-pending { overflow: hidden !important; }
body.wl-pending > *:not(#welcome-overlay),
body.wl-pending #sidebar,
body.wl-pending #toggleSidebar,
body.wl-pending #volume-widget {
  opacity: 0 !important;
  pointer-events: none !important;
}
/* Le display/opacity/pointer-events de #welcome-overlay lui-même ne dépend
   PLUS de body.wl-pending (voir wl_render_overlay) : sinon retirer
   wl-pending au début du fade faisait retomber l'overlay sur son
   display:none par défaut instantanément, tuant toute transition
   d'opacité (un élément display:none ne peut pas s'animer). */
</style>
    <?php
}

function wl_render_overlay(): void {
    global $show_welcome;
    if (!$show_welcome) {
        return;
    }
    ?>
<div id="welcome-overlay" aria-hidden="true">
  <video id="wl-video" src="assets/videos/FoodHub_anim.mp4" playsinline></video>
</div>

<style>
#welcome-overlay {
  /* display:flex par défaut : ce bloc n'est de toute façon rendu par PHP
     que lorsque $show_welcome est vrai (voir wl_render_overlay), donc pas
     besoin de conditionner l'affichage à body.wl-pending. Seule l'opacité
     doit rester animable indépendamment de cette classe. */
  display: flex;
  position: fixed;
  inset: 0;
  z-index: 999999;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  pointer-events: all;
  background: transparent;
  opacity: 1;
  transition: opacity 2s ease;
}
#welcome-overlay.wl-fading {
  opacity: 0;
  pointer-events: none;
}
#wl-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* État caché du container, indépendant de body.wl-pending : il doit rester
   hors-écran pendant tout le fade de la vidéo, et ne remonter qu'à la toute
   fin (revealInterface), pas dès que wl-pending est retiré. */
.wl-container-hidden {
  opacity: 0;
  transform: translateY(100vh);
}

/* ── Remontée + fondu du container après la vidéo ── */
@keyframes wlContainerSlideUp {
  from {
    opacity: 0;
    transform: translateY(100vh);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.wl-container-reveal {
  animation: wlContainerSlideUp 3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
</style>

<script>
(function () {
  var overlay = document.getElementById('welcome-overlay');
  var video   = document.getElementById('wl-video');
  if (!overlay || !video) return;

  // Durée réelle de la vidéo (16s + 54 frames à 60fps).
  // Fixée en dur : les exports AE/Media Encoder n'ont souvent pas le flag
  // "faststart", ce qui fait que video.duration renvoie Infinity dans le
  // navigateur — on ne peut donc pas se fier uniquement à cette valeur.
  var KNOWN_DURATION   = 16 + 54 / 60; // 16.9s
  var FADE_BEFORE_END  = 2;            // secondes avant la fin où le fade démarre

  var fadeStarted = false;
  var revealed    = false;
  var fadeTimer   = null;
  var revealTimer = null;

  function startFade() {
    if (fadeStarted) return;
    fadeStarted = true;
    // Retire wl-pending maintenant : sinon le CSS critique (!important)
    // garde l'overlay à opacity:1 de force pendant toute la transition,
    // ce qui donnait l'impression d'une coupure nette à la fin.
    document.body.classList.remove('wl-pending');
    overlay.classList.add('wl-fading');
  }

  function revealInterface() {
    if (revealed) return;
    revealed = true;

    clearTimeout(fadeTimer);
    clearTimeout(revealTimer);

    // Au cas où revealInterface() serait appelée sans être passée par
    // startFade() juste avant (ex: event 'ended' qui devance les timers)
    document.body.classList.remove('wl-pending');

    overlay.style.display = 'none';

    var container = document.getElementById('home-main-container');
    if (container) {
      container.classList.remove('wl-container-hidden');
      container.classList.add('wl-container-reveal');
    }

    fetch('welcome_mark_shown.php', { method: 'POST' }).catch(function () {});
    document.dispatchEvent(new Event('wl:revealed'));
  }

  function scheduleFixedTimers() {
    // Filet de sécurité indépendant du player : garantit le fade et la
    // révélation même si les events 'timeupdate'/'ended' de la vidéo
    // se comportent mal (durée mal détectée, buffering...).
    var fadeDelayMs   = Math.max(0, (KNOWN_DURATION - FADE_BEFORE_END) * 1000);
    var revealDelayMs = KNOWN_DURATION * 1000;
    fadeTimer   = setTimeout(startFade, fadeDelayMs);
    revealTimer = setTimeout(revealInterface, revealDelayMs);
  }

  // Complément : si le navigateur arrive quand même à donner une durée
  // fiable, on garde aussi la détection dynamique en plus des timers fixes.
  video.addEventListener('timeupdate', function () {
    if (isFinite(video.duration) && !fadeStarted && (video.duration - video.currentTime) <= FADE_BEFORE_END) {
      startFade();
    }
  });
  video.addEventListener('ended', revealInterface);
  video.addEventListener('error', revealInterface);

  function beginPlayback() {
    var timersScheduled = false;

    function onPlaybackStarted() {
      if (timersScheduled) return;
      timersScheduled = true;
      video.removeEventListener('playing', onPlaybackStarted);
      // Les timers démarrent ici, au moment où la lecture a RÉELLEMENT
      // commencé (pas au moment où on a appelé play()) — ça évite le
      // décalage causé par le buffering réseau, qui faisait couper la
      // vidéo trop tôt par rapport à ce qu'on voyait à l'écran.
      scheduleFixedTimers();
    }

    video.addEventListener('playing', onPlaybackStarted);

    video.play().catch(function () {
      video.muted = true;
      video.play().catch(function () {
        // Lecture totalement impossible : ne pas bloquer le site indéfiniment
        onPlaybackStarted();
      });
    });
  }

  beginPlayback();

  // Filet de sécurité ultime au cas où même les timers fixes échoueraient
  // (onglet mis en arrière-plan, throttling navigateur, etc.)
  setTimeout(function () {
    if (!revealed) revealInterface();
  }, (KNOWN_DURATION + 5) * 1000);
})();
</script>
    <?php
}

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
#welcome-overlay {
  display: flex !important;
  opacity: 1 !important;
  pointer-events: all !important;
}
</style>
    <?php
}

function wl_render_overlay(): void {
    ?>
<!-- Sons placeholder — remplacer quand les fichiers sont importés :
     assets/sounds/welcome_circle.mp3  (2-4s, rotation cercle)
     assets/sounds/welcome_loop.mp3    (≥4s, sauts lettres)
     assets/sounds/welcome_end.mp3     (stinger fin boucle)
     assets/sounds/welcome_burst.mp3   (cercles finaux)
-->

<
<div id="welcome-overlay" aria-hidden="true">
  <div id="wl-bg"></div>
  <div id="wl-stage">
    <div id="wl-circle-wrap">
      <canvas id="wl-circle" width="120" height="120"></canvas>
    </div>
    <div id="wl-logo-wrap">
      <div id="wl-logo"><?php
        foreach (str_split('FoodHub') as $l) {
            echo "<span class='wl-letter'>$l</span>";
        }
      ?></div>
    </div>
    <div id="wl-burst-wrap"></div>
  </div>
</div>

<style>
#welcome-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 999999;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  pointer-events: all;
  background: transparent;
}
#wl-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
}
#wl-stage {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
}
#wl-circle-wrap {
  margin-bottom: 18px;
  opacity: 0;
  transform: scale(0.9);
  transition: opacity 0.4s ease, transform 0.4s ease;
}
#wl-circle-wrap.wl-visible {
  opacity: 1;
  transform: scale(1);
}
#wl-logo-wrap {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.8s ease, transform 0.8s ease;
}
#wl-logo-wrap.wl-visible {
  opacity: 1;
  transform: translateY(0);
}
#wl-logo-wrap.wl-exit {
  opacity: 0;
  transform: scale(0.85);
  transition: opacity 0.7s ease, transform 0.7s ease;
}
#wl-logo {
  font-family: 'HSR';
  font-size: clamp(2.6rem, 8vw, 5rem);
  font-weight: 900;
  letter-spacing: 0.04em;
  color: #fff;
  display: flex;
  user-select: none;
  perspective: 600px;
  text-shadow: 0 0 14px rgba(220, 50, 30, 0.8), 0 0 32px rgba(220, 50, 30, 0.4);
}
.wl-letter {
  display: inline-block;
  transform-origin: bottom center;
  will-change: transform, text-shadow;
  text-shadow: 0 0 6px rgba(220, 50, 30, 0.35);
}
#wl-burst-wrap {
  position: absolute;
  top: -70px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 14px;
  align-items: center;
  pointer-events: none;
}
.wl-burst-circle {
  opacity: 0;
  transform: scale(0.5);
}
</style>
    <?php
}

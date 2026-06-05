<?php
/**
 * welcome_animation.php
 * À inclure avec require_once AVANT le <!DOCTYPE html> dans home.php.
 * Nécessite : session_start() déjà fait, $conn (PDO) disponible, $_SESSION['user_id'] défini.
 */

$show_welcome   = false;
$welcome_reason = '';
$_wl_uid        = (int)($_SESSION['user_id'] ?? 0);

if ($_wl_uid > 0) {

    /* ── Cas 1 : nouveau compte (flag posé par register.php) ── */
    if (!empty($_SESSION['nouveau_compte'])) {
        $show_welcome   = true;
        $welcome_reason = 'new_account';
        unset($_SESSION['nouveau_compte']);

    } else {
        /* ── Récupérer les champs nécessaires de l'utilisateur ── */
        $_wl_stmt = $conn->prepare(
            "SELECT date_inscription, derniere_connexion FROM users WHERE user_id = ?"
        );
        $_wl_stmt->execute([$_wl_uid]);
        $_wl_row = $_wl_stmt->fetch(PDO::FETCH_ASSOC);

        if ($_wl_row) {
            $created_at  = strtotime($_wl_row['date_inscription']   ?? '') ?: 0;
            $last_conn   = strtotime($_wl_row['derniere_connexion']  ?? '') ?: 0;
            $age_hours   = $created_at ? (time() - $created_at) / 3600 : 999;

            /* Compte de plus de 24h → première connexion après 6h du matin aujourd'hui */
            if ($age_hours >= 24) {
                $six_am_today = mktime(6, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
                if ($last_conn < $six_am_today) {
                    $show_welcome   = true;
                    $welcome_reason = 'daily';
                }
            }
        }
    }
}

$_wl_show_js = $show_welcome ? 'true' : 'false';
?>

<?php if ($show_welcome): ?>
<style id="wl-mask">body,body *{pointer-events:none!important}#welcome-overlay{display:none}</style>
<?php endif; ?>

<!-- ═══════════════════════════════════════ WELCOME OVERLAY ══════════════════════════════════════
     Sons placeholder — remplace les chemins quand tu as les fichiers :
       /assets/sounds/welcome_circle.mp3   (son cercle, 2-4s)
       /assets/sounds/welcome_loop.mp3     (son lettres, ≥4s)
       /assets/sounds/welcome_end.mp3      (stinger de fin)
       /assets/sounds/welcome_burst.mp3    (son cercles finaux)
═══════════════════════════════════════════════════════════════════════════════════════════════ -->
<div id="welcome-overlay">
  <div id="wl-bg"></div>
  <div id="wl-stage">
    <div id="wl-circle-wrap">
      <canvas id="wl-circle" width="120" height="120"></canvas>
    </div>
    <div id="wl-logo-wrap">
      <div id="wl-logo"><?php
        foreach (str_split('FoodHub') as $i => $l) {
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
  background: #0a0a0a;
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
}
#wl-logo-wrap {
  opacity: 0;
  transform: translateY(30px);
}
#wl-logo {
  font-family: 'Montserrat','Segoe UI',sans-serif;
  font-size: clamp(2.6rem, 8vw, 5rem);
  font-weight: 900;
  letter-spacing: .04em;
  color: #fff;
  display: flex;
  user-select: none;
  text-shadow: 0 0 14px rgba(220,50,30,.8), 0 0 32px rgba(220,50,30,.4);
}
.wl-letter {
  display: inline-block;
  transform-origin: bottom center;
  will-change: transform, text-shadow;
  text-shadow: 0 0 6px rgba(220,50,30,.35);
}
</style>

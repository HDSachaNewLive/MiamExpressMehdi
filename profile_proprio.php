<?php
// profile_proprio.php
session_start();
require_once 'db/config.php';
require_once 'upload_helper.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid = (int)$_SESSION['user_id'];
$msg = $_SESSION['msg'] ?? '';
if (isset($_SESSION['msg'])) unset($_SESSION['msg']);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
$proprio_email_fictif_warning = ($user['email_fictif'] == 1);
if (!$user) { abort_404('profil'); }

if (!$user['compte_actif']){
  header("Location: desactive.php"); 
  exit;
} 

$proprio_email_fictif_warning = ($user['type_compte'] === 'proprietaire' && $user['email_fictif']);

$couleur_vanta_public = $user['couleur_vanta'] ?? '#7cc6e6';

$proprio_email_fictif_warning = ($user['email_fictif'] == 1);

if ($user['type_compte'] !== 'proprietaire') {
    header('Location: profile.php');
    exit;
}

// récupérer restaurants du proprio
$stmt_r = $conn->prepare("SELECT * FROM restaurants WHERE proprietaire_id = ? ORDER BY nom_restaurant");
$stmt_r->execute([$uid]);
$restaurants = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

// ── AJAX : Renvoyer l'email de vérification ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resend_verify'])) {
    header('Content-Type: application/json; charset=utf-8');
  require_once 'mail_helper.php';
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
    exit;
  }
  $sent = fh_send_verify_email($conn, $uid, $user['nom_user'], $user['email']);
    echo json_encode(['success' => $sent]);
    exit;
}

// ── AJAX : Demande de changement d'email ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_change_email'])) {
    header('Content-Type: application/json; charset=utf-8');
    require_once 'mail_helper.php';
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
    exit;
  }

    $new_email = trim($_POST['new_email'] ?? '');
    $is_fictif = fh_is_admin($conn) ? (int)($_POST['is_fictif'] ?? 0) : 0;

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Email invalide.']);
        exit;
    }
    $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $chk->execute([$new_email, $uid]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé par un autre compte.']);
        exit;
    }

    if ($is_fictif && fh_is_admin($conn)) {
    $conn->prepare("UPDATE email_tokens SET used = 1 WHERE user_id = ? AND type = 'verify' AND used = 0")
         ->execute([$uid]);
    $conn->prepare("UPDATE users SET email = ?, email_fictif = 1, email_verifie = 1, email_verifie_at = NOW() WHERE user_id = ?")
         ->execute([$new_email, $uid]);
    echo json_encode(['success' => true, 'fictif' => true]);
    } else {
    $sent = fh_send_verify_email($conn, $uid, $user['nom_user'], $new_email, 'verify', $new_email);
    $conn->prepare("UPDATE users SET email_fictif = 0, email_verifie = 0 WHERE user_id = ?")->execute([$uid]);
    echo json_encode(['success' => $sent, 'fictif' => false]);
    }
    exit;
}

// ── Traitement formulaire principal ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Vérification CSRF pour les actions POST (les AJAX sont traités séparément)
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $_SESSION['msg'] = 'Jeton CSRF invalide.';
    header('Location: profile_proprio.php');
    exit;
  }
    if (isset($_POST['delete_photo'])) {
      if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $_SESSION['msg'] = 'Jeton CSRF invalide.';
        header('Location: profile_proprio.php');
        exit;
      }
        if ($user['photo_profil'] && file_exists($user['photo_profil'])) unlink($user['photo_profil']);
        $conn->prepare("UPDATE users SET photo_profil = NULL WHERE user_id = ?")->execute([$uid]);
        $_SESSION['msg'] = "Photo de profil supprimée.";
        header('Location: profile_proprio.php');
        exit;
    } else {
        $nom                = trim($_POST['nom_user']           ?? '');
        $tel                = trim($_POST['telephone']          ?? '');
        $adresse            = trim($_POST['adresse_livraison']  ?? '');
        $description        = trim($_POST['description_profil'] ?? '');
        $couleur_vanta_form = trim($_POST['couleur_vanta']      ?? '#7cc6e6');
        $new_pass           = $_POST['motdepasse']              ?? '';
        $confirm_pass       = $_POST['confirm_motdepasse']      ?? '';

        if ($nom === '') $errors[] = "Nom requis.";
        if ($new_pass !== '') {
            if ($new_pass !== $confirm_pass) $errors[] = "Les mots de passe ne correspondent pas.";
            elseif (strlen($new_pass) < 6)   $errors[] = "Mot de passe trop court (6+).";
        }

        $photo_path = $user['photo_profil'];
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
          $upload_dir = 'uploads/profils/';
          if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
          $res = fh_handle_image_upload($_FILES['photo_profil'], $upload_dir, 5242880);
          if ($res['success']) {
            $new_path = $upload_dir . $res['filename'];
            if ($photo_path && file_exists($photo_path)) unlink($photo_path);
            $photo_path = $new_path;
          } else {
            $errors[] = $res['error'] ?? "Erreur lors de l'upload.";
          }
        }

        if (empty($errors)) {
            if ($new_pass !== '') {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE users SET nom_user=?, telephone=?, adresse_livraison=?, description_profil=?, photo_profil=?, couleur_vanta=?, motdepasse=? WHERE user_id=?")
                     ->execute([$nom, $tel, $adresse, $description, $photo_path, $couleur_vanta_form, $hash, $uid]);
            } else {
                $conn->prepare("UPDATE users SET nom_user=?, telephone=?, adresse_livraison=?, description_profil=?, photo_profil=?, couleur_vanta=? WHERE user_id=?")
                     ->execute([$nom, $tel, $adresse, $description, $photo_path, $couleur_vanta_form, $uid]);
            }
            $couleur_vanta_public          = $couleur_vanta_form;
            $_SESSION['msg']               = "Profil mis à jour.";
            $_SESSION['nom_user']          = $nom;
            $_SESSION['adresse_livraison'] = $adresse;
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
            header('Location: profile_proprio.php');
            exit;
        }
    }
}

// Token de changement d'email en attente
$pending_email = null;
$stmt_pending  = $conn->prepare("
    SELECT new_email FROM email_tokens
    WHERE user_id = ? AND type = 'verify' AND used = 0 AND expires_at > NOW() AND new_email IS NOT NULL
    ORDER BY created_at DESC LIMIT 1
");
$stmt_pending->execute([$uid]);
$row_pending = $stmt_pending->fetch();
if ($row_pending && !$user['email_fictif']) $pending_email = $row_pending['new_email'];
?>
<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Profil - Propriétaire</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="profil_image.css">
</head>
<?php include 'sidebar.php'; ?>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Account Settings Wii U System Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
  </style>
<main class="container">
  <h2>Mon profil - Propriétaire</h2>

  <?php if ($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="error"><?php foreach($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div><?php endif; ?>

  <div class="profile-preview">
    <div class="current-photo">
      <?php if ($user['photo_profil'] && file_exists($user['photo_profil'])): ?>
        <img src="<?= htmlspecialchars($user['photo_profil']) ?>" alt="Photo actuelle">
      <?php else: ?>
        <div class="default-photo"><?= strtoupper(substr($user['nom_user'], 0, 2)) ?></div>
      <?php endif; ?>
    </div>
    <a href="profil_public.php?user_id=<?= $uid ?>" class="btn-public-profile">👁️ Voir mon profil public</a>
  </div>

  <!-- section email + vérif-->
  <div class="email-section">
    <h4>📧 Adresse email</h4>
    <?php if ($proprio_email_fictif_warning): ?>
  <div class="proprio-fictif-alert">
    ⚠️ <strong>Action requise :</strong> Ton compte propriétaire utilise actuellement une adresse email fictive. 
    Tu dois renseigner une vraie adresse email pour continuer à utiliser toutes les fonctionnalités 
    (vérification de restaurant, etc.).<br>
    Utilise la section <em>"Modifier mon adresse email"</em> ci-dessous pour la mettre à jour.
  </div>
  <?php endif; ?>

    <div class="email-current-row">
      <span class="email-address"><?= htmlspecialchars($user['email']) ?></span>
      <?php if ($user['email_fictif']): ?>
        <span class="badge-email fictif">📝 Fictive</span>
      <?php elseif ($user['email_verifie']): ?>
        <span class="badge-email verifie">✅ Vérifiée</span>
      <?php else: ?>
        <span class="badge-email non-verifie">⚠️ Non vérifiée</span>
      <?php endif; ?>
    </div>

    <?php if ($pending_email): ?>
      <div class="pending-email-box">
        ⏳ Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($pending_email) ?></strong>.<br>
        Clique sur le lien reçu pour valider le changement.
      </div>
    <?php endif; ?>

    <?php if (!$user['email_fictif'] && !$user['email_verifie']): ?>
      <div class="verify-alert">
        <p>Ton adresse email n'a pas encore été vérifiée. Certaines fonctionnalités peuvent être limitées.</p>
        <button type="button" class="btn-resend" id="btn-resend-verify">
          📨 Renvoyer l'email de vérification
        </button>
        <div id="resend-msg" class="inline-msg" style="display:none;"></div>
      </div>
    <?php endif; ?>

    <details class="change-email-details" <?= $proprio_email_fictif_warning ? 'open' : '' ?>>
      <summary>✏️ Modifier mon adresse email</summary>
      <div class="change-email-form">
        <p class="change-email-info">
          Saisis ta nouvelle adresse email ci-dessous.<br>
          Tu es propriétaire de restaurants, elle ne peut pas être <strong>fictive</strong>.
        </p>
        <input type="email" id="new-email-input" placeholder="Nouvelle adresse email" autocomplete="email">
        
        <button type="button" class="btn-change-email" id="btn-change-email">
          Enregistrer la nouvelle adresse
        </button>
        <div id="change-email-msg" class="inline-msg" style="display:none;"></div>
      </div>
    </details>
  </div>

  <form method="post" action="profile_proprio.php" class="form" enctype="multipart/form-data">
    <?= fh_csrf_field() ?>
    <input type="text"  maxlength="45" name="nom_user"  required value="<?= htmlspecialchars($_POST['nom_user'] ?? $user['nom_user']) ?>"><br>
    <input type="text"  name="telephone" placeholder="Téléphone" value="<?= htmlspecialchars($_POST['telephone'] ?? $user['telephone'] ?? '') ?>">
    <input type="text"  name="adresse_livraison" placeholder="Adresse de livraison" value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? $user['adresse_livraison'] ?? '') ?>" data-address-autocomplete>
    <p style="color:rgba(0,0,0,0.75);font-size:1rem;margin-bottom:15px;">
      Compte créé le : <b><?= htmlspecialchars(date("d/m/Y H:i", strtotime($user['date_creation']))) ?></b>
    </p>

    <button class="btn" type="submit">Sauvegarder les modifications</button>

    <hr>
    <div class="section-photo">
      <h4 style="color:#000000cb;margin-top:1rem;">Photo de profil</h4>
      <input type="file" name="photo_profil" id="photo_profil" accept="image/*"><br>
      <div id="photo-preview" style="display:none;margin-top:10px;margin-bottom:15px;text-align:center;">
        <img id="preview-img" src="" alt="Aperçu" style="max-width:120px;height:120px;object-fit:cover;border-radius:50%;border:3px solid #ff6b6b;">
      </div>
      <?php if ($user['photo_profil'] && file_exists($user['photo_profil'])): ?>
        <button type="submit" name="delete_photo" value="1" class="btn-delete-photo" style="margin-top:10px;">🗑️ Supprimer la photo actuelle</button>
      <?php endif; ?>
        <?= fh_csrf_field() ?>
    </div>

    <hr>
    <div class="section-description">
      <h4 style="color:#000000cb;margin-top:1rem;">Description de votre profil</h4>
      <textarea name="description_profil" rows="4" placeholder="Parle de toi..."><?= htmlspecialchars($_POST['description_profil'] ?? $user['description_profil'] ?? '') ?></textarea><br>
    </div>

    <hr>
    <div class="section-couleur">
      <h4 style="color:#000000cb;margin-top:1rem;">Couleur du fond de votre profil public</h4>
      <input type="color" name="couleur_vanta" value="<?= htmlspecialchars($couleur_vanta_public) ?>" id="couleur_vanta" style="width:80px;height:40px;border:none;border-radius:8px;cursor:pointer;">
      <span id="color-preview-text" style="font-family:monospace;font-weight:700;color:rgba(0,0,0,0.75);"><?= htmlspecialchars($couleur_vanta_public) ?></span><br>
    </div>

    <hr>
    <?php if (!empty($user['google_id'])): ?>
      <p><small>ℹ️ Ton compte est lié à Google.</small><br>
        <?= empty($user['telephone']) && empty($user['adresse_livraison'])
            ? 'Tu peux définir un mot de passe pour te connecter aussi sans Google.'
            : 'Changer le mot de passe (laisser vide pour garder l\'actuel).' ?>
      </p>
    <?php else: ?>
      <p>Changer le mot de passe (laisser vide pour garder l'actuel)</p>
    <?php endif; ?>
    <input type="password" name="motdepasse" placeholder="<?= !empty($user['google_id']) ? 'Définir ou changer le mot de passe' : 'Nouveau mot de passe' ?>"><br>
    <input type="password" name="confirm_motdepasse" placeholder="Confirmer le mot de passe"><br>

    <button class="btn" type="submit">Sauvegarder les modifications</button>
  </form>

  <hr>
  <h3>Mes restaurants</h3>
  <a href="statistiques_vendeur.php" class="btn-stat" style="margin-top:20px;">📊 Voir les statistiques</a>
  <?php if (count($restaurants) > 0): ?>
    <div class="restaurant-cards">
      <?php foreach($restaurants as $r): ?>
        <div class="restaurant-card">
          <h4><?= htmlspecialchars($r['nom_restaurant']) ?></h4>
          <div class="card-buttons">
            <a href="vendor_edit_restaurant.php?restaurant_id=<?= $r['restaurant_id'] ?>">✏️ Modifier</a>
            <a href="menu.php?restaurant_id=<?= $r['restaurant_id'] ?>">👀 Voir</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <br><p>Tu n'as encore créé aucun restaurant.</p>
  <?php endif; ?>

  <a href="vendor_add_restaurant.php" class="btn" style="margin-top:25px;font-family:'HSR',sans-serif;background:var(--accent);color:white;border:none;border-radius:12px;padding:10px 18px;cursor:pointer;transition:all 0.3s ease;">+ Ajouter un restaurant</a>

    <hr>
    <h4>📦 Mes données personnelles</h4>
        <p style="font-size:0.9rem;color:#000000cb;">Télécharge l'ensemble de tes données conformément au RGPD (Art. 20).</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:10px;">
      <a href="export_mes_donnees.php?format=pdf" class="btn-export">
        🖨️ Exporter en PDF
      </a>
      <a href="export_mes_donnees.php?format=json" class="btn-export btn-export-json">
        ⬇️ Exporter en JSON
      </a>
      </div>
    
  <hr>
  <h4>Supprimer le compte</h4>
  <?php if ($user['user_id'] != 1): ?>
  <form method="post" action="delete_account.php" onsubmit="return confirm('Tu es sûr ? Cette action est IRRÉVERSIBLE.')">
    <?= fh_csrf_field() ?>
    <button class="btn-del" type="submit">Supprimer mon compte</button>
  </form>
  <?php else: ?>
  <p>Vous ne pouvez pas supprimer votre compte.</p>
  <ul><li>ℹ️ Vous avez l'ID utilisateur n°1, par conséquent vous êtes administrateur et votre rôle est indispensable au fonctionnement du site.</li></ul>
  <?php endif; ?>

  <p style="margin-top:20px;margin-bottom:-10px;"><a href="home.php">← Retour</a></p>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
window.vantaEffect = VANTA.WAVES({
  el: "body", mouseControls: true, touchControls: true, gyroControls: false,
  minHeight: 885.00, minWidth: 200.00, scale: 1.00, scaleMobile: 1.00,
  color: 0x7cc6e6, shininess: 25, waveHeight: 25, waveSpeed: 0.9, zoom: 0.9
});

document.getElementById('photo_profil')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('preview-img').src = e.target.result;
      document.getElementById('photo-preview').style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

document.getElementById('couleur_vanta')?.addEventListener('input', function(e) {
  document.getElementById('color-preview-text').textContent = e.target.value;
});

// ── Renvoyer email de vérification ───────────────────────────
document.getElementById('btn-resend-verify')?.addEventListener('click', async function() {
  const btn   = this;
  const msgEl = document.getElementById('resend-msg');
  btn.disabled = true;
  btn.textContent = 'Envoi…';
  msgEl.style.display = 'none';
  try {
    const fd = new FormData();
    fd.append('ajax_resend_verify', '1');
    const csrfEl = document.querySelector('input[name="csrf_token"]');
    if (csrfEl) fd.append('csrf_token', csrfEl.value);
    const resp = await fetch('profile_proprio.php', { method: 'POST', body: fd });
    const data = await resp.json();
    msgEl.style.display = 'block';
    if (data.success) {
      msgEl.className   = 'inline-msg success';
      msgEl.textContent = '✅ Email de vérification renvoyé ! Vérifie ta boîte de réception.';
      btn.textContent   = 'Envoyé ✓';
    } else {
      msgEl.className   = 'inline-msg error';
      msgEl.textContent = '❌ Échec de l\'envoi. Réessaie dans un instant.';
      btn.disabled      = false;
      btn.textContent   = '📨 Renvoyer l\'email de vérification';
    }
  } catch(e) {
    msgEl.style.display = 'block';
    msgEl.className     = 'inline-msg error';
    msgEl.textContent   = '❌ Erreur réseau.';
    btn.disabled        = false;
    btn.textContent     = '📨 Renvoyer l\'email de vérification';
  }
});

// ── Changer l'email ───────────────────────────────────────────
document.getElementById('btn-change-email')?.addEventListener('click', async function() {
  const btn      = this;
  const newEmail = document.getElementById('new-email-input').value.trim();
  const isFictif = 0; // Les propriétaires ne peuvent pas utiliser un email fictif
  const msgEl    = document.getElementById('change-email-msg');

  if (!newEmail) {
    msgEl.style.display = 'block';
    msgEl.className     = 'inline-msg error';
    msgEl.textContent   = '❌ Saisis une adresse email.';
    return;
  }
  btn.disabled        = true;
  btn.textContent     = 'Enregistrement…';
  msgEl.style.display = 'none';
  try {
    const fd = new FormData();
    fd.append('ajax_change_email', '1');
    fd.append('new_email',  newEmail);
    fd.append('is_fictif',  isFictif);
    const csrfEl2 = document.querySelector('input[name="csrf_token"]');
    if (csrfEl2) fd.append('csrf_token', csrfEl2.value);
    const resp = await fetch('profile_proprio.php', { method: 'POST', body: fd });
    const data = await resp.json();
    msgEl.style.display = 'block';
    if (data.success) {
      if (data.fictif) {
        msgEl.className   = 'inline-msg success';
        msgEl.textContent = '✅ Adresse mise à jour immédiatement (email fictif).';
        setTimeout(() => location.reload(), 1500);
      } else {
        msgEl.className   = 'inline-msg success';
        msgEl.textContent = '📧 Un email de confirmation a été envoyé à ' + newEmail + '. Clique sur le lien pour valider.';
        btn.textContent   = 'Email envoyé ✓';
      }
    } else {
      msgEl.className   = 'inline-msg error';
      msgEl.textContent = '❌ ' + (data.error || 'Erreur inconnue.');
      btn.disabled      = false;
      btn.textContent   = 'Enregistrer la nouvelle adresse';
    }
  } catch(e) {
    msgEl.style.display = 'block';
    msgEl.className     = 'inline-msg error';
    msgEl.textContent   = '❌ Erreur réseau.';
    btn.disabled        = false;
    btn.textContent     = 'Enregistrer la nouvelle adresse';
  }
});
</script>

<style>
.container {
  max-width: 700px; margin: 100px auto; padding: 40px;
  border-radius: 20px; backdrop-filter: blur(15px);
  background: rgba(255,255,255,0.15);
  box-shadow: 0 8px 30px rgba(0,0,0,0.25);
  border: 1px solid rgba(255,255,255,0.25);
  color: #fff; text-align: center;
  font-family: 'HSR', sans-serif; animation: fadeIn 0.8s ease;
}
.container h2 { color: rgba(0,0,0,0.78); }
.container h3 { color: rgba(32,32,32,0.75); }
.container h4 { color: rgba(0,0,0,0.75); }
.container p  { color: #000000cb; }

/* ── Section email (identique profile.php) ── */
.email-section {
  background: rgba(255,255,255,0.18);
  border-radius: 1rem; padding: 1.4rem 1.6rem;
  padding-bottom: 0.6rem;
  margin-bottom: 0.2rem; text-align: left;
}
.email-section h4 { color: #333 !important; margin: 0 0 0.8rem 0; font-size: 1.1rem; }
.email-current-row {
  display: flex; align-items: center; gap: 0.8rem;
  flex-wrap: wrap; margin-bottom: 0.8rem;
}
.email-address { font-weight: 600; color: #333; font-size: 1rem; word-break: break-all; }
.badge-email {
  padding: 0.25rem 0.7rem; border-radius: 10px;
  font-size: 0.8rem; font-weight: 700; white-space: nowrap;
}
.badge-email.verifie     { background: rgba(76,175,80,0.2);  color: #2e7d32; }
.badge-email.non-verifie { background: rgba(255,152,0,0.2);  color: #e65100; }
.badge-email.fictif      { background: rgba(158,158,158,0.2);color: #555; }
.pending-email-box {
  background: rgba(33,150,243,0.12); border-left: 4px solid #2196F3;
  border-radius: 0.8rem; padding: 0.8rem 1rem; color: #1565c0;
  font-size: 0.9rem; margin-bottom: 0.8rem; line-height: 1.5;
}
.verify-alert {
  background: rgba(255,152,0,0.12); border-left: 4px solid #FF9800;
  border-radius: 0.8rem; padding: 0.9rem 1rem; color: #e65100;
  font-size: 0.9rem; margin-bottom: 0.8rem; line-height: 1.5;
}
.verify-alert p { margin: 0 0 0.7rem; color: #e65100; }
.btn-resend {
  padding: 0.6rem 1.2rem;
  background: #f68b00;
  color: white; border: none; border-radius: 10px;
  font-family: 'HSR', sans-serif; font-weight: 600; font-size: 0.9rem;
  cursor: pointer; transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(255,152,0,0.3);
}
.btn-resend:hover { backdrop-filter: blur(10px); transition: all 0.3s ease; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(255,152,0,0.4); background:  #f68b00a4; }
.btn-resend:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.change-email-details { margin-top: 0.8rem; }
.change-email-details summary {
  cursor: pointer; color: #7cc6e6; font-weight: 600;
  font-size: 0.95rem; padding: 0.5rem 0;
  transition: color 0.3s ease; list-style: none;
}
.change-email-details summary::-webkit-details-marker { display: none; }
.change-email-details summary::before { content: '▶ '; font-size: 0.75rem; }
.change-email-details[open] summary::before { content: '▼ '; }
.change-email-details summary:hover { color: #5ab3d8; }
.change-email-form {
  padding: 1rem 0 0.5rem; display: flex;
  flex-direction: column; gap: 0.7rem;
}
.change-email-info { color: #555 !important; font-size: 0.88rem !important; line-height: 1.5; margin: 0 !important; }
.change-email-form input[type="email"] {
  width: 100%; padding: 10px 14px; border-radius: 10px;
  border: 1px solid rgba(124,198,230,0.4);
  background: rgba(255,255,255,0.55);
  font-family: 'HSR', sans-serif; font-size: 0.95rem;
  outline: none; box-sizing: border-box; transition: all 0.3s ease; color: #222;
}
.change-email-form input[type="email"]:focus {
  border-color: #7cc6e6; background: rgba(255,255,255,0.8);
  box-shadow: 0 0 8px rgba(124,198,230,0.3);
    transform: scale(1.013);
}
.checkbox-label-small {
  display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
  font-size: 0.85rem; color: #444; font-family: 'HSR', sans-serif; line-height: 1.4;
}
.checkbox-label-small input[type="checkbox"] {
  width: 16px !important; height: 16px !important; min-width: 16px;
  margin: 2px 0 0 0 !important; padding: 0 !important;
  accent-color: #7cc6e6; cursor: pointer; flex-shrink: 0; transform: none !important;
}
.checkbox-label-small input[type="checkbox"]:focus { transform: none !important; }
.btn-change-email {
  margin-top: 2px;
  padding: 0.65rem 1.4rem;
  background: #5ab3d8;
  color: white; border: none; border-radius: 10px;
  font-family: 'HSR', sans-serif; font-weight: 600; font-size: 0.95rem;
  cursor: pointer; transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(124,198,230,0.35); align-self: center;
}
.btn-change-email:hover {
  transform: translateY(-2px); box-shadow: 0 6px 18px rgba(124,198,230,0.5);
  background: #7cc6e6c4; backdrop-filter: blur(5px); transform: scale(1.02);
}
.btn-change-email:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.inline-msg { margin-bottom: -20px; padding: 8px 12px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; line-height: 1.4; }
.inline-msg.success { margin-top: 5px; margin-bottom: -3px; background: rgba(76,175,80,0.15); border-left: 3px solid #4CAF50; color: #2e7d32; }
.inline-msg.error   { margin-top: 5px; margin-bottom: -3px; background: rgba(255,77,77,0.15);  border-left: 3px solid #ff4d4d; color: #8b0000; }
#resend-msg.inline-msg.success { margin-top: 12px; }

/* ── Reste styles proprio ── */
.profile-preview {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; margin-bottom: 1rem; padding: 1.5rem;
  background: rgba(255,255,255,0.21); border-radius: 15px;
}
.current-photo { width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid #ff6b6b; }
.current-photo img { width: 100%; height: 100%; object-fit: cover; }
.default-photo {
  width: 100%; height: 100%;
  background: linear-gradient(135deg, #ff6b6b, #ff8c42);
  display: flex; align-items: center; justify-content: center;
  font-size: 3rem; font-weight: 700; color: white;
}
.form input, .form select {
  width: 90%; margin: 10px 0; padding: 12px;
  border-radius: 10px; border: none;
  background: rgba(255,255,255,0.25); color: #000;
  font-size: 1rem; outline: none;
  transition: background 0.3s ease, transform 0.2s; font-family: 'HSR';
}
.form textarea {
  width: 90%; padding: 12px; border-radius: 10px; border: none;
  background: rgba(255,255,255,0.25); color: #000;
  font-size: 1rem; font-family: 'HSR'; resize: none; margin: 10px 0;
}
.form input:focus, .form select:focus, .form textarea:focus {
  background: rgba(255,255,255,0.35); transform: scale(1.02);
}
hr { margin-top: 20px; }
.btn { font-family: 'HSR',sans-serif; background: var(--accent); color: white; border: none; border-radius: 12px; padding: 10px 18px; cursor: pointer; transition: all 0.3s ease; }
.btn:hover { background: var(--accent-dark); transform: translateY(-2px); color: white; }
.btn-delete-photo {
  padding: 8px 16px; background: rgba(244,67,54,0.7); color: white;
  border: none; border-radius: 8px; font-family: 'HSR',sans-serif;
  cursor: pointer; font-weight: 600; transition: all 0.3s ease;
}
.btn-delete-photo:hover { background: rgba(244,67,54,0.85); transform: translateY(-2px); }
.btn-public-profile {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 12px 24px; margin-top: 12px;
  background: linear-gradient(135deg, #33b0d2, #58edf5);
  color: white; text-decoration: none; border-radius: 12px;
  font-family: 'HSR',sans-serif; font-weight: 600; font-size: 1rem;
  cursor: pointer; transition: all 0.35s ease;
  box-shadow: 0 6px 20px rgba(51,176,210,0.3); border: none; position: relative; overflow: hidden;
}
.btn-public-profile:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 10px 30px rgba(51, 176, 210, 0.5);
    background: linear-gradient(135deg, #58edf5, #33b0d2);
    color: white;
}

.restaurant-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    margin-top: 20px;
}

.restaurant-card {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    padding: 25px 20px;
    border-radius: 20px;
    min-width: 220px;
    max-width: 260px;
    text-align: center;
    backdrop-filter: blur(15px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    transition: transform 0.25s, box-shadow 0.25s;
}

.restaurant-card h4 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
    margin: 4px 4px 12px 0
    padding: 0 4px;
}

.restaurant-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
}

.card-buttons a {
    display: inline-block;
    margin: 8px 5px 0;
    padding: 6px 12px;
    border-radius: 12px;
    background: var(--accent);
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: background 0.3s, transform 0.2s;
}

.card-buttons a:hover {
    background: var(--accent-dark);
    transform: translateY(-2px);
}

.btn-stat {
    display: block;
    margin: 0 auto;
    text-align: center;
    font-family: 'HSR', sans-serif;
    font-size: 1.3rem;
    padding: 15px 23px;
    backdrop-filter: blur(15px);
    background: rgba(231, 173, 131, 0.44);
    color: white;
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    max-width: fit-content;
}

.btn-stat:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
    background: rgba(249, 158, 72, 0.55);
}

.btn-del {
    padding: 15px 23px;
    font-size: 1.3rem;
    backdrop-filter: blur(15px);
    background: rgba(231, 131, 131, 0.62);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
    border: none;
    border-radius: 12px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-del:hover {
    background: rgba(255, 100, 100, 0.75);
    box-shadow: 0 8px 35px rgba(255, 80, 80, 0.5);
    transform: translateY(-3px) scale(1.03);
}

.success {
    background: rgba(0, 255, 127, 0.25);
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.error {
    background: rgba(255, 77, 77, 0.25);
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 15px;
}

ul li {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 12px;
    padding: 12px 18px;
    margin-bottom: 10px;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    color: rgba(53, 53, 53, 0.85);
}

ul li:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-2px);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.proprio-fictif-alert {
  background: rgba(255, 152, 0, 0.18);
  border: 2px solid #FF9800;
  border-radius: 1rem;
  padding: 1rem 1.4rem;
  color: #7a4000;
  font-size: 0.95rem;
  line-height: 1.6;
  margin-bottom: 1.2rem;
  text-align: left;
}

.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1.3rem;
  font-size: 1rem;
  font-weight: 600;
  font-family: 'HSR', sans-serif;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transition: all 0.35s ease;
  position: relative;
  overflow: hidden;
}
.btn-export::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}
.btn-export:hover::after { left: 0; }
.btn-export:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 12px 25px rgba(0,0,0,0.25);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}
.btn-export-json {
  background: rgba(255,255,255,0.35);
  color: #444;
  box-shadow: 0 4px 14px rgba(0,0,0,0.1);
  border: 1px solid rgba(255,255,255,0.5);
}
.btn-export-json:hover {
  background: rgba(255,255,255,0.55);
  color: #222;
}
</style>
</main>
<script src="address-autocomplete.js"></script>
</body>
</html>
<?php
// verify_email.php
// Accessible depuis le lien envoyé par email (vérification ou changement d'adresse)
session_start();
require_once 'db/config.php';

$token   = trim($_GET['token'] ?? '');
$status  = 'error'; // 'success' | 'already' | 'expired' | 'error'
$message = '';

if ($token === '') {
    $status  = 'error';
    $message = "Token manquant.";
} else {
    $stmt = $conn->prepare("
        SELECT et.*, u.nom_user, u.email AS email_actuel
        FROM email_tokens et
        JOIN users u ON et.user_id = u.user_id
        WHERE et.token = ?
          AND et.type  = 'verify'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        $status  = 'error';
        $message = "Ce lien de vérification est invalide.";
    } elseif ($row['used']) {
        $status  = 'already';
        $message = "Cette adresse email a déjà été vérifiée.";
    } elseif ($row['expires_at'] < date('Y-m-d H:i:s')) {
        $status  = 'expired';
        $message = "Ce lien a expiré. Fais une nouvelle demande depuis ton profil.";
    } else {
        // Token valide → marquer comme utilisé
        $conn->prepare("UPDATE email_tokens SET used = 1 WHERE token_id = ?")->execute([$row['token_id']]);

        if ($row['new_email']) {
            // Changement d'email : on applique la nouvelle adresse
            $conn->prepare("
                UPDATE users SET email = ?, email_verifie = 1, email_verifie_at = NOW(), email_fictif = 0
                WHERE user_id = ?
            ")->execute([$row['new_email'], $row['user_id']]);
            $status  = 'success';
            $message = "Ton adresse email a été mise à jour vers <strong>" . htmlspecialchars($row['new_email']) . "</strong> et vérifiée avec succès !";
        } else {
            // Vérification initiale
            $conn->prepare("
                UPDATE users SET email_verifie = 1, email_verifie_at = NOW()
                WHERE user_id = ?
            ")->execute([$row['user_id']]);
            $status  = 'success';
            $message = "Ton adresse email <strong>" . htmlspecialchars($row['email_actuel']) . "</strong> a été vérifiée avec succès !";
        }

        // Mettre à jour la session si l'utilisateur est connecté
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$row['user_id']) {
            $_SESSION['email_verifie'] = 1;
        }
    }
}

$connected = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Vérification email - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/verify.css">
</head>
<body>
  <?php include "slider_son.php"; ?>
  <?php if ($connected): ?>
    <?php include "sidebar.php"; ?>
  <?php endif; ?>

  <main class="container">

    <?php if ($status === 'success'): ?>
      <div class="verify-icon">✅</div>
      <h2 class="verify-title success-title">Email vérifié !</h2>
      <p class="verify-msg"><?= $message ?></p>
      <p class="verify-sub">Tu peux maintenant profiter de toutes les fonctionnalités de FoodHub.</p>
      <div class="verify-actions">
        <?php if ($connected): ?>
          <a href="home.php" class="btn-verify">🏠 Accueil</a>
        <?php else: ?>
          <a href="login.php" class="btn-verify">🔐 Se connecter</a>
        <?php endif; ?>
      </div>

    <?php elseif ($status === 'already'): ?>
      <div class="verify-icon">ℹ️</div>
      <h2 class="verify-title info-title">Déjà vérifié</h2>
      <p class="verify-msg"><?= htmlspecialchars($message) ?></p>
      <div class="verify-actions">
        <a href="<?= $connected ? 'home.php' : 'login.php' ?>" class="btn-verify">
          <?= $connected ? '🏠 Accueil' : '🔐 Se connecter' ?>
        </a>
      </div>

    <?php elseif ($status === 'expired'): ?>
      <div class="verify-icon">⏰</div>
      <h2 class="verify-title warn-title">Lien expiré</h2>
      <p class="verify-msg"><?= htmlspecialchars($message) ?></p>
      <div class="verify-actions">
        <?php if ($connected): ?>
          <a href="profile.php" class="btn-verify">👤 Mon profil</a>
        <?php else: ?>
          <a href="login.php" class="btn-verify">🔐 Se connecter</a>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <div class="verify-icon">❌</div>
      <h2 class="verify-title error-title">Lien invalide</h2>
      <p class="verify-msg"><?= htmlspecialchars($message) ?></p>
      <div class="verify-actions">
        <a href="<?= $connected ? 'home.php' : 'index.php' ?>" class="btn-verify">← Retour</a>
      </div>
    <?php endif; ?>

  </main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
window.vantaEffect = VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 885.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xffe599,
  shininess: 40,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
});
</script>
</body>
</html>

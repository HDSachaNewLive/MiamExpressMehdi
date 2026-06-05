<?php
// reset_password.php
// Page atteinte via le lien envoyé par email (comptes avec email réel)
session_start();
require_once 'db/config.php';

$token = trim($_GET['token'] ?? '');
$status = 'form'; // 'form' | 'success' | 'expired' | 'error'
$errors = [];
$row = null;

if ($token === '') {
  $status = 'error';
} else {
  $stmt = $conn->prepare("
        SELECT et.*, u.nom_user, u.email
        FROM email_tokens et
        JOIN users u ON et.user_id = u.user_id
        WHERE et.token = ?
          AND et.type  = 'reset'
        LIMIT 1
    ");
  $stmt->execute([$token]);
  $row = $stmt->fetch();

  if (!$row) {
    $status = 'error';
  } elseif ($row['used']) {
    $status = 'expired';
  } elseif ($row['expires_at'] < date('Y-m-d H:i:s')) {
    $status = 'expired';
  }
}

// Traitement POST : enregistrer le nouveau mot de passe
if ($status === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $new_pass = $_POST['new_pass'] ?? '';
  $confirm_pass = $_POST['confirm_pass'] ?? '';

  if (strlen($new_pass) < 6) {
    $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
  }
  if ($new_pass !== $confirm_pass) {
    $errors[] = "Les mots de passe ne correspondent pas.";
  }

  if (empty($errors)) {
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $conn->prepare("UPDATE users SET motdepasse = ? WHERE user_id = ?")->execute([$hash, $row['user_id']]);
    $conn->prepare("UPDATE email_tokens SET used = 1 WHERE token_id = ?")->execute([$row['token_id']]);
    $status = 'success';
  }
}

$connected = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Nouveau mot de passe - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>
  <?php include "slider_son.php"; ?>
  <?php if ($connected): ?>
    <?php include "sidebar.php"; ?>
  <?php endif; ?>

  <main class="container">

    <?php if ($status === 'form'): ?>
      <div class="reset-icon">🔑</div>
      <h2 class="reset-title">Nouveau mot de passe</h2>
      <p class="reset-sub">Bonjour <strong><?= htmlspecialchars($row['nom_user']) ?></strong> !<br>Choisis un nouveau mot
        de passe pour ton compte.</p>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <?php foreach ($errors as $e)
            echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="reset_password.php?token=<?= urlencode($token) ?>" class="reset-form">
        <input type="password" name="new_pass" placeholder="Nouveau mot de passe (min. 6 caractères)" required
          autocomplete="new-password">
        <input type="password" name="confirm_pass" placeholder="Confirmer le mot de passe" required
          autocomplete="new-password">
        <button type="submit" class="btn-reset">Enregistrer le nouveau mot de passe</button>
      </form>

    <?php elseif ($status === 'success'): ?>
      <div class="reset-icon">✅</div>
      <h2 class="reset-title success-title">Mot de passe modifié !</h2>
      <p class="reset-sub">Ton mot de passe a été mis à jour avec succès. Tu peux maintenant te connecter.</p>
      <div class="reset-actions">
        <a href="login.php" class="btn-reset">🔐 Se connecter</a>
      </div>

    <?php elseif ($status === 'expired'): ?>
      <div class="reset-icon">⏰</div>
      <h2 class="reset-title warn-title">Lien expiré ou déjà utilisé</h2>
      <p class="reset-sub">Ce lien de réinitialisation n'est plus valide. Fais une nouvelle demande depuis la page de
        connexion.</p>
      <div class="reset-actions">
        <a href="login.php" class="btn-reset">← Retour à la connexion</a>
      </div>

    <?php else: ?>
      <div class="reset-icon">❌</div>
      <h2 class="reset-title error-title">Lien invalide</h2>
      <p class="reset-sub">Ce lien de réinitialisation est introuvable ou invalide.</p>
      <div class="reset-actions">
        <a href="login.php" class="btn-reset">← Retour à la connexion</a>
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
      color: 0x7cc6e6,
      shininess: 40,
      waveHeight: 25,
      waveSpeed: 0.9,
      zoom: 0.9
    });
  </script>
  <style>
    .container {
      max-width: 520px;
      margin: 120px auto;
      padding: 3rem 2.5rem;
      border-radius: 1.8rem;
      backdrop-filter: blur(15px);
      background: rgba(255, 255, 255, 0.28);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
      text-align: center;
      animation: fadeInUp 0.6s ease;
      padding-bottom: 30px;
    }

    .reset-icon {
      font-size: 3.5rem;
      margin-bottom: 0.8rem;
    }

    .reset-title {
      font-size: 1.8rem;
      margin: 0 0 0.8rem;
      font-weight: 700;
      color: #333;
    }

    .success-title {
      color: #2e7d32;
    }

    .warn-title {
      color: #856404;
    }

    .error-title {
      color: #c62828;
    }

    .reset-sub {
      color: #555;
      font-size: 1rem;
      line-height: 1.6;
      margin-bottom: 1.5rem;
    }

    .error-box {
      background: rgba(255, 77, 77, 0.18);
      border-left: 4px solid #ff4d4d;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 1rem;
      color: #8b0000;
      font-weight: 600;
      font-size: 0.9rem;
      text-align: left;
    }

    .reset-form {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .reset-form input {
      width: 100%;
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid rgba(124, 198, 230, 0.3);
      background: rgba(255, 255, 255, 0.5);
      font-family: 'HSR', sans-serif;
      font-size: 1rem;
      outline: none;
      box-sizing: border-box;
      transition: all 0.3s ease;
    }

    .reset-form input:focus {
      transform: scale(1.03);
      border-color: #7cc6e6;
      background: rgba(255, 255, 255, 0.75);
      box-shadow: 0 0 10px rgba(124, 198, 230, 0.3);
    }

    .btn-reset {
      backdrop-filter: blur(15px);
      display: inline-block;
      padding: 0.9rem 2rem;
      background: #7cc6e6c0;
      color: white;
      border: none;
      border-radius: 12px;
      font-family: 'HSR', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(124, 198, 230, 0.4);
      transition: all 0.3s ease;
      margin-top: 15px;
    }

    .btn-reset:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 8px 22px rgba(124, 198, 230, 0.5);
      color: white;
      background:  #5ab3d8;
    }

    .reset-actions {
      margin-top: 1.5rem;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

     #volume-slider { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
 
  </style>
</body>

</html>
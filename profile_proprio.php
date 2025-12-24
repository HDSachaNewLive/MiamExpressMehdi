<?php
// profile_proprio.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); 
  exit; 
}

$uid = (int)$_SESSION['user_id'];
$msg = $_SESSION['msg'] ?? '';
if (isset($_SESSION['msg'])) {
    unset($_SESSION['msg']);
}
$errors = [];

// obtenir données existantes
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { die("Utilisateur introuvable."); }

// Vérifier que le compte est actif
if (!$user['compte_actif']) {
    die("Votre compte a été désactivé. Contactez l'administrateur.");
}

// Récupérer la couleur vanta du profil public depuis la bdd
$couleur_vanta_public = $user['couleur_vanta'] ?? '#7cc6e6';

if ($user['type_compte'] !== 'proprietaire') {
    header('Location: profile.php'); // rediriger les autres vers le profil normal
    exit;
}

// récupérer restaurants du proprio
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE proprietaire_id = ? ORDER BY nom_restaurant");
$stmt->execute([$uid]);
$restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// traitement formulaire update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Suppression de la photo
    if (isset($_POST['delete_photo'])) {
        if ($user['photo_profil'] && file_exists($user['photo_profil'])) {
            unlink($user['photo_profil']);
        }
        $u = $conn->prepare("UPDATE users SET photo_profil = NULL WHERE user_id = ?");
        $u->execute([$uid]);
        $_SESSION['msg'] = "Photo de profil supprimée.";
        // Rafraîchir les données
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        header('Location: profile_proprio.php');
        exit;
    } else {
        $nom = trim($_POST['nom_user'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $tel = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse_livraison'] ?? '');
        $description = trim($_POST['description_profil'] ?? '');
        $couleur_vanta_form = trim($_POST['couleur_vanta'] ?? '#7cc6e6');
        $new_pass = $_POST['motdepasse'] ?? '';
        $confirm_pass = $_POST['confirm_motdepasse'] ?? '';

    if ($nom === '' || $email === '') $errors[] = "Nom et email requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";

    if ($email !== $user['email']) {
      $s = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
      $s->execute([$email, $uid]);
      if ($s->fetch()) $errors[] = "Cet email est déjà utilisé.";
    }

    if ($new_pass !== '') {
      if ($new_pass !== $confirm_pass) $errors[] = "Les mots de passe ne correspondent pas.";
      elseif (strlen($new_pass) < 6) $errors[] = "Mot de passe trop court (6+).";
    }
    
    // Gestion de l'upload de photo de profil
    $photo_path = $user['photo_profil'];
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profils/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['photo_profil']['tmp_name'];
        $file_name = $_FILES['photo_profil']['name'];
        $file_size = $_FILES['photo_profil']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_extensions) && $file_size <= 5242880) {
            $new_filename = 'profil_' . $uid . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                // Supprimer l'ancienne photo
                if ($photo_path && file_exists($photo_path)) {
                    unlink($photo_path);
                }
                $photo_path = $destination;
            }
        } else {
            $errors[] = "Format d'image non valide ou fichier trop volumineux (max 5MB).";
        }
    }

    if (empty($errors)) {
      if ($new_pass !== '') {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE users SET nom_user=?, email=?, telephone=?, adresse_livraison=?, description_profil=?, photo_profil=?, couleur_vanta=?, motdepasse=? WHERE user_id=?");
        $u->execute([$nom, $email, $tel, $adresse, $description, $photo_path, $couleur_vanta_form, $hash, $uid]);
      } else {
        $u = $conn->prepare("UPDATE users SET nom_user=?, email=?, telephone=?, adresse_livraison=?, description_profil=?, photo_profil=?, couleur_vanta=? WHERE user_id=?");
        $u->execute([$nom, $email, $tel, $adresse, $description, $photo_path, $couleur_vanta_form, $uid]);
      }
      
      // update la couleur vanta du profil public
      $couleur_vanta_public = $couleur_vanta_form;
      
      $_SESSION['msg'] = "Profil mis à jour.";
      $_SESSION['nom_user'] = $nom;
      $_SESSION['adresse_livraison'] = $adresse;
      $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
      $stmt->execute([$uid]);
      $user = $stmt->fetch();
      header('Location: profile_proprio.php');
      exit;
    }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Profil - Propriétaire</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="profil_image.css">
</head>
<?php include 'sidebar.php'; ?>
<body> 
  <audio id="player" autoplay loop> <source src="assets\Account Settings Wii U System Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
    }
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
        <div class="default-photo">
          <?= strtoupper(substr($user['nom_user'], 0, 2)) ?>
        </div>
      <?php endif; ?>
    </div>
    <a href="profil_public.php?user_id=<?= $uid ?>" class="btn-public-profile">
      👁️ Voir mon profil public
    </a>
  </div>

  <form method="post" action="profile_proprio.php" class="form" enctype="multipart/form-data">
    <input type="text" name="nom_user" required value="<?= htmlspecialchars($_POST['nom_user'] ?? $user['nom_user']) ?>"><br>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>"><br>
    <input type="text" name="telephone" placeholder="Téléphone" value="<?= htmlspecialchars($_POST['telephone'] ?? $user['telephone']) ?>"><br>
    <input type="text" name="adresse_livraison" placeholder="Adresse de livraison" value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? $user['adresse_livraison']) ?>"><br>
    <p style="color:rgba(0, 0, 0, 0.75); font-size:1rem; margin-bottom:15px;">
    Compte créé le : 
    <b><?= htmlspecialchars(date("d/m/Y H:i", strtotime($user['date_creation']))) ?></b>
</p>

    <button class="btn" type="submit">Sauvegarder les modifications</button>

    <hr>

    <div class="section-photo">
      <h4 style="color: #000000cb; margin-top: 1rem;">Photo de profil</h4>
      <input type="file" name="photo_profil" id="photo_profil" accept="image/*"><br>
      <div id="photo-preview" style="display: none; margin-top: 10px; margin-bottom: 15px; text-align: center;">
        <img id="preview-img" src="" alt="Aperçu" style="max-width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #ff6b6b;">
      </div>
      <?php if ($user['photo_profil'] && file_exists($user['photo_profil'])): ?>
        <button type="submit" name="delete_photo" value="1" class="btn-delete-photo" style="margin-top: 10px;">🗑️ Supprimer la photo actuelle</button>
      <?php endif; ?>
    </div>
        
    <hr>

    <div class="section-description">
      <h4 style="color: #000000cb; margin-top: 1rem;">Description de votre profil</h4>
      <textarea name="description_profil" rows="4" placeholder="Parle de toi..."><?php echo htmlspecialchars($_POST['description_profil'] ?? $user['description_profil'] ?? ''); ?></textarea><br>
    </div>

    <hr>

    <div class="section-couleur">
      <h4 style="color: #000000cb; margin-top: 1rem;">Couleur du fond de votre profil public</h4>
      <input type="color" name="couleur_vanta" value="<?= htmlspecialchars($couleur_vanta_public) ?>" id="couleur_vanta" style="width: 80px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
      <span class="color-preview" id="color-preview-text" style="font-family: monospace; font-weight: 700; color: rgba(0, 0, 0, 0.75);"><?= htmlspecialchars($couleur_vanta_public) ?></span><br>
    </div>

    <hr>
    <p>Changer le mot de passe (laisser vide pour garder l'actuel)</p>
    <input type="password" name="motdepasse" placeholder="Nouveau mot de passe"><br>
    <input type="password" name="confirm_motdepasse" placeholder="Confirmer le mot de passe"><br>

    <button class="btn" type="submit">Sauvegarder les modifications</button>
  </form>

  <hr>
  <h3>Mes restaurants</h3>
  <a href="statistiques_vendeur.php" class="btn-stat" style="margin-top: 20px;"> Voir les statistiques</a>
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
      <p>Tu n'as encore créé aucun restaurant.</p>
  <?php endif; ?>

  <a href="vendor_add_restaurant.php" class="btn" style="margin-top: 25px; font-family: 'HSR', sans-serif; background: var(--accent); color: white; border: none; border-radius: 12px; padding: 10px 18px; cursor: pointer;
  transition: all 0.3s ease;">+ Ajouter un restaurant</a>
  <hr>
  <h4>Supprimer le compte</h4>
  <?php if ($user['user_id'] != 1): ?>
  <form method="post" action="delete_account.php" onsubmit="return confirm('Tu es sûr ? Cette action est IRRÉVERSIBLE.')">
    <button class="btn-del" type="submit">Supprimer mon compte</button>
  </form>
  <?php endif; ?>
  <?php if ($user['user_id'] === 1): ?>
  <p>Vous ne pouvez pas supprimer votre compte.</p>
  <ul>
    <li>ℹ️ Vous avez l'ID utilisateur n°1, par conséquent vous êtes administrateur et votre rôle est indispensable au fonctionnement du site.</li>
  </ul>
  <?php endif; ?>

  <p><a href="home.php">← Retour</a></p>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 885.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0x7cc6e6,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
})

// Preview photo
document.getElementById('photo_profil')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('preview-img').src = e.target.result;
      document.getElementById('photo-preview').style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

// Color picker preview
document.getElementById('couleur_vanta')?.addEventListener('input', function(e) {
  document.getElementById('color-preview-text').textContent = e.target.value;
});
</script>
<style>
.container {
  max-width: 700px;
  margin: 100px auto;
  padding: 40px;
  border-radius: 20px;
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #fff;
  text-align: center;
  font-family: 'HSR', sans-serif;
  animation: fadeIn 0.8s ease;
}

.container h1 {
  color: #f37163;
  text-shadow: 4px 4px 6px rgba(255, 107, 107, 0.53);
}
.container h4 {
  color:rgba(0, 0, 0, 0.75);
} 
.container h3 {
  color: rgba(0, 0, 0, 0.75)
}
.container p {
  color: #000000cb;
}

.profile-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.21);
  border-radius: 15px;
}

.current-photo {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  overflow: hidden;
  border: 4px solid #ff6b6b;
}

.current-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.default-photo {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #ff6b6b, #ff8c42);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  font-weight: 700;
  color: white;
}

.form input {
  width: 90%;
  margin: 10px 0;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  outline: none;
  transition: background 0.3s ease, transform 0.2s;
  font-family: 'HSR';
}

.form textarea {
  width: 90%;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  font-family: 'HSR';
  resize: none;
  margin: 10px 0;
}

.form input:focus, .form textarea:focus {
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
}
hr {
  margin-top: 20px;
}
.btn {
  font-family: 'HSR', sans-serif;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 10px 18px;
  cursor: pointer;
  transition: all 0.3s ease;
}
.btn:hover {
  background: var(--accent-dark);
  transform: translateY(-2px);
  color: white;
}

.btn-delete-photo {
  padding: 8px 16px;
  background: rgba(244, 67, 54, 0.7);
  color: white;
  border: none;
  border-radius: 8px;
  font-family: 'HSR', sans-serif;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}

.btn-delete-photo:hover {
  background: rgba(244, 67, 54, 0.85);
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(244, 67, 54, 0.4);
}

.btn-public-profile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 24px;
  margin-top: 12px;
  background: linear-gradient(135deg, #33b0d2, #58edf5);
  color: white;
  text-decoration: none;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.35s ease;
  box-shadow: 0 6px 20px rgba(51, 176, 210, 0.3);
  border: none;
  position: relative;
  overflow: hidden;
}

.btn-public-profile::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.2);
  transition: left 0.4s ease;
  border-radius: 12px;
}

.btn-public-profile:hover {
  transform: translateY(-4px) scale(1.05);
  box-shadow: 0 10px 30px rgba(51, 176, 210, 0.5);
  background: linear-gradient(135deg, #58edf5, #33b0d2);
  color: white;
}

.btn-public-profile:hover::before {
  left: 0;
}

.btn-public-profile:active {
  transform: translateY(-2px) scale(1.02);
}
/*styles pour les cartes restos dans le profil*/
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
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
  transition: transform 0.25s, box-shadow 0.25s;
}

.restaurant-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(0,0,0,0.4);
}

.card-buttons a {
  display: inline-block;
  margin: 8px 5px 0 5px;
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

.container .btn-del {
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
  transition: all 0.3s ease, transform 0.2s;
}

.container .btn-del:hover {
  background: rgba(255, 100, 100, 0.75);
  box-shadow: 0 8px 35px rgba(255, 80, 80, 0.5);
  transform: translateY(-3px) scale(1.03);
}

.container .btn-del:active {
  transform: scale(1.05);
  box-shadow: 0 5px 20px rgba(255, 80, 80, 0.4);
}

.btn-add-resto:hover {
  background: var(--accent-dark);
  transform: translateY(-2px), scale(1.05);
  color: white;
}
/* btn stats - NON UTILISÉÉÉÉ*/
.btn-stat-color {
  background: rgba(236, 198, 95, 0.67); 
  color: #fff;
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
  box-shadow: 0 8px 30px rgba(0,0,0,0.25);
  cursor: pointer;
  transition: all 0.3s ease, transform 0.2s;
  text-decoration: none;
  display: block;
  max-width: fit-content; 
}

.btn-stat:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    background: rgba(249, 158, 72, 0.55);
}

.btn-stat:active {
  transform: scale(0.98);
  box-shadow: 0 5px 20px rgba(255, 80, 80, 0.4);
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

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
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

</style>

</main>
</body>
</html>

<?php
// commenter.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $restaurant_id = (int)($_POST['restaurant_id'] ?? 0);
  $note = max(1, min(5, (int)($_POST['note'] ?? 5)));
  $commentaire = trim($_POST['commentaire'] ?? '');

  if ($restaurant_id > 0 && $commentaire !== '') {
    
    // Gestion de l'upload d'image
    $image_path = null;
    if (isset($_FILES['image_avis']) && $_FILES['image_avis']['error'] === UPLOAD_ERR_OK) {
      $upload_dir = 'uploads/avis/';
      
      // Créer le dossier s'il n'existe pas
      if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
      }
      
      $file_tmp = $_FILES['image_avis']['tmp_name'];
      $file_name = $_FILES['image_avis']['name'];
      $file_size = $_FILES['image_avis']['size'];
      $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
      
      // Extensions autorisées
      $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      
      // Vérifications
      if (in_array($file_ext, $allowed_extensions) && $file_size <= 5242880) { // 5MB max
        // Nom unique pour éviter les conflits
        $new_filename = uniqid('avis_', true) . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file_tmp, $destination)) {
          $image_path = $destination;
        }
      }
    }
    
    $ins = $conn->prepare("INSERT INTO avis (user_id, restaurant_id, note, commentaire, image_path) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$uid, $restaurant_id, $note, $commentaire, $image_path]);

    // --- NOTIF POUR LE PROPRIÉTAIRE ---
    $stmt2 = $conn->prepare("SELECT proprietaire_id, nom_restaurant FROM restaurants WHERE restaurant_id=?");
    $stmt2->execute([$restaurant_id]);
    $resto = $stmt2->fetch();

    if ($resto && $resto['proprietaire_id'] != $uid) {
        $message = "Un utilisateur a commenté dans la page de votre restaurant « {$resto['nom_restaurant']} » ";
        $stmt3 = $conn->prepare("
            INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) 
            VALUES (?, 'comment', ?, ?, ?)
        ");
        $lastAvisId = $conn->lastInsertId();
        $stmt3->execute([$resto['proprietaire_id'], $restaurant_id, $lastAvisId, $message]);
    }
    $_SESSION['message'] = "💬 Commentaire ajouté avec succès !";
    header("Location: menu.php?restaurant_id=" . (int)$_POST['restaurant_id']);
    exit;
}
}
header("Location: menu.php?restaurant_id=".$restaurant_id);
exit;
?>
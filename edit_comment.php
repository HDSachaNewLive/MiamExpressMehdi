<?php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $comment_id = (int)($_POST['comment_id'] ?? 0);
  $restaurant_id = (int)($_POST['restaurant_id'] ?? 0);
  $new_comment = trim($_POST['new_comment'] ?? '');

  if ($new_comment === '') {
    // ⚠️ Si vide, on reste sur le menu avec message
    $_SESSION['message'] = "⚠️ Ton commentaire est vide.";
    if ($restaurant_id > 0) {
        header("Location: menu.php?restaurant_id=" . $restaurant_id);
        exit;
    }
    header("Location: restaurants.php");
    exit;
  }

  if ($comment_id > 0) {
    // vérifier que c'est bien le propriétaire du commentaire
    $stmt = $conn->prepare("SELECT restaurant_id FROM avis WHERE avis_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $uid]);
    $avis = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($avis) {
      $upd = $conn->prepare("UPDATE avis SET commentaire = ? WHERE avis_id = ?");
      $upd->execute([$new_comment, $comment_id]);
      $_SESSION['message'] = "💾 Commentaire modifié avec succès !";
      header("Location: menu.php?restaurant_id=" . $avis['restaurant_id']);
      exit;
    }
  }
}
header("Location: restaurants.php");
exit;
?>

<?php
// export_mes_donnees.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$format = $_GET['format'] ?? 'json'; // 'json' ou 'pdf'

// ── Récupérer toutes les données de l'utilisateur ────────────

// Infos utilisateur (on masque le hash du mot de passe)
$stmt = $conn->prepare("
    SELECT user_id, nom_user, email, email_fictif, email_verifie, email_verifie_at,
           telephone, adresse_livraison, description_profil, couleur_vanta,
           type_compte, date_creation
    FROM users WHERE user_id = ?
");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { die("Utilisateur introuvable."); }
if (!$user) {
    header('Location: login');
    exit;
}

// Préférences
$stmt_pref = $conn->prepare("SELECT notif_forum_actif, reduire_animations, profil_prive, updated_at FROM user_preferences WHERE user_id = ?");
$stmt_pref->execute([$uid]);
$preferences = $stmt_pref->fetch() ?: [];

// Commandes
$stmt_cmd = $conn->prepare("
    SELECT c.commande_id, c.date_commande, c.statut, c.montant_total,
           c.montant_reduction, c.mode_paiement, c.date_paiement
    FROM commandes c
    WHERE c.user_id = ?
    ORDER BY c.date_commande DESC
");
$stmt_cmd->execute([$uid]);
$commandes = $stmt_cmd->fetchAll();

// Détail des plats par commande
foreach ($commandes as &$cmd) {
    $stmt_cp = $conn->prepare("
        SELECT p.nom_plat, cp.quantite, cp.prix_unitaire
        FROM commande_plats cp
        JOIN plats p ON p.plat_id = cp.plat_id
        WHERE cp.commande_id = ?
    ");
    $stmt_cp->execute([$cmd['commande_id']]);
    $cmd['plats'] = $stmt_cp->fetchAll();
}
unset($cmd);

// Avis
$stmt_avis = $conn->prepare("
    SELECT a.avis_id, r.nom_restaurant, a.note, a.commentaire,
           a.date_avis, a.reponse, a.likes, a.dislikes
    FROM avis a
    JOIN restaurants r ON r.restaurant_id = a.restaurant_id
    WHERE a.user_id = ?
    ORDER BY a.date_avis DESC
");
$stmt_avis->execute([$uid]);
$avis = $stmt_avis->fetchAll();

// Favoris
$stmt_fav = $conn->prepare("
    SELECT r.nom_restaurant, r.adresse, r.categorie, f.date_ajout
    FROM favoris f
    JOIN restaurants r ON r.restaurant_id = f.restaurant_id
    WHERE f.user_id = ?
    ORDER BY f.date_ajout DESC
");
$stmt_fav->execute([$uid]);
$favoris = $stmt_fav->fetchAll();

// Topics forum
$stmt_topics = $conn->prepare("
    SELECT topic_id, titre, categorie, date_creation, nb_reponses, vues
    FROM forum_topics WHERE user_id = ?
    ORDER BY date_creation DESC
");
$stmt_topics->execute([$uid]);
$topics = $stmt_topics->fetchAll();

// Messages forum
$stmt_msgs = $conn->prepare("
    SELECT fm.message_id, ft.titre AS topic_titre, fm.contenu,
           fm.date_message, fm.modifie, fm.date_modification
    FROM forum_messages fm
    JOIN forum_topics ft ON ft.topic_id = fm.topic_id
    WHERE fm.user_id = ?
    ORDER BY fm.date_message DESC
");
$stmt_msgs->execute([$uid]);
$forum_messages = $stmt_msgs->fetchAll();

// Restaurants (si propriétaire)
$restaurants = [];
if ($user['type_compte'] === 'proprietaire') {
    $stmt_r = $conn->prepare("
        SELECT restaurant_id, nom_restaurant, adresse, categorie,
               description_resto, verified
        FROM restaurants WHERE proprietaire_id = ?
        ORDER BY nom_restaurant
    ");
    $stmt_r->execute([$uid]);
    $restaurants = $stmt_r->fetchAll();
}

// Stats profil
$stmt_stat = $conn->prepare("SELECT nb_visites, derniere_visite FROM profil_stats WHERE user_id = ?");
$stmt_stat->execute([$uid]);
$profil_stats = $stmt_stat->fetch() ?: [];

// ── Construire la structure de données ──────────────────────
$export = [
    'export_info' => [
        'date_export'     => date('Y-m-d H:i:s'),
        'site'            => 'FoodHub',
        'conformite_rgpd' => 'Ce fichier contient l\'ensemble des données personnelles associées à votre compte, conformément au Règlement Général sur la Protection des Données (RGPD - Art. 20).',
    ],
    'compte'          => $user,
    'preferences'     => $preferences,
    'profil_stats'    => $profil_stats,
    'commandes'       => $commandes,
    'avis'            => $avis,
    'favoris'         => $favoris,
    'forum_topics'    => $topics,
    'forum_messages'  => $forum_messages,
    'restaurants'     => $restaurants,
];

$nom_fichier = 'mes_donnees_foodhub_' . date('Ymd_His');

// ════════════════════════════════════════════════════════════
// FORMAT JSON
// ════════════════════════════════════════════════════════════
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom_fichier . '.json"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ════════════════════════════════════════════════════════════
// FORMAT PDF
// ════════════════════════════════════════════════════════════
if ($format === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');

    $nb_commandes   = count($commandes);
    $nb_avis        = count($avis);
    $nb_favoris     = count($favoris);
    $nb_topics      = count($topics);
    $nb_msgs        = count($forum_messages);
    $nb_restaurants = count($restaurants);

    $montant_total_all = array_sum(array_column($commandes, 'montant_total'));

    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
    function fmt_date(?string $d): string {
        if (!$d) return '—';
        return date('d/m/Y à H:i', strtotime($d));
    }
    function stars(int $n): string {
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }

    ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes données - FoodHub</title>
<style>
  @font-face {
    font-family: 'HSR';
    src: url('https://raw.githubusercontent.com/HDSachaNewLive/polices-discord/b6235ae7a94601cdc54a11b065f75cd68de13a01/HSR.ttf') format('truetype');
    font-weight: normal; font-style: normal;
  }

  :root {
    --accent:      #ff6b6b;
    --accent2:     #ff8c42;
    --glass-bg:    rgba(255, 255, 255, 0.82);
    --border:      rgba(255, 255, 255, 0.35);
    --shadow:      0 10px 40px rgba(0, 0, 0, 0.18);
    --text-mid:    #555;
    --text-light:  #888;
    --section-bg:  rgba(255, 107, 107, 0.07);
    --row-alt:     rgba(255, 107, 107, 0.04);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body { font-family: 'HSR', Georgia, serif; color: #222; min-height: 100vh; overflow-x: hidden; }
  body { background: none !important; }

  /* Fond image fixe */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    z-index: 0;
    background-image: url('assets/menu_bg.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }

  /* Fade haut */
  body::after {
    content: '';
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 220px;
    z-index: 1;
    background: linear-gradient(to bottom, rgba(255,248,242,0.92) 0%, rgba(255,248,242,0) 100%);
    pointer-events: none;
  }

  #fade-bottom {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 220px;
    z-index: 1;
    background: linear-gradient(to top, rgba(255,248,242,0.92) 0%, rgba(255,248,242,0) 100%);
    pointer-events: none;
  }

  .page-wrapper {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 2.5rem 3rem;
  }

  /* Barre d'action */
  .print-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.8rem;
    margin-bottom: 2rem;
  }

  .btn-action {
    padding: 0.65rem 1.4rem;
    border: none;
    border-radius: 14px;
    font-family: 'HSR', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }

  .btn-print {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: #fff;
    box-shadow: 0 4px 14px rgba(255, 107, 107, 0.35);
  }
  .btn-print:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 8px 22px rgba(255, 107, 107, 0.45);
    background: linear-gradient(135deg, var(--accent2), var(--accent));
  }

  .btn-json {
    background: rgba(255, 255, 255, 0.55);
    color: var(--text-mid);
    border: 1px solid rgba(200, 200, 200, 0.6);
    backdrop-filter: blur(6px);
  }
  .btn-json:hover {
    background: rgba(255, 255, 255, 0.75);
    transform: translateY(-2px);
  }

  .btn-back {
    background: rgba(255, 255, 255, 0.55);
    color: var(--text-mid);
    border: 1px solid rgba(200, 200, 200, 0.6);
    backdrop-filter: blur(6px);
  }
  .btn-back:hover { background: rgba(255, 255, 255, 0.75); transform: translateY(-2px); }

  /* Carte principale */
  .menu-card {
    background: var(--glass-bg);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 20px;
    padding: 2.2rem 2.5rem 2.5rem;
    box-shadow: var(--shadow);
  }

  /* En-tête */
  .menu-header {
    text-align: center;
    border-bottom: 2.5px solid var(--accent);
    padding-bottom: 1.5rem;
    margin-bottom: 1.6rem;
  }

  .menu-header .logo {
    height: 60px;
    width: auto;
    margin-bottom: 0.6rem;
    object-fit: contain;
  }

  .menu-header h1 {
    font-size: 2rem;
    color: var(--accent);
    letter-spacing: 0.04em;
    line-height: 1.15;
  }

  .menu-header .meta {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.6rem;
    font-size: 0.88rem;
    color: var(--text-mid);
  }

  /* Notice RGPD */
  .rgpd-notice {
    background: rgba(255, 107, 107, 0.07);
    border-left: 4px solid var(--accent);
    padding: 0.85rem 1.1rem;
    border-radius: 0 10px 10px 0;
    font-size: 0.88rem;
    color: #a0442e;
    line-height: 1.6;
    margin-bottom: 1.8rem;
  }

  /* Stat cards */
  .stat-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
  }

  .stat-card {
    flex: 1;
    min-width: 100px;
    background: rgba(255, 107, 107, 0.06);
    border: 1px solid rgba(255, 107, 107, 0.18);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
  }

  .stat-card .val {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent);
    line-height: 1;
  }

  .stat-card .lbl {
    font-size: 0.78rem;
    color: var(--text-light);
    margin-top: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  /* Titres de section */
  .section-title {
    font-size: 1.15rem;
    color: var(--accent);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(255, 107, 107, 0.25);
    padding-bottom: 0.4rem;
    margin: 1.8rem 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  /* Grille info */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.8rem 2rem;
  }

  .info-row { display: flex; flex-direction: column; gap: 2px; }
  .info-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-light);
    font-weight: 600;
  }
  .info-value { font-size: 0.95rem; color: #222; }

  /* Badges */
  .badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
  }
  .badge-green  { background: #e6f9ec; color: #2e7d32; }
  .badge-orange { background: #fff3e0; color: #c45900; }
  .badge-gray   { background: #f2f2f2; color: #555; }
  .badge-blue   { background: #e3f2fd; color: #1565c0; }

  /* Tableau */
  table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  th {
    background: rgba(255, 107, 107, 0.08);
    color: var(--accent);
    padding: 7px 10px;
    text-align: left;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1.5px solid rgba(255, 107, 107, 0.2);
  }
  td { padding: 7px 10px; border-bottom: 1px dotted rgba(0,0,0,0.1); vertical-align: top; }
  tr:last-child td { border-bottom: none; }

  /* Bloc commande */
  .cmd-bloc { border: 1px solid rgba(255,107,107,0.15); border-radius: 10px; margin-bottom: 10px; overflow: hidden; }
  .cmd-head {
    background: rgba(255,107,107,0.06);
    padding: 9px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 0.88rem;
  }
  .cmd-head .cmd-id { font-weight: 700; color: var(--accent); }
  .cmd-head .cmd-date { color: var(--text-light); font-size: 0.8rem; }
  .cmd-body { padding: 10px 14px; }
  .cmd-body table { font-size: 0.82rem; }

  /* Bloc message forum */
  .msg-bloc {
    border-left: 3px solid rgba(255,107,107,0.3);
    padding: 8px 12px;
    margin-bottom: 8px;
    border-radius: 0 8px 8px 0;
    background: rgba(255,107,107,0.04);
  }
  .msg-topic { font-size: 0.75rem; color: var(--text-light); margin-bottom: 3px; }
  .msg-contenu { color: #333; line-height: 1.5; font-size: 0.88rem; overflow-wrap: break-word; word-break: break-word; }
  .msg-date { font-size: 0.75rem; color: var(--text-light); margin-top: 4px; }

  /* Carte restaurant */
  .resto-card {
    border: 1px solid rgba(255,107,107,0.15);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 8px;
  }
  .resto-name { font-weight: 700; color: var(--accent); font-size: 1rem; }

  /* Étoiles */
  .stars { color: #f5a623; }

  /* Total */
  .total-line {
    text-align: right;
    font-weight: 700;
    color: var(--accent);
    margin-top: 8px;
    font-size: 0.95rem;
  }

  /* Description profil */
  .profil-desc {
    margin-top: 14px;
    font-size: 0.88rem;
    color: #444;
    line-height: 1.6;
    font-style: italic;
    border-left: 3px solid rgba(255,107,107,0.3);
    padding-left: 10px;
  }

  /* Pied de page */
  .menu-footer {
    margin-top: 2rem;
    padding-top: 1.1rem;
    border-top: 1.5px solid var(--accent);
    text-align: center;
    color: var(--text-light);
    font-size: 0.8rem;
  }
  .menu-footer strong { color: var(--accent); }

  /* Vide */
  .empty { color: var(--text-light); font-style: italic; font-size: 0.88rem; padding: 8px 0; }

  @media print {
    .print-toolbar { display: none !important; }
    #fade-bottom   { display: none !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body { font-size: 11pt; }
    body::before { position: absolute; }
    body::after  { position: absolute; }
    .page-wrapper { padding: 1cm 1.5cm; max-width: 100%; }
    .menu-card { background: rgba(255,255,255,0.88) !important; box-shadow: none; border-radius: 12px; }
    .section-title { page-break-after: avoid; }
    .cmd-bloc, .msg-bloc, .resto-card { page-break-inside: avoid; }
    a { color: inherit; text-decoration: none; }
  }
</style>
</head>
<body>

<div id="fade-bottom"></div>

<div class="page-wrapper">

  <!-- Barre d'action -->
  <div class="print-toolbar">
    <a class="btn-action btn-back" href="profile.php">⬅ Retour au profil</a>
    <a class="btn-action btn-json" href="export_mes_donnees.php?format=json">⬇️ Télécharger JSON</a>
    <button class="btn-action btn-print" onclick="window.print()">🖨️ Imprimer / PDF</button>
  </div>

  <div class="menu-card">

    <!-- En-tête -->
    <header class="menu-header">
      <img src="Logo foodhub transparent.png" alt="Logo FoodHub" class="logo">
      <h1>Mes données personnelles</h1>
      <div class="meta">
        <span>👤 <?= h($user['nom_user']) ?></span>
        <span>📧 <?= h($user['email']) ?></span>
        <span>🆔 Utilisateur #<?= $uid ?></span>
        <span>📅 Généré le <?= date('d/m/Y à H:i') ?></span>
      </div>
    </header>

    <!-- Notice RGPD -->
    <div class="rgpd-notice">
      📋 <strong>Conformité RGPD – Art. 20 :</strong> Ce document contient l'ensemble des données personnelles associées à votre compte FoodHub. Vous pouvez le conserver, le transmettre ou demander sa suppression auprès de notre équipe.
    </div>

    <!-- Vue d'ensemble -->
    <h2 class="section-title">📊 Vue d'ensemble</h2>
    <div class="stat-row">
      <div class="stat-card"><div class="val"><?= $nb_commandes ?></div><div class="lbl">Commandes</div></div>
      <div class="stat-card"><div class="val"><?= $nb_avis ?></div><div class="lbl">Avis</div></div>
      <div class="stat-card"><div class="val"><?= $nb_favoris ?></div><div class="lbl">Favoris</div></div>
      <div class="stat-card"><div class="val"><?= $nb_topics ?></div><div class="lbl">Topics</div></div>
      <div class="stat-card"><div class="val"><?= $nb_msgs ?></div><div class="lbl">Messages</div></div>
      <?php if ($nb_restaurants > 0): ?>
      <div class="stat-card"><div class="val"><?= $nb_restaurants ?></div><div class="lbl">Restaurants</div></div>
      <?php endif; ?>
    </div>

    <!-- Compte -->
    <h2 class="section-title">👤 Informations du compte</h2>
    <div class="info-grid">
      <div class="info-row">
        <span class="info-label">Nom d'utilisateur</span>
        <span class="info-value"><?= h($user['nom_user']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Adresse e-mail</span>
        <span class="info-value">
          <?= h($user['email']) ?>
          <?php if ($user['email_fictif']): ?>
            <span class="badge badge-gray">Fictive</span>
          <?php elseif ($user['email_verifie']): ?>
            <span class="badge badge-green">Vérifiée</span>
          <?php else: ?>
            <span class="badge badge-orange">Non vérifiée</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Téléphone</span>
        <span class="info-value"><?= h($user['telephone'] ?: '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Adresse de livraison</span>
        <span class="info-value"><?= h($user['adresse_livraison'] ?: '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Type de compte</span>
        <span class="info-value">
          <span class="badge <?= $user['type_compte'] === 'proprietaire' ? 'badge-blue' : 'badge-green' ?>">
            <?= ucfirst(h($user['type_compte'])) ?>
          </span>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Membre depuis</span>
        <span class="info-value"><?= fmt_date($user['date_creation']) ?></span>
      </div>
      <?php if ($user['email_verifie_at']): ?>
      <div class="info-row">
        <span class="info-label">E-mail vérifié le</span>
        <span class="info-value"><?= fmt_date($user['email_verifie_at']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($profil_stats): ?>
      <div class="info-row">
        <span class="info-label">Visites du profil reçues</span>
        <span class="info-value"><?= (int)$profil_stats['nb_visites'] ?></span>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($user['description_profil'])): ?>
    <p class="profil-desc"><?= nl2br(h($user['description_profil'])) ?></p>
    <?php endif; ?>

    <!-- Préférences -->
    <?php if ($preferences): ?>
    <h2 class="section-title">⚙️ Préférences</h2>
    <div class="info-grid">
      <div class="info-row">
        <span class="info-label">Notifications forum</span>
        <span class="info-value"><?= $preferences['notif_forum_actif'] ? '✅ Activées' : '❌ Désactivées' ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Animations réduites</span>
        <span class="info-value"><?= $preferences['reduire_animations'] ? '✅ Oui' : '❌ Non' ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Profil privé</span>
        <span class="info-value"><?= $preferences['profil_prive'] ? '🔒 Oui' : '🌐 Non' ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Mis à jour le</span>
        <span class="info-value"><?= fmt_date($preferences['updated_at']) ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Restaurants (propriétaire) -->
    <?php if ($nb_restaurants > 0): ?>
    <h2 class="section-title">🏪 Mes restaurants (<?= $nb_restaurants ?>)</h2>
    <?php foreach ($restaurants as $r): ?>
    <div class="resto-card">
      <div class="resto-name"><?= h($r['nom_restaurant']) ?></div>
      <div style="color:var(--text-mid);font-size:0.85rem;margin-top:4px;">
        <?= h($r['adresse'] ?: '—') ?> · <?= h($r['categorie'] ?: '—') ?>
        · <?= $r['verified'] ? '<span class="badge badge-green">Vérifié</span>' : '<span class="badge badge-orange">En attente</span>' ?>
      </div>
      <?php if ($r['description_resto']): ?>
      <div style="margin-top:6px;color:#555;font-size:0.85rem;font-style:italic;"><?= h($r['description_resto']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Commandes -->
    <h2 class="section-title">🛒 Historique des commandes (<?= $nb_commandes ?>)</h2>
    <?php if (empty($commandes)): ?>
      <p class="empty">Aucune commande passée.</p>
    <?php else:
      $statut_labels = [
        'en_attente'     => ['label' => 'En attente',     'class' => 'badge-orange'],
        'en_preparation' => ['label' => 'En préparation', 'class' => 'badge-blue'],
        'en_livraison'   => ['label' => 'En livraison',   'class' => 'badge-blue'],
        'livree'         => ['label' => 'Livrée',         'class' => 'badge-green'],
        'annulee'        => ['label' => 'Annulée',        'class' => 'badge-gray'],
      ];
      foreach ($commandes as $cmd):
        $s = $statut_labels[$cmd['statut']] ?? ['label' => $cmd['statut'], 'class' => 'badge-gray'];
    ?>
    <div class="cmd-bloc">
      <div class="cmd-head">
        <span class="cmd-id">Commande #<?= $cmd['commande_id'] ?></span>
        <span class="cmd-date"><?= fmt_date($cmd['date_commande']) ?></span>
        <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
        <span style="font-weight:700;color:var(--accent);"><?= number_format((float)$cmd['montant_total'], 2, ',', ' ') ?> €</span>
        <?php if ((float)$cmd['montant_reduction'] > 0): ?>
          <span class="badge badge-green">-<?= number_format((float)$cmd['montant_reduction'], 2, ',', ' ') ?> €</span>
        <?php endif; ?>
        <span style="color:var(--text-light);font-size:0.8rem;"><?= ucfirst(h($cmd['mode_paiement'])) ?></span>
      </div>
      <?php if (!empty($cmd['plats'])): ?>
      <div class="cmd-body">
        <table>
          <tr><th>Plat</th><th>Qté</th><th>Prix unitaire</th><th>Sous-total</th></tr>
          <?php foreach ($cmd['plats'] as $p): ?>
          <tr>
            <td><?= h($p['nom_plat']) ?></td>
            <td><?= (int)$p['quantite'] ?></td>
            <td><?= number_format((float)$p['prix_unitaire'], 2, ',', ' ') ?> €</td>
            <td><?= number_format((float)$p['prix_unitaire'] * (int)$p['quantite'], 2, ',', ' ') ?> €</td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <p class="total-line">Total dépensé : <?= number_format($montant_total_all, 2, ',', ' ') ?> €</p>
    <?php endif; ?>

    <!-- Avis -->
    <h2 class="section-title">⭐ Mes avis (<?= $nb_avis ?>)</h2>
    <?php if (empty($avis)): ?>
      <p class="empty">Aucun avis posté.</p>
    <?php else: ?>
    <table>
      <tr><th>Restaurant</th><th>Note</th><th>Commentaire</th><th>Date</th><th>Votes</th></tr>
      <?php foreach ($avis as $av): ?>
      <tr>
        <td><?= h($av['nom_restaurant']) ?></td>
        <td><span class="stars"><?= stars((int)$av['note']) ?></span></td>
        <td><?= h($av['commentaire'] ?: '—') ?></td>
        <td><?= fmt_date($av['date_avis']) ?></td>
        <td>
          <span style="color:#4CAF50;">👍 <?= (int)$av['likes'] ?></span>
          <span style="color:#f44336;margin-left:6px;">👎 <?= (int)$av['dislikes'] ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Favoris -->
    <h2 class="section-title">❤️ Mes restaurants favoris (<?= $nb_favoris ?>)</h2>
    <?php if (empty($favoris)): ?>
      <p class="empty">Aucun favori enregistré.</p>
    <?php else: ?>
    <table>
      <tr><th>Restaurant</th><th>Adresse</th><th>Catégorie</th><th>Ajouté le</th></tr>
      <?php foreach ($favoris as $fav): ?>
      <tr>
        <td><?= h($fav['nom_restaurant']) ?></td>
        <td><?= h($fav['adresse'] ?: '—') ?></td>
        <td><?= h($fav['categorie'] ?: '—') ?></td>
        <td><?= fmt_date($fav['date_ajout']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Topics forum -->
    <h2 class="section-title">💬 Mes topics forum (<?= $nb_topics ?>)</h2>
    <?php if (empty($topics)): ?>
      <p class="empty">Aucun topic créé.</p>
    <?php else: ?>
    <table>
      <tr><th>Titre</th><th>Catégorie</th><th>Réponses</th><th>Vues</th><th>Créé le</th></tr>
      <?php foreach ($topics as $t): ?>
      <tr>
        <td><?= h($t['titre']) ?></td>
        <td><?= h($t['categorie']) ?></td>
        <td><?= (int)$t['nb_reponses'] ?></td>
        <td><?= (int)$t['vues'] ?></td>
        <td><?= fmt_date($t['date_creation']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Messages forum -->
    <h2 class="section-title">📝 Mes messages forum (<?= $nb_msgs ?>)</h2>
    <?php if (empty($forum_messages)): ?>
      <p class="empty">Aucun message posté.</p>
    <?php else: ?>
      <?php foreach ($forum_messages as $m): ?>
      <div class="msg-bloc">
        <div class="msg-topic">Topic : <?= h($m['topic_titre']) ?></div>
        <div class="msg-contenu"><?= nl2br(h($m['contenu'])) ?></div>
        <div class="msg-date">
          Posté le <?= fmt_date($m['date_message']) ?>
          <?php if ($m['modifie']): ?> · Modifié le <?= fmt_date($m['date_modification']) ?><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Pied de page -->
    <footer class="menu-footer">
      Export RGPD via <strong>FoodHub</strong> — <?= date('d/m/Y') ?>
    </footer>

  </div><!-- .menu-card -->
</div><!-- .page-wrapper -->
</body>
</html>
<?php
    exit;
}
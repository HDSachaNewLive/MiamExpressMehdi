<?php
// menu_print.php
session_start();
require_once 'db/config.php';

if (!isset($_GET['restaurant_id'])) {
    abort_404('restaurant');
}
$restaurant_id = (int)$_GET['restaurant_id'];

// restaurant + owner (même requête que menu.php)
$stmt = $conn->prepare("
  SELECT r.*, u.nom_user AS owner_name, u.user_id AS owner_id, adresse AS adresse_resto, categorie AS categorie
  FROM restaurants r
  LEFT JOIN users u ON r.proprietaire_id = u.user_id
  WHERE r.restaurant_id = ?
");
$stmt->execute([$restaurant_id]);
$restaurant = $stmt->fetch();
if (!$restaurant) abort_404('restaurant');

// plats (même requête que menu.php, triés par type puis nom)
$stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id = ? ORDER BY type_plat, nom_plat");
$stmt->execute([$restaurant_id]);
$plats = $stmt->fetchAll();

// Grouper par type (même logique que menu.php)
$plats_par_type = [];
foreach ($plats as $p) {
    $type = $p['type_plat'] ?? 'plat';
    if (!isset($plats_par_type[$type])) {
        $plats_par_type[$type] = [];
    }
    $plats_par_type[$type][] = $p;
}

// Même mapping que menu.php
$type_icons = [
    'entree'         => '🥗',
    'plat'           => '🍽️',
    'accompagnement' => '🍚',
    'boisson'        => '🥤',
    'dessert'        => '🍰',
    'sauce'          => '🧂'
];

$type_labels = [
    'entree'         => 'Entrées',
    'plat'           => 'Plats',
    'accompagnement' => 'Accompagnements',
    'boisson'        => 'Boissons',
    'dessert'        => 'Desserts',
    'sauce'          => 'Sauces'
];

// Note moyenne (même requête que menu.php)
$stmt = $conn->prepare("SELECT AVG(note) AS avg_note, COUNT(*) AS cnt FROM avis WHERE restaurant_id = ?");
$stmt->execute([$restaurant_id]);
$rating = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Menu - <?= htmlspecialchars($restaurant['nom_restaurant']) ?></title>
  <style>
    /*  Police HSR (même source que style.css) ── */
    @font-face {
      font-family: 'HSR';
      src: url('https://raw.githubusercontent.com/HDSachaNewLive/polices-discord/b6235ae7a94601cdc54a11b065f75cd68de13a01/HSR.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }

    /*  Variables FoodHub ── */
    :root {
      --accent:      #ff6b6b;
      --accent-dark: #e05555;
      --shadow:      rgba(0, 0, 0, 0.08);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /*  Fond image + fades haut/bas ─ */
    body {
      font-family: 'HSR', Georgia, serif;
      color: #222;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }

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
      background: linear-gradient(
        to bottom,
        rgba(255, 248, 242, 0.92) 0%,
        rgba(255, 248, 242, 0.0)  100%
      );
      pointer-events: none;
    }

    /* Fade bas — pseudo sur un élément dédié */
    #fade-bottom {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: 220px;
      z-index: 1;
      background: linear-gradient(
        to top,
        rgba(255, 248, 242, 0.92) 0%,
        rgba(255, 248, 242, 0.0)  100%
      );
      pointer-events: none;
    }

    /* Contenu au-dessus du fond */
    .page-wrapper {
      position: relative;
      z-index: 2;
      max-width: 860px;
      margin: 0 auto;
      padding: 2rem 2.5rem 3rem;
    }

    /*  Barre d'action (écran seulement) ─ */
    .print-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.8rem;
      margin-bottom: 2rem;
    }

    .btn-print, .btn-back {
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
      background: linear-gradient(135deg, #ff6b6b, #ffc342);
      color: #fff;
      box-shadow: 0 4px 14px rgba(255, 107, 107, 0.35);
    }
    .btn-print:hover {
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 8px 22px rgba(255, 107, 107, 0.45);
      background: linear-gradient(135deg, #ff8c42, #ff6b6b);
    }

    .btn-back {
      background: rgba(255, 255, 255, 0.55);
      color: #555;
      border: 1px solid rgba(200, 200, 200, 0.6);
      backdrop-filter: blur(6px);
    }
    .btn-back:hover {
      background: rgba(255, 255, 255, 0.75);
      transform: translateY(-2px);
    }

    /*  Carte centrale (fond semi-transparent) ── */
    .menu-card {
      background: rgba(255, 255, 255, 0.82);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-radius: 20px;
      padding: 2.2rem 2.5rem 2.5rem;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.18);
    }

    /*  En-tête ── */
    .menu-header {
      text-align: center;
      border-bottom: 2.5px solid var(--accent);
      padding-bottom: 1.5rem;
      margin-bottom: 1.6rem;
    }

    .menu-header .logo {
      height: 70px;
      width: auto;
      margin-bottom: 0.7rem;
      object-fit: contain;
    }

    .menu-header h1 {
      font-size: 2.3rem;
      color: var(--accent);
      letter-spacing: 0.04em;
      line-height: 1.1;
    }

    .menu-header .meta {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 1.1rem;
      margin-top: 0.7rem;
      font-size: 0.9rem;
      color: #555;
    }

    /*  Description ─ */
    .menu-description {
      text-align: center;
      font-size: 0.96rem;
      color: #666;
      font-style: italic;
      margin-bottom: 1.8rem;
      line-height: 1.6;
    }

    /*  Titres de section  */
    .section-title {
      font-size: 1.3rem;
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

    /*  Ligne de plat  */
    .plat-row {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 0.8rem;
      padding: 0.6rem 0;
      border-bottom: 1px dotted rgba(0, 0, 0, 0.12);
    }

    .plat-row:last-child { border-bottom: none; }

    .plat-left { flex: 1; min-width: 0; }

    .plat-nom {
      font-size: 1.03rem;
      font-weight: 700;
      color: #1a1a1a;
    }

    .plat-desc {
      font-size: 0.86rem;
      color: #666;
      margin-top: 0.12rem;
      line-height: 1.4;
      font-style: italic;
    }

    .plat-prix {
      font-size: 1.03rem;
      font-weight: 700;
      color: var(--accent);
      white-space: nowrap;
      flex-shrink: 0;
    }

    /*  Pied de page  */
    .menu-footer {
      margin-top: 2rem;
      padding-top: 1.1rem;
      border-top: 1.5px solid var(--accent);
      text-align: center;
      color: #999;
      font-size: 0.82rem;
    }

    .menu-footer strong { color: var(--accent); }

    /*  @media print  */
    @media print {
      .print-toolbar { display: none !important; }
      #fade-bottom   { display: none !important; }

      /* Forcer l'impression des couleurs de fond */
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      body {
        font-size: 11pt;
      }

      /* Le fond image reste via body::before (fixed → devient absolu à l'impression) */
      body::before {
        position: absolute;
        print-color-adjust: exact;
      }

      /* Fades à l'impression */
      body::after {
        position: absolute;
      }

      .page-wrapper {
        padding: 1cm 1.5cm;
        max-width: 100%;
      }

      .menu-card {
        background: rgba(255, 255, 255, 0.84) !important;
        box-shadow: none;
        border-radius: 12px;
      }

      .menu-header h1  { font-size: 20pt; }
      .section-title   { font-size: 12pt; page-break-after: avoid; }
      .plat-nom        { font-size: 10pt; }
      .plat-desc       { font-size: 8.5pt; }
      .plat-prix       { font-size: 10pt; }
      .plat-row        { page-break-inside: avoid; }

      a { color: inherit; text-decoration: none; }
    }
  </style>
</head>
<body>

  <!-- Fade bas (pseudo-élément dédié car ::after est déjà utilisé) -->
  <div id="fade-bottom"></div>

  <div class="page-wrapper">

    <!-- Barre d'action -->
    <div class="print-toolbar">
      <a class="btn-back" href="menu.php?restaurant_id=<?= $restaurant_id ?>" onclick="window.close()">⬅ Retour au menu</a>
      <button class="btn-print" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button>
    </div>

    <!-- Carte principale -->
    <div class="menu-card">

      <!-- En-tête -->
      <header class="menu-header">
        <img src="Logo foodhub transparent.png" alt="Logo FoodHub" class="logo">
        <h1><?= htmlspecialchars($restaurant['nom_restaurant']) ?></h1>
        <div class="meta">
          <?php if (!empty($restaurant['adresse_resto'])): ?>
            <span>📍 <?= htmlspecialchars($restaurant['adresse_resto']) ?></span>
          <?php endif; ?>
          <?php if (!empty($restaurant['categorie'])): ?>
            <span>🍴 <?= htmlspecialchars($restaurant['categorie']) ?></span>
          <?php endif; ?>
          <?php if ($rating['cnt']): ?>
            <span>⭐ <?= round($rating['avg_note'], 1) ?> / 5 (<?= $rating['cnt'] ?> avis)</span>
          <?php endif; ?>
          <?php if (!empty($restaurant['owner_name'])): ?>
            <span>👤 <?= htmlspecialchars($restaurant['owner_name']) ?></span>
          <?php endif; ?>
        </div>
      </header>

      <?php if (!empty($restaurant['description_resto'])): ?>
        <p class="menu-description"><?= htmlspecialchars($restaurant['description_resto']) ?></p>
      <?php endif; ?>

      <!-- Plats par catégorie -->
      <?php if (empty($plats)): ?>
        <p style="text-align:center; color:#aaa; margin-top:1.5rem;">Aucun plat disponible pour ce restaurant.</p>
      <?php else: ?>
        <?php foreach ($plats_par_type as $type => $items): ?>
          <h2 class="section-title">
            <?= $type_icons[$type] ?? '' ?>&nbsp;<?= $type_labels[$type] ?? ucfirst($type) ?>
          </h2>
          <?php foreach ($items as $p): ?>
            <div class="plat-row">
              <div class="plat-left">
                <div class="plat-nom"><?= htmlspecialchars($p['nom_plat']) ?></div>
                <?php if (!empty($p['description_plat'])): ?>
                  <div class="plat-desc"><?= htmlspecialchars($p['description_plat']) ?></div>
                <?php endif; ?>
              </div>
              <div class="plat-prix"><?= number_format($p['prix'], 2) ?> €</div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- Pied de page -->
      <footer class="menu-footer">
        Menu exporté via <strong>FoodHub</strong> - <?= date('d/m/Y') ?>
      </footer>

    </div>
  </div>
</body>
</html>

<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
// restaurants.php
session_start();
require_once 'db/config.php';
$connected = isset($_SESSION['user_id']);

// Récupère les restos triés par user_id
$stmt = $conn->query("
  SELECT r.*, u.nom_user AS owner_name, u.user_id AS owner_id
  FROM restaurants r
  LEFT JOIN users u ON r.proprietaire_id = u.user_id
  WHERE r.verified = 1
  ORDER BY u.user_id, r.nom_restaurant
");
$restaurants = $stmt->fetchAll();

// organiser par propriétaire
$grouped_restaurants = [];
foreach ($restaurants as $r) {
    $owner_id = $r['owner_id'] ?? 0;
    $grouped_restaurants[$owner_id][] = $r;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Restaurants - FoodHub</title>
  <audio autoplay> <source src="assets/00259 - WAV_259_GUESS_BANK_MEN.wav" type="audio/wav"> </audio>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="restaurants.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <?php include 'sidebar.php'; ?>
  <?php include "slider_son.php"; ?>
<audio id="player"></audio>
<div id="music-controls">
  <button id="prev-track" class="music-btn" title="Piste précédente">⏮️</button>
  <button id="next-track" class="music-btn" title="Piste suivante">⏭️</button>
</div>

  <style>
  #map {
  height: 450px;
  width: 100%;
  margin-bottom: 2rem;
  border-radius: 15px;
  position: relative;
  z-index: -10000000000;
  }

  #volume-slider {
    background: linear-gradient(135deg, #935cf2ff, #b88effff);
  }
  #volume-button {
    background: linear-gradient(135deg, #935cf2ff, #b88effff);
    }
  
  #music-controls {
    position: fixed;
  top: 82.5px;
  right: 90px;
  z-index: 99998;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  padding: 0.4rem 0.4rem;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  animation: fadeIn 0.4s ease forwards;
}

  .music-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 10px;
  height: 20px;
  font-size: 0.7rem;
  background: linear-gradient(135deg, #935cf2ff, #b88effff);
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

  .music-btn:hover {
  background: linear-gradient(135deg, #935cf2ff, #b88effff);
  transform: scale(1.10);
  box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}

  @keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

</head>
<body>
  <main class="container">
    <h1>Restaurants</h1>

    <!-- carte -->
    <div id="map" style="z-index:-100;"></div>

    <?php foreach ($grouped_restaurants as $owner_id => $restos): ?>
      <div class="owner-section">
        <div class="owner-title"><?= htmlspecialchars($restos[0]['owner_name'] ?? 'Indépendant') ?></div>
          <div class="resto-list">

          <?php foreach ($restos as $r): ?>

            <div class="resto-card">
              <h3><?= htmlspecialchars($r['nom_restaurant']) ?></h3>
              <p><?= htmlspecialchars($r['adresse']) ?></p>
              <p><strong><?= htmlspecialchars($r['categorie']) ?></strong></p>
              <p><?= htmlspecialchars(substr($r['description_resto'],0,80)) ?>...</p>
              <a class="btn" href="menu.php?restaurant_id=<?= (int)$r['restaurant_id'] ?>">Voir le menu</a>
              <?php if ($connected && (int)$_SESSION['user_id'] === (int)$r['owner_id']): ?>
                <a class="btn" href="vendor_edit_restaurant.php?restaurant_id=<?= (int)$r['restaurant_id'] ?>">Modifier</a>
              <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
      </div>
    <?php endforeach; ?>

  <p><a class="back-link" href="<?= $connected ? 'home.php' : 'index.php' ?>">⬅ Retour</a></p>
</main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const restos = <?= json_encode($restaurants, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    let center = [48.8566, 2.3522];
    if (restos.length > 0 && restos[0].latitude && restos[0].longitude) {
      center = [parseFloat(restos[0].latitude), parseFloat(restos[0].longitude)];
    }
    const map = L.map('map').setView(center, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    restos.forEach(r => {
      const lat = parseFloat(r.latitude);
      const lng = parseFloat(r.longitude);
      if (!isNaN(lat) && !isNaN(lng)) {
        const marker = L.marker([lat, lng]).addTo(map);
        const popup = `<strong>${escapeHtml(r.nom_restaurant)}</strong><br>${escapeHtml(r.adresse)}<br><em>${escapeHtml(r.categorie || '')}</em>`;
        marker.bindPopup(popup);
  }
});
    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.fog.min.js"></script>
<script>
  VANTA.FOG({
    el: "body",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 890.00,      
    minWidth: 500.00,
    highlightColor:0xb4a7d6,
    midtoneColor: 0x9e8fcb,
    lowlightColor: 0x8e7cc3,
    baseColor: 0xffffff,
    blurFactor: 0.7,
    speed: 1.5,
    zoom: 1
  })
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const sections = document.querySelectorAll(".owner-section");

  // --- container des filtres ---
  const filterWrapper = document.createElement("div");
  filterWrapper.className = "filter-wrapper";

  const title = document.createElement("h3");
  title.textContent = "Filtrer par proprio";
  title.className = "filter-title";
  filterWrapper.appendChild(title);

  // bouton "Tous"
  const allBtn = document.createElement("button");
  allBtn.textContent = "Tous";
  allBtn.className = "filter-btn active";
  filterWrapper.appendChild(allBtn);

  // boutons dynamiques
  sections.forEach(section => {
    const owner = section.querySelector(".owner-title").textContent.trim();
    const btn = document.createElement("button");
    btn.textContent = owner;
    btn.className = "filter-btn";
    filterWrapper.appendChild(btn);
  });

  document.body.appendChild(filterWrapper);

  //style général des boutons de filtre
  const style = document.createElement("style");
  style.textContent = `
    .filter-wrapper {
      position: fixed;
      top: 50%;
      right: 1rem;
      transform: translateY(-50%);
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
      z-index: 999999999;
      background: rgba(255,255,255,0.3);
      backdrop-filter: blur(8px);
      border-radius: 1rem;
      padding: 0.8rem;
      box-shadow: 0 6px 18px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }

    .filter-title {
      color: #000;
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 0.4rem;
      text-align: center;
    }

    .filter-btn {
      background: rgba(255,255,255,0.4);
      color: #0f0f0fce;
      border: 1px solid rgba(0,0,0,0.1);
      padding: 0.6rem 0.8rem;
      border-radius: 0.7rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.25s ease;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      backdrop-filter: blur(6px);
    }

    .filter-btn:hover {
      background: rgba(255,255,255,0.7);
      transform: scale(1.05);
    }

    .filter-btn.active {
      background: #ff6b6b;
      color: white;
      transform: scale(1.1);
      box-shadow: 0 4px 12px rgba(255,107,107,0.4);
    }

    /* animation douce quand les sections apparaissent/disparaissent */
    .owner-section {
      opacity: 1;
      transform: scale(1);
      transition: opacity 0.4s ease, transform 0.4s ease;
    }

    .owner-section.hidden {
      opacity: 0;
      transform: scale(0.95);
      pointer-events: none;
    }
  `;
  document.head.appendChild(style);

  // --- logique du filtre ---
  const buttons = filterWrapper.querySelectorAll(".filter-btn");
  let activeFilter = "Tous";

  buttons.forEach(btn => {
    btn.addEventListener("click", () => {
      activeFilter = btn.textContent.trim();

      buttons.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");

      sections.forEach(section => {
        const owner = section.querySelector(".owner-title").textContent.trim();

        if (activeFilter === "Tous" || owner === activeFilter) {
          section.classList.remove("hidden");
          setTimeout(() => (section.style.display = "block"), 100);
        } else {
          section.classList.add("hidden");
          setTimeout(() => (section.style.display = "none"), 450);
        }
      });
    });
  });
});
</script>

<!-- sons hover et click sur les boutons de filtre -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const hoverSound = new Audio('assets/00240 - WAV_240_GUESS_BANK_MEN.wav');
  const clickSound = new Audio('assets/00254 - WAV_254_GUESS_BANK_MEN.wav');

  // fonction pour attacher le son de hover uniquement aux boutons de filtre
  function attachHoverSound(buttons) {
    buttons.forEach(btn => {
      if (btn.classList.contains('filter-btn')) {
        // souris passe par dessus
        btn.addEventListener('mouseenter', () => {
          hoverSound.currentTime = 0;
          hoverSound.volume = 0.6;
          hoverSound.play().catch(() => {});
        });
      }
    });
  }

  // fonction pour attacher le son de click uniquement aux boutons de filtre
  function attachClickSoundToFilters(buttons) {
    buttons.forEach(btn => {
      if (btn.classList.contains('filter-btn')) {
        btn.addEventListener('click', () => {
          clickSound.currentTime = 0;
          clickSound.play().catch(() => {});
        });
      }
    });
  }

  //attache initialement les sons
  const allButtons = document.querySelectorAll('button, .btn');
  attachHoverSound(allButtons);
  attachClickSoundToFilters(allButtons);

  //surveille les nouveaux boutons ajoutés dynamiquement(bah oui frère)
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) {
          if (node.matches('button, .btn')) {
            attachHoverSound([node]);
            attachClickSoundToFilters([node]);
          } else if (node.querySelectorAll) {
            const newBtns = node.querySelectorAll('button, .btn');
            if (newBtns.length > 0) {
              attachHoverSound(newBtns);
              attachClickSoundToFilters(newBtns);
            }
          }
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
});
</script>

<audio id="player" autoplay></audio>
<!-- musique -->
<script>
window.addEventListener('load', function() {
  const playlist = [
  "assets/June 2015 - Nintendo eShop Music.mp3",
  "assets/September 2015 Nintendo eShop Music.mp3",
  "assets/23. May 2019 (3DS & Wii U).mp3",
  "assets/nte_gw_bgm_20250514.mp3",
  "assets/08. June 2011 (3DS).mp3",
];

  const trackNames = [
    "",
    "",
    "",
    "",
    "",
    ""
  ];

  let current = 0;
  const player = document.getElementById("player");
  const trackNameEl = document.getElementById("track-name");
  const nextBtn = document.getElementById("next-track");
  const prevBtn = document.getElementById("prev-track");

  if (!player || !nextBtn || !prevBtn) {
    console.error('❌ Éléments manquants');
    return;
  }

  function playTrack() {
    player.src = playlist[current];
    player.load();
    
    if (trackNameEl) {
      trackNameEl.textContent = trackNames[current];
    }
    
    player.play().catch(err => {
      console.log('En attente interaction utilisateur');
    });
  }

  // Bouton suivant
  nextBtn.onclick = function() {
    current = (current + 1) % playlist.length;
    playTrack();
  };

  // Bouton précédent
  prevBtn.onclick = function() {
    current = (current - 1 + playlist.length) % playlist.length;
    playTrack();
  };

  // Piste terminée = suivante
  player.onended = function() {
    current = (current + 1) % playlist.length;
    playTrack();
  };

  // Démarrer
  playTrack();
});
</script>

</body>
</html>

<?php
// 404.php

// Définir le bon code HTTP
http_response_code(404);

// S'assurer que la session est active pour lire $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Messages humoristiques selon l'URL demandée
$path = $_SERVER['REQUEST_URI'] ?? '';

$bouton_texte = "";
if (!isset($_SESSION['user_id'])) {
    $bouton_texte = "Retour à la page de connexion";
} else {
    $bouton_texte = "Retour à l'accueil";
}
    
$messages = [
    // Messages contextuels selon l'URL
    'profil'     => ["👤 Ce profil s'est évaporé comme une réduction de vin blanc…",     "Ce compte n'existe pas (ou plus). Peut-être parti manger ailleurs ?"],
    'restaurant' => ["🏚️ Ce restaurant a fermé ses portes… numériques.",                  "Ils ont fait faillite à cause BIGMACBOURDON."],
    'commande'   => ["📦 Cette commande s'est perdue en livraison interdimensionnelle.", "On a cherché partout. Même sous la friteuse. Mais BIGMACBOURDON avait tout aspiré."],
    'plat'       => ["🍽️ Ce plat n'est plus à la carte.",                                "Le chef a retiré ce plat du menu. (Il n'a jamais existé. Ou pas.)"],
    'avis'       => ["⭐ Cet avis a disparu avant même qu'on puisse le lire.",           "Volatilisé. Probablement un 1 étoile honteux."],
    'panier'     => ["🛒 Votre panier s'est sauvé sans payer.",                           "Le contenu de ce panier est introuvable. Comme vos clés."],
    'admin'      => ["🔐 Zone interdite ou inexistante.",                                 "Si vous cherchez l'admin, il est probablement en train de manger."],
    'forum'      => ["💬 Ce topic a été avalé par le vide.",                             "QUOI ?! Encore BIGMACBOURDON qui aspire tout avec son trou noir de ventre ?!\nDiscussion introuvable. Elle a peut-être manqué de réponses."],
    'coupon'     => ["🎟️ Ce coupon a expiré… ou n'a jamais existé.",                     "Réduction introuvable. Comme les promos les jours de fête."],
];

// Détection du contexte via l'URL
$matched = null;
foreach ($messages as $keyword => $pair) {
    if (stripos($path, $keyword) !== false) {
        $matched = $pair;
        break;
    }
}

// Message générique si aucun contexte trouvé
if (!$matched) {
    $generiques = [
        ["🍝 Cette page a été trop cuite.",                   "Elle s'est désintégrée avant d'arriver dans votre assiette."],
        ["🔪 La page a été découpée en julienne.",            "C'est BIGMACBOURDON qui a dû tout manger..."],
        ["🧂 Quelqu'un a trop salé l'URL.",                   "La page a rendu l'âme. Reposez-vous, recommencez."],
        ["🥚 Cette page était encore à l'état d'œuf.",        "Elle n'a jamais éclos. C'est la vie."],
        ["🍕 La page a été livrée à la mauvaise adresse.",    "Le livreur a tourné à gauche. On cherche encore."],
        ["🧁 Cette page est partie avant même d'être cuite.", "Four éteint, page inexistante. Logique."],
        ["🥩 Cette URL est introuvable.",                     "Y'a moyen c'est Jallon le GOAT il a foutu une backdoor."],
        ["🥗 La page a été emportée par le vent de salade.",  "Légère, volatile, et désormais introuvable."],
    ];
    $matched = $generiques[array_rand($generiques)];
}

[$titre, $sous_titre] = $matched;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <title>404 - Page FoodHub non trouvé</title>
    <style>
        /* ── Polices identiques au reste du site ── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --accent:      #ff6b6b;
            --accent2:     #ff8c42;
            --accent-pale: rgba(255, 107, 107, 0.15);
            --glass-bg:    rgba(255, 255, 255, 0.22);
            --glass-hover: rgba(255, 255, 255, 0.30);
            --border:      rgba(255, 255, 255, 0.35);
            --shadow:      0 8px 32px rgba(0, 0, 0, 0.15);
            --text-dark:   #333;
            --text-mid:    #555;
            --text-light:  #888;
        }

        html, body {
            height: 100%;
            font-family: 'HSR', 'Segoe UI', sans-serif;
            background: #fef3ec;
            color: var(--text-dark);
            overflow: hidden;
        }

        /* ── Fond Vanta ── */
        body {
            background: none !important;
        }

        /* ── Layout centré ── */
        .page-404 {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
        }

        /* ── Carte glassmorphism — même style que .container du site ── */
        .card-404 {
            backdrop-filter: blur(15px);
            background: var(--glass-bg);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 3rem 3.5rem 2.5rem;
            max-width: 560px;
            width: 100%;
            box-shadow: var(--shadow);
            animation: cardIn 0.5s cubic-bezier(.16,1,.3,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(.95); }
            to   { opacity: 1; transform: none; }
        }

        /* ── Grand 404 — style gradient identique aux boutons du site ── */
        .num-404,
        a[id="404"] {
            font-size: clamp(5rem, 16vw, 9rem);
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.1rem;
            filter: drop-shadow(2px 4px 8px rgba(255,107,107,0.3));
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { filter: drop-shadow(2px 4px 8px rgba(255,107,107,0.25)); }
            50%       { filter: drop-shadow(2px 4px 18px rgba(255,107,107,0.55)); }
        }

        /* ── Séparateur — même style que les dividers du site ── */
        .divider-404 {
            width: 56px;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
            border-radius: 3px;
            margin: 1rem auto 1.4rem;
        }

        /* ── Titre du message ── */
        .titre-404 {
            font-size: clamp(1rem, 3vw, 1.3rem);
            font-weight: 700;
            color: var(--accent);
            text-shadow: 1px 1px 2px rgba(255, 107, 107, 0.2);
            margin-bottom: 0.6rem;
            line-height: 1.35;
        }

        /* ── Sous-titre ── */
        .sous-titre-404 {
            font-size: 0.95rem;
            color: var(--text-mid);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* ── Bouton retour — identique aux .btn du site ── */
        .btn-home-404 {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.2rem;
            text-decoration: none;
            text-align: center;
            padding: 0.65rem 1.4rem;
            border-radius: 0.8rem;
            background: linear-gradient(135deg, #ff6b6b, #ff8c42);
            color: #fff;
            font-family: 'HSR', 'Segoe UI', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-home-404::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            border-radius: 0.8rem;
        }

        .btn-home-404:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
            background: linear-gradient(135deg, #ff8c42, #ff6b6b);
        }

        .btn-home-404:hover::after {
            left: 0;
        }

        .btn-home-404:active {
            transform: translateY(0) scale(0.98);
        }

        /* ── URL affichée en bas ── */
        .url-hint-404 {
            margin-top: 1.6rem;
            font-size: 0.78rem;
            color: var(--text-light);
        }

        .url-hint-404 span {
            color: var(--accent);
            font-weight: 600;
            word-break: break-all;
        }

        /* ── Logo FoodHub en haut à gauche — identique à la sidebar ── */
        .brand-404 {
            position: fixed;
            top: 1.4rem;
            left: 1.8rem;
            font-size: 1.35rem;
            font-weight: 700;
            color: #333;
            text-decoration: none;
            z-index: 100;
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            padding: 0.5rem 1rem;
            border-radius: 0.8rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .brand-404:hover {
            background: var(--glass-hover);
            transform: translateY(-2px);
        }

        .brand-404 span {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Flash message — identique à restaurants.css ── */
        .flash-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.49);
            backdrop-filter: blur(12px);
            color: #333;
            padding: 12px 24px;
            border-radius: 12px;
            font-family: 'HSR', sans-serif;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 10000;
            font-weight: 600;
            border-left: 4px solid var(--accent);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to   { opacity: 1; transform: translate(-50%, 0); }
        }

        /* ── Lien retour secondaire — identique à .back-link du site ── */
        .back-link-404 {
            display: inline-block;
            margin-top: 0.8rem;
            text-decoration: none;
            color: var(--accent);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.6rem;
            background: var(--accent-pale);
            transition: all 0.3s ease;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        .back-link-404:hover {
            transform: scale(1.05);
            background: rgba(255, 107, 107, 0.22);
            color: #e05555;
        }

        @media (max-width: 600px) {
            .card-404 { padding: 2rem 1.5rem; }
            .brand-404 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<!-- Lien marque -->
<a class="brand-404" href="/home.php">🍽️ <span>FoodHub</span></a>

<main class="page-404">
    <div class="card-404">

        <div class="num-404"><a id="404" href="/404.php">404</a></div>

        <div class="divider-404"></div>

        <?php if ($path !== "/404.php"): ?>
        <h1 class="titre-404"><?= htmlspecialchars($titre) ?></h1>
        <?php else: ?>
        <h1 class="titre-404"><?= htmlspecialchars("ta tête de con.jpg") ?></h1>
        <?php endif; ?>
        
        <?php if ($path !== "/404.php"): ?>
        <p class="sous-titre-404"><?= nl2br(htmlspecialchars($sous_titre)) ?></p>
        <?php else: ?>
        <p class="sous-titre-404"><?= htmlspecialchars("MDR, t'es pas drôle. Pourquoi t'as mis ça dans l'URL ? Va te faire cuire un oeuf, t'as rien à faire ici.") ?></p>
        <?php endif; ?>

        <?php if($bouton_texte === "Retour à la page de connexion"): ?>
        <a href="/login.php" class="btn-home-404">
            👤 <?= htmlspecialchars($bouton_texte) ?>
        </a>
        <?php else: ?>
        <a href="/home.php" class="btn-home-404">
            🏠 <?= htmlspecialchars($bouton_texte) ?>
        </a>
        <?php endif; ?>

        <?php if (!empty($path) && $path !== '/'): ?>
            <p class="url-hint-404">Page introuvable : <span><?= htmlspecialchars($path) ?></span></p>
        <?php endif; ?>

    </div>
</main>

<!-- Vanta Waves — identique à home.php -->
<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
    VANTA.WAVES({
        el: "body",
        mouseControls: true,
        touchControls: true,
        gyroControls: false,
        minHeight: 200.00,
        minWidth: 200.00,
        scale: 1.00,
        scaleMobile: 1.00,
        color: 0xf6b26b,
        shininess: 60,
        waveHeight: 22,
        waveSpeed: 0.7,
        zoom: 1.1
    });
</script>

<!-- musique d'ambiance -->
<?php if(random_int(1,10) > 5): ?>
    <?php if(random_int(1,10) > 5): ?>
        <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Cité souterraine Gerudo - The Legend of Zelda Tears of the Kingdom (OST).mp3" type="audio/mp3"> </audio>
    <?php else: ?>
        <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Kara Kara Bazaar (The Legend of Zelda Breath of the Wild OST).mp3" type="audio/mp3"> </audio>
    <?php endif; ?>
<?php else: ?>
    <?php if(random_int(1,10) > 5): ?>
        <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Outside a Shrire (Wasteland) - Tears of the Kingdom OST.mp3" type="audio/mp3"> </audio>
    <?php else: ?>
        <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Maison de Link TOTK.mp3" type="audio/mp3"> </audio>
    <?php endif; ?>
<?php endif; ?>

<script>
    // Les navigateurs bloquent l'autoplay sans interaction utilisateur.
    // On tente d'abord, puis on écoute le premier clic/toucher si ça échoue.
    const player = document.getElementById('player');
    player.play().catch(() => {
        const unlock = () => {
            player.play();
            document.removeEventListener('click', unlock);
            document.removeEventListener('keydown', unlock);
      document.removeEventListener('touchstart', unlock);
        };
        document.addEventListener('click', unlock);
        document.addEventListener('keydown', unlock);
        document.addEventListener('touchstart', unlock);
    });
</script>
</body>
</html>
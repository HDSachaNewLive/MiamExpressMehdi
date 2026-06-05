<?php
// desactive.php — Page affichée aux comptes désactivés
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
    <title>Compte désactivé - FoodHub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:      #ff6b6b;
            --accent2:     #ff8c42;
            --glass-bg:    rgba(255, 255, 255, 0.22);
            --glass-hover: rgba(255, 255, 255, 0.30);
            --border:      rgba(255, 255, 255, 0.35);
            --shadow:      0 8px 32px rgba(0, 0, 0, 0.15);
            --text-mid:    #555;
            --text-light:  #888;
        }

        html, body { height: 100%; font-family: 'HSR', 'Segoe UI', sans-serif; overflow: hidden; }
        body { background: none !important; }

        .page-desactive {
            position: relative; z-index: 10;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 100vh; padding: 2rem; text-align: center;
        }

        .card-desactive {
            backdrop-filter: blur(15px);
            background: var(--glass-bg);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 3rem 3.5rem 2.5rem;
            max-width: 560px; width: 100%;
            box-shadow: var(--shadow);
            animation: cardIn 0.5s cubic-bezier(.16,1,.3,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(.97); }
            to   { opacity: 1; transform: none; }
        }

        .icon-desactive {
            font-size: clamp(4rem, 14vw, 7rem);
            line-height: 1; margin-bottom: 0.4rem;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { filter: drop-shadow(2px 4px 8px rgba(255,107,107,0.25)); }
            50%       { filter: drop-shadow(2px 4px 18px rgba(255,107,107,0.55)); }
        }

        .divider-desactive {
            width: 56px; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
            border-radius: 3px; margin: 1rem auto 1.4rem;
        }

        .titre-desactive {
            font-size: clamp(1rem, 3vw, 1.35rem);
            font-weight: 700; color: var(--accent);
            text-shadow: 1px 1px 2px rgba(255, 107, 107, 0.2);
            margin-bottom: 0.6rem; line-height: 1.35;
        }

        .sous-titre-desactive {
            font-size: 0.95rem; color: var(--text-mid);
            margin-bottom: 2rem; line-height: 1.7;
        }

        .btn-contact {
            display: inline-flex; align-items: center; gap: 0.5rem;
            text-decoration: none; padding: 0.65rem 1.4rem;
            border-radius: 0.8rem;
            background: linear-gradient(135deg, #ff6b6b, #ff8c42);
            color: #fff; font-family: 'HSR', 'Segoe UI', sans-serif;
            font-weight: 600; font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
            position: relative; overflow: hidden;
        }

        .btn-contact::after {
            content: ""; position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease; border-radius: 0.8rem;
        }

        .btn-contact:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
            background: linear-gradient(135deg, #ff8c42, #ff6b6b);
        }

        .btn-contact:hover::after { left: 0; }
        .btn-contact:active { transform: translateY(0) scale(0.98); }

        .logout-link {
            display: inline-block; margin-top: 1rem;
            text-decoration: none; color: var(--text-light);
            font-size: 0.85rem; transition: color 0.3s ease;
        }
        .logout-link:hover { color: var(--accent); }

        .brand-desactive {
            position: fixed; top: 1.4rem; left: 1.8rem;
            font-size: 1.35rem; font-weight: 700; color: #333;
            text-decoration: none; z-index: 100;
            background: var(--glass-bg); backdrop-filter: blur(15px);
            padding: 0.5rem 1rem; border-radius: 0.8rem;
            border: 1px solid var(--border); box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        .brand-desactive:hover { background: var(--glass-hover); transform: translateY(-2px); }
        .brand-desactive span {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        @media (max-width: 600px) {
            .card-desactive { padding: 2rem 1.5rem; }
            .brand-desactive { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<!-- musique d'ambiance -->
<?php if(random_int(1,10) > 5): ?>
    <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Cité souterraine Gerudo - The Legend of Zelda Tears of the Kingdom (OST).mp3" type="audio/mp3"> </audio>
<?php else: ?>
    <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Kara Kara Bazaar (The Legend of Zelda Breath of the Wild OST).mp3" type="audio/mp3"> </audio>
<?php endif; ?>

<main class="page-desactive">
    <div class="card-desactive">

        <div class="icon-desactive">🚫</div>

        <div class="divider-desactive"></div>

        <h1 class="titre-desactive">Ton compte a été désactivé</h1>

        <p class="sous-titre-desactive">
            Ton accès à FoodHub a été temporairement suspendu par un administrateur.<br>
            Si tu penses qu'il s'agit d'une erreur ou pour en savoir plus,
            contacte-nous via la page dédiée ci-dessous.
        </p>

        <a href="/contact_admin.php" class="btn-contact">
            📧 Contacter l'administrateur
        </a>

        <br>
        <a href="/logout.php" class="logout-link">← Se déconnecter</a>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
    VANTA.WAVES({
        el: "body",
        mouseControls: true, touchControls: true, gyroControls: false,
        minHeight: 200.00, minWidth: 200.00, scale: 1.00, scaleMobile: 1.00,
        color: 0xf6b26b, shininess: 60, waveHeight: 22, waveSpeed: 0.7, zoom: 1.1
    });
</script>
</body>
</html>
<?php
// approvisionnement_success.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$montant_ajoute = (float)($_GET['montant'] ?? 0);
if ($montant_ajoute < 0) $montant_ajoute = 0;

// Récupérer le nouveau solde réel (jamais faire confiance au paramètre GET)
$stmt = $conn->prepare("SELECT solde FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
$nouveau_solde = $user['solde'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Approvisionnement réussi - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php include 'sidebar.php'; ?>
</head>
<body>
    <audio id="player" autoplay loop>
        <source src="assets/Account Settings Wii U System Music.mp3" type="audio/mpeg">
    </audio>
    <?php include "slider_son.php"; ?>

    <style>
        #volume-slider {
            background: linear-gradient(135deg, #4caf50, #66bb6a);
        }
        #volume-button {
            background: linear-gradient(135deg, #4caf50, #66bb6a);
        }
    </style>

    <main class="container">

        <h2>✅ Approvisionnement réussi !</h2>

        <div class="success-card">
            <p class="success-text">Votre compte a été approvisionné avec succès</p>
            
            <div class="montant-info">
                <div class="info-item">
                    <span class="info-label">Montant ajouté</span>
                    <span class="info-value">+<?= number_format($montant_ajoute, 2) ?> €</span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Nouveau solde</span>
                    <span class="info-value highlight"><?= number_format($nouveau_solde, 2) ?> €</span>
                </div>
            </div>

            <div class="info-box">
                <p>💡 <strong>Votre solde est maintenant disponible !</strong></p>
                <p>Au moment de payer ton panier, tu pourras choisir de l'utiliser ou non.</p>
            </div>
        </div>

        <div class="action-buttons">
            <a href="panier.php" class="btn btn-primary">🛒 Aller au panier</a>
            <a href="approvisionnement.php" class="btn btn-secondary">➕ Approvisionner à nouveau</a>
            <a href="home.php" class="btn btn-tertiary">🏠 Retour à l'accueil</a>
        </div>
    </main>

    <style>
    .container {
        max-width: 700px;
        margin: 120px auto;
        padding: 50px;
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

    h2 {
        color: #4caf50;
        font-size: 2.2rem;
        margin: 30px 0;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .success-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 30px;
        margin: 30px 0;
    }

    .success-text {
        font-size: 1.2rem;
        color: rgba(0, 0, 0, 0.75);
        margin-bottom: 30px;
    }

    .montant-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin: 30px 0;
    }

    .info-item {
        background: rgba(255, 255, 255, 0.15);
        padding: 20px;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .info-label {
        font-size: 0.9rem;
        color: rgba(0, 0, 0, 0.65);
        font-weight: 600;
    }

    .info-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #4caf50;
    }

    .info-value.highlight {
        color: #2e7d32;
        font-size: 2.2rem;
    }

    .info-box {
        background: rgba(76, 175, 80, 0.2);
        border-left: 4px solid #4caf50;
        padding: 20px;
        border-radius: 10px;
        margin-top: 25px;
        text-align: left;
    }

    .info-box p {
        margin: 8px 0;
        color: rgba(0, 0, 0, 0.75);
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 40px;
    }

    .btn {
        padding: 15px 30px;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-family: 'HSR', sans-serif;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4caf50, #66bb6a);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #66bb6a, #4caf50);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #2196F3, #42A5F5);
        color: white;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #42A5F5, #2196F3);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
    }

    .btn-tertiary {
        background: rgba(255, 255, 255, 0.2);
        color: rgba(0, 0, 0, 0.75);
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-tertiary:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes bounceIn {
        0% { transform: scale(0); opacity: 0; }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); opacity: 1; }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    @keyframes checkmarkDraw {
        to { opacity: 1; }
    }

    @media (max-width: 768px) {
        .montant-info {
            grid-template-columns: 1fr;
        }

        .container {
            margin: 80px auto;
            padding: 30px;
        }
    }
    </style>
    </main>
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
    color: 0xf2c461,
    shininess: 25,
    waveHeight: 25,
    waveSpeed: 0.9,
    zoom: 0.9
})
</script>
</body>
</html>

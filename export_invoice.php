<?php
// export_invoice.php - Export de facture en PDF
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['commande_id']) || !isset($_GET['format'])) {
    die("Paramètres manquants");
}

$uid = (int)$_SESSION['user_id'];
$commande_id = (int)$_GET['commande_id'];
$format = $_GET['format']; // 'pdf' ou 'csv' (csv non utilisé dans la version finale du site)

// Récupérer la commande
$stmt = $conn->prepare("
    SELECT c.*, u.nom_user, u.email, u.telephone, u.adresse_livraison, cp.code_reduction
    FROM commandes c
    JOIN users u ON c.user_id = u.user_id
    LEFT JOIN coupons cp ON c.coupon_id = cp.coupon_id
    WHERE c.commande_id = ? AND c.user_id = ?
");
$stmt->execute([$commande_id, $uid]);
$commande = $stmt->fetch();

if (!$commande) {
    die("Commande introuvable");
}

// Récupérer les articles
$stmt = $conn->prepare("
    SELECT cp.*, pl.nom_plat, r.nom_restaurant
    FROM commande_plats cp
    JOIN plats pl ON cp.plat_id = pl.plat_id
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    WHERE cp.commande_id = ?
");
$stmt->execute([$commande_id]);
$items = $stmt->fetchAll();

// Calculer le sous-total
$sous_total = 0;
foreach ($items as $item) {
    $sous_total += $item['prix_unitaire'] * $item['quantite'];
}

if ($format === 'csv') {
    // Export CSV (non utilisé dans la version finale du site car trop compliqué à mettre en page proporement)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="facture_' . $commande['numero_utilisateur'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-tête
    fputcsv($output, ['FACTURE FOODHUB'], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['Commande n°', $commande['numero_utilisateur']], ';');
    fputcsv($output, ['Date', $commande['date_commande']], ';');
    fputcsv($output, ['Client', $commande['nom_user']], ';');
    fputcsv($output, ['Email', $commande['email']], ';');
    fputcsv($output, ['Téléphone', $commande['telephone']], ';');
    fputcsv($output, ['Adresse', $commande['adresse_livraison']], ';');
    fputcsv($output, ['Mode de paiement', $commande['mode_paiement']], ';');
    fputcsv($output, [''], ';');
    
    // Articles
    fputcsv($output, ['Article', 'Restaurant', 'Prix unitaire', 'Quantité', 'Total'], ';');
    foreach ($items as $item) {
        fputcsv($output, [
            $item['nom_plat'],
            $item['nom_restaurant'],
            number_format($item['prix_unitaire'], 2) . ' €',
            $item['quantite'],
            number_format($item['prix_unitaire'] * $item['quantite'], 2) . ' €'
        ], ';');
    }
    
    fputcsv($output, [''], ';');
    fputcsv($output, ['Sous-total', '', '', '', number_format($sous_total, 2) . ' €'], ';');
    
    if ($commande['montant_reduction'] > 0) {
        $coupon_text = $commande['code_reduction'] ? ' (Code: ' . $commande['code_reduction'] . ')' : '';
        fputcsv($output, ['Réduction' . $coupon_text, '', '', '', '-' . number_format($commande['montant_reduction'], 2) . ' €'], ';');
    }
    
    fputcsv($output, ['TOTAL', '', '', '', number_format($commande['montant_total'], 2) . ' €'], ';');
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // Export PDF (version HTML simple)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Facture <?= $commande['numero_utilisateur'] ?></title>
        <style>
            @media print {
                .no-print { display: none; }
            }
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 20px auto;
                padding: 20px;
                background: white;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #ff6b6b;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #ff6b6b;
                margin: 0;
            }
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            .info-section {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 8px;
            }
            .info-section h3 {
                margin-top: 0;
                color: #333;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background: #ff6b6b;
                color: white;
            }
            .total-section {
                text-align: right;
                margin-top: 20px;
            }
            .total-line {
                display: flex;
                justify-content: flex-end;
                gap: 20px;
                padding: 8px 0;
            }
            .total-line.grand-total {
                font-size: 1.3rem;
                font-weight: bold;
                border-top: 2px solid #333;
                padding-top: 15px;
                margin-top: 10px;
                color: #ff6b6b;
            }
            .btn {
                background: #ff6b6b;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin: 10px 5px;
            }
            .btn:hover {
                background: #ff8c42;
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button class="btn" onclick="window.print()">🖨️ Imprimer / Sauvegarder en PDF</button>
            <button class="btn" onclick="window.close()">← Retour</button>
        </div>
        
        <div class="header">
            <h1>🍽️ FOODHUB</h1>
            <p>Facture n° <?= htmlspecialchars($commande['numero_utilisateur']) ?></p>
            <p>Date: <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
        </div>
        
        <div class="info-grid">
            <div class="info-section">
                <h3>Informations client</h3>
                <p><strong>Nom:</strong> <?= htmlspecialchars($commande['nom_user']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($commande['email']) ?></p>
                <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['telephone'] ?: 'Non renseigné') ?></p>
            </div>
            
            <div class="info-section">
                <h3>Livraison</h3>
                <p><strong>Adresse:</strong><br><?= htmlspecialchars($commande['adresse_livraison'] ?: 'Non renseignée') ?></p>
                <p><strong>Mode de paiement:</strong> <?= htmlspecialchars($commande['mode_paiement']) ?></p>
                <p><strong>Statut:</strong> <?= htmlspecialchars($commande['statut']) ?></p>
            </div>
        </div>
        
        <h3>Détail de la commande</h3>
        <table>
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Restaurant</th>
                    <th>Prix unitaire</th>
                    <th>Quantité</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nom_plat']) ?></td>
                    <td><?= htmlspecialchars($item['nom_restaurant']) ?></td>
                    <td><?= number_format($item['prix_unitaire'], 2) ?> €</td>
                    <td><?= $item['quantite'] ?></td>
                    <td><?= number_format($item['prix_unitaire'] * $item['quantite'], 2) ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-line">
                <span>Sous-total:</span>
                <span><?= number_format($sous_total, 2) ?> €</span>
            </div>
            
            <?php if ($commande['montant_reduction'] > 0): ?>
            <div class="total-line" style="color: #4CAF50;">
                <span>Réduction <?= $commande['code_reduction'] ? '(' . htmlspecialchars($commande['code_reduction']) . ')' : '' ?>:</span>
                <span>-<?= number_format($commande['montant_reduction'], 2) ?> €</span>
            </div>
            <?php endif; ?>
            
            <div class="total-line grand-total">
                <span>TOTAL:</span>
                <span><?= number_format($commande['montant_total'], 2) ?> €</span>
            </div>
        </div>
        
        <div style="margin-top: 40px; text-align: center; color: #666; font-size: 0.9rem;">
            <p>Merci de votre commande !</p>
            <p>FoodHub - La plateforme qui simplifie la commande et livraison gratuite de repas en ligne.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Format non reconnu
die("Format non supporté");
?>
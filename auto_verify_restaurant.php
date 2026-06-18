<?php
// auto_verify_restaurant.php
// Vérifie automatiquement les restaurants en attente depuis +10 min via detection_NSFW.php.
// Lancé via cron alwaysdata toutes les 10 minutes : php auto_verify_restaurant.php
//
// Réponse JSON si ?ajax=1&key=CRON_SECRET : {"checked": N, "results": [...]}
// SÉCURITÉ : accès web uniquement avec clé secrète (variable d'env CRON_SECRET)

if (PHP_SAPI !== 'cli') {
    // Accès web : exiger une clé secrète
    $cron_secret = getenv('CRON_SECRET');
    $provided_key = $_GET['key'] ?? '';

    if (!$cron_secret || !hash_equals($cron_secret, $provided_key)) {
        http_response_code(403);
        exit('Accès refusé.');
    }
}

$is_ajax = PHP_SAPI !== 'cli' && isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/detection_NSFW.php';

// Récupérer les restaurants non vérifiés soumis depuis +10 minutes.
try {
    $stmt = $conn->prepare("
        SELECT restaurant_id, nom_restaurant, adresse, categorie,
               description_resto, proprietaire_id
        FROM restaurants
        WHERE verified = 0
          AND created_at <= (NOW() - INTERVAL 10 MINUTE)
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[FoodHub] auto_verify: erreur requête — ' . $e->getMessage());
    if ($is_ajax) echo json_encode(['error' => 'db_error']);
    exit;
}

if (empty($pending)) {
    if ($is_ajax) echo json_encode(['checked' => 0, 'results' => []]);
    exit;
}

$results = [];

foreach ($pending as $resto) {
    $rid   = (int)$resto['restaurant_id'];
    $nom   = $resto['nom_restaurant']    ?? '';
    $desc  = $resto['description_resto'] ?? '';
    $addr  = $resto['adresse']           ?? '';
    $cat   = $resto['categorie']         ?? '';

    try {
        $stmtP = $conn->prepare("SELECT nom_plat, description_plat, prix FROM plats WHERE restaurant_id = ?");
        $stmtP->execute([$rid]);
        $plats = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $plats = [];
    }

    $check = fh_verify_restaurant($nom, $desc, $addr, $cat, $plats);

    if ($check['accepted']) {
        try {
            $conn->prepare("UPDATE restaurants SET verified = 1 WHERE restaurant_id = ?")
                 ->execute([$rid]);
            error_log("[FoodHub] auto_verify: #{$rid} '{$nom}' ACCEPTÉ (score {$check['score']})");
            $results[] = ['restaurant_id' => $rid, 'nom' => $nom, 'action' => 'accepted', 'score' => $check['score'], 'reason' => $check['reason']];
        } catch (PDOException $e) {
            error_log('[FoodHub] auto_verify: erreur accept #' . $rid . ' — ' . $e->getMessage());
        }
    } else {
        try {
            $conn->prepare("DELETE FROM plats WHERE restaurant_id = ?")->execute([$rid]);
            $conn->prepare("DELETE FROM restaurants WHERE restaurant_id = ?")->execute([$rid]);
            $conn->prepare("DELETE FROM notifications WHERE restaurant_id = ? AND user_id = 1")->execute([$rid]);
            error_log("[FoodHub] auto_verify: #{$rid} '{$nom}' REFUSÉ (score {$check['score']}) — {$check['reason']}");
            $results[] = ['restaurant_id' => $rid, 'nom' => $nom, 'action' => 'refused', 'score' => $check['score'], 'reason' => $check['reason']];
        } catch (PDOException $e) {
            error_log('[FoodHub] auto_verify: erreur refuse #' . $rid . ' — ' . $e->getMessage());
        }
    }
}

if ($is_ajax) {
    echo json_encode(['checked' => count($pending), 'results' => $results]);
}

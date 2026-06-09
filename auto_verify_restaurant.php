<?php
// auto_verify_restaurant.php
// Vérifie automatiquement les restaurants en attente depuis +10 min via detection_NSFW.php.
// Appelé en AJAX (fire-and-forget) depuis vendor_add_restaurant.php juste après soumission.
// Peut aussi être lancé via cron : php auto_verify_restaurant.php
//
// Réponse JSON si ?ajax=1 : {"checked": N, "results": [...]}

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/detection_NSFW.php';

// Récupérer les restaurants non vérifiés soumis depuis +10 minutes.
// On s'appuie sur la notification créée lors de l'ajout (qui a un created_at fiable).
try {
    $stmt = $conn->prepare("
        SELECT r.restaurant_id, r.nom_restaurant, r.adresse, r.categorie,
               r.description_resto, r.proprietaire_id
        FROM restaurants r
        INNER JOIN notifications n
            ON  n.restaurant_id = r.restaurant_id
            AND n.type          = 'comment'
            AND n.user_id       = 1
        WHERE r.verified = 0
          AND n.created_at <= (NOW() - INTERVAL 10 MINUTE)
        ORDER BY n.created_at ASC
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[FoodHub] auto_verify: erreur requête — ' . $e->getMessage());
    if ($is_ajax) echo json_encode(['error' => 'db_error', 'message' => $e->getMessage()]);
    exit;
}

if (empty($pending)) {
    if ($is_ajax) echo json_encode(['checked' => 0, 'results' => []]);
    exit;
}

$results = [];

foreach ($pending as $resto) {
    $rid   = (int)$resto['restaurant_id'];
    $nom   = $resto['nom_restaurant']  ?? '';
    $desc  = $resto['description_resto'] ?? '';
    $addr  = $resto['adresse']         ?? '';
    $cat   = $resto['categorie']       ?? '';

    // Récupérer les plats
    try {
        $stmtP = $conn->prepare("SELECT nom_plat, description_plat, prix FROM plats WHERE restaurant_id = ?");
        $stmtP->execute([$rid]);
        $plats = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $plats = [];
    }

    // Vérification locale (aucune API externe)
    $check = fh_verify_restaurant($nom, $desc, $addr, $cat, $plats);

    if ($check['accepted']) {
        // ── ACCEPTER ────────────────────────────────────────────────────────
        try {
            $conn->prepare("UPDATE restaurants SET verified = 1 WHERE restaurant_id = ?")
                 ->execute([$rid]);
            error_log("[FoodHub] auto_verify: #{$rid} '{$nom}' ACCEPTÉ (score {$check['score']})");
            $results[] = [
                'restaurant_id' => $rid,
                'nom'           => $nom,
                'action'        => 'accepted',
                'score'         => $check['score'],
                'reason'        => $check['reason'],
            ];
        } catch (PDOException $e) {
            error_log('[FoodHub] auto_verify: erreur accept #' . $rid . ' — ' . $e->getMessage());
        }
    } else {
        // ── REFUSER (même comportement que le bouton "Refuser" dans notifications.php) ──
        try {
            $conn->prepare("DELETE FROM plats WHERE restaurant_id = ?")
                 ->execute([$rid]);
            $conn->prepare("DELETE FROM restaurants WHERE restaurant_id = ?")
                 ->execute([$rid]);
            // Supprimer aussi la notification admin pour ne pas laisser de fantôme
            $conn->prepare("DELETE FROM notifications WHERE restaurant_id = ? AND user_id = 1")
                 ->execute([$rid]);
            error_log("[FoodHub] auto_verify: #{$rid} '{$nom}' REFUSÉ (score {$check['score']}) — {$check['reason']}");
            $results[] = [
                'restaurant_id' => $rid,
                'nom'           => $nom,
                'action'        => 'refused',
                'score'         => $check['score'],
                'reason'        => $check['reason'],
            ];
        } catch (PDOException $e) {
            error_log('[FoodHub] auto_verify: erreur refuse #' . $rid . ' — ' . $e->getMessage());
        }
    }
}

if ($is_ajax) {
    echo json_encode(['checked' => count($pending), 'results' => $results]);
}

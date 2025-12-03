<?php
// vote_comment.php
session_start();
require_once 'db/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['avis_id']) || !isset($_POST['type'])) {
    echo json_encode(['error' => 'invalid_request']);
    exit;
}
$avis_id = (int)$_POST['avis_id'];
$type = $_POST['type'] === 'dislike' ? 'dislike' : 'like';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged']);
    exit;
}
$uid = (int)$_SESSION['user_id'];

try {
    $conn->beginTransaction();

    // verrouille la ligne de vote existante si elle existe
    $sel = $conn->prepare("SELECT type FROM avis_votes WHERE avis_id = ? AND user_id = ? FOR UPDATE");
    $sel->execute([$avis_id, $uid]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['type'] === $type) {
            // toggle off
            $del = $conn->prepare("DELETE FROM avis_votes WHERE avis_id = ? AND user_id = ?");
            $del->execute([$avis_id, $uid]);

            if ($type === 'like') {
                $conn->prepare("UPDATE avis SET likes = GREATEST(0, likes - 1) WHERE avis_id = ?")->execute([$avis_id]);
            } else {
                $conn->prepare("UPDATE avis SET dislikes = GREATEST(0, dislikes - 1) WHERE avis_id = ?")->execute([$avis_id]);
            }
        } else {
            // change vote
            $upd = $conn->prepare("UPDATE avis_votes SET type = ?, date_vote = NOW() WHERE avis_id = ? AND user_id = ?");
            $upd->execute([$type, $avis_id, $uid]);

            if ($type === 'like') {
                $conn->prepare("UPDATE avis SET likes = likes + 1, dislikes = GREATEST(0, dislikes - 1) WHERE avis_id = ?")->execute([$avis_id]);
            } else {
                $conn->prepare("UPDATE avis SET dislikes = dislikes + 1, likes = GREATEST(0, likes - 1) WHERE avis_id = ?")->execute([$avis_id]);
            }
        }
    } else {
        // new vote
        $ins = $conn->prepare("INSERT INTO avis_votes (avis_id, user_id, type) VALUES (?, ?, ?)");
        $ins->execute([$avis_id, $uid, $type]);

        if ($type === 'like') {
            $conn->prepare("UPDATE avis SET likes = likes + 1 WHERE avis_id = ?")->execute([$avis_id]);
        } else {
            $conn->prepare("UPDATE avis SET dislikes = dislikes + 1 WHERE avis_id = ?")->execute([$avis_id]);
        }
    }

    $conn->commit();

    // retourne les totaux mis à jour
    $c = $conn->prepare("SELECT likes, dislikes FROM avis WHERE avis_id = ?");
    $c->execute([$avis_id]);
    $totals = $c->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'likes' => (int)($totals['likes'] ?? 0),
        'dislikes' => (int)($totals['dislikes'] ?? 0),
        'your_vote' => ($existing ? ($existing['type'] === $type ? null : $type) : $type) // aide UI: null si toggled off
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("vote_comment error: " . $e->getMessage());
    echo json_encode(['error' => 'server_error']);
}

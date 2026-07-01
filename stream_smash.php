<?php
// stream_smash.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}
require_once 'db/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/csrf_helper.php';

$uid        = (int)$_SESSION['user_id'];
$is_admin   = fh_is_admin($conn);
$nom_user   = htmlspecialchars($_SESSION['nom_user'] ?? 'toi');

// HANDLER AJAX : vérification live (utilisé par le polling JS, uniquement en mode auto)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_live') {
    header('Content-Type: application/json; charset=utf-8');

    if (get_param($conn, 'stream_auto', '0') !== '1') {
        echo json_encode(['auto' => false]);
        exit;
    }

    $auto = fh_get_live_youtube_video($conn);
    $payload = [
        'auto'     => true,
        'live'     => $auto['live'],
        'video_id' => $auto['video_id'],
        'titre'    => $auto['titre'],
    ];

    if ($auto['live'] && $auto['video_id']) {
        $payload['embed_url'] = 'https://www.youtube.com/embed/' . $auto['video_id']
            . '?autoplay=1&mute=0&rel=0&modestbranding=1&color=white';
    }

    echo json_encode($payload);
    exit;
}

//  Clés de stockage en base (table `parametres_stream` key/value) 
// Si tu n'as pas cette table, on crée un fallback sur des variables PHP.
// La table attendue : CREATE TABLE parametres_stream (cle VARCHAR(100) PRIMARY KEY, valeur TEXT);

function get_param(PDO $conn, string $key, string $default = ''): string {
    try {
        $s = $conn->prepare("SELECT valeur FROM parametres_stream WHERE cle = ? LIMIT 1");
        $s->execute([$key]);
        $r = $s->fetchColumn();
        return ($r !== false) ? (string)$r : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function set_param(PDO $conn, string $key, string $value): void {
    try {
        $conn->prepare("INSERT INTO parametres_stream (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)")
             ->execute([$key, $value]);
    } catch (\Throwable $e) { /* silencieux */ }
}

//  Admin : sauvegarde du stream 
$flash = '';
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stream'])) {
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $flash = ['type' => 'error', 'msg' => 'Jeton CSRF invalide.'];
    } else {
        $raw_url   = trim($_POST['stream_url'] ?? '');
        $titre     = trim($_POST['stream_titre'] ?? 'Stream en direct');
        $en_ligne  = isset($_POST['stream_online']) ? '1' : '0';
        $auto      = isset($_POST['stream_auto']) ? '1' : '0';

        set_param($conn, 'stream_url',    $raw_url);
        set_param($conn, 'stream_titre',  $titre);
        set_param($conn, 'stream_online', $en_ligne);
        set_param($conn, 'stream_auto',   $auto);
        $flash = ['type' => 'success', 'msg' => '✅ Stream mis à jour !'];
    }
}

/**
 * Détecte si la chaîne est en live via OAuth (liveBroadcasts.list), ce qui
 * permet de voir les lives NON RÉPERTORIÉS du propriétaire de la chaîne —
 * contrairement à search.list (API clé simple) qui n'indexe que le contenu
 * public. Nécessite qu'un refresh_token ait été obtenu au préalable via
 * youtube_auth.php (panneau admin, à faire une seule fois).
 *
 * Retourne ['live' => bool, 'video_id' => string|null, 'titre' => string|null]
 */
function fh_fetch_live_via_oauth(PDO $conn): array {
    $result = ['live' => false, 'video_id' => null, 'titre' => null];

    $refresh_token = get_param($conn, 'yt_oauth_refresh_token', '');
    if ($refresh_token === '') {
        error_log("[FH stream_smash] aucun refresh_token YouTube — autorise la chaîne via youtube_auth.php");
        return $result;
    }

    // Réutiliser l'access_token en cache s'il n'est pas expiré, sinon le renouveler
    $access_token = get_param($conn, 'yt_oauth_access_token', '');
    $expires_at   = (int) get_param($conn, 'yt_oauth_access_token_expires', '0');

    if ($access_token === '' || time() >= $expires_at) {
        require_once __DIR__ . '/config_google.php';

        $post_fields = [
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ];
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($post_fields),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents(GOOGLE_TOKEN_URL, false, $context);
        $data = $resp !== false ? json_decode($resp, true) : null;

        if (!$data || empty($data['access_token'])) {
            error_log("[FH stream_smash] échec du renouvellement de l'access_token YouTube : " . ($resp ?: 'réponse vide'));
            return $result;
        }

        $access_token = $data['access_token'];
        $expires_at   = time() + (int)($data['expires_in'] ?? 3000) - 60;
        set_param($conn, 'yt_oauth_access_token', $access_token);
        set_param($conn, 'yt_oauth_access_token_expires', (string)$expires_at);
    }

    $url = 'https://www.googleapis.com/youtube/v3/liveBroadcasts'
         . '?part=snippet,status'
         . '&broadcastStatus=active'
         . '&broadcastType=all';

    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $access_token\r\n",
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        error_log("[FH stream_smash] échec de la requête liveBroadcasts.list");
        return $result;
    }

    $data = json_decode($json, true);

    if (isset($data['error'])) {
        error_log("[FH stream_smash] erreur API liveBroadcasts : " . json_encode($data['error']));
        return $result;
    }

    if (empty($data['items'][0])) {
        return $result; // pas de live actif (public ou non répertorié)
    }

    $item = $data['items'][0];
    $result['live']     = true;
    $result['video_id'] = $item['id'] ?? null;
    $result['titre']    = $item['snippet']['title'] ?? null;

    return $result;
}

function fh_get_live_youtube_video(PDO $conn, int $ttl = 30): array {
    // Avec OAuth, on identifie la chaîne via le refresh_token autorisé
    // (liveBroadcasts.list répond pour la chaîne du compte qui a autorisé l'app) —
    // plus besoin de YOUTUBE_CHANNEL_ID.

    // Vérifier le cache
    $checked_at = (int) get_param($conn, 'yt_auto_checked_at', '0');
    if ((time() - $checked_at) < $ttl) {
        $cached = get_param($conn, 'yt_auto_cache', '');
        if ($cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) return $decoded;
        }
    }

    // Verrou MySQL pour empêcher plusieurs requêtes simultanées de scraper en parallèle.
    // Si on n'obtient pas le verrou rapidement, on retombe sur le cache existant (même expiré)
    // plutôt que de risquer plusieurs requêtes HTTP en double.
    $got_lock = false;
    try {
        $lockStmt = $conn->prepare("SELECT GET_LOCK('fh_yt_live_check', 3)");
        $lockStmt->execute();
        $got_lock = ((int) $lockStmt->fetchColumn()) === 1;
    } catch (\Throwable $e) {
        $got_lock = false;
    }

    if (!$got_lock) {
        $cached = get_param($conn, 'yt_auto_cache', '');
        if ($cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) return $decoded;
        }
        return ['live' => false, 'video_id' => null, 'titre' => null];
    }

    // Revérifier le cache une fois le verrou obtenu : une autre requête a peut-être
    // déjà rafraîchi pendant qu'on attendait le verrou.
    $checked_at = (int) get_param($conn, 'yt_auto_checked_at', '0');
    if ((time() - $checked_at) < $ttl) {
        $cached = get_param($conn, 'yt_auto_cache', '');
        if ($cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                $conn->prepare("SELECT RELEASE_LOCK('fh_yt_live_check')")->execute();
                return $decoded;
            }
        }
    }

    $result = fh_fetch_live_via_oauth($conn);

    // Mettre à jour le cache même en cas d'absence de live (évite de re-scraper en boucle)
    set_param($conn, 'yt_auto_cache', json_encode($result));
    set_param($conn, 'yt_auto_checked_at', (string) time());

    $conn->prepare("SELECT RELEASE_LOCK('fh_yt_live_check')")->execute();

    return $result;
}

//  Lecture des paramètres ─
$stream_url    = get_param($conn, 'stream_url',    '');
$stream_titre  = get_param($conn, 'stream_titre',  'Stream en direct 🎮');
$stream_online = get_param($conn, 'stream_online', '0') === '1';
$stream_auto   = get_param($conn, 'stream_auto',   '0') === '1';
$stream_auto_video_id = null;

// Mode automatique : on écrase $stream_url / $stream_titre / $stream_online
// avec le résultat de la détection YouTube si la chaîne a été autorisée via OAuth.
$auto_checked_no_key = false;
if ($stream_auto) {
    $yt_refresh_token = get_param($conn, 'yt_oauth_refresh_token', '');
    if (empty($yt_refresh_token)) {
        $auto_checked_no_key = true;
        $stream_online = false;
    } else {
        $auto = fh_get_live_youtube_video($conn);
        $stream_auto_video_id = $auto['video_id'];
        if ($auto['live']) {
            $stream_url    = 'https://www.youtube.com/watch?v=' . $auto['video_id'];
            $stream_titre  = $auto['titre'] ?: $stream_titre;
            $stream_online = true;
        } else {
            $stream_online = false;
        }
    }
}

//  Conversion de l'URL en embed iframe ─
/**
 * Convertit une URL YouTube/Twitch publique en URL d'embed.
 * Supporte :
 *   - https://www.youtube.com/watch?v=XXXX           → embed YouTube (live ou VOD)
 *   - https://youtu.be/XXXX
 *   - https://www.youtube.com/live/XXXX
 *   - https://www.twitch.tv/CHANNEL
 *
 * Retourne ['platform' => 'youtube'|'twitch'|'unknown', 'embed_url' => string]
 */
function parse_stream_url(string $url): array {
    if ($url === '') return ['platform' => 'none', 'embed_url' => ''];

    $host = parse_url($url, PHP_URL_HOST) ?? '';
    $host = strtolower(str_replace('www.', '', $host));

    //  YouTube 
    if (in_array($host, ['youtube.com', 'youtu.be'])) {
        $video_id = '';

        if ($host === 'youtu.be') {
            $video_id = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        } else {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $qs);

            if (isset($qs['v']) && $qs['v'] !== '') {
                $video_id = $qs['v'];
            } elseif (preg_match('#^/(?:live|embed)/([A-Za-z0-9_\-]{11})#', $path, $m)) {
                $video_id = $m[1];
            }
        }

        // Nettoyer l'ID (11 chars alphanum)
        $video_id = preg_replace('/[^A-Za-z0-9_\-]/', '', $video_id);
        if (strlen($video_id) !== 11) {
            return ['platform' => 'youtube', 'embed_url' => ''];
        }

        // autoplay=1 + mute=0 pour avoir le son (navigateur peut bloquer l'autoplay avec son)
        $embed = 'https://www.youtube.com/embed/' . $video_id
               . '?autoplay=1&mute=0&rel=0&modestbranding=1&color=white';
        return ['platform' => 'youtube', 'embed_url' => $embed];
    }

    //  Twitch ─
    if ($host === 'twitch.tv') {
        $path    = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        $channel = explode('/', $path)[0] ?? '';
        $channel = preg_replace('/[^A-Za-z0-9_]/', '', $channel);
        if ($channel === '') {
            return ['platform' => 'twitch', 'embed_url' => ''];
        }
        // parent = domaine du site (alwaysdata)
        $parent  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $embed   = 'https://player.twitch.tv/?channel=' . urlencode($channel)
                 . '&parent=' . urlencode($parent)
                 . '&autoplay=true&muted=false';
        return ['platform' => 'twitch', 'embed_url' => $embed];
    }

    return ['platform' => 'unknown', 'embed_url' => ''];
}

$parsed      = parse_stream_url($stream_url);
$platform    = $parsed['platform'];
$embed_url   = $parsed['embed_url'];
$embed_valid = ($embed_url !== '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($stream_titre) ?> - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php include 'sidebar.php'; ?>
    <?php if (!$stream_online): ?> <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Maison de Link TOTK.mp3" type="audio/mp3"> </audio>
    <?php endif; ?>
</head>
<body>

<main class="container stream-page">

    <!--  En-tête  -->
    <div class="stream-header">
        <div class="stream-header-left">
            <h2 class="stream-title">
                <span id="stream-title-text"><?= htmlspecialchars($stream_titre) ?></span>
            </h2>
            <p class="stream-sub">
                Ryujinx · Smash Bros · streamé par l'admin
                <?php if ($platform === 'youtube'): ?>
                    · <span class="platform-tag yt">▶ YouTube</span>
                <?php elseif ($platform === 'twitch'): ?>
                    · <span class="platform-tag tw">💜 Twitch</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!--  Lecteur vidéo ─ -->
    <div class="stream-player-wrap" id="stream-player-wrap">
        <?php if (!$stream_online): ?>
            <div class="stream-offline">
                <div class="offline-icon">📴</div>
                <h3>Pas de stream en ce moment</h3>
                <p>L'admin n'est pas en direct pour l'instant. Reviens plus tard !</p>
                <?php if ($is_admin): ?>
                    <p class="admin-hint">👇 Configure le stream dans le panneau ci-dessous.</p>
                <?php endif; ?>
            </div>

        <?php elseif (!$embed_valid): ?>
            <div class="stream-offline">
                <div class="offline-icon">⚠️</div>
                <h3>URL de stream invalide</h3>
                <p>L'URL configurée n'est pas reconnue (YouTube ou Twitch uniquement).</p>
                <?php if ($is_admin): ?>
                    <p class="admin-hint">Corrige l'URL dans le panneau admin ci-dessous.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="iframe-container">
                <iframe
                    id="stream-iframe"
                    src="<?= htmlspecialchars($embed_url) ?>"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture; web-share; accelerometer; clipboard-write; encrypted-media; gyroscope"
                    allowfullscreen
                    title="<?= htmlspecialchars($stream_titre) ?>"
                ></iframe>
            </div>

            <!-- Bouton son (certains navigateurs bloquent l'autoplay avec son) -->
            
        <?php endif; ?>
    </div>

    <!--  Chat Twitch intégré (uniquement si Twitch)  -->
    <!-- <?php if ($stream_online && $embed_valid && $platform === 'twitch'): ?>
        <?php
        $path_twitch = trim(parse_url($stream_url, PHP_URL_PATH) ?? '', '/');
        $channel_twitch = preg_replace('/[^A-Za-z0-9_]/', '', explode('/', $path_twitch)[0] ?? '');
        $parent_twitch  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        ?>
        <div class="twitch-chat-wrap">
            <h3>💬 Chat en direct</h3>
            <iframe
                src="https://www.twitch.tv/embed/<?= urlencode($channel_twitch) ?>/chat?parent=<?= urlencode($parent_twitch) ?>&darkpopout"
                height="400"
                width="100%"
                frameborder="0"
                title="Twitch Chat"
            ></iframe>
        </div>
    <?php endif; ?> -->

    <!-- Panneau admin -->
    <?php if ($is_admin): ?>
    <div class="admin-panel">
        <div class="admin-panel-header">
            <h3>🛠️ Panneau Admin - Configurer le stream</h3>
        </div>

        <?php if ($flash): ?>
            <div class="flash-msg <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <?php if ($auto_checked_no_key): ?>
            <div class="flash-msg error">
                ⚠️ Mode auto activé mais ta chaîne YouTube n'est pas encore autorisée.
                <a href="youtube_auth.php" style="font-weight:700;">Connecter ma chaîne YouTube</a>
            </div>
        <?php endif; ?>

        <?php if (!$auto_checked_no_key && $stream_auto): ?>
            <p class="tip-note" style="margin-top:-0.4rem;">
                <a href="youtube_auth.php">🔄 Reconnecter ma chaîne YouTube</a> (si la détection ne fonctionne plus)
            </p>
        <?php endif; ?>

        <form method="POST" class="admin-form" id="stream-admin-form">
            <?= fh_csrf_field() ?>

            <label class="checkbox-toggle">
                <input type="checkbox" name="stream_auto" id="stream_auto" <?= $stream_auto ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
                <span class="toggle-label">🔴 Mode auto — détecter le live directement sur ma chaîne YouTube</span>
            </label>
            <p class="tip-note" style="margin: -0.2rem 0 0.6rem;">
                Vérifie automatiquement (toutes les 3 min) si ta chaîne est en live. Si activé,
                l'URL/titre/statut manuels ci-dessous sont ignorés.
            </p>

            <div id="manual-fields" style="<?= $stream_auto ? 'opacity:0.45;pointer-events:none;' : '' ?>">
                <label>URL du stream <small>(YouTube Live ou Twitch)</small></label>
                <input
                    type="url"
                    name="stream_url"
                    placeholder="https://www.youtube.com/watch?v=… ou https://www.twitch.tv/tonpseudo"
                    value="<?= htmlspecialchars($stream_url) ?>"
                >

                <label>Titre affiché</label>
                <input
                    type="text"
                    name="stream_titre"
                    placeholder="Stream en direct sur Smash"
                    value="<?= htmlspecialchars($stream_titre) ?>"
                    maxlength="80"
                    style="margin-top: -0.15rem;"
                >

                <label class="checkbox-toggle">
                    <input type="checkbox" name="stream_online" <?= $stream_online ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                    <span class="toggle-label">Le stream est en ligne (affiche le lecteur)</span>
                </label>
            </div>

            <button type="submit" name="save_stream" class="btn btn-save">💾 Enregistrer</button>
        </form>

        <script>
        document.getElementById('stream_auto')?.addEventListener('change', function() {
            const manual = document.getElementById('manual-fields');
            manual.style.opacity = this.checked ? '0.45' : '1';
            manual.style.pointerEvents = this.checked ? 'none' : 'auto';
        });
        </script>

        <div class="admin-tips">
            <h4>🎮 Comment streamer Ryujinx avec OBS</h4>
            <ol>
                <li>Dans OBS : <strong>Paramètres → Flux</strong> → choisir YouTube ou Twitch</li>
                <li>Créer un stream sur <a href="https://studio.youtube.com" target="_blank" rel="noopener">YouTube Studio</a> ou <a href="https://dashboard.twitch.tv" target="_blank" rel="noopener">Twitch Dashboard</a></li>
                <li>Copier la clé de stream dans OBS</li>
                <li>Ajouter une <strong>Capture de fenêtre</strong> sur Ryujinx dans OBS</li>
                <li>Ajouter une <strong>Capture audio de bureau</strong> pour le son du jeu</li>
                <li>Démarrer le stream OBS → coller l'URL ici → cocher "En ligne"</li>
            </ol>
            <p class="tip-note">💡 Pour YouTube : mets le stream en <strong>Non répertorié</strong> si tu ne veux pas qu'il apparaisse publiquement sur ta chaîne.</p>
        </div>
    </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
//fixer hauteur du body à la hauteur de la fenêtre
document.addEventListener('DOMContentLoaded', () => {
  //créer conteneur fixe pour Vanta en arrière-plan
  const vantaBg = document.createElement('div');
  vantaBg.id = 'vanta-bg';
  vantaBg.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 110vw;
    height: 150vh;
    z-index: 2;
    pointer-events: none;
  `;
  document.body.insertBefore(vantaBg, document.body.firstChild);

  // vanta js
    if (typeof VANTA === 'undefined' || typeof VANTA.WAVES === 'undefined') {
        console.warn('VANTA non chargé — vérifie les scripts three.js / vanta.waves.');
    } else {
        window.vantaEffect = VANTA.WAVES({
    el: "#vanta-bg",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 885.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0xf6b26b,
    shininess: 25,
    waveHeight: 25,
    waveSpeed: 0.9,
    zoom: 1
        });
    }
});
// Tenter de démuet l'iframe YouTube après interaction utilisateur
function unmutePlayer() {
    const iframe = document.getElementById('stream-iframe');
    if (!iframe) return;
    // Recharger l'iframe sans &mute=1
    const src = iframe.src.replace('&mute=1', '').replace('muted=true', 'muted=false');
    iframe.src = src;
    document.getElementById('soundReminder').style.display = 'none';
}

// Cacher le rappel son après 8 secondes
setTimeout(() => {
    const r = document.getElementById('soundReminder');
    if (r) r.style.opacity = '0';
    setTimeout(() => { if (r) r.style.display = 'none'; }, 600);
}, 8000);

// ══════════════════════════════════════════
//  POLLING AJAX — mode auto YouTube
//  Vérifie périodiquement si la chaîne passe en
//  live / hors-ligne et met à jour le lecteur
//  sans recharger la page.
// ══════════════════════════════════════════
<?php if ($stream_auto): ?>
(function () {
    let lastVideoId = <?= json_encode($stream_online ? $stream_auto_video_id : null) ?>;
    let lastLive     = <?= $stream_online ? 'true' : 'false' ?>;
    const POLL_INTERVAL = 30000; // 30s, aligné sur le cache serveur

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    function buildLiveHtml(embedUrl, titre) {
        return `<div class="iframe-container">
            <iframe id="stream-iframe" src="${embedUrl}" frameborder="0"
                allow="autoplay; fullscreen; picture-in-picture; web-share; accelerometer; clipboard-write; encrypted-media; gyroscope"
                allowfullscreen title="${escHtml(titre)}"></iframe>
        </div>`;
    }

    function buildOfflineHtml() {
        return `<div class="stream-offline">
            <div class="offline-icon">📴</div>
            <h3>Pas de stream en ce moment</h3>
            <p>L'admin n'est pas en direct pour l'instant. Reviens plus tard !</p>
        </div>`;
    }

    async function checkLive() {
        try {
            const resp = await fetch('stream_smash.php?ajax=check_live');
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.auto) return;

            const wrap  = document.getElementById('stream-player-wrap');
            const badge = document.getElementById('stream-live-badge');
            const titleEl = document.getElementById('stream-title-text');

            if (data.live && data.video_id) {
                // Live actif : si nouvelle vidéo ou on était hors-ligne → on (re)construit le lecteur
                if (!lastLive || data.video_id !== lastVideoId) {
                    wrap.innerHTML = buildLiveHtml(data.embed_url, data.titre || '');
                    if (titleEl && data.titre) titleEl.textContent = data.titre;
                    if (badge) badge.style.display = '';
                    showToastStream('🔴 Le stream vient de démarrer !');
                }
                lastLive     = true;
                lastVideoId  = data.video_id;
            } else {
                // Plus de live
                if (lastLive) {
                    wrap.innerHTML = buildOfflineHtml();
                    if (badge) badge.style.display = 'none';
                    showToastStream('📴 Le stream est terminé.');
                }
                lastLive    = false;
                lastVideoId = null;
            }
        } catch (e) { /* silencieux */ }
    }

    function showToastStream(msg) {
        const old = document.querySelector('.flash-message');
        if (old) old.remove();
        const t = document.createElement('div');
        t.className = 'flash-message success';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 400); }, 3500);
    }

    setInterval(checkLive, POLL_INTERVAL);
})();
<?php endif; ?>
</script>

<style>
/*  Layout page stream  */
.stream-page {
    max-width: 1100px;
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.22);
    padding: 2rem;
    border-radius: 1.5rem;
}

/*  Header  */
.stream-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.stream-header-left { flex: 1; min-width: 0; }

.stream-title {
    font-size: 1.8rem;
    color: #333;
    text-shadow: 2px 2px 4px rgba(255, 107, 107, 0.35);
    margin: 0 0 0.4rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.live-badge {
            margin-top:7px;
    display: inline-flex;
    align-items: center;
    background: #e53935;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    animation: pulse-red 2s infinite;
    flex-shrink: 0;
}

@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(229, 57, 53, 0.5); }
    50%       { box-shadow: 0 0 0 8px rgba(229, 57, 53, 0); }
}

.stream-sub {
    color: #666;
    font-size: 0.95rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.platform-tag {
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
}
.platform-tag.yt { background: rgba(255, 0, 0, 0.12); color: #cc0000; }
.platform-tag.tw { background: rgba(145, 70, 255, 0.12); color: #6441a5; }

.btn-back {
    flex-shrink: 0;
    padding: 0.7rem 1.2rem;
    font-size: 0.9rem;
}

/*  Player wrapper  */
.stream-player-wrap {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 1.2rem;
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}

/* 16:9 responsive */
.iframe-container {
    position: relative;
    padding-top: 56.25%;
    width: 100%;
    background: #000;
}

.iframe-container iframe {
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/*  Offline / erreur  */
.stream-offline {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    color: #555;
}

.offline-icon { font-size: 3.5rem; margin-bottom: 1rem; }
.stream-offline h3 { font-size: 1.5rem; color: #444; margin: 0 0 0.5rem; }
.stream-offline p  { margin: 0.3rem 0; line-height: 1.6; }
.admin-hint { color: #ff6b6b; font-weight: 600; margin-top: 0.8rem !important; }

/*  Rappel son  */
.sound-reminder {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.8rem 1.2rem;
    background: rgba(255, 193, 7, 0.18);
    border-top: 2px solid rgba(255, 193, 7, 0.4);
    font-size: 0.9rem;
    color: #5a4200;
    font-weight: 600;
    transition: opacity 0.6s ease;
    flex-wrap: wrap;
}

.btn-unmute {
    padding: 0.4rem 1rem;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'HSR', sans-serif;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.btn-unmute:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255,107,107,0.4); }

.btn-dismiss {
    background: none;
    border: none;
    color: #888;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.2rem 0.4rem;
    margin-left: auto;
}

/*  Chat Twitch  */
.twitch-chat-wrap {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 1.2rem;
    padding: 1.2rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.twitch-chat-wrap h3 {
    color: #6441a5;
    margin: 0 0 0.8rem;
    font-size: 1.1rem;
}
.twitch-chat-wrap iframe {
    border-radius: 0.8rem;
    display: block;
}

/*  Panneau admin  */
.admin-panel {
    background: rgba(255, 107, 107, 0.06);
    border: 2px dashed rgba(255, 107, 107, 0.35);
    border-radius: 1.2rem;
    padding: 1.5rem;
    margin-top: 1rem;
}

.admin-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.admin-panel-header h3 {
    color: #ff6b6b;
    margin: 0;
    font-size: 1.15rem;
}

.admin-badge {
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    letter-spacing: 0.05em;
}

.admin-form {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1.5rem;
}

.admin-form label {
    font-weight: 700;
    color: #333;
    font-size: 0.9rem;
}

.admin-form label small { font-weight: 400; color: #888; margin-left: 0.4rem; }

.admin-form input[type="url"],
.admin-form input[type="text"] {
    width: 100%;
    padding: 0.8rem;
    margin-bottom: 0.4rem;
    border-radius: 0.8rem;
    border: 2px solid rgba(255, 107, 107, 0.2);
    background: rgba(255, 255, 255, 0.6);
    font-family: 'HSR', sans-serif;
    transition: all 0.3s ease;
    box-sizing: border-box;
    resize: none;
}
.admin-form input:focus {
    outline: none;
    border-color: #ff6b6b;
    background: rgba(255, 255, 255, 0.8);
    transform: scale(1.01);
    box-shadow: 0 0 10px rgba(255, 107, 107, 0.3);
}

/* Toggle checkbox */
.checkbox-toggle {
    display: flex !important;
    align-items: center;
    gap: 0.8rem;
    cursor: pointer;
    font-weight: 600 !important;
    color: #444 !important;
    padding: 0.6rem 0;
}

.checkbox-toggle input { display: none; }

.toggle-track {
    width: 44px;
    height: 24px;
    background: #ccc;
    border-radius: 12px;
    position: relative;
    flex-shrink: 0;
    transition: background 0.3s ease;
}

.toggle-track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.3s ease;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.checkbox-toggle input:checked + .toggle-track { background: #4CAF50; }
.checkbox-toggle input:checked + .toggle-track::after { transform: translateX(20px); }

.btn-save {
    align-self: flex-start;
    padding: 0.75rem 1.8rem;
    font-size: 1rem;
    background: rgba(255, 107, 107, 0.27);
    transition: all ease 0.3s;
}

.btn-save:hover {
    background: rgba(255, 107, 107, 0.58);
    
}
/*  Tips admin  */
.admin-tips {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    padding: 1.2rem 1.5rem;
    border-left: 4px solid #ff6b6b;
}

.admin-tips h4 { color: #ff6b6b; margin: 0 0 0.8rem; font-size: 1rem; }

.admin-tips ol {
    margin: 0;
    padding-left: 1.4rem;
    color: #444;
    font-size: 0.9rem;
    line-height: 1.9;
}

.admin-tips a {
    color: #ff6b6b;
    font-weight: 600;
    text-decoration: none;
}
.admin-tips a:hover { text-decoration: underline; }

.tip-note {
    margin: 0.8rem 0 0;
    font-size: 0.88rem;
    color: #666;
    font-style: italic;
}

/*  Flash messages  */
.flash-msg {
    padding: 0.8rem 1.2rem;
    border-radius: 10px;
    font-weight: 600;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}
.flash-msg.success { background: rgba(76,175,80,0.18); border-left: 4px solid #4CAF50; color: #2e7d32; }
.flash-msg.error   { background: rgba(255,77,77,0.18); border-left: 4px solid #ff4d4d; color: #8b0000; }

/*  Responsive  */
@media (max-width: 640px) {
    .stream-title { font-size: 1.3rem; }
    .stream-page  { padding: 1.2rem; }
    .admin-panel  { padding: 1rem; }
}
</style>
<!-- style vantaEffect -->
<style>
body {
  background: none !important;
  overflow-x: clip;
}

canvas.vanta-canvas {
  position: absolute !important;
  top: 0;
  left: 0;
  width: fit-content;
  height: fit-content;
  z-index: 1 !important;
}
</style>
</body>
</html>
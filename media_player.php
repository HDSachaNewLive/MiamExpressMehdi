<?php
// media_player.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$connected = isset($_SESSION['user_id']);
// ID de la dernière vidéo vue (lu depuis le cookie client)
$lastVideoId = isset($_COOKIE['last_video_id']) ? $_COOKIE['last_video_id'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodHub - NTE OST / Vidéos</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<?php include 'slider_son.php'; ?>

<!-- Lecteur principal -->
<div id="player-wrap">

    <!-- Zone vidéo YouTube plein écran -->
    <div id="yt-container">
        <div id="yt-player"></div>
        <div id="yt-overlay"></div>
    </div>

    <!-- Barre de progression flottante -->
    <div id="ui-layer">
    <div id="progress-bar-wrap">
        <span id="time-current">0:00</span>
        <div id="progress-track">
            <div id="progress-fill"></div>
            <div id="progress-thumb"></div>
        </div>
        <span id="time-total">0:00</span>
    </div>

    <!-- Panneau de contrôle bas — transparent -->
    <div id="control-panel">
        <div id="top-bar">
            <div id="track-info">
                <div id="track-index-badge">01 / 21</div>
                <div id="track-title">Chargement…</div>
                <div id="track-channel"></div>
            </div>
        </div>

        <!-- Boutons centraux -->
        <div id="controls-center">
            <button class="ctrl-btn" id="btn-shuffle" title="Aléatoire (S)">
                <img width="20" height="20" src="https://img.icons8.com/?size=100&id=91479&format=png&color=000000"></img>
            </button>
            <button class="ctrl-btn" id="btn-prev" title="Précédent (P)">
                <img width="20" height="20" src="https://img.icons8.com/?size=100&id=91482&format=png&color=000000"></img>
            </button>
            <button class="ctrl-btn" id="btn-play" title="Lecture/Pause (Espace)">
                <img id="icon-play" width="20" height="20" src="https://img.icons8.com/?size=100&id=59862&format=png&color=000000"></img>
                <img id="icon-pause" width="20" height="20" src="https://img.icons8.com/?size=100&id=61012&format=png&color=000000" style="display:none"></img>
            </button>
            <button class="ctrl-btn" id="btn-next" title="Suivant (N)">
                <img width="20" height="20" src="https://img.icons8.com/?size=100&id=91474&format=png&color=000000"></img>
            </button>
            <button class="ctrl-btn" id="btn-loop" title="Boucle (L)">
                <img width="20" height="20" src="https://img.icons8.com/?size=100&id=91481&format=png&color=000000"></img>
            </button>
        </div>

        <!-- Volume + playlist + plein écran -->
        <div id="controls-right">
            <div id="volume-wrap">
                <button class="ctrl-btn" id="btn-mute" title="Muet">
                    <img id="icon-vol" width="20" height="20" src="https://img.icons8.com/?size=100&id=91646&format=png&color=000000">
                    <img id="icon-mute" width="15" height="15" src="https://img.icons8.com/?size=100&id=643&format=png&color=000000" style="display:none"></img>
                </button>
                <input type="range" id="vol-slider" min="0" max="100" value="100">
            </div>
            <button class="ctrl-btn" id="btn-list" title="Playlist (Tab)">
                <img src="https://img.icons8.com/?size=100&id=113920&format=png&color=000000" width="20" height="20" alt="Playlist"></img>
            </button>
            <button class="ctrl-btn" id="btn-fullscreen" title="Plein écran (F)">
                <img id="icon-fs" width="20" height="20" src="https://img.icons8.com/?size=100&id=38034&format=png&color=000000"></img>
                <img id="icon-fs-exit" width="20" height="20" src="https://img.icons8.com/?size=100&id=38032&format=png&color=000000" style="display:none"></img>
            </button>
        </div>
    </div>
    
</div>
</div>

<!-- Panneau playlist slide-up -->
<div id="playlist-panel">
    <div id="playlist-header">
        <span>Playlist - <span id="pl-count">Musiques et vidéos</span></span>
        <button id="btn-close-list">✕</button>
    </div>
    <div id="playlist-body"></div>
</div>
<div id="playlist-backdrop"></div>

<!-- Toast -->
<div id="toast"></div>

<style>
/* ═══════════════════════════════════════════
   BASE
═══════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    background: transparent;
    font-family: 'HSR', 'Segoe UI', sans-serif;
    color: #fff;
    overflow: hidden;
    height: 100vh;
    width: 100vw;
}

/* ═══════════════════════════════════════════
   PLAYER WRAP — plein écran
═══════════════════════════════════════════ */
#player-wrap {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    z-index: 1;
    background: transparent;
}

/* ═══════════════════════════════════════════
   ZONE YOUTUBE
═══════════════════════════════════════════ */
#yt-container {
    position: relative;
    flex: 1;
    background: url('assets/OST.png') center center / cover no-repeat;
    overflow: hidden;
}

#yt-player {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: transparent;
    opacity: 0.85;
}

#yt-player iframe {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 177.78vh;
    min-width: 100%;
    height: 56.25vw;
    min-height: 100%;
    border: none;
    background: transparent;
}

#yt-overlay {
    position: absolute;
    inset: 0;
    cursor: default;
    z-index: 2;
}

/* ═══════════════════════════════════════════
   TOP BAR — titre flottant transparent
   Position absolute par rapport à #player-wrap
   z-index élevé pour passer au-dessus de yt-overlay
═══════════════════════════════════════════ */
#top-bar {
    position: relative;
    margin-right: auto;
    padding: 12px 16px;
    background: none;
    border-radius: 16px;
    max-width: min(45%, 420px);
    min-width: 0;
    overflow: hidden;
    z-index: 1;
    pointer-events: none;
    opacity: 1;
}

#player-wrap:hover #top-bar,
#player-wrap.show-ui #top-bar {
    opacity: 1;
}

#track-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

#track-index-badge {
    font-size: 0.65rem;
    color: #ffc342;
    letter-spacing: 3px;
    text-transform: uppercase;
    font-weight: 700;
    text-shadow: 0 1px 6px rgba(0,0,0,0.8);
}

#track-title,
#track-channel {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

#track-title {
    font-size: 1.07rem;
    font-weight: 700;
    color: #fff;
    max-width: 100%;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.82);
}

#track-channel {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.55);
    text-shadow: 0 1px 4px rgba(0,0,0,0.7);
}

/* ═══════════════════════════════════════════
   PROGRESS BAR — flottante transparente
═══════════════════════════════════════════ */
#progress-bar-wrap {
    position: absolute;
    bottom: 70px;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 22px;
    height: 26px;
    z-index: 10;
    opacity: 0;
    transition: opacity 0.4s ease;
    background: rgba(225, 225, 225, 0.54);
    backdrop-filter: blur(12px);
}

#time-current, #time-total {
    font-size: 0.72rem;
    color: rgba(255,255,255,0.8);
    min-width: 36px;
    text-align: center;
    text-shadow: 0 1px 4px rgba(0,0,0,0.7);
    font-family: 'HSR';
}

#progress-track {
    flex: 1;
    height: 4px;
    background: rgba(255,255,255,0.25);
    border-radius: 4px;
    position: relative;
    cursor: pointer;
    transition: height 0.2s ease;
}

#progress-track:hover { height: 6px; }

#progress-fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    background: linear-gradient(90deg, #ff6b6b, #ffc342);
    border-radius: 4px;
    width: 0%;
    pointer-events: none;
    transition: width 0.15s linear;
}

#progress-thumb {
    position: absolute;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 13px;
    height: 13px;
    background: #fff;
    border-radius: 50%;
    left: 0%;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
    box-shadow: 0 0 8px rgba(255,107,107,0.9);
    transition: 0.2s ease;
}

#progress-track:hover #progress-thumb { opacity: 1; }

/* ═══════════════════════════════════════════
   CONTROL PANEL — transparent, flottant
═══════════════════════════════════════════ */
#control-panel {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    /* Dégradé sombre depuis le bas — la vidéo reste visible */
    background: rgba(225, 225, 225, 0.54);
    backdrop-filter: blur(12px);
    z-index: 10;
    gap: 12px;
    opacity: 0;
    transition: opacity 0.4s ease;
}

#controls-right {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: flex-end;
}
/* gestion UI layer */
#ui-layer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 140px;
    pointer-events: auto;
}

#player-wrap,
#control-panel,
#progress-bar-wrap {
    transition: opacity 0.4s ease;
}

#control-panel,
#progress-bar-wrap {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.4s ease;
}

#player-wrap.show-ui #control-panel,
#player-wrap.show-ui #progress-bar-wrap {
    opacity: 1;
    pointer-events: auto;
}
/* ═══════════════════════════════════════════
   BOUTONS — style FoodHub
═══════════════════════════════════════════ */
.ctrl-btn {
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    color: rgba(255,255,255,0.88);
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.ctrl-btn svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
    position: relative;
    z-index: 1;
}

/* Effet shimmer FoodHub */
.ctrl-btn::after {
    content: "";
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: rgba(255,255,255,0.18);
    transition: left 0.4s ease;
    border-radius: 12px;
}

.ctrl-btn:hover::after { left: 0; }

.ctrl-btn:hover {
    background: rgba(255, 107, 107, 0.28);
    border-color: rgba(255, 107, 107, 0.55);
    color: #fff;
    transform: translateY(-2px) scale(1.07);
    box-shadow: 0 6px 18px rgba(255, 107, 107, 0.35);
}

.ctrl-btn.active {
    background: rgba(255, 107, 107, 0.35);
    border-color: #ff6b6b;
    color: #ffc342;
    box-shadow: 0 4px 14px rgba(255, 107, 107, 0.4);
}

/* Bouton Play */
#btn-play {
    width: 54px;
    height: 54px;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    border: none;
    color: #fff;
    border-radius: 14px;
    box-shadow: 0 6px 22px rgba(255, 107, 107, 0.55);
    transition: all 0.3s ease;
}

#btn-play::after { display: none; }

#btn-play:hover {
    color: #fff;
    transform: translateY(-3px) scale(1.07);
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.70);
}

#btn-play svg { width: 24px; height: 24px; }

/* ═══════════════════════════════════════════
   CONTRÔLES — groupes
═══════════════════════════════════════════ */
#controls-center {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: center;
}

/* Volume slider style FoodHub */
#volume-wrap { display: flex; align-items: center; gap: 8px; }

#vol-slider {
    -webkit-appearance: none;
    appearance: none;
    width: 90px;
    height: 4px;
    border-radius: 4px;
    background: rgba(255,255,255,0.22);
    outline: none;
    cursor: pointer;
}

#vol-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 14px; height: 14px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    cursor: pointer;
    box-shadow: 0 0 8px rgba(255,107,107,0.65);
    transition: transform 0.2s;
}

#vol-slider::-webkit-slider-thumb:hover { transform: scale(1.25); }

#vol-slider::-moz-range-thumb {
    width: 14px; height: 14px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    cursor: pointer;
    border: none;
}

/* ═══════════════════════════════════════════
   PLAYLIST PANEL
═══════════════════════════════════════════ */
#playlist-panel {
    position: fixed;
    bottom: -100%;
    left: 50%;
    transform: translateX(-50%);
    width: min(580px, 96vw);
    max-height: 60vh;
    background: rgba(235, 235, 235, 0.53);
    backdrop-filter: blur(30px);
    border: 1px solid rgba(160, 160, 160, 0.55);
    border-bottom: none;
    border-radius: 20px 20px 0 0;
    z-index: 1000;
    transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 -8px 35px rgba(0,0,0,0.6);
}

#playlist-panel.open { bottom: 0; }

#playlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px 12px;
    border-bottom: 1px solid rgba(219, 219, 219, 0.15);
    font-weight: 700;
    font-size: 0.95rem;
    color: #171717;
    flex-shrink: 0;
    background: rgba(245, 245, 245, 0.6);
}

#btn-close-list {
    background: rgba(255, 107, 107, 0.15);
    border: 1px solid rgba(255, 107, 107, 0.3);
    color: rgba(255,255,255,0.6);
    width: 30px; height: 30px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s ease;
    font-family: 'HSR', sans-serif;
    top: -1.3px;
}

#btn-close-list:hover {
    background: rgba(255, 107, 107, 0.35);
    color: #ff6b6b;
    transform: scale(1.1);
}

#playlist-body {
    overflow-y: auto;
    flex: 1;
    padding: 8px 0;
}

#playlist-body::-webkit-scrollbar { width: 4px; }
#playlist-body::-webkit-scrollbar-track { background: transparent; }
#playlist-body::-webkit-scrollbar-thumb {
    background: rgba(211, 211, 211, 0.5);
    border-radius: 4px;
}

.pl-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    cursor: pointer;
    transition: all 0.25s ease;
    border-radius: 8px;
    margin: 2px 8px;
}

.pl-item:hover { background: rgba(255, 255, 255, 0.7); }

.pl-item.playing {
    background: rgba(255, 255, 255, 0.29);
    border-left: 3px solid #ffaf5a;
    padding-left: 17px;
}

.pl-num {
    font-size: 0.7rem;
    color: rgba(37, 37, 37, 0.67);
    min-width: 22px;
    text-align: right;
    font-family: 'HSR';
}

.pl-item.playing .pl-num { color: #1b1b1b; font-weight: 700; }

.pl-thumb {
    width: 48px; height: 32px;
    border-radius: 6px;
    object-fit: cover;
    background: rgba(255,255,255,0.06);
    flex-shrink: 0;
    border: 1px solid rgba(187, 187, 187, 0.46);
}

.pl-info { flex: 1; overflow: hidden; }

.pl-title {
    font-size: 0.83rem;
    font-weight: 600;
    color: rgba(27, 27, 27, 0.82);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pl-item.playing .pl-title { color: #080808f7; }

/* EQ animé */
.pl-eq { display: none; width: 18px; flex-shrink: 0; align-items: flex-end; gap: 2px; height: 16px; }
.pl-item.playing .pl-eq { display: flex; }

.eq-bar {
    display: inline-block;
    width: 3px;
    background: linear-gradient(180deg, #ffc342, #ff6b6b);
    border-radius: 2px;
}

@keyframes eq1 { 0%,100%{height:4px} 50%{height:14px} }
@keyframes eq2 { 0%,100%{height:12px} 50%{height:5px} }
@keyframes eq3 { 0%,100%{height:7px} 50%{height:15px} }

.eq-bar:nth-child(1) { animation: eq1 0.7s ease-in-out infinite; height: 8px; }
.eq-bar:nth-child(2) { animation: eq2 0.8s ease-in-out infinite; height: 11px; }
.eq-bar:nth-child(3) { animation: eq3 0.65s ease-in-out infinite; height: 6px; }

/* ═══════════════════════════════════════════
   BACKDROP
═══════════════════════════════════════════ */
#playlist-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(57, 57, 57, 0.55);
    z-index: 999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    backdrop-filter: blur(2px);
}

#playlist-backdrop.open { opacity: 1; pointer-events: all; }

/* ═══════════════════════════════════════════
   TOAST (miamammmm)
═══════════════════════════════════════════ */
#toast {
    position: fixed;
    top: 22px;
    left: 50%;
    transform: translateX(-50%) translateY(-12px);
    background: rgba(236, 236, 236, 0.79);
    backdrop-filter: blur(18px);
    border: 1px solid rgba(172, 172, 172, 0.45);
    color: #0f0f0ff1;
    padding: 10px 22px;
    border-radius: 14px;
    font-size: 0.88rem;
    font-weight: 600;
    opacity: 0;
    transition: all 0.35s ease;
    z-index: 9999;
    pointer-events: none;
    white-space: nowrap;
    box-shadow: 0 6px 22px rgba(222, 222, 222, 0.28);
}

#toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* ═══════════════════════════════════════════
   SIDEBAR au-dessus + masquer volume FoodHub
═══════════════════════════════════════════ */
#sidebar { z-index: 2000 !important; }
#toggleSidebar { z-index: 2001 !important; }
#volume-widget { display: none !important; }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media (max-width: 600px) {
    #track-title { max-width: 200px; font-size: 0.9rem; }
    #vol-slider { width: 60px; }
    #btn-shuffle, #btn-loop { display: none; }
    #control-panel { padding: 0 12px; }
}

/* Cursor global control propre */
#player-wrap {
    cursor: none;
}

/* Dès qu'on interagit avec l'UI */
#ui-layer:hover,
#control-panel:hover,
#progress-bar-wrap:hover,
#playlist-panel:hover,
.ctrl-btn {
    cursor: default;
}
</style>

<script>
// PLAYLIST
const PLAYLIST = [
    { id: 'hfOOdiBCSqk', title: 'NTE - New Herland District OST Extended', channel: '' },
    { id: 'VEiPhLC0ty4', title: 'NTE - Hankaku Street OST Extended', channel: '' },
    { id: 'iZmmkZ_zt3w', title: 'NTE - Croisée des ponts OST Extended', channel: '' },
    { id: 'Xw-xqSPm4tI', title: 'NTE - Galerie Marchande - Ville Chimérique OST Extended', channel: '' },
    { id: 'td1tBIaoRn0', title: 'NTE - Bureau of Anomaly Control OST Extended', channel: '' },
    { id: 'RPhuB3nuxI8', title: 'NTE - Ville Chimérique OST Extended', channel: '' },
    { id: 'kWJnDdnslbs', title: 'NTE - Miguel District OST Extended', channel: '' },
    { id: 'kYlTjwXdvmo', title: 'NTE - Avenue de Saint-Torres OST Extended', channel: '' },
    { id: 'tgHTfObs_pM', title: 'NTE - Galerie Marchande - Miguel District OST', channel: '' },
    { id: '4vkNQD0wldI', title: 'NTE - New Herland District OST Extended (Variation)', channel: '' },
    { id: 'DupFpj51h-E', title: 'NTE - 2-Four Mart OST Extended', channel: '' },
    { id: 'RQ5BAz41NkY', title: 'NTE - New Herland Harbor OST Extended', channel: '' },
    { id: '453oGV6x1Oo', title: 'NTE - 9°C Coffee OST Extended', channel: '' },
    { id: 'lFdfoSte6fI', title: 'NTE - Hankaku Street (Night) OST Extended', channel: '' },
    { id: 'YtnNAePTuvU', title: 'NTE - Vielle Ville - Croisée des ponts - Pente Verte OST Extended', channel: '' },
    { id: 'jNf8FH0SZ4Q', title: 'NTE - New Herland (Pluie) OST Extended', channel: '' },
    { id: '3jxAlIlzBiU', title: 'NTE - Orichalcos Imaginist Stadium OST Extended', channel: '' },
    { id: 'LeZdIw-hRNU', title: 'NTE - New Herland (Nuit) OST Extended', channel: '' },
    { id: '0RKJPEXLcHg', title: 'NTE - Beach OST Extended', channel: '' },
    { id: 'hFbKPmJ0APs', title: 'NTE - Le Kapé OST Extended', channel: '' },
    { id: 'TJiM6XFBcqI', title: 'NTE - Tunnel de Nautili OST Extended', channel: '' },
    { id: '0zU7lsE4xSU', title: 'C Falcon DÉTRUIT Ike', channel: 'BOTCH Shorts (Fan)' },
    { id: 'TKAl53CeeGk', title: "POV Jallon à la plage pendant l'étude de cas au lieu de nous surveiller comme prévu - PARTIE 2", channel: 'BOTCH Shorts (Fan)' },
    { id: 'dKN7ZU9SWBU', title: "POV Jallon chez lui pendant l'étude de cas au lieu de nous surveiller comme prévu", channel: 'BOTCH Shorts (Fan)' },
    { id: 'uAxvGahklEY', title: 'NTE | BIGMACBOURDON meme Extended | 10 Min Perfect Loop', channel: 'BOTCH Shorts (Fan)' },
    { id: 'okkUIg3OBZU', title: '20251119 125928', channel: 'BOTCH Shorts (Fan)' },
    { id: 'YGPijPJRpx0', title: 'Yanis et axel mettent restau sur mon site foodhub', channel: 'BOTCH Shorts (Fan)' },
    { id: 'dFXU_8KOxDU', title: 'ils ont fait de la D avec mon cv 🥀😭🔥🔥🙏', channel: 'BOTCH Shorts (Fan)' }, 
    { id: 'h98em4eRLik', title: 'Une journée à Hethereau (feat. JLN et BMB) - Collab NTE', channel: 'BOTCH Shorts (Fan)' },
    { id: 'o_Q48TJbEzA', title: 'DiepSQL - Présentation de Projet BTS SIO SLAM', channel: 'BOTCH Shorts (Fan)' },
];

// Index initial déterminé par l'ID stocké en cookie (si présent)
let initialVideoId = <?php echo json_encode($lastVideoId); ?>;
let initialIndex = 0;
if (initialVideoId) {
    const _idx = PLAYLIST.findIndex(p => p.id === initialVideoId);
    initialIndex = _idx >= 0 ? _idx : 0;
}

// ══════════════════════════════════════════
//  ÉTAT
// ══════════════════════════════════════════
let player        = null;
let currentIdx    = 0;
let isPlaying     = false;
let isShuffle     = false;
let loopMode      = 0; // 0=off 1=playlist 2=piste
let isMuted       = false;
let prevVol       = 100;
let progressRAF   = null;
let shuffledOrder = [];
let shufflePos    = 0;
let isFullscreen  = false;
let playlistOpen  = false;
let uiVisible = true;

const wrap = document.getElementById('player-wrap');

// ══════════════════════════════════════════
//  YOUTUBE IFrame API
// ══════════════════════════════════════════
function onYouTubeIframeAPIReady() {
    player = new YT.Player('yt-player', {
        height: '100%',
        width: '100%',
        videoId: '',
        playerVars: {
            autoplay: 0,
            controls: 0,
            disablekb: 1,
            iv_load_policy: 3,
            modestbranding: 1,
            rel: 0,
            showinfo: 0,
            fs: 0,
            playsinline: 1,
            origin: window.location.origin,
        },
        events: {
            onReady: onPlayerReady,
            onStateChange: onStateChange,
        }
    });
}

function onPlayerReady(e) {
    player.setVolume(100);
    buildPlaylist();
    loadTrack(initialIndex, false);

    wrap.classList.add('playing');

    // 👇 force UI visible au chargement
    wrap.classList.add('show-ui');
    uiVisible = true;

    progressLoop();
}

function onStateChange(e) {
    const S = YT.PlayerState;

    if (e.data === S.PLAYING) {
        isPlaying = true;
        wrap.classList.add('playing');
        updatePlayBtn();
        updateTrackInfo();
    } 
    else if (e.data === S.PAUSED) {
        isPlaying = false;
        wrap.classList.remove('playing');
        updatePlayBtn();
    } 
    else if (e.data === S.ENDED) {
        handleTrackEnd();
    } 
    else if (e.data === S.BUFFERING) {
        updateTrackInfo();
    }
    if (e.data === S.PLAYING) {
    isPlaying = true;
    updateUIVisibility();
}

if (e.data === S.PAUSED) {
    isPlaying = false;
    updateUIVisibility();
}
}

// ══════════════════════════════════════════
//  NAVIGATION
// ══════════════════════════════════════════
function loadTrack(idx, autoplay = true) {
    currentIdx = idx;
    const vid = PLAYLIST[idx];
    // sauvegarde de l'ID courant dans un cookie pour s'en souvenir au rechargement
    try { document.cookie = 'last_video_id=' + encodeURIComponent(vid.id) + '; path=/; max-age=' + (60*60*24*30); } catch(e) {}
    if (autoplay) player.loadVideoById(vid.id);
    else player.cueVideoById(vid.id);
    updateTrackInfo();
    updatePlaylistHighlight();
    updateBadge();
}

function handleTrackEnd() {
    if (loopMode === 2) { player.seekTo(0); player.playVideo(); return; }
    nextTrack();
    if (loopMode === 0 && currentIdx === 0 && !isShuffle) {
        player.pauseVideo();
    }
}

function nextTrack() {
    if (isShuffle) {
        shufflePos = (shufflePos + 1) % shuffledOrder.length;
        loadTrack(shuffledOrder[shufflePos]);
    } else {
        const next = (currentIdx + 1) % PLAYLIST.length;
        if (loopMode === 0 && next === 0) {
            loadTrack(0, false);
            isPlaying = false;
            updatePlayBtn();
        } else {
            loadTrack(next);
        }
    }
}

function prevTrack() {
    if (player.getCurrentTime() > 4) { player.seekTo(0); return; }
    if (isShuffle) {
        shufflePos = (shufflePos - 1 + shuffledOrder.length) % shuffledOrder.length;
        loadTrack(shuffledOrder[shufflePos]);
    } else {
        loadTrack((currentIdx - 1 + PLAYLIST.length) % PLAYLIST.length);
    }
}

function toggleShuffle() {
    isShuffle = !isShuffle;
    if (isShuffle) {
        shuffledOrder = [...Array(PLAYLIST.length).keys()].sort(() => Math.random() - 0.5);
        shufflePos = shuffledOrder.indexOf(currentIdx);
    }
    document.getElementById('btn-shuffle').classList.toggle('active', isShuffle);
    showToast(isShuffle ? '🔀 Aléatoire activé' : '🔀 Aléatoire désactivé');
}

function cycleLoop() {
    loopMode = (loopMode + 1) % 3;
    const btn = document.getElementById('btn-loop');
    btn.classList.toggle('active', loopMode > 0);
    const msgs = ['🔁 Boucle désactivée', '🔁 Playlist en boucle', '🔂 Piste en boucle'];
    showToast(msgs[loopMode]);
    btn.innerHTML = loopMode === 2
        ? `<img width="20" height="20" src="https://img.icons8.com/?size=100&id=91477&format=png&color=000000"></img>`
        : `<img width="20" height="20" src="https://img.icons8.com/?size=100&id=91481&format=png&color=000000"></img>`;
}

// ══════════════════════════════════════════
//  UI UPDATES
// ══════════════════════════════════════════
function updatePlayBtn() {
    document.getElementById('icon-play').style.display  = isPlaying ? 'none' : 'block';
    document.getElementById('icon-pause').style.display = isPlaying ? 'block' : 'none';
}

function updateTrackInfo() {
    try {
        const data = player.getVideoData();

        const fallback = PLAYLIST[currentIdx];

        const title = data?.title || fallback.title;
        const author = data?.author || fallback.channel || '';

        document.getElementById('track-title').textContent = title;
        document.getElementById('track-channel').textContent = author;

        PLAYLIST[currentIdx].title = title;
        PLAYLIST[currentIdx].channel = author;

        const item = document.querySelector(`.pl-item[data-idx="${currentIdx}"]`);
        if (item) item.querySelector('.pl-title').textContent = title;

    } catch(e) {}
}

function updateBadge() {
    const n = String(currentIdx + 1).padStart(2, '0');
    document.getElementById('track-index-badge').textContent = `${n} / ${PLAYLIST.length}`;
}

function updatePlaylistHighlight() {
    document.querySelectorAll('.pl-item').forEach(el => {
        el.classList.toggle('playing', parseInt(el.dataset.idx) === currentIdx);
    });
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        wrap.classList.remove('show-ui');
    }
});

// ══════════════════════════════════════════
//  PROGRESS BAR
// ══════════════════════════════════════════
function progressLoop() {
    tickProgress();
    progressRAF = requestAnimationFrame(progressLoop);
}

function startProgressTimer() {
    stopProgressTimer();
    progressLoop();
}

function stopProgressTimer() {
    if (progressRAF) {
        cancelAnimationFrame(progressRAF);
        progressRAF = null;
    }
}

function tickProgress() {
    try {
        const cur = player.getCurrentTime() || 0;
        const dur = player.getDuration() || 1;
        const pct = (cur / dur) * 100;
        document.getElementById('progress-fill').style.width = pct + '%';
        document.getElementById('progress-thumb').style.left = pct + '%';
        document.getElementById('time-current').textContent  = fmtTime(cur);
        document.getElementById('time-total').textContent    = fmtTime(dur);
    } catch(e) {}
}

function fmtTime(s) {
    s = Math.floor(s);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

document.getElementById('progress-track').addEventListener('click', e => {
    if (!player) return;
    const rect = e.currentTarget.getBoundingClientRect();
    player.seekTo(((e.clientX - rect.left) / rect.width) * player.getDuration(), true);
});

// ══════════════════════════════════════════
//  PLAYLIST PANEL
// ══════════════════════════════════════════
function buildPlaylist() {
    const body = document.getElementById('playlist-body');
    body.innerHTML = '';
    PLAYLIST.forEach((v, i) => {
        const div = document.createElement('div');
        div.className = 'pl-item' + (i === currentIdx ? ' playing' : '');
        div.dataset.idx = i;
        div.innerHTML = `
            <span class="pl-num">${String(i + 1).padStart(2, '0')}</span>
            <img class="pl-thumb" src="https://img.youtube.com/vi/${v.id}/default.jpg" alt="">
            <div class="pl-info"><div class="pl-title">${v.title}</div></div>
            <div class="pl-eq">
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
            </div>
        `;
        div.addEventListener('click', () => { closePlaylist(); loadTrack(i); });
        body.appendChild(div);
    });
}

function openPlaylist() {
    playlistOpen = true;
    document.getElementById('playlist-panel').classList.add('open');
    document.getElementById('playlist-backdrop').classList.add('open');
    const active = document.querySelector('.pl-item.playing');
    if (active) active.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function closePlaylist() {
    playlistOpen = false;
    document.getElementById('playlist-panel').classList.remove('open');
    document.getElementById('playlist-backdrop').classList.remove('open');
}

document.getElementById('btn-list').addEventListener('click', () => playlistOpen ? closePlaylist() : openPlaylist());
document.getElementById('btn-close-list').addEventListener('click', closePlaylist);
document.getElementById('playlist-backdrop').addEventListener('click', closePlaylist);

// ══════════════════════════════════════════
//  VOLUME
// ══════════════════════════════════════════
document.getElementById('vol-slider').addEventListener('input', e => {
    const v = parseInt(e.target.value);
    if (player) player.setVolume(v);
    isMuted = (v === 0);
    updateMuteIcon();
});

document.getElementById('btn-mute').addEventListener('click', () => {
    if (!player) return;
    isMuted = !isMuted;
    if (isMuted) {
        prevVol = player.getVolume();
        player.setVolume(0);
        document.getElementById('vol-slider').value = 0;
    } else {
        player.setVolume(prevVol);
        document.getElementById('vol-slider').value = prevVol;
    }
    updateMuteIcon();
});

function updateMuteIcon() {
    document.getElementById('icon-vol').style.display  = isMuted ? 'none' : 'block';
    document.getElementById('icon-mute').style.display = isMuted ? 'block' : 'none';
}

// ══════════════════════════════════════════
//  BOUTONS PRINCIPAUX
// ══════════════════════════════════════════
document.getElementById('btn-play').addEventListener('click', () => {
    if (!player) return;
    isPlaying ? player.pauseVideo() : player.playVideo();
});
document.getElementById('btn-prev').addEventListener('click', prevTrack);
document.getElementById('btn-next').addEventListener('click', nextTrack);
document.getElementById('btn-shuffle').addEventListener('click', toggleShuffle);
document.getElementById('btn-loop').addEventListener('click', cycleLoop);

// ══════════════════════════════════════════
//  PLEIN ÉCRAN
// ══════════════════════════════════════════
document.getElementById('btn-fullscreen').addEventListener('click', toggleFullscreen);

function toggleFullscreen() {
    const el = document.getElementById('player-wrap');
    if (!document.fullscreenElement) el.requestFullscreen().catch(() => {});
    else document.exitFullscreen();
}

document.addEventListener('fullscreenchange', () => {
    isFullscreen = !!document.fullscreenElement;
    document.getElementById('icon-fs').style.display      = isFullscreen ? 'none' : 'block';
    document.getElementById('icon-fs-exit').style.display = isFullscreen ? 'block' : 'none';
});

// ══════════════════════════════════════════
//  AFFICHAGE UI AU SURVOL / INACTIVITÉ
//  Les contrôles apparaissent au survol et
//  disparaissent après 0.4s d'inactivité.
// ══════════════════════════════════════════

function updateUIVisibility() {
    const wrap = document.getElementById('player-wrap');

    if (!isPlaying) {
        // si pause → UI toujours visible
        uiVisible = true;
        wrap.classList.add('show-ui');
        return;
    }

    // si en lecture → dépend de la souris
    if (uiVisible) {
        wrap.classList.add('show-ui');
    } else {
        wrap.classList.remove('show-ui');
    }
}

const uiLayer = document.getElementById('ui-layer');

uiLayer.addEventListener('mouseenter', () => {
    uiVisible = true;
    updateUIVisibility();
});

uiLayer.addEventListener('mouseleave', () => {
    if (isPlaying) {
        uiVisible = false;
        updateUIVisibility();
    }
});
// ══════════════════════════════════════════
//  RACCOURCIS CLAVIER
// ══════════════════════════════════════════
document.addEventListener('keydown', e => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    showUI();
    switch (e.code) {
        case 'Space':
            e.preventDefault();
            isPlaying ? player.pauseVideo() : player.playVideo();
            break;
        case 'ArrowLeft':
            if (player) player.seekTo(Math.max(0, player.getCurrentTime() - 10), true);
            break;
        case 'ArrowRight':
            if (player) player.seekTo(player.getCurrentTime() + 10, true);
            break;
        case 'ArrowUp':
            e.preventDefault();
            if (player) {
                const v = Math.min(100, player.getVolume() + 10);
                player.setVolume(v);
                document.getElementById('vol-slider').value = v;
            }
            break;
        case 'ArrowDown':
            e.preventDefault();
            if (player) {
                const v = Math.max(0, player.getVolume() - 10);
                player.setVolume(v);
                document.getElementById('vol-slider').value = v;
            }
            break;
        case 'KeyN': nextTrack(); break;
        case 'KeyP': prevTrack(); break;
        case 'KeyS': toggleShuffle(); break;
        case 'KeyL': cycleLoop(); break;
        case 'KeyF': toggleFullscreen(); break;
        case 'Tab':
            e.preventDefault();
            playlistOpen ? closePlaylist() : openPlaylist();
            break;
        case 'Escape': closePlaylist(); break;
    }
});

// ══════════════════════════════════════════
//  TOAST
// ══════════════════════════════════════════
let toastTimer = null;
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2200);
}

window.addEventListener('beforeunload', () => {
    if (player && typeof player.destroy === 'function') {
        player.destroy();
    }
});
</script>

<!-- YouTube IFrame API -->
<script src="https://www.youtube.com/iframe_api"></script>

</body>
</html>
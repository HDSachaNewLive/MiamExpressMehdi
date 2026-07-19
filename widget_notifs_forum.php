<?php
// Vérifier la préférence en base avant le rendu pour éviter tout flash
$_widget_notif_actif = false;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $stmt_pref = $conn->prepare("SELECT notif_forum_actif FROM user_preferences WHERE user_id = ? LIMIT 1");
    $stmt_pref->execute([(int) $_SESSION['user_id']]);
    $pref_row = $stmt_pref->fetch();
    $_widget_notif_actif = $pref_row && $pref_row['notif_forum_actif'];
}
?>
<div id="widget-notifs-forum" class="widget-minimise<?= !$_widget_notif_actif ? ' widget-hidden' : '' ?>">
    <!-- Widget notifications forum -->
    <div id="entete-notifs-forum">
        <div style="display: flex; align-items: center; gap: 0.6rem; flex: 1;">
            <span>💬 Forum</span>
            <span id="badge-notifs-forum" style="display:none;">0</span>
        </div>
        <button id="btn-toggle-notifs-forum" type="button" title="Minimiser/Maximiser"
            style="background: none; border: none; cursor: pointer; font-size: 1.1rem; color: #f35959; padding: 0; transition: transform 0.3s ease;">
            ▼
        </button>
    </div>
    <div id="liste-notifs-forum" style="display:none;">
        <p class="notif-forum-vide">Aucune notification</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const PAGE_COURANTE = window.location.pathname.split('/').pop();
        const PAGES_EXCLUES = ['vendor_edit_restaurant.php', 'media_player.php'];
        if (PAGES_EXCLUES.includes(PAGE_COURANTE)) return;

        const INTERVALLE_MS = 5000;
        const badge = document.getElementById('badge-notifs-forum');
        const liste = document.getElementById('liste-notifs-forum');
        const btn_toggle = document.getElementById('btn-toggle-notifs-forum');
        const widget = document.getElementById('widget-notifs-forum');

        if (!badge || !liste || !btn_toggle || !widget) return;

        let dernier_notif_id = 0;
        let timer_polling = null;
        let actif = true;
        let widget_minimise = true;

        // État initial : minimisé, liste cachée, bouton cohérent
        widget.classList.add('widget-minimise');
        liste.style.display = 'none';
        btn_toggle.textContent = '▲';
        btn_toggle.title = 'Afficher les notifs';

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        function extraire_topic_id() {
            const page = window.location.pathname.split('/').pop();
            if (page !== 'forum_topic.php') return null;
            const params = new URLSearchParams(window.location.search);
            const topic_id = params.get('topic_id');
            return topic_id ? parseInt(topic_id) : null;
        }

        function construire_notif_html(n) {
            const lien = `forum_topic.php?topic_id=${n.topic_id}`;
            const non_lue_class = n.is_read == 0 ? 'notif-forum-non-lue' : '';
            const sous_titre = n.is_reply == 1
                ? `↩ a répondu à ton message dans`
                : `a posté dans`;
            return `
            <a href="${lien}" class="notif-forum-item ${non_lue_class}"
            onclick="marquer_notif_lue(${n.topic_id})">
                <div class="notif-forum-corps">
                    <span class="notif-forum-auteur">${escapeHtml(n.auteur_nom)}</span>
                    <span class="notif-forum-sous-titre">${sous_titre}</span>
                    <span class="notif-forum-titre">${escapeHtml(n.topic_titre)}</span>
                </div>
                <span class="notif-forum-date">${escapeHtml(n.date_formatee)}</span>
            </a>
        `;
        }

        function rafraichir_liste(notifs, nb_non_lues) {
            if (!notifs || notifs.length === 0) {
                liste.innerHTML = '<p class="notif-forum-vide">Aucune notification</p>';
                badge.style.display = 'none';
                return;
            }

            const topic_actuel = extraire_topic_id();
            const notifs_filtrees = topic_actuel
                ? notifs.filter(n => parseInt(n.topic_id) !== topic_actuel)
                : notifs;
            const notifs_affichees = notifs_filtrees.slice(0, 30);

            if (notifs_affichees.length === 0) {
                liste.innerHTML = '<p class="notif-forum-vide">Aucune notification</p>';
            } else {
                liste.innerHTML = notifs_affichees.map(construire_notif_html).join('');
            }

            let badge_count = nb_non_lues;
            if (topic_actuel && notifs && Array.isArray(notifs)) {
                const notifs_topic_actuel = notifs.filter(n => parseInt(n.topic_id) === topic_actuel && n.is_read == 0).length;
                badge_count = nb_non_lues - notifs_topic_actuel;
            }

            if (badge_count > 0) {
                badge.textContent = badge_count > 99 ? '99+' : badge_count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }

        async function faire_polling() {
            if (!actif) return;
            try {
                const resp = await fetch('get_forum_notifs.php');
                if (!resp.ok) return;
                const data = await resp.json();

                // Si désactivé en BDD, cacher le widget entièrement
                if (data.desactive) {
                    widget.classList.add('widget-hidden');
                    return;
                }

                // Si réactivé, afficher le widget
                widget.classList.remove('widget-hidden');

                if (data.erreur) return;

                rafraichir_liste(data.notifs, data.nb_non_lues);

                if (data.notifs && data.notifs.length > 0) {
                    const nouveau_id = parseInt(data.notifs[0].notif_id);
                    if (dernier_notif_id && nouveau_id > dernier_notif_id && data.nb_non_lues > 0) {
                        try {
                            const son = new Audio('https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/confirm.wav');
                            son.volume = 0.35;
                            son.play().catch(() => { });
                        } catch (e) { }
                        badge.classList.remove('badge-pulse');
                        void badge.offsetWidth;
                        badge.classList.add('badge-pulse');
                    }
                    dernier_notif_id = nouveau_id;
                }
            } catch (e) { }
            timer_polling = setTimeout(faire_polling, INTERVALLE_MS);
        }

        //  Toggle minimisation (animée)
        btn_toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            widget_minimise = !widget_minimise;

            liste.classList.remove('anim-ouverture', 'anim-fermeture');

            if (widget_minimise) {
                //  Fermeture 
                widget.classList.add('widget-minimise');
                btn_toggle.textContent = '▲';
                btn_toggle.title = 'Afficher les notifs';

                liste.style.display = 'block'; // reste visible le temps de l'anim
                void liste.offsetWidth; // force le reflow pour relancer l'animation
                liste.classList.add('anim-fermeture');

                liste.addEventListener('animationend', function handler() {
                    liste.style.display = 'none';
                    liste.classList.remove('anim-fermeture');
                    liste.removeEventListener('animationend', handler);
                });
            } else {
                //  Ouverture 
                widget.classList.remove('widget-minimise');
                btn_toggle.textContent = '▼';
                btn_toggle.title = 'Minimiser les notifs';

                liste.style.display = 'block';
                void liste.offsetWidth;
                liste.classList.add('anim-ouverture');

                liste.addEventListener('animationend', function handler() {
                    liste.classList.remove('anim-ouverture');
                    liste.removeEventListener('animationend', handler);
                });
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                actif = false;
                clearTimeout(timer_polling);
            } else {
                actif = true;
                faire_polling();
            }
        });

        window.marquer_notif_lue = function (topic_id) {
            const fd = new FormData();
            fd.append('topic_id', topic_id);
            const csrfEl = document.querySelector('input[name="csrf_token"]');
            if (csrfEl) fd.append('csrf_token', csrfEl.value);
            fetch('marquer_notifs_forum_lues.php', { method: 'POST', body: fd }).catch(() => { });
            document.querySelectorAll(`.notif-forum-item[href="forum_topic.php?topic_id=${topic_id}"]`)
                .forEach(el => el.classList.remove('notif-forum-non-lue'));
        };

        faire_polling();
    });
</script>

<style>
    /*  Widget conteneur toujours visible ─ */
    /* Cache complètement le widget si désactivé */
    #widget-notifs-forum.widget-hidden {
        display: none !important;
    }
   
    #widget-notifs-forum {
        position: fixed;
        top: 22px;
        right: 280px;
        z-index: 99999;
        border-radius: 2rem;
        font-family: 'HSR', sans-serif;
        width: 340px;
        background: rgba(255, 255, 255, 0.10);
        backdrop-filter: blur(25px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        animation: slideInForum 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                    background 0.3s ease,
                    box-shadow 0.3s ease;
    }

    #widget-notifs-forum:hover {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.3);
        box-shadow: 0 16px 50px rgba(0, 0, 0, 0.15);
    }

    @keyframes slideInForum {
        from {
            opacity: 0;
            transform: translateY(-25px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Responsive pour petits écrans */
    @media (max-width: 1200px) {
        #widget-notifs-forum {
            right: 20px;
            width: 300px;
        }
        #widget-notifs-forum.widget-minimise {
            width: 190px;
        }
    }

    @media (max-width: 768px) {
        #widget-notifs-forum {
            top: auto;
            bottom: 90px;
            right: 10px;
            width: 280px;
        }
        #widget-notifs-forum.widget-minimise {
            width: 190px;
        }
    }

    /* État minimisé */
    #widget-notifs-forum.widget-minimise {
        width: 190px;
    }

    /*  En-tête du widget  */
    #entete-notifs-forum {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.2rem;
        background: linear-gradient(135deg, rgba(228, 228, 228, 0.15), rgba(216, 77, 77, 0.1));
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        font-size: 0.95rem;
        font-weight: 700;
        color: rgba(0, 0, 0, 0.7);
        letter-spacing: 0.3px;
    }

    #btn-toggle-notifs-forum.rotate-ouvert {
        transform: rotate(180deg);
    }

    #btn-toggle-notifs-forum {
        color: #ff6b6b;
    }

    #btn-toggle-notifs-forum:hover,
    #btn-toggle-notifs-forum:focus {
        box-shadow: none !important;
        outline: none;
    }

    /*  Badge nombre de notifs  */
    #badge-notifs-forum {
        min-width: 24px;
        height: 24px;
        background: linear-gradient(135deg, #ff6b6b, #ff8c42);
        color: white;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        box-shadow: 0 4px 12px rgba(255, 107, 107, 0.35);
    }

    @keyframes badge-pulse {
        0% {
            transform: scale(1);
        }

        40% {
            transform: scale(1.35);
        }

        70% {
            transform: scale(0.95);
        }

        100% {
            transform: scale(1);
        }
    }

    #badge-notifs-forum.badge-pulse {
        animation: badge-pulse 0.5s ease;
    }

    /*  Liste des notifs avec scroll  */
    #liste-notifs-forum {
        max-height: 420px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.2rem 0;
        scroll-behavior: smooth;
    }

    #liste-notifs-forum::-webkit-scrollbar {
        width: 5px;
    }

    #liste-notifs-forum::-webkit-scrollbar-track {
        background: transparent;
    }

    #liste-notifs-forum::-webkit-scrollbar-thumb {
        background: rgba(212, 212, 212, 0.4);
        border-radius: 5px;
        transition: background 0.2s ease;
    }

    #liste-notifs-forum::-webkit-scrollbar-thumb:hover {
        background: rgba(224, 224, 224, 0.6);
    }

    /* ── Animations d'ouverture / fermeture ─────────────────── */
    @keyframes notifForumOuverture {
        from {
            max-height: 0;
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            max-height: 420px;
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes notifForumFermeture {
        from {
            max-height: 420px;
            opacity: 1;
            transform: translateY(0);
        }
        to {
            max-height: 0;
            opacity: 0;
            transform: translateY(-8px);
        }
    }

    #liste-notifs-forum.anim-ouverture {
        animation: notifForumOuverture 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        overflow: hidden;
    }

    #liste-notifs-forum.anim-fermeture {
        animation: notifForumFermeture 0.28s ease forwards;
        overflow: hidden;
    }

    /*  Item notif ─ */
    .notif-forum-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.7rem;
        padding: 0.85rem 1.2rem;
        text-decoration: none;
        color: #333;
        transition: all 0.25s ease;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        cursor: pointer;
        position: relative;
    }

    .notif-forum-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 0;
        background: rgba(124, 198, 230, 0.08);
        transition: width 0.25s ease;
        pointer-events: none;
    }

    .notif-forum-item:hover {
        background: rgba(124, 198, 230, 0.08);
    }

    .notif-forum-item:hover::before {
        width: 3px;
    }

    .notif-forum-item:last-child {
        border-bottom: none;
    }

    /* Pastille bleue = non lue */
    .notif-forum-non-lue {
        background: rgba(204, 204, 204, 0.12);
        border-left: 3px solid #f35959;
    }

    .notif-forum-non-lue:hover {
        background: rgba(255, 255, 255, 0.18);
    }

    .notif-forum-corps {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        flex: 1;
        min-width: 0;
    }

    .notif-forum-auteur {
        font-size: 0.80rem;
        font-weight: 700;
        color: #f35959;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .notif-forum-titre {
        font-size: 0.85rem;
        color: #222;
        line-height: 1.35;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        font-weight: 600;
    }
    .notif-forum-sous-titre {
        font-size: 0.72rem;
        color: #999;
        font-weight: 400;
        font-style: italic;
    }
    
    .notif-forum-date {
        font-size: 0.73rem;
        color: #bbb;
        white-space: nowrap;
        flex-shrink: 0;
        padding-top: 3px;
    }

    /* Message vide */
    .notif-forum-vide {
        text-align: center;
        color: #ccc;
        font-size: 0.87rem;
        padding: 2rem 1.2rem;
        margin: 0;
        font-weight: 500;
    }
</style>
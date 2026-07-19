<?php
// approvisionnement.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Récupérer le solde actuel
$stmt = $conn->prepare("SELECT solde, nom_user FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
$solde_actuel = $user['solde'] ?? 0;

// Historique combiné : approvisionnements + cadeaux envoyés/reçus
// (la fonctionnalité cadeaux arrive plus tard, mais la table existe déjà donc l'historique est prêt)
$stmt = $conn->prepare("
    (SELECT 'approvisionnement' AS type_transaction, montant, date_approvisionnement AS date_transaction,
            statut, NULL AS autre_user_id, carte_masquee
     FROM approvisionnements WHERE user_id = ?)
    UNION ALL
    (SELECT 'cadeau_envoye', montant, date_envoi, 'validé', destinataire_id, NULL
     FROM cadeaux WHERE expediteur_id = ?)
    UNION ALL
    (SELECT 'cadeau_recu', montant, date_envoi, 'validé', expediteur_id, NULL
     FROM cadeaux WHERE destinataire_id = ?)
    ORDER BY date_transaction DESC
    LIMIT 15
");
$stmt->execute([$uid, $uid, $uid]);
$historique = $stmt->fetchAll();

// Résoudre le nom de l'autre utilisateur pour les lignes de cadeaux
foreach ($historique as &$h) {
    if ($h['autre_user_id']) {
        $stmtNom = $conn->prepare("SELECT nom_user FROM users WHERE user_id = ?");
        $stmtNom->execute([$h['autre_user_id']]);
        $h['autre_user_nom'] = $stmtNom->fetchColumn();
    }
}
unset($h);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Approvisionner mon compte - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php include 'sidebar.php'; ?>
</head>
<body>
    <audio id="player" autoplay loop>
        <source src="assets/Account Settings Wii U System Music.mp3" type="audio/mpeg">
    </audio>
    <?php include "slider_son.php"; ?>

    <style>
        #volume-slider { background: linear-gradient(135deg, #4caf50, #66bb6a); }
        #volume-button { background: linear-gradient(135deg, #4caf50, #66bb6a); }
    </style>

    <main class="container">
        <h2 style="text-align: center;">💰 Mon portefeuille FoodHub</h2>

        <div id="msg-zone"></div>

        <!-- Solde — compact, dégradé façon 404.php -->
        <div class="solde-card">
            <span class="solde-label">Solde actuel</span>
            <div class="solde-amount-wrap">
                <div class="solde-amount-viewport" id="solde-affiche" data-value="<?= number_format($solde_actuel, 2, '.', '') ?>">
                    <span class="solde-amount"><?= number_format($solde_actuel, 2, '.', '') ?></span>
                </div>
                <span class="solde-devise">€</span>
            </div>
        </div>

        <!-- Sélecteur d'action : un seul panneau ouvert à la fois -->
        <div class="action-selector">
            <button type="button" class="action-choice" data-panel="appro" id="choice-appro">
                <span class="action-choice-icon">💳</span>
                <span class="action-choice-text">
                    <strong>Ajouter de l'argent</strong>
                    <small>Recharge ton solde par carte (simulée)</small>
                </span>
                <span class="action-choice-chevron">›</span>
            </button>
            <button type="button" class="action-choice" data-panel="cadeau" id="choice-cadeau">
                <span class="action-choice-icon">🎁</span>
                <span class="action-choice-text">
                    <strong>Envoyer un cadeau</strong>
                    <small>Partage une partie de ton solde</small>
                </span>
                <span class="action-choice-chevron">›</span>
            </button>
        </div>

        <!-- Panneau : Ajouter de l'argent -->
        <div class="action-panel" id="panel-appro" hidden>
            <form id="approvisionnement-form" class="form-appro">
                <?= fh_csrf_field() ?>

                <div class="form-group">
                    <label>Montant à ajouter</label>
                    <input type="number" id="montant-input" name="montant" min="5" max="500" step="0.01" required placeholder="Min 5€ – Max 500€">
                    <div class="amount-buttons">
                        <button type="button" class="amount-btn" data-amount="10">10 €</button>
                        <button type="button" class="amount-btn" data-amount="20">20 €</button>
                        <button type="button" class="amount-btn" data-amount="50">50 €</button>
                        <button type="button" class="amount-btn" data-amount="100">100 €</button>
                    </div>
                </div>

                <p class="info-text">⚠️ Simulation : aucun paiement réel n'est effectué. Le numéro de carte n'est jamais stocké (seuls les 4 derniers chiffres sont conservés).</p>

                <div class="form-group">
                    <label>Numéro de carte</label>
                    <input type="text" id="numero-carte" maxlength="19" placeholder="1234 5678 9012 3456" autocomplete="off" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Expiration</label>
                        <input type="text" id="expiration-carte" maxlength="5" placeholder="MM/AA" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" id="cvv" maxlength="3" placeholder="123" autocomplete="off" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nom sur la carte</label>
                    <input type="text" id="nom-carte" placeholder="Nom du titulaire de la carte" value="<?= strtoupper(htmlspecialchars($user['nom_user'])) ?>" required>
                </div>

                <button type="submit" class="btn-appro" id="btn-approvisionner">Approvisionner mon compte</button>
            </form>
        </div>

        <!-- Panneau : Envoyer un cadeau -->
        <div class="action-panel" id="panel-cadeau" hidden>
            <p class="cadeau-intro">Fais plaisir à un autre utilisateur en lui envoyant une partie de ton solde FoodHub.</p>

            <div id="cadeau-msg-zone"></div>

            <form id="cadeau-form" class="form-cadeau" autocomplete="off">
                <?= fh_csrf_field() ?>

                <div class="form-group">
                    <label>Destinataire</label>
                    <input type="text" id="cadeau-destinataire" placeholder="Tape le nom d'un utilisateur…" data-user-autocomplete data-target="#cadeau-destinataire-id">
                    <input type="hidden" id="cadeau-destinataire-id" name="destinataire_id" value="">
                </div>

                <div class="form-group">
                    <label>Montant (€)</label>
                    <input type="number" id="cadeau-montant" name="montant" 
                    min="1" max="500" max-length="3" step="0.01" placeholder="Ex: 10" required>
                </div>

                <div class="form-group">
                    <label>Message (optionnel)</label>
                    <input type="text" id="cadeau-message" name="message" maxlength="255" placeholder="Un petit mot pour accompagner ton cadeau…">
                </div>

                <button type="submit" class="btn-cadeau" id="btn-envoyer-cadeau">🎁 Envoyer le cadeau</button>
            </form>
        </div>

        <!-- Historique -->
        <div class="historique-section" id="historique-section">
            <h3>📜 Historique</h3>

            <div class="historique-filters">
                <button type="button" class="hist-filter-btn active" data-filter="tout">Tout</button>
                <button type="button" class="hist-filter-btn" data-filter="approvisionnement">💳 Argent ajouté</button>
                <button type="button" class="hist-filter-btn" data-filter="cadeau_envoye">🎁 Envoyés</button>
                <button type="button" class="hist-filter-btn" data-filter="cadeau_recu">🎁 Reçus</button>
            </div>

            <div class="historique-list" id="historique-list">
                <?php if (empty($historique)): ?>
                <p class="historique-vide" id="historique-vide">Aucune transaction pour l'instant.</p>
                <?php else: ?>
                <?php foreach ($historique as $h): ?>
                <div class="historique-item historique-<?= $h['type_transaction'] ?>" data-type="<?= $h['type_transaction'] ?>">
                    <div class="hist-info">
                        <?php if ($h['type_transaction'] === 'approvisionnement'): ?>
                            <span class="hist-montant hist-plus">+<?= number_format($h['montant'], 2) ?> €</span>
                            <span class="hist-libelle">Approvisionnement <?= $h['carte_masquee'] ? htmlspecialchars($h['carte_masquee']) : '' ?></span>
                        <?php elseif ($h['type_transaction'] === 'cadeau_envoye'): ?>
                            <span class="hist-montant hist-moins">-<?= number_format($h['montant'], 2) ?> €</span>
                            <span class="hist-libelle">🎁 Cadeau envoyé à <?= htmlspecialchars($h['autre_user_nom'] ?? '?') ?></span>
                        <?php else: ?>
                            <span class="hist-montant hist-plus">+<?= number_format($h['montant'], 2) ?> €</span>
                            <span class="hist-libelle">🎁 Cadeau reçu de <?= htmlspecialchars($h['autre_user_nom'] ?? '?') ?></span>
                        <?php endif; ?>
                        <span class="hist-date"><?= date('d/m/Y H:i', strtotime($h['date_transaction'])) ?></span>
                    </div>
                    <?php if ($h['type_transaction'] === 'approvisionnement'): ?>
                    <span class="hist-statut <?= $h['statut'] === 'validé' ? 'statut-valide' : 'statut-en-cours' ?>">
                        <?= $h['statut'] === 'validé' ? '✅' : '⏳' ?> <?= ucfirst($h['statut']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <p><a href="home.php" class="back-link" style="margin-bottom: -30px; margin: 0px;">← Retour</a></p>
    </main>

    <script>
    // ── Sélecteur d'action : un seul panneau ouvert à la fois ──
    (function() {
        const choices = document.querySelectorAll('.action-choice');
        const panels = {
            appro:  document.getElementById('panel-appro'),
            cadeau: document.getElementById('panel-cadeau')
        };

        choices.forEach(btn => {
            btn.addEventListener('click', () => {
                const cible = btn.dataset.panel;
                const dejaOuvert = panels[cible] && !panels[cible].hidden;

                Object.values(panels).forEach(p => { if (p) p.hidden = true; });
                choices.forEach(b => b.classList.remove('active'));

                if (!dejaOuvert && panels[cible]) {
                    panels[cible].hidden = false;
                    btn.classList.add('active');
                    panels[cible].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
    })();

    // ── Filtres de l'historique ──
    document.querySelectorAll('.hist-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.hist-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filtre = btn.dataset.filter;
            document.querySelectorAll('.historique-item').forEach(item => {
                item.style.display = (filtre === 'tout' || item.dataset.type === filtre) ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('montant-input').value = this.dataset.amount;
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    document.getElementById('numero-carte').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
        e.target.value = value.match(/.{1,4}/g)?.join(' ') || value;
    });

    document.getElementById('expiration-carte').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) value = value.substring(0, 2) + '/' + value.substring(2, 4);
        e.target.value = value;
    });

    document.getElementById('cvv').addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '');
    });

    function showMsg(text, type) {
        const zone = document.getElementById('msg-zone');
        zone.innerHTML = `<div class="${type}">${text}</div>`;
        zone.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Animation du solde façon molette de cadenas ─────────
    // L'ancien montant "tombe" vers le bas et disparaît, le nouveau
    // arrive par le haut et vient prendre sa place. Le montant est un
    // seul bloc de texte (comme le "404" de 404.php) pour garantir un
    // rendu fiable, quelle que soit la police utilisée.
    function animerSolde(nouvelleValeur) {
        const viewport = document.getElementById('solde-affiche');
        if (!viewport) return;

        const nouvelleStr = parseFloat(nouvelleValeur).toFixed(2);
        if (viewport.dataset.value === nouvelleStr) return; // rien à animer
        viewport.dataset.value = nouvelleStr;

        const ancien = viewport.querySelector('.solde-amount');
        if (!ancien) {
            viewport.innerHTML = `<span class="solde-amount">${nouvelleStr}</span>`;
            return;
        }

        // Figer la largeur actuelle pendant la transition (les éléments animés
        // passent en position absolute et ne contribuent plus à la largeur du parent)
        viewport.style.width = viewport.offsetWidth + 'px';

        const nouveau = document.createElement('span');
        nouveau.className = 'solde-amount solde-incoming';
        nouveau.textContent = nouvelleStr;
        viewport.appendChild(nouveau);

        ancien.classList.add('solde-leaving');

        // Forcer un reflow pour garantir que la transition CSS se déclenche
        void nouveau.offsetWidth;

        nouveau.classList.remove('solde-incoming');
        nouveau.classList.add('solde-settling');

        setTimeout(() => {
            ancien.remove();
            nouveau.classList.remove('solde-settling');
            viewport.style.width = ''; // laisser l'auto-dimensionnement reprendre la main
        }, 600);
    }

    // ── Ajout d'une ligne d'historique sans recharger la page ──
    function ajouterLigneHistorique(type, innerHtml) {
        const liste = document.getElementById('historique-list');
        if (!liste) return;

        const vide = document.getElementById('historique-vide');
        if (vide) vide.remove();

        const item = document.createElement('div');
        item.className = 'historique-item historique-' + type + ' historique-item-new';
        item.dataset.type = type;
        item.innerHTML = innerHtml;

        liste.prepend(item);

        // Respecter le filtre actuellement actif
        const filtreActif = document.querySelector('.hist-filter-btn.active')?.dataset.filter || 'tout';
        item.style.display = (filtreActif === 'tout' || filtreActif === type) ? '' : 'none';

        // Petite animation d'apparition
        requestAnimationFrame(() => item.classList.add('historique-item-in'));
    }

    document.getElementById('approvisionnement-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const montant    = parseFloat(document.getElementById('montant-input').value);
        const cardNumber  = document.getElementById('numero-carte').value;
        const cardExpiry  = document.getElementById('expiration-carte').value;
        const cardCvv     = document.getElementById('cvv').value;
        const cardName    = document.getElementById('nom-carte').value;
        const csrfToken   = document.querySelector('#approvisionnement-form input[name="csrf_token"]').value;

        if (montant < 5 || montant > 500) { showMsg('Le montant doit être entre 5€ et 500€', 'error'); return; }
        if (cardNumber.replace(/\s/g, '').length !== 16) { showMsg('Numéro de carte invalide', 'error'); return; }
        if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) { showMsg("Date d'expiration invalide (format: MM/AA)", 'error'); return; }
        if (cardCvv.length !== 3) { showMsg('CVV invalide', 'error'); return; }

        const btn = document.getElementById('btn-approvisionner');
        btn.disabled = true;
        btn.textContent = 'Traitement en cours…';

        const formData = new FormData();
        formData.append('montant', montant);
        formData.append('card_number', cardNumber);
        formData.append('card_expiry', cardExpiry);
        formData.append('card_cvv', cardCvv);
        formData.append('card_name', cardName);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('process_approvisionnement.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                showMsg(`✅ Approvisionnement de ${montant.toFixed(2)} € réussi !`, 'success');
                animerSolde(result.nouveau_solde);

                ajouterLigneHistorique('approvisionnement', `
                    <div class="hist-info">
                        <span class="hist-montant hist-plus">+${parseFloat(result.montant).toFixed(2)} €</span>
                        <span class="hist-libelle">Approvisionnement ${result.carte_masquee || ''}</span>
                        <span class="hist-date">${result.date_transaction}</span>
                    </div>
                    <span class="hist-statut statut-valide">✅ Validé</span>
                `);

                // Réinitialiser le formulaire
                document.getElementById('approvisionnement-form').reset();
                document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
                btn.disabled = false;
                btn.textContent = 'Approvisionner mon compte';
            } else {
                showMsg('❌ ' + result.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Approvisionner mon compte';
            }
        } catch (error) {
            showMsg('Erreur de connexion au serveur', 'error');
            btn.disabled = false;
            btn.textContent = 'Approvisionner mon compte';
        }
    });

    // ── Envoi de cadeau ─────────────────────────────────────
    function showCadeauMsg(text, type) {
        const zone = document.getElementById('cadeau-msg-zone');
        zone.innerHTML = `<div class="${type}">${text}</div>`;
    }

    const cadeauForm = document.getElementById('cadeau-form');
    if (cadeauForm) {
        cadeauForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const destId    = document.getElementById('cadeau-destinataire-id').value;
            const montant   = parseFloat(document.getElementById('cadeau-montant').value);
            const message   = document.getElementById('cadeau-message').value;
            const csrfToken = document.querySelector('#cadeau-form input[name="csrf_token"]').value;

            if (!destId) {
                showCadeauMsg('⚠️ Choisis un destinataire dans la liste proposée.', 'error');
                return;
            }
            if (isNaN(montant) || montant < 1 || montant > 500) {
                showCadeauMsg('⚠️ Le montant doit être compris entre 1€ et 500€.', 'error');
                return;
            }

            const btn = document.getElementById('btn-envoyer-cadeau');
            btn.disabled = true;
            btn.textContent = 'Envoi en cours…';

            const formData = new FormData();
            formData.append('destinataire_id', destId);
            formData.append('montant', montant);
            formData.append('message', message);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('envoyer_cadeau.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    showCadeauMsg('✅ ' + result.message, 'success');
                    animerSolde(result.nouveau_solde);

                    ajouterLigneHistorique('cadeau_envoye', `
                        <div class="hist-info">
                            <span class="hist-montant hist-moins">-${parseFloat(result.montant).toFixed(2)} €</span>
                            <span class="hist-libelle">🎁 Cadeau envoyé à ${result.destinataire_nom}</span>
                            <span class="hist-date">${result.date_transaction}</span>
                        </div>
                    `);

                    cadeauForm.reset();
                    document.getElementById('cadeau-destinataire-id').value = '';
                    btn.disabled = false;
                    btn.textContent = '🎁 Envoyer le cadeau';
                } else {
                    showCadeauMsg('❌ ' + result.message, 'error');
                    btn.disabled = false;
                    btn.textContent = '🎁 Envoyer le cadeau';
                }
            } catch (error) {
                showCadeauMsg('Erreur de connexion au serveur', 'error');
                btn.disabled = false;
                btn.textContent = '🎁 Envoyer le cadeau';
            }
        });
    }
    </script>

    <style>
    .container {
        max-width: 800px;
        margin: 100px auto;
        padding: 40px;
        border-radius: 20px;
        backdrop-filter: blur(15px);
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #fff;
        font-family: 'HSR', sans-serif;
        animation: fadeIn 0.8s ease;
    }

    /* ── Carte solde — compacte, ne couvre plus que le montant ── */
    .solde-card {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.3), rgba(102, 187, 106, 0.3));
        border-radius: 18px;
        padding: 18px 24px;
        text-align: center;
        margin-bottom: 24px;
        border: 2px solid rgba(255, 161, 107, 0.2);
        box-shadow: 0 8px 25px rgba(255, 161, 107, 0.2);
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        width: 100%;
    }

    .solde-label {
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: rgba(0, 0, 0, 0.55);
    }

    /* ── Montant du solde — même traitement visuel que le "404" de 404.php,
         appliqué directement sur chaque span porteur de texte (indispensable
         pour que le dégradé s'affiche correctement — un seul bloc de texte,
         exactement comme .num-404, garantit un rendu fiable quelle que soit
         la police ; l'effet "molette" anime le nombre entier plutôt que
         chiffre par chiffre pour éviter tout souci de largeur de glyphe) ── */
    .solde-amount-wrap {
        display: inline-flex;
        align-items: baseline;
        gap: 0.35rem;
        filter: drop-shadow(2px 4px 8px rgba(255, 107, 107, 0.3));
        animation: soldePulse 3s ease-in-out infinite;
    }

    .solde-amount-viewport {
        position: relative;
        display: inline-block;
        height: 1.25em;
        line-height: 1.25em;
        overflow-x: visible;
        overflow-y: hidden;
        vertical-align: top;
    }

    .solde-amount {
        display: inline-block;
        font-size: clamp(2.4rem, 7vw, 3.2rem);
        font-weight: 700;
        line-height: 1.25em;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        background: linear-gradient(135deg, var(--accent, #ff6b6b), var(--accent2, #ff8c42));
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: transparent;
    }

    .solde-devise {
        font-size: clamp(1.5rem, 4vw, 2rem);
        font-weight: 700;
        background: linear-gradient(135deg, var(--accent, #ff6b6b), var(--accent2, #ff8c42));
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: transparent;
    }

    @keyframes soldePulse {
        0%, 100% { filter: drop-shadow(2px 4px 8px rgba(255, 107, 107, 0.25)); }
        50%      { filter: drop-shadow(2px 4px 18px rgba(255, 107, 107, 0.55)); }
    }

    /* ── Molette façon cadenas : l'ancien montant tombe, le nouveau vient d'en haut ── */
    .solde-amount.solde-incoming,
    .solde-amount.solde-settling,
    .solde-amount.solde-leaving {
        position: absolute;
        left: 0;
        top: 0;
        transition: transform 0.55s cubic-bezier(0.55, 0, 0.1, 1), opacity 0.5s ease;
    }

    .solde-amount.solde-incoming {
        transform: translateY(-100%);
        opacity: 0;
    }

    .solde-amount.solde-settling {
        transform: translateY(0);
        opacity: 1;
    }

    .solde-amount.solde-leaving {
        transform: translateY(100%);
        opacity: 0;
    }

    /* ── Sélecteur d'action (Ajouter de l'argent / Envoyer un cadeau) ── */
    .action-selector {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 20px;
    }

    .action-choice {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.25);
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.25s ease;
        text-align: left;
        font-family: 'HSR', sans-serif;
    }

    .action-choice:hover {
        background: rgba(255, 255, 255, 0.28);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .action-choice.active {
        background: linear-gradient(135deg, rgba(255, 107, 107, 0.25), rgba(255, 140, 66, 0.2));
        border-color: rgba(255, 107, 107, 0.5);
        box-shadow: 0 8px 22px rgba(255, 107, 107, 0.2);
    }

    .action-choice-icon {
        font-size: 1.8rem;
        flex-shrink: 0;
    }

    .action-choice-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        flex: 1;
        min-width: 0;
    }

    .action-choice-text strong {
        color: rgba(0, 0, 0, 0.8);
        font-size: 1rem;
    }

    .action-choice-text small {
        color: rgba(0, 0, 0, 0.55);
        font-size: 0.78rem;
        line-height: 1.3;
    }

    .action-choice-chevron {
        font-size: 1.4rem;
        font-weight: 700;
        color: rgba(0, 0, 0, 0.3);
        transition: transform 0.25s ease;
        flex-shrink: 0;
    }

    .action-choice.active .action-choice-chevron {
        transform: rotate(90deg);
        color: #ff6b6b;
    }

    /* ── Panneaux repliables ── */
    .action-panel {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 18px;
        padding: 24px;
        margin-bottom: 24px;
        animation: panelIn 0.35s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    @keyframes panelIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .action-panel .form-appro,
    .action-panel .form-cadeau {
        background: none;
        padding: 0;
        margin: 0;
    }

    .cadeau-intro {
        color: rgba(0, 0, 0, 0.65);
        font-size: 0.95rem;
        margin-bottom: 20px;
        margin-top:-7px;
    }

    .form-cadeau .form-group {
        margin-bottom: 18px;
        position: relative;
    }

    .form-cadeau input[type="text"],
    .form-cadeau input[type="number"] {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 2px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.4);
        color: #000;
        font-size: 1rem;
        font-family: 'HSR', sans-serif;
        transition: all 0.3s ease;
    }

    .form-cadeau input:focus {
        background: rgba(255, 255, 255, 0.55);
        border-color: #ff6b6b;
        outline: none;
        transform: scale(1.01);
    }

    .btn-cadeau {
        width: 100%;
        padding: 16px;
        font-size: 1.15rem;
        font-weight: 700;
        background: linear-gradient(135deg, #ff6b6b, #ff8c42);
        color: white;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'HSR', sans-serif;
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.35);
    }

    .btn-cadeau:hover {
        background: linear-gradient(135deg, #ff8c42, #ff6b6b);
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(255, 107, 107, 0.5);
    }

    .btn-cadeau:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    #cadeau-msg-zone .success {
        background: rgba(0, 255, 127, 0.25);
        border-left: 4px solid #00ff7f;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        color: #075c33;
        font-weight: 600;
        font-size: 0.9rem;
    }
    #cadeau-msg-zone .error {
        background: rgba(255, 77, 77, 0.25);
        border-left: 4px solid #ff4d4d;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        color: #8b0000;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .amount-buttons {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-top: 8px;
    }

    .amount-btn {
        padding: 9px 6px;
        font-size: 0.9rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(76, 175, 80, 0.3);
        border-radius: 10px;
        color: #000;
        cursor: pointer;
        transition: all 0.25s ease;
        font-family: 'HSR', sans-serif;
    }

    .amount-btn:hover {
        background: rgba(76, 175, 80, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
    }

    .amount-btn.selected {
        background: rgba(76, 175, 80, 0.5);
        border-color: #4caf50;
        transform: scale(1.05);
    }

    .form-appro {
        background: rgba(255, 255, 255, 0.1);
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
    }

    .form-appro h3 {
        color: rgba(0, 0, 0, 0.75);
        margin-top: 0;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        color: rgba(0, 0, 0, 0.75);
        font-weight: 600;
        margin-bottom: 8px;
    }

    .form-group input {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 2px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.15);
        color: #000;
        font-size: 1rem;
        font-family: 'HSR', sans-serif;
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        background: rgba(255, 255, 255, 0.25);
        border-color:rgba(231, 141, 62, 1);
        outline: none;
        transform: scale(1.02);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .info-text {
        background: rgba(255, 193, 7, 0.2);
        padding: 12px;
        border-radius: 8px;
        border-left: 4px solid #ffc107;
        color: rgba(0, 0, 0, 0.75);
        margin-bottom: 20px;
    }

    .btn-appro {
        width: 100%;
        padding: 18px;
        font-size: 1.3rem;
        font-weight: 700;
        background: linear-gradient(135deg, #4caf50, #66bb6a);
        color: white;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'HSR', sans-serif;
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
    }

    .btn-appro:hover {
        background: linear-gradient(135deg, #66bb6a, #4caf50);
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(76, 175, 80, 0.5);
    }

    .btn-appro:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .historique-section {
        margin-top: 40px;
        background: rgba(255, 255, 255, 0.1);
        padding: 25px;
        border-radius: 15px;
    }

    .historique-section h3 {
        color: rgba(0, 0, 0, 0.75);
        margin-top: 0;
        margin-bottom: 20px;
    }

    .historique-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 18px;
    }

    .hist-filter-btn {
        padding: 7px 14px;
        font-size: 0.82rem;
        font-weight: 700;
        font-family: 'HSR', sans-serif;
        color: rgba(0, 0, 0, 0.65);
        background: rgba(255, 255, 255, 0.25);
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .hist-filter-btn:hover {
        background: rgba(255, 255, 255, 0.4);
    }

    .hist-filter-btn.active {
        background: linear-gradient(135deg, #ff6b6b, #ff8c42);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(255, 107, 107, 0.35);
    }

    .historique-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .historique-vide {
        color: rgba(0, 0, 0, 0.5);
        font-size: 0.9rem;
        text-align: center;
        padding: 20px 0;
        font-style: italic;
    }

    .historique-item-new {
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.4s ease, transform 0.4s ease;
    }
    .historique-item-new.historique-item-in {
        opacity: 1;
        transform: translateY(0);
    }

    .historique-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.15);
        padding: 15px;
        border-radius: 10px;
        border-left: 4px solid #4caf50;
    }

    .historique-item.historique-cadeau_envoye { border-left-color: #ff8c42; }
    .historique-item.historique-cadeau_recu   { border-left-color: #ff6b6b; }

    .hist-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .hist-montant {
        font-size: 1.2rem;
        font-weight: 700;
    }
    .hist-montant.hist-plus  { color: #4caf50; }
    .hist-montant.hist-moins { color: #e05555; }

    .hist-libelle {
        font-size: 0.9rem;
        color: rgba(0,0,0,0.7);
    }

    .hist-date {
        font-size: 0.85rem;
        color: rgba(0, 0, 0, 0.65);
    }

    .hist-statut {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .statut-valide {
        background: rgba(76, 175, 80, 0.2);
        color: #2e7d32;
    }

    .statut-en-cours {
        background: rgba(255, 152, 0, 0.2);
        color: #e65100;
    }

    hr {
        border: none;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        margin: 25px 0;
    }

    #msg-zone .success {
        background: rgba(0, 255, 127, 0.25);
        border-left: 4px solid #00ff7f;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        color: #075c33;
        font-weight: 600;
    }
    #msg-zone .error {
        background: rgba(255, 77, 77, 0.25);
        border-left: 4px solid #ff4d4d;
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        color: #8b0000;
        font-weight: 600;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .amount-buttons {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>

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
<script src="user-autocomplete.js"></script>
</body>
</html>

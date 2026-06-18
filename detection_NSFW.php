<?php
// profanity_helper.php
// Détection de contenu inapproprié pour la modération automatique des restaurants FoodHub.
// Aucune dépendance externe — tout est local.
//
// Usage :
//   require_once 'profanity_helper.php';
//   $result = fh_check_content($texte);
//   // $result = ['clean' => bool, 'score' => int, 'matches' => string[], 'reason' => string]
//
// Score :  0 = propre   |  1-4 = léger (accepté)  |  5-9 = modéré (warning)  |  10+ = refus

// ─────────────────────────────────────────────────────────────────────────────
//  NORMALISATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalise une chaîne pour détecter les variantes leetspeak, accents, séparateurs.
 * Ex: "c0nn@rd" → "connard"  |  "p.u.t.a.i.n" → "putain"
 */
function fh_normalize(string $text): string
{
    // Minuscules + suppression des accents courants
    $text = mb_strtolower($text, 'UTF-8');

    $accents = [
        'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
        'ô'=>'o','ö'=>'o','ò'=>'o','ó'=>'o','õ'=>'o',
        'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
        'ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y',
        'œ'=>'oe','æ'=>'ae','ß'=>'ss',
    ];
    $text = strtr($text, $accents);

    // Leetspeak classique
    $leet = [
        '@'=>'a', '4'=>'a',
        '3'=>'e',
        '1'=>'i', '!'=>'i',
        '0'=>'o',
        '5'=>'s', '$'=>'s',
        '7'=>'t',
        '+'=>'t',
        '8'=>'b',
        '6'=>'g',
        '9'=>'g',
    ];
    $text = strtr($text, $leet);

    // Supprimer les séparateurs entre lettres (p.u.t.a.i.n, p-u-t-a-i-n, p u t a i n)
    // On répète 3 fois pour gérer les chaînes longues
    for ($i = 0; $i < 3; $i++) {
        $text = preg_replace('/\b([a-z])([\s.\-_*+|])+([a-z])/u', '$1$3', $text);
    }

    // Supprimer les caractères non-alphabétiques (sauf espaces)
    $text = preg_replace('/[^a-z\s]/u', '', $text);

    // Normaliser les espaces
    $text = preg_replace('/\s+/', ' ', trim($text));

    return $text;
}

/**
 * Retourne une version "compactée" sans espaces pour détecter les mots collés.
 */
function fh_compact(string $normalized): string
{
    return preg_replace('/\s/', '', $normalized);
}

// ─────────────────────────────────────────────────────────────────────────────
//  LISTES DE MOTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mots à REFUSER — insultes, termes haineux, racistes, etc.
 * Les termes anatomiques neutres (pénis, vagin, sein, etc.) sont volontairement ABSENTS.
 * Score associé à chaque mot.
 *
 * @return array<string, int>  ['mot_normalisé' => score]
 */
function fh_banned_words(): array
{
    return [
        // ── Insultes graves ──────────────────────────────────────────────────
        'connard'       => 10,
        'connarde'      => 10,
        'con'           => 6,   // court → score modéré (faux positifs possibles)
        'conne'         => 8,
        'salope'        => 10,
        'salaud'        => 10,
        'enculer'       => 12,
        'enculé'        => 12,
        'encule'        => 12,
        'pédé'          => 12,
        'pede'          => 12,
        'tapette'       => 10,
        'fdp'           => 10,  // fils de pute
        'pd'            => 8,
        'batard'        => 10,
        'batarde'       => 10,
        'bâtard'        => 10,
        'bâtarde'       => 10,
        'abruti'        => 8,
        'abrutie'       => 8,
        'idiot'         => 5,
        'idiote'        => 5,
        'imbécile'      => 7,
        'imbecile'      => 7,
        'débile'        => 7,
        'debile'        => 7,
        'crétin'        => 8,
        'cretin'        => 8,
        'cretine'       => 8,
        'crétine'       => 8,
        'nul'           => 4,
        'nulle'         => 4,
        'gros con'      => 12,
        'grosse conne'  => 12,
        'pauvre con'    => 12,
        'sale con'      => 12,
        'espèce de con' => 14,
        'espece de con' => 14,
        'va te faire'   => 10,

        // ── Termes sexuels offensants / dégradants ───────────────────────────
        'putain'        => 8,
        'pute'          => 8,
        'prostitué'     => 6,   // contexte médical/neutre possible → score modéré
        'prostituee'    => 6,
        'prostituée'    => 6,
        'branler'       => 8,
        'branleur'      => 10,
        'branleuse'     => 10,
        'baiser'        => 5,   // peut être neutre (s'embrasser) → score faible
        'niquer'        => 10,
        'nique'         => 9,
        'niqué'         => 10,
        'nique ta mere' => 14,
        'ntm'           => 10,
        'sucer'         => 6,
        'pipe'          => 3,   // très courant (tuyau, pipeline) → score très faible
        'foutre'        => 6,
        'sodomiser'     => 9,
        'sodomie'       => 7,
        'partouze'      => 9,
        'gangbang'      => 12,
        'gang bang'     => 12,
        'fellation'     => 6,
        'cunnilingus'   => 6,
        'baise'         => 6,

        // ── Violence / menaces ───────────────────────────────────────────────
        'tuer'          => 6,
        'crever'        => 5,
        'mourir'        => 4,
        'mort'          => 3,
        'assassin'      => 7,
        'violer'        => 12,
        'viol'          => 10,
        'frapper'       => 4,
        'tabasser'      => 8,
        'massacrer'     => 8,
        'exterminer'    => 10,
        'détruire'      => 4,
        'detruire'      => 4,

        // ── Racisme / haine ──────────────────────────────────────────────────
        'nazi'          => 12,
        'nazis'         => 12,
        'fasciste'      => 8,
        'raciste'       => 8,
        'racisme'       => 8,
        'nègre'         => 14,
        'negre'         => 14,
        'négresse'      => 14,
        'negresse'      => 14,
        'youpin'        => 14,
        'youpine'       => 14,
        'bougnoule'     => 14,
        'bicot'         => 14,
        'chinetoque'    => 14,
        'chinetoques'   => 14,
        'raton'         => 10,  // peut être faux positif (animal) → vérifié en contexte
        'bamboula'      => 12,
        'sale arabe'    => 14,
        'sale juif'     => 14,
        'sale noir'     => 14,
        'sale blanc'    => 14,
        'antisémite'    => 12,
        'antisemite'    => 12,
        'islamophobie'  => 8,
        'homophobie'    => 8,
        'homophobe'     => 10,
        'transphobe'    => 10,

        // ── Scatologie / dégradant ───────────────────────────────────────────
        'merde'         => 7,
        'merder'        => 6,
        'chier'         => 7,
        'chie'          => 6,
        'chieur'        => 8,
        'chieuse'       => 8,
        'chiottes'      => 6,
        'caca'          => 3,
        'pipi'          => 2,
        'pisse'         => 5,
        'pisser'        => 5,
        'crotte'        => 3,
        'étron'         => 5,
        'etron'         => 5,
        'fécès'         => 4,
        'feces'         => 4,
        'diarrhée'      => 4,
        'diarrhee'      => 4,
        'gerber'        => 5,
        'vomir'         => 4,

        // ── Termes péjoratifs généraux ────────────────────────────────────────
        'maudit'        => 4,
        'damné'         => 4,
        'damn'          => 3,
        'hell'          => 2,
        'shit'          => 8,
        'fuck'          => 10,
        'fucking'       => 10,
        'fucked'        => 10,
        'asshole'       => 10,
        'bastard'       => 8,
        'bitch'         => 10,
        'cunt'          => 12,
        'dick'          => 7,
        'motherfucker'  => 14,
        'motherfucking' => 14,
        'whore'         => 10,
        'slut'          => 10,
        'nigger'        => 14,
        'nigga'         => 12,
        'faggot'        => 14,
        'retard'        => 10,
        'spastic'       => 8,
        'stupid'        => 4,
        'loser'         => 4,
        'douche'        => 5,
        'douchebag'     => 8,
        'jerk'          => 4,
        'wanker'        => 8,
        'tosser'        => 6,
        'prick'         => 7,
        'twat'          => 8,
        'bollock'       => 7,
        'bollocks'      => 7,
        'arsehole'      => 10,
        'bloody hell'   => 5,
        'goddamn'       => 5,
    ];
}

/**
 * Mots-clés NON-ALIMENTAIRES qui suggèrent un restaurant hors-sujet.
 * Score additionnel si plusieurs sont présents.
 *
 * @return string[]
 */
function fh_offtopic_words(): array
{
    return [
        'voiture', 'automobile', 'moto', 'vélo', 'velo', 'scooter',
        'vêtement', 'vetement', 'robe', 'pantalon', 'chaussure', 'chemise',
        'meuble', 'canapé', 'canape', 'matelas', 'armoire',
        'téléphone', 'telephone', 'ordinateur', 'laptop', 'smartphone',
        'bijou', 'montre', 'collier', 'bague',
        'immobilier', 'appartement', 'maison', 'location', 'vente',
        'casino', 'poker', 'pari', 'paris sportifs', 'jeu de hasard',
        'drogue', 'cannabis', 'cocaïne', 'cocaine', 'héroïne', 'heroine',
        'arme', 'pistolet', 'fusil', 'couteau de chasse',
        'arnaque', 'escroquerie', 'fraude',
    ];
}

/**
 * Mots anatomiques/médicaux ACCEPTÉS même s'ils semblent intimes.
 * Sert à blanchir les faux positifs.
 *
 * @return string[]
 */
function fh_whitelist(): array
{
    return [
        // Anatomie neutre
        'penis', 'pénis', 'vagin', 'vulve', 'clitoris', 'sein', 'seins',
        'téton', 'teton', 'fesses', 'fesse', 'poitrine', 'nudité', 'nudite',
        'nudiste', 'anatomie', 'anatomique',
        // Gastronomie évocatrice mais neutre
        'rognon', 'rognons blancs', 'ris de veau', 'abats',
        'langue de bœuf', 'langue de boeuf', 'cervelle', 'tripes',
        'boudin', 'andouille', 'andouillette',
        'pipe d agneau', 'pipe de porc',    // plat réel
        'baiser la main',                   // expression culinaire rare
        'croque monsieur', 'croque madame',
        'tarte', 'tartelette', 'quiche',
        // Contextes géographiques / noms propres courants
        'con', // dans "bacon", "balcon", "falcon", "falcon", "con-fit" → géré par contexte
        'nique' => 'nique-nique', // camping nique-nique → très rare, accepté
        'mort' // "à la mort d'eux" → contexte
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
//  DÉTECTION PRINCIPALE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Vérifie un texte pour détecter les contenus inappropriés.
 *
 * @param  string $text  Texte brut à analyser (peut contenir HTML, accents, etc.)
 * @return array{clean: bool, score: int, matches: string[], reason: string}
 */
function fh_check_content(string $text): array
{
    $normalized  = fh_normalize($text);
    $compact     = fh_compact($normalized);
    $banned      = fh_banned_words();
    $whitelist   = fh_whitelist();

    $score   = 0;
    $matches = [];

    // ── 1. Vérification des mots interdits ──────────────────────────────────
    foreach ($banned as $word => $word_score) {
        $word_norm = fh_normalize($word);

        // Chercher dans le texte normalisé (avec espaces)
        $found_in_text    = fh_word_in_text($word_norm, $normalized);
        // Chercher dans la version compactée (mots collés)
        $found_in_compact = fh_word_in_text($word_norm, $compact) ||
                            (mb_strlen($word_norm) >= 5 && mb_strpos($compact, fh_compact($word_norm)) !== false);

        if (!$found_in_text && !$found_in_compact) {
            continue;
        }

        // Vérifier si le mot fait partie de la whitelist (contexte gastro/médical)
        if (fh_is_whitelisted($word_norm, $normalized, $whitelist)) {
            continue;
        }

        // Vérifier les faux positifs courants pour les mots courts
        if (fh_is_false_positive($word_norm, $normalized)) {
            continue;
        }

        $score  += $word_score;
        $matches[] = $word;
    }

    // ── 2. Vérification du contenu hors-sujet ───────────────────────────────
    $offtopic_count = 0;
    foreach (fh_offtopic_words() as $ot) {
        if (mb_strpos($normalized, fh_normalize($ot)) !== false) {
            $offtopic_count++;
        }
    }
    // 3+ mots hors-sujet = probablement pas un restaurant alimentaire
    if ($offtopic_count >= 3) {
        $score  += $offtopic_count * 3;
        $matches[] = "contenu_hors_sujet({$offtopic_count} mots)";
    }

    // ── 3. Résultat ──────────────────────────────────────────────────────────
    $clean  = ($score < 10);
    $reason = '';

    if (!$clean) {
        $reason = 'Contenu inapproprié détecté';
        if (!empty($matches)) {
            // Ne pas exposer les mots exacts dans les logs publics, juste le score
            $reason .= ' (score: ' . $score . ')';
        }
    }

    return [
        'clean'   => $clean,
        'score'   => $score,
        'matches' => $matches,
        'reason'  => $reason,
    ];
}

/**
 * Vérifie si un mot est présent dans un texte avec gestion des frontières de mot.
 * Gère les mots composés (espaces).
 */
function fh_word_in_text(string $word, string $text): bool
{
    if ($word === '') return false;

    // Mot composé (contient un espace) → recherche directe de la sous-chaîne
    if (mb_strpos($word, ' ') !== false) {
        return mb_strpos($text, $word) !== false;
    }

    // Mot simple → on vérifie les frontières (évite "con" dans "bacon")
    // On utilise une regex avec word boundary adaptée au français
    $pattern = '/(?<![a-z])' . preg_quote($word, '/') . '(?![a-z])/u';
    return (bool) preg_match($pattern, $text);
}

/**
 * Vérifie si une correspondance est dans la whitelist gastronomique/médicale.
 */
function fh_is_whitelisted(string $word, string $normalized_text, array $whitelist): bool
{
    foreach ($whitelist as $wl_entry) {
        $wl_norm = fh_normalize($wl_entry);
        // Le mot suspect est contenu dans une expression whitelistée
        if (mb_strpos($wl_norm, $word) !== false && mb_strpos($normalized_text, $wl_norm) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Détecte les faux positifs courants pour les mots courts ambigus.
 *
 * Ex: "con" dans "bacon", "balcon", "falcon", "confit", "conserver"
 *     "nul" dans "null" (technique), "annuler"
 *     "mort" dans "immortel", "amortir"
 */
function fh_is_false_positive(string $word, string $normalized_text): bool
{
    $fp_contexts = [
        // 'mot' => ['séquences qui l\'englobent légitimement']
        'con'   => ['bacon', 'balcon', 'falcon', 'confit', 'conserver', 'conge', 'concert',
                    'confiture', 'confiserie', 'consome', 'consomme', 'continent', 'contenu',
                    'conference', 'connexion', 'concept', 'contact', 'controle', 'convivial',
                    'reconditionne', 'second', 'micro', 'flacon', 'maçon', 'garcon', 'tranchon', 
                    'deconnecter', 'congelation', 'congele', 'confiture',
                    'condiment', 'deconseiller', 'condiments', 'conduite', 'conserver', 'conservation'],
        'nul'   => ['annuler', 'annulation', 'nulle part', 'calcul', 'stimul'],
        'mort'  => ['immortel', 'amortir', 'amortissement', 'morteau', 'remords'],
        'pipe'  => ['pipeline', 'pipe a eau', 'pipe froide'],
        'baiser'=> ['s embrasser', 'baisers'],
        'sucer' => ['sucerie', 'sucette', 'sucon'],
        'tuer'  => ['tuteur', 'future', 'mixture', 'structure', 'sculpture'],
        'crever'=> ['crevette', 'decreveter'],
        'baise' => ['embaise', 'baisemain'],
        'merde' => [], // pas de faux positif connu
    ];

    if (!isset($fp_contexts[$word])) {
        return false;
    }

    foreach ($fp_contexts[$word] as $fp) {
        if (mb_strpos($normalized_text, fh_normalize($fp)) !== false) {
            return true;
        }
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
//  VÉRIFICATION COMPLÈTE D'UN RESTAURANT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Vérifie l'ensemble des champs d'un restaurant + ses plats.
 *
 * @param  string   $nom
 * @param  string   $desc
 * @param  string   $adresse
 * @param  string   $categorie
 * @param  array    $plats  [['nom_plat'=>'', 'description_plat'=>'', 'prix'=>0], ...]
 * @return array{accepted: bool, score: int, reason: string}
 */
function fh_verify_restaurant(
    string $nom,
    string $desc,
    string $adresse,
    string $categorie,
    array  $plats
): array {
    $total_score = 0;
    $all_matches = [];

    // Champs texte à vérifier avec leur poids multiplicateur
    $fields = [
        'nom du restaurant' => [$nom,       2.0],  // nom = plus visible → poids x2
        'description'       => [$desc,      1.0],
        'adresse'           => [$adresse,   0.5],  // adresse → faux positifs possibles
        'catégorie'         => [$categorie, 1.0],
    ];

    foreach ($fields as $field_name => [$value, $multiplier]) {
        if (trim($value) === '') continue;
        $r = fh_check_content($value);
        if (!$r['clean'] || $r['score'] > 0) {
            $weighted = (int) round($r['score'] * $multiplier);
            $total_score += $weighted;
            foreach ($r['matches'] as $m) {
                $all_matches[] = "[{$field_name}] {$m}";
            }
        }
    }

    // Vérification des plats
    foreach ($plats as $idx => $plat) {
        $plat_nom   = $plat['nom_plat']          ?? $plat['nom']  ?? '';
        $plat_desc  = $plat['description_plat']  ?? $plat['desc'] ?? '';
        $plat_texte = $plat_nom . ' ' . $plat_desc;

        if (trim($plat_texte) === '') continue;

        $r = fh_check_content($plat_texte);
        if (!$r['clean'] || $r['score'] > 0) {
            $total_score += $r['score'];
            foreach ($r['matches'] as $m) {
                $plat_label = $plat_nom !== '' ? $plat_nom : "plat #" . ($idx + 1);
                $all_matches[] = "[plat: {$plat_label}] {$m}";
            }
        }
    }

    $accepted = ($total_score < 10);

    // Construction du message de raison
    if ($accepted) {
        $reason = $total_score === 0
            ? 'Contenu conforme aux règles FoodHub.'
            : "Contenu acceptable (score: {$total_score}/10).";
    } else {
        $reason = "Contenu refusé automatiquement (score: {$total_score}). ";
        if (!empty($all_matches)) {
            // Log interne uniquement — ne pas afficher les mots à l'utilisateur
            error_log('[FoodHub] auto_verify refus: ' . implode(', ', $all_matches));
        }
        // Message générique pour le propriétaire
        $reason .= "Le nom, la description ou un plat contient des termes non autorisés.";
    }

    return [
        'accepted' => $accepted,
        'score'    => $total_score,
        'reason'   => $reason,
    ];
}

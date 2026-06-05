<?php
// mail_helper.php
// Fonctions utilitaires pour l'envoi de mails (vérification email, reset mdp)
// Nécessite : composer require phpmailer/phpmailer

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Slogan BTS SIO 2025 - mèmes de cette annéé
define('_FH_SLOGAN', "FoodHub - Jallon GOAT a validé la BDD. Chill et zen, BIGMACBOURDON a validé la commande. Gloire au SQL, gloire aux bons plats. Zachabian13 ne nous mettra pas en faillite — t'as plus qu'à manger.");

/**
 * Crée une instance PHPMailer prête à l'emploi.
 */
function _fh_mailer(): PHPMailer
{
  $env = function (string $key, string $default = ''): string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '')
      return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '')
      return $_SERVER[$key];
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
  };

  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = $env('MAIL_HOST', 'smtp-foodhub-sio.alwaysdata.net');
  $mail->SMTPAuth = true;
  $mail->Username = $env('MAIL_USER', 'foodhub-sio@alwaysdata.net');
  $mail->Password = $env('MAIL_PASS', '');
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = (int) $env('MAIL_PORT', '587');
  $mail->CharSet = 'UTF-8';
  $mail->setFrom(
    $env('MAIL_FROM', $mail->Username),
    $env('MAIL_FROM_NOM', 'FoodHub')
  );
  return $mail;
}

/**
 * Retourne l'URL de base du site (tunnel ou alwaysdata ou localhost)
 */
function _fh_site_url(): string
{
  $tunnel = getenv('TUNNEL_URL');
  if ($tunnel && $tunnel !== '')
    return rtrim($tunnel, '/');
  $site_url = getenv('SITE_URL');
  if ($site_url && $site_url !== '')
    return rtrim($site_url, '/');
  if (isset($_SERVER['HTTP_HOST'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = '';
    if (isset($_SERVER['SCRIPT_NAME'])) {
      $path = dirname($_SERVER['SCRIPT_NAME']);
      if ($path === '/' || $path === '\\')
        $path = '';
    }
    return $proto . '://' . $host . $path;
  }
  return 'https://foodhub-sio.alwaysdata.net/';
}

/**
 * Génère un token sécurisé de 64 chars hexadécimaux
 */
function _fh_gen_token(): string
{
  return bin2hex(random_bytes(32));
}

/**
 * Envoie l'email de vérification d'adresse
 */
function fh_send_verify_email(PDO $conn, int $user_id, string $nom, string $email, string $type = 'verify', string $new_email = ''): bool
{
  $conn->prepare("DELETE FROM email_tokens WHERE user_id = ? AND type = 'verify' AND used = 0")->execute([$user_id]);

  $token = _fh_gen_token();
  $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
  $new_email = $new_email ?: null;

  $conn->prepare("INSERT INTO email_tokens (user_id, token, type, new_email, expires_at) VALUES (?, ?, 'verify', ?, ?)")
    ->execute([$user_id, $token, $new_email, $expires]);

  $site_url = _fh_site_url();
  $link = $site_url . '/verify_email.php?token=' . urlencode($token);
  $nom_esc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
  $link_esc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
  $target_esc = $new_email ? htmlspecialchars($new_email, ENT_QUOTES, 'UTF-8') : htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

  $subject = $type === 'change'
    ? '📧 FoodHub — Confirme ton nouvel email'
    : '📧 FoodHub — Vérifie ton adresse email';

  $intro = $type === 'change'
    ? "Tu as demandé à changer ton adresse email. Clique sur le bouton ci-dessous pour confirmer la nouvelle adresse <strong>{$target_esc}</strong>."
    : "Merci de t'être inscrit(e) sur FoodHub ! Clique sur le bouton ci-dessous pour vérifier ton adresse email.";

  $body = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#ff6b6b,#ffc342);padding:36px 40px 28px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:2rem;">🍽️ FoodHub</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">Vérification d'adresse email</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="font-size:1.1rem;color:#333;">Bonjour <strong>{$nom_esc}</strong> 👋</p>
          <p style="color:#555;line-height:1.6;">{$intro}</p>
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center" style="padding:20px 0;">
              <a href="{$link_esc}"
                 style="display:inline-block;padding:14px 36px;
                        background:linear-gradient(135deg,#ff6b6b,#ffc342);
                        color:#fff;text-decoration:none;font-weight:700;
                        font-size:1.1rem;border-radius:12px;
                        box-shadow:0 4px 14px rgba(255,107,107,0.4);">
                ✅ Vérifier mon email
              </a>
            </td></tr>
          </table>
          <p style="color:#888;font-size:0.85rem;line-height:1.5;">
            Ce lien est valable <strong>24 heures</strong>. Si tu n'es pas à l'origine de cette demande, ignore ce mail.
          </p>
          <p style="color:#aaa;font-size:0.8rem;word-break:break-all;">
            Lien direct : <a href="{$link_esc}" style="color:#ff6b6b;">{$link_esc}</a>
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="margin:0;color:#aaa;font-size:0.8rem;">© FoodHub</p>
          <p style="margin:6px 0 0;color:#ccc;font-size:0.72rem;font-style:italic;"><?php echo _FH_SLOGAN; ?></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

  try {
    $mail = _fh_mailer();
    $mail->addAddress($email, $nom);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = "Bonjour {$nom},\n\nVérifie ton email FoodHub ici :\n{$link}\n\nCe lien expire dans 24h.";
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[FoodHub mail_helper] verify mail error: ' . $mail->ErrorInfo);
    return false;
  }
}

/**
 * Envoie l'email de réinitialisation de mot de passe
 */
function fh_send_reset_email(PDO $conn, int $user_id, string $nom, string $email): bool
{
  $conn->prepare("DELETE FROM email_tokens WHERE user_id = ? AND type = 'reset' AND used = 0")->execute([$user_id]);

  $token = _fh_gen_token();
  $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

  $conn->prepare("INSERT INTO email_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'reset', ?)")
    ->execute([$user_id, $token, $expires]);

  $site_url = _fh_site_url();
  $link = $site_url . '/reset_password.php?token=' . urlencode($token);
  $nom_esc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
  $link_esc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

  $body = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#7cc6e6,#5ab3d8);padding:36px 40px 28px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:2rem;">🍽️ FoodHub</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">Réinitialisation du mot de passe</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="font-size:1.1rem;color:#333;">Bonjour <strong>{$nom_esc}</strong> 👋</p>
          <p style="color:#555;line-height:1.6;">
            Tu as demandé à réinitialiser ton mot de passe FoodHub.<br>
            Clique sur le bouton ci-dessous pour choisir un nouveau mot de passe.
          </p>
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center" style="padding:20px 0;">
              <a href="{$link_esc}"
                 style="display:inline-block;padding:14px 36px;
                        background:linear-gradient(135deg,#7cc6e6,#5ab3d8);
                        color:#fff;text-decoration:none;font-weight:700;
                        font-size:1.1rem;border-radius:12px;
                        box-shadow:0 4px 14px rgba(124,198,230,0.4);">
                🔑 Réinitialiser mon mot de passe
              </a>
            </td></tr>
          </table>
          <p style="color:#888;font-size:0.85rem;line-height:1.5;">
            Ce lien est valable <strong>1 heure</strong>. Si tu n'as pas fait cette demande, ignore ce mail - ton mot de passe reste inchangé.
          </p>
          <p style="color:#aaa;font-size:0.8rem;word-break:break-all;">
            Lien direct : <a href="{$link_esc}" style="color:#7cc6e6;">{$link_esc}</a>
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="margin:0;color:#aaa;font-size:0.8rem;">© FoodHub</p>
          <p style="margin:6px 0 0;color:#ccc;font-size:0.72rem;font-style:italic;"><?php echo _FH_SLOGAN; ?></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

  try {
    $mail = _fh_mailer();
    $mail->addAddress($email, $nom);
    $mail->isHTML(true);
    $mail->Subject = '🔑 FoodHub - Réinitialisation de ton mot de passe';
    $mail->Body = $body;
    $mail->AltBody = "Bonjour {$nom},\n\nRéinitialise ton mot de passe ici :\n{$link}\n\nCe lien expire dans 1h.";
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[FoodHub mail_helper] reset mail error: ' . $e->getMessage());
    return false;
  }
}

/**
 * Envoie l'email de confirmation de suppression de compte
 */
function fh_send_account_deletion_email(string $nom, string $email): bool
{
  $nom_esc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');

  $body = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#ff6b6b,#ff8a8a);padding:36px 40px 28px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:2rem;">🍽️ FoodHub</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">Compte supprimé</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="font-size:1.1rem;color:#333;">Bonjour <strong>{$nom_esc}</strong>,</p>
          <p style="color:#555;line-height:1.6;">
            Nous te confirmons que ton compte FoodHub a été complètement supprimé.
          </p>
          <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;border-radius:4px;">
            <p style="margin:0;color:#856404;font-size:0.95rem;"><strong>📋 Qu'est-ce qui a été supprimé :</strong></p>
            <ul style="margin:10px 0 0 20px;color:#856404;font-size:0.9rem;">
              <li>Tes données personnelles et historique de compte</li>
              <li>Tes adresses de livraison et informations de paiement</li>
              <li>Tes avis et commentaires</li>
              <li>Ton panier et tes favoris</li>
              <li>Tes commandes</li>
            </ul>
          </div>
          <div style="background:#f0f0f0;padding:15px;margin:20px 0;border-radius:4px;font-size:0.9rem;color:#666;">
            <p style="margin:0;"><strong>📝 Note :</strong> Tes messages sur le forum sont conservés mais anonymisés pour préserver les discussions publiques.</p>
          </div>
          <p style="color:#888;font-size:0.9rem;line-height:1.6;">
            Si tu ne reconnais pas cette suppression ou si c'est une erreur, contacte notre support immédiatement.
          </p>
          <p style="color:#aaa;font-size:0.8rem;margin-top:20px;">
            Nous sommes tristes de te voir partir. N'hésite pas à créer un nouveau compte si tu changes d'avis !
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="margin:0;color:#aaa;font-size:0.8rem;">© FoodHub</p>
          <p style="margin:6px 0 0;color:#ccc;font-size:0.72rem;font-style:italic;"><?php echo _FH_SLOGAN; ?></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

  try {
    $mail = _fh_mailer();
    $mail->addAddress($email, $nom);
    $mail->isHTML(true);
    $mail->Subject = '🗑️ FoodHub — Ton compte a été supprimé';
    $mail->Body = $body;
    $mail->AltBody = "Bonjour {$nom},\n\nTon compte FoodHub a été supprimé.\n\nTes données personnelles ont été supprimées. Tes messages sur le forum sont conservés mais anonymisés.\n\nCordialement,\nL'équipe FoodHub";
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[FoodHub mail_helper] account deletion mail error: ' . $e->getMessage());
    return false;
  }
}

/**
 * Envoie un vrai email à l'admin lorsqu'un utilisateur contacte via le formulaire.
 *
 * @param string $nom              Nom de l'expéditeur
 * @param string $email_expediteur Email de l'expéditeur (mis en Reply-To)
 * @param string $sujet            Sujet du message
 * @param string $message          Corps du message
 * @param string $type_message     Type de demande (general, compte, signalement…)
 * @return bool
 */
function fh_send_contact_to_admin_email(
  string $nom,
  string $email_expediteur,
  string $sujet,
  string $message,
  string $type_message
): bool {
  $nom_esc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
  $email_esc = htmlspecialchars($email_expediteur, ENT_QUOTES, 'UTF-8');
  $sujet_esc = htmlspecialchars($sujet, ENT_QUOTES, 'UTF-8');
  $message_esc = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
  $type_esc = htmlspecialchars($type_message, ENT_QUOTES, 'UTF-8');
  $date_esc = htmlspecialchars(date('d/m/Y à H:i'), ENT_QUOTES, 'UTF-8');

  $type_labels = [
    'general' => 'Question générale',
    'compte' => 'Problème de compte',
    'signalement' => 'Signalement',
    'technique' => 'Problème technique',
    'suggestion' => 'Suggestion',
    'autre' => 'Autre',
  ];
  $type_label = htmlspecialchars($type_labels[$type_message] ?? $type_message, ENT_QUOTES, 'UTF-8');

  $body = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#7cc6e6,#5ab3d8);padding:36px 40px 28px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:2rem;">🍽️ FoodHub</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">📬 Nouveau message de contact</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="font-size:1.05rem;color:#333;margin:0 0 1.2rem 0;">
            Un visiteur t'a contacté directement par email depuis FoodHub.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0"
                 style="background:#f4f8fb;border-radius:10px;padding:18px;margin-bottom:1.5rem;font-size:0.95rem;color:#333;">
            <tr>
              <td style="padding:6px 0;"><strong>👤 Nom :</strong></td>
              <td style="padding:6px 0;">{$nom_esc}</td>
            </tr>
            <tr>
              <td style="padding:6px 0;"><strong>📧 Email :</strong></td>
              <td style="padding:6px 0;"><a href="mailto:{$email_esc}" style="color:#7cc6e6;">{$email_esc}</a></td>
            </tr>
            <tr>
              <td style="padding:6px 0;"><strong>🏷️ Type :</strong></td>
              <td style="padding:6px 0;">{$type_label}</td>
            </tr>
            <tr>
              <td style="padding:6px 0;"><strong>📌 Sujet :</strong></td>
              <td style="padding:6px 0;">{$sujet_esc}</td>
            </tr>
            <tr>
              <td style="padding:6px 0;"><strong>🕐 Date :</strong></td>
              <td style="padding:6px 0;">{$date_esc}</td>
            </tr>
          </table>

          <p style="font-weight:700;color:#333;margin:0 0 0.5rem 0;">💬 Message :</p>
          <div style="background:#fff;border:1px solid #e0edf5;border-left:4px solid #7cc6e6;
                      border-radius:8px;padding:16px 20px;color:#444;line-height:1.7;font-size:0.95rem;">
            {$message_esc}
          </div>

          <p style="color:#888;font-size:0.82rem;margin-top:1.5rem;line-height:1.5;">
            Ce message a été envoyé via le formulaire de contact FoodHub.<br>
            Pour répondre, utilise directement le panneau admin ou réponds à cet email (le Reply-To pointe vers l'expéditeur).
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="margin:0;color:#aaa;font-size:0.8rem;">© FoodHub - Message automatique</p>
          <p style="margin:6px 0 0;color:#ccc;font-size:0.72rem;font-style:italic;"><?php echo _FH_SLOGAN; ?></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

  try {
    $mail = _fh_mailer();
    // Destination : l'admin
    $mail->addAddress('foodhub-sio@alwaysdata.net', 'Admin FoodHub');
    // Reply-To : l'expéditeur, pour que tu puisses répondre directement depuis ta boîte mail
    $mail->addReplyTo($email_expediteur, $nom);
    $mail->isHTML(true);
    $mail->Subject = "[FoodHub Contact] {$sujet}";
    $mail->Body = $body;
    $mail->AltBody = "De : {$nom} ({$email_expediteur})\nType : {$type_message}\nSujet : {$sujet}\n\n{$message}";
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[FoodHub mail_helper] contact-to-admin mail error: ' . $e->getMessage());
    return false;
  }
}

/**
 * Envoie un vrai email de réponse de l'admin vers l'utilisateur.
 *
 * @param string $nom_destinataire   Nom de l'utilisateur
 * @param string $email_destinataire Email de l'utilisateur
 * @param string $sujet_original     Sujet du message d'origine
 * @param string $corps_reponse      Corps de la réponse de l'admin
 * @return bool
 */
function fh_send_admin_reply_email(
  string $nom_destinataire,
  string $email_destinataire,
  string $sujet_original,
  string $corps_reponse
): bool {
  $nom_esc = htmlspecialchars($nom_destinataire, ENT_QUOTES, 'UTF-8');
  $sujet_esc = htmlspecialchars($sujet_original, ENT_QUOTES, 'UTF-8');
  $reponse_esc = nl2br(htmlspecialchars($corps_reponse, ENT_QUOTES, 'UTF-8'));
  $date_esc = htmlspecialchars(date('d/m/Y à H:i'), ENT_QUOTES, 'UTF-8');

  $body = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#fff;border-radius:16px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#ff6b6b,#ffc342);padding:36px 40px 28px;text-align:center;">
          <h1 style="margin:0;color:#fff;font-size:2rem;">🍽️ FoodHub</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">📬 Réponse de l'administrateur</p>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 40px;">
          <p style="font-size:1.1rem;color:#333;">Bonjour <strong>{$nom_esc}</strong> 👋</p>
          <p style="color:#555;line-height:1.6;">
            L'administrateur FoodHub t'a répondu concernant ton message
            « <strong>{$sujet_esc}</strong> » du {$date_esc}.
          </p>

          <p style="font-weight:700;color:#333;margin:1.5rem 0 0.5rem 0;">💬 Réponse de l'admin :</p>
          <div style="background:#fff;border:1px solid #ffe0b2;border-left:4px solid #ffc342;
                      border-radius:8px;padding:16px 20px;color:#444;line-height:1.7;font-size:0.95rem;">
            {$reponse_esc}
          </div>

          <p style="color:#888;font-size:0.85rem;margin-top:1.5rem;line-height:1.5;">
            Si tu as d'autres questions, tu peux contacter l'administrateur depuis la page
            <a href="https://foodhub-sio.alwaysdata.net/contact_admin.php" style="color:#ff6b6b;">contact FoodHub</a>.
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="margin:0;color:#aaa;font-size:0.8rem;">© FoodHub — Réponse automatique</p>
          <p style="margin:6px 0 0;color:#ccc;font-size:0.72rem;font-style:italic;"><?php echo _FH_SLOGAN; ?></p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

  try {
    $mail = _fh_mailer();
    $mail->addAddress($email_destinataire, $nom_destinataire);
    $mail->isHTML(true);
    $mail->Subject = "📬 FoodHub — Réponse à votre message : {$sujet_original}";
    $mail->Body = $body;
    $mail->AltBody = "Bonjour {$nom_destinataire},\n\nRéponse de l'admin FoodHub concernant \"{$sujet_original}\" :\n\n{$corps_reponse}\n\nCordialement,\nL'équipe FoodHub";
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('[FoodHub mail_helper] admin-reply mail error: ' . $e->getMessage());
    return false;
  }
}

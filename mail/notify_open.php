<?php
// notify_open.php
// Script CLI — appelé automatiquement par Run FoodHub.bat au lancement du tunnel trycloudflared
// Usage : php notify_open.php
// Nécessite : composer require phpmailer/phpmailer

// ── Chemins ────────────────────────────────────────────────
define('BASE_DIR', __DIR__);
require_once BASE_DIR . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// ── Chargement .env ────────────────────────────────────────
$dotenv = Dotenv\Dotenv::createImmutable(BASE_DIR);
$dotenv->safeLoad();

// ── Config SMTP (à renseigner dans .env) ────────────────────
$smtp_host = $_ENV['MAIL_HOST'] ?? 'smtp-foodhub-sio.alwaysdata.net';
$smtp_port = (int) ($_ENV['MAIL_PORT'] ?? 587);
$smtp_user = $_ENV['MAIL_USER'] ?? 'foodhub-sio@alwaysdata.net';  // ton adresse Gmail
$smtp_pass = $_ENV['MAIL_PASS'] ?? '!Gambas2O25$';  // mot de passe d'application Gmail
$mail_from = $_ENV['MAIL_FROM'] ?? $smtp_user;
$mail_from_nom = $_ENV['MAIL_FROM_NOM'] ?? 'FoodHub Notifications';

// TUNNEL_URL : priorité à la variable d'environnement injectée par le .bat
// (contient l'URL trycloudflare fraîchement générée)
$site_url = (getenv('TUNNEL_URL') !== false && getenv('TUNNEL_URL') !== '')
    ? getenv('TUNNEL_URL')
    : ($_ENV['TUNNEL_URL'] ?? 'https://foodhub-sio.alwaysdata.net/');

// ── Connexion BDD ────────────────────────────────────────────
$servername = 'localhost';
$username = 'mehdi.guerbas';
$password = '!Gambas2O25$';
$dbname = 'foodhub_db';

try {
  $conn = new PDO(
    "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
    $username,
    $password
  );
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  fwrite(STDERR, '[FoodHub Notify] Erreur BDD : ' . $e->getMessage() . "\n");
  exit(1);
}

// ── Récupérer les destinataires actifs ───────────────────────
$stmt = $conn->query('SELECT nom, email FROM notify_list WHERE actif = 1 ORDER BY id ASC');
$destinataires = $stmt->fetchAll();

if (empty($destinataires)) {
  echo "[FoodHub Notify] Aucun destinataire actif. Rien à envoyer.\n";
  exit(0);
}

// ── Vérifier qu'on n'a pas déjà envoyé dans les 10 dernières minutes
// (protection anti-double lancement si le .bat est relancé vite)
$recent = $conn->query("
    SELECT COUNT(*) FROM notify_log
    WHERE statut = 'ok'
      AND date_envoi >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
")->fetchColumn();

if ($recent > 0) {
  echo "[FoodHub Notify] Notification déjà envoyée il y a moins de 10 min. Annulé.\n";
  exit(0);
}

// ── Heure actuelle (Paris) ───────────────────────────────────
date_default_timezone_set('Europe/Paris');
$heure = date('H\hi');
$date = date('d/m/Y');

// ── Corps du mail (HTML) ─────────────────────────────────────
function buildHtml(string $nom, string $siteUrl, string $heure, string $date): string
{
  // On échappe les variables dynamiques
  $nomEsc = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
  $urlEsc = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

  return <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodHub est en ligne !</title>
    </head>
    <body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f6f6f6;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:30px 0;">
        <tr>
          <td align="center">
            <table width="600" cellpadding="0" cellspacing="0"
                   style="background:#ffffff;border-radius:16px;overflow:hidden;
                          box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:600px;width:100%;">

              <!-- Header -->
              <tr>
                <td style="background:linear-gradient(135deg,#ff6b6b,#ffc342);
                           padding:36px 40px 28px 40px;text-align:center;">
                  <h1 style="margin:0;color:#fff;font-size:2rem;letter-spacing:1px;">🍽️ FoodHub</h1>
                  <p style="margin:8px 0 0 0;color:rgba(255,255,255,0.9);font-size:1.05rem;">
                    Le site est maintenant en ligne !
                  </p>
                </td>
              </tr>

              <!-- Corps -->
              <tr>
                <td style="padding:36px 40px;">
                  <p style="margin:0 0 18px 0;font-size:1.1rem;color:#333;">
                    Bonjour <strong>{$nomEsc}</strong> 👋
                  </p>
                  <p style="margin:0 0 18px 0;color:#555;line-height:1.6;">
                    Le serveur de FoodHub vient d'être lancé et est accessible en ce moment même.<br>
                    Tu peux commander, explorer les restaurants et laisser tes avis !
                  </p>

                  <!-- Bloc info -->
                  <table width="100%" cellpadding="0" cellspacing="0"
                         style="background:#fff7f0;border-radius:12px;border-left:4px solid #ff6b6b;
                                margin:24px 0;padding:0;">
                    <tr>
                      <td style="padding:18px 22px;">
                        <p style="margin:0 0 6px 0;font-weight:700;color:#ff6b6b;font-size:0.9rem;
                                   text-transform:uppercase;letter-spacing:0.5px;">Infos de connexion</p>
                        <p style="margin:4px 0;color:#444;font-size:0.95rem;">
                          Date : <strong>{$date}</strong>
                        </p>
                        <p style="margin:4px 0;color:#444;font-size:0.95rem;">
                          Heure : <strong>{$heure}</strong>
                        </p>
                        <p style="margin:4px 0;color:#444;font-size:0.95rem;">
                          Lien :
                          <a href="{$urlEsc}" style="color:#ff6b6b;font-weight:600;">{$urlEsc}</a>
                        </p>
                        <p style="margin:4px 0;color:#444;font-size:0.95rem;">
                          Lien accessible 24H/24 :
                          <a href="https://foodhub-sio.alwaysdata.net/index.php" style="color:#ff6b6b;font-weight:600;">https://foodhub-sio.alwaysdata.net/</a>
                        </p>
                      </td>
                    </tr>
                  </table>

                  <!-- Bouton CTA -->
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td align="center" style="padding:10px 0 20px 0;">
                        <a href="{$urlEsc}"
                           style="display:inline-block;padding:14px 36px;
                                  background:linear-gradient(135deg,#ff6b6b,#ffc342);
                                  color:#ffffff;text-decoration:none;font-weight:700;
                                  font-size:1.1rem;border-radius:12px;
                                  box-shadow:0 4px 14px rgba(255,107,107,0.4);">
                          Accéder au site →
                        </a>
                      </td>
                    </tr>
                  </table>
                  
                  
                  <p style="margin:0;color:#888;font-size:0.85rem;line-height:1.5;">
                    Le premier lien est susceptible de ne pas fonctionner (tunnel ngrok). Il sera valide tant que le serveur
                    est allumé. En cas d'indisponibilité, réessaie un peu plus tard et un nouveau lien sera envoyé dès que le serveur sera de nouveau route.
                  </p>
                  <p style="margin:0;color:#888;font-size:0.85rem;line-height:1.5;">
                    <b>Note :</b> Les serveurs n'échangent PAS de données. Gardez cela en tête lorsque vous faites des actions sur le site.
                  </p>
                </td>
              </tr>

              <!-- Footer -->
              <tr>
                <td style="background:#f9f9f9;padding:18px 40px;text-align:center;
                           border-top:1px solid #eee;">
                  <p style="margin:0;color:#aaa;font-size:0.8rem;">
                    Tu reçois cet email car tu es inscrit(e) sur la liste de notifications FoodHub.<br>
                    Pour te désinscrire, contacte l'administrateur (GLWARO_SQL).
                  </p>
                </td>
              </tr>

            </table>
          </td>
        </tr>
      </table>
    </body>
    </html>
    HTML;
}

// ── Envoi ────────────────────────────────────────────────────
$nb_ok = 0;
$nb_erreur = 0;
$details = [];

foreach ($destinataires as $dest) {
  $mail = new PHPMailer(true);
  try {
    // Serveur SMTP
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;
    $mail->CharSet = 'UTF-8';

    // Expéditeur & destinataire
    $mail->setFrom($mail_from, $mail_from_nom);
    $mail->addAddress($dest['email'], $dest['nom']);

    // Contenu
    $mail->isHTML(true);
    $mail->Subject = "🍽️ FoodHub est en ligne ! — {$date} à {$heure}";
    $mail->Body = buildHtml($dest['nom'], $site_url, $heure, $date);
    $mail->AltBody = "Bonjour {$dest['nom']},\n\nFoodHub est en ligne !\nAccède au site ici : {$site_url}\n\nDate : {$date} — {$heure}";

    // envoi du mail

    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = 'html';
    $mail->send();
    $nb_ok++;
    echo "[FoodHub Notify] ✅ Mail envoyé à {$dest['email']}\n";
    $details[] = "OK: {$dest['email']}";
  } catch (Exception $e) {
    $nb_erreur++;
    $errMsg = $mail->ErrorInfo;
    echo "[FoodHub Notify] ❌ Échec pour {$dest['email']} : {$errMsg}\n";
    $details[] = "ERR: {$dest['email']} — {$errMsg}";
  }
}

// ── Log en BDD ───────────────────────────────────────────────
$statut = ($nb_erreur === 0) ? 'ok' : 'erreur';
$detail = implode("\n", $details);

$logStmt = $conn->prepare('
    INSERT INTO notify_log (nb_envoyes, statut, detail)
    VALUES (?, ?, ?)
');
$logStmt->execute([$nb_ok, $statut, $detail]);

echo "\n[FoodHub Notify] Terminé — {$nb_ok} envoyé(s), {$nb_erreur} échec(s).\n";
exit(0);

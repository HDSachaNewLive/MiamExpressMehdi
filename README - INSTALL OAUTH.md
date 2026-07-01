# IMPORTANT - Installation et configuration Google OAuth pour FoodHub 3.0
# REMARQUE: VOUS N'AVEZ PAS L'OBLIGATION D'UTILISER DOTENV, POUR CE FAIRE, APRES AVOIR TÉLÉCHARGÉ LA DERNIÈRE RELEASE/ZIP DU PROJET, SUPPRIMER LE FICHIER config_google.php ET RENONMMEZ config_google_sans_env.php EN config_google.php PUIS AJOUTEZ VOS CLES A LA LIGNE 6 et 7 !!
## Cloner/Télécharger le projet

```bash
git clone https://github.com/HDSachaNewLive/MiamExpressMehdi.git
cd MiamExpressMehdi
```
Ou téléchargez la dernière release (3.0.1), et extrayez le dossier dans C:/wamp64/www/ et renommez-le foodhub (en minuscule IMPORTANT)
---

## Installer les dépendances PHP

Assurez-vous d’avoir **Composer** installé et choisissez la bonne version de PHP (pour vérifier votre version => Clic droit > PHP (numéro de version) : [https://getcomposer.org/](https://getcomposer.org/)
<img width="1482" height="419" alt="image" src="https://github.com/user-attachments/assets/c17fd727-92b8-469b-948e-b2dd93f10cf8" />

Lancez cette commande à la racine du projet (cliquez sur la barre d'adresse et tapez "cmd" directement puis Entrée) :
<img width="1613" height="637" alt="image" src="https://github.com/user-attachments/assets/8505903d-3c0b-4dc1-89de-74a4c3d983ca" />

Puis celle là :

```bash
cd C:\wamp64\www\foodhub
composer require vlucas/phpdotenv
```

> Cela va créer le dossier `vendor/` nécessaire pour utiliser dotenv et charger les variables d’environnement.

# SI VOUS AVEZ DES ERREURS :
Ré-installez dotenv pour PHP

Dans le terminal, place-vous dans le dossier du projet www/foodhub/ :

cd C:\wamp64\www\foodhub
composer require vlucas/phpdotenv

Ça va créer/réinstaller le dossier vendor/ et ajouter Dotenv(si il est pas déjà présent).
---

## Préparer le fichier de configuration des clés

1. Copier le fichier `.env.example`
2. Renommer la copie en `.env`

```bash
cp .env.example .env  # ou simplement copier/coller dans l’explorateur Windows
```

3. Remplir vos propres clés Google OAuth dans `.env` :

```env
GOOGLE_CLIENT_ID=ici_votre_client_id
GOOGLE_CLIENT_SECRET=ici_votre_client_secret
```

> Important : **ne jamais push votre `.env` sur GitHub**

---

## Créer un projet Google et récupérer les clés

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un projet
3. Activez **OAuth 2.0**
4. Créez un identifiant **Application Web**
5. Ajouter l’URI de redirection :

```
http://localhost/google_callback.php
```

6. Copier le **Client ID** et le **Client Secret** dans votre `.env`

---

## Charger dotenv dans le projet

Dans `config_google.php` (ou le fichier principal avant tout code) :

```php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI', 'http://localhost/google_callback.php');

if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
    die('Google OAuth non configuré. Voir README.md');
}
```

---

## Tester

Ajoutez temporairement pour vérifier que tout est bien chargé :

```php
var_dump($_ENV['GOOGLE_CLIENT_ID']);
```

Si la clé s’affiche Google OAuth devrait fonctionner

---
## Lancer le projet

* Démarre ton serveur WAMP
* Accède à `http://localhost/foodhub/`
* Clique sur **Se connecter avec Google** → ça doit fonctionner

---
## Notes importantes pour GitHub

* `.env.example` → pour les utilisateurs, pushé sur GitHub
* `.env` → contient les clés personnelles, **jamais** pushé
* Toute personne qui télécharge le projet doit faire **composer install** à la racine et configurer son `.env`

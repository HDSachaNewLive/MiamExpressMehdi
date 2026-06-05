<?php
// skip_google_setup.php
// Supprime le flag nouveau_compte_google si l'utilisateur clique sur "Passer cette étape"
session_start();
unset($_SESSION['nouveau_compte_google']);
// Pas de redirect ici, le lien href="home.php" s'en charge côté HTML

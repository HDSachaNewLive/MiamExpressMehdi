<?php
// not_found_helper.php
// Usage :
//   abort_404('restaurant');  → message restaurant
//   abort_404('profil');      → message profil
//   abort_404('commande');    → message commande
//   abort_404('plat');        → message plat
//   abort_404('avis');        → message avis
//   abort_404('panier');      → message panier
//   abort_404('admin');       → message admin
//   abort_404('forum');       → message forum
//   abort_404('coupon');      → message coupon
//   abort_404();              → message générique aléatoire

if (!function_exists('abort_404')) {
    function abort_404(string $context = ''): never
    {
        // Injecter le contexte dans REQUEST_URI pour que 404.php
        // le détecte via stripos($path, $keyword)
        if ($context !== '') {
            $_SERVER['REQUEST_URI'] = '/' . ltrim($context, '/');
        }

        $path_404 = __DIR__ . '/404.php';
        if (file_exists($path_404)) {
            require $path_404;
        } else {
            http_response_code(404);
            echo '<h1>404 - Page introuvable</h1>';
        }
        exit;
    }
}
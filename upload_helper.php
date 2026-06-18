<?php
// upload_helper.php
// Helpers pour valider et stocker les images uploadées en toute sécurité.

/**
 * Valide et déplace une image uploadée.
 * Retourne ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function fh_handle_image_upload(array $file, string $destDir, int $maxBytes = 2 * 1024 * 1024, ?array $allowedMime = null): array {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if ($allowedMime === null) {
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }

    if (!isset($file) || !isset($file['error'])) {
        return ['success' => false, 'filename' => null, 'error' => 'Aucun fichier reçu'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'Erreur lors de l\'upload.'];
    }

    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'filename' => null, 'error' => 'Fichier trop volumineux.'];
    }

    if (!is_dir($destDir) || !is_writable($destDir)) {
        return ['success' => false, 'filename' => null, 'error' => 'Répertoire de destination non accessible.'];
    }

    // Vérifier le type MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMime, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Type de fichier non autorisé.'];
    }

    // Déduire extension à partir du mime
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = strtolower($ext ?: 'dat');

    // Générer nom de fichier aléatoire
    $basename = bin2hex(random_bytes(16));
    $filename = $basename . '.' . $ext;
    $target = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => false, 'filename' => null, 'error' => 'Impossible de déplacer le fichier.'];
    }

    // Permissions plus strictes
    @chmod($target, 0644);

    return ['success' => true, 'filename' => $filename, 'error' => null];
}


/**
 * Traite un champ d'upload `$_FILES[$fieldName]` qui peut contenir
 * un seul fichier ou plusieurs (inputs name="photos[]").
 * Retourne ['success' => bool, 'results' => array, 'error' => string|null]
 * où 'results' est une liste de retours produits par `fh_handle_image_upload()`.
 */
function fh_handle_uploaded_field(string $fieldName, string $destDir, int $maxBytes = 2 * 1024 * 1024, ?array $allowedMime = null): array {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_FILES[$fieldName])) {
        return ['success' => false, 'results' => [], 'error' => 'Aucun fichier reçu'];
    }

    $field = $_FILES[$fieldName];
    $results = [];

    // Cas : champ multiple (name[]=...)
    if (is_array($field['name'])) {
        $count = count($field['name']);
        for ($i = 0; $i < $count; $i++) {
            // Construire un tableau file 'single' compatible avec move_uploaded_file
            $single = [
                'name'     => $field['name'][$i],
                'type'     => $field['type'][$i] ?? null,
                'tmp_name' => $field['tmp_name'][$i] ?? null,
                'error'    => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i] ?? 0,
            ];

            if ($single['error'] !== UPLOAD_ERR_OK) {
                $results[] = ['success' => false, 'filename' => null, 'error' => 'Aucun fichier sélectionné ou erreur d\'upload.'];
                continue;
            }

            $res = fh_handle_image_upload($single, $destDir, $maxBytes, $allowedMime);
            $results[] = $res;
        }

        $anySuccess = count(array_filter($results, function($r){ return isset($r['success']) && $r['success']; })) > 0;
        return ['success' => $anySuccess, 'results' => $results, 'error' => null];
    }

    // Cas : fichier unique
    if ($field['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'results' => [], 'error' => 'Erreur lors de l\'upload.'];
    }

    $res = fh_handle_image_upload($field, $destDir, $maxBytes, $allowedMime);
    return ['success' => $res['success'], 'results' => [$res], 'error' => $res['error']];
}

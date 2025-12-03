<?php
// config_recaptcha.php
// Clés reCAPTCHA v2 (à obtenir sur https://www.google.com/recaptcha/admin)
define('RECAPTCHA_SITE_KEY', '6LfE7hosAAAAANMyhFUepVZDmzLvOWNgZNlj_Clx');
define('RECAPTCHA_SECRET_KEY', '6LfE7hosAAAAAEZ6GOHcYA8TIhI5tq3FW3ckWBCZ');

/**
 * Vérifie la réponse reCAPTCHA
 * @param string $response Le token reCAPTCHA
 * @return bool True si valide, False sinon
 */
function verifyRecaptcha($response) {
    if (empty($response)) {
        return false;
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return false;
    }
    
    $resultJson = json_decode($result, true);
    return isset($resultJson['success']) && $resultJson['success'] === true;
}
?>
<?php
require_once __DIR__ . '/../config/init.php';

// Vide la session et supprime le cookie de session
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Nouvelle session pour pouvoir afficher le message
session_start();
definir_message('success', 'Vous etes deconnecte.');
rediriger('/auth/connexion.php');

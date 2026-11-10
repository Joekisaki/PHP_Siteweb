<?php
// Demarrage de la session + fonctions utiles

session_start();

// Dossier du projet dans l'URL : http://localhost/VoteCampus
define('BASE_URL', '/VoteCampus');

require_once __DIR__ . '/database.php';

// Echappe le texte avant affichage (protection XSS)
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

// Redirige vers une page du projet (ex: rediriger('/index.php'))
function rediriger($chemin)
{
    header('Location: ' . BASE_URL . $chemin);
    exit;
}

// L'utilisateur est-il connecte ?
function est_connecte()
{
    return isset($_SESSION['user_id']);
}

// Bloque l'acces aux pages reservees aux utilisateurs connectes
function exiger_connexion()
{
    if (!est_connecte()) {
        rediriger('/auth/connexion.php');
    }
}

// Retourne les infos de l'utilisateur connecte (ou null)
function utilisateur_courant($pdo)
{
    if (!est_connecte()) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// Messages flash (affiches une seule fois)
function definir_message($type, $texte)
{
    $_SESSION['flash'] = ['type' => $type, 'texte' => $texte];
}

function recuperer_message()
{
    if (isset($_SESSION['flash'])) {
        $message = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $message;
    }
    return null;
}

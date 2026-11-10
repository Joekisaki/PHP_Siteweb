<?php
// En-tete commun. Avant de l'inclure, la page doit charger init.php.
// Variables optionnelles : $titre_page (string), $page_auth (bool, pas de barre de navigation)

$titre_page = $titre_page ?? 'VoteCampus';
$page_auth = $page_auth ?? false;
$utilisateur = est_connecte() ? utilisateur_courant($pdo) : null;
$message = $page_auth ? null : recuperer_message();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titre_page) ?> - VoteCampus</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<?php if (!$page_auth): ?>
<nav class="navbar navbar-expand-lg navbar-vc">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">Vote<span> Campus</span></a>
    <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
    </button>
    <div class="collapse navbar-collapse" id="menuPrincipal">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if ($utilisateur): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php">Annonces</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/elections/creer.php">Creer une election</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/compte/mon_compte.php">Profile</a></li>
          <?php if ($utilisateur['est_premier_enseignant']): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/valider_enseignants.php">Validation des enseignants</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if ($utilisateur): ?>
          <li class="nav-item"><span class="navbar-text me-3"><?= e($utilisateur['prenom']) ?></span></li>
          <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= BASE_URL ?>/auth/deconnexion.php">Deconnexion</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/connexion.php">Connexion</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/inscription.php">Inscription</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php if ($message): ?>
    <div class="alert alert-<?= e($message['type']) ?>" role="alert"><?= e($message['texte']) ?></div>
  <?php endif; ?>
<?php endif; ?>

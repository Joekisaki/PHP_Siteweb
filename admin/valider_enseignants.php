<?php
require_once __DIR__ . '/../config/init.php';
exiger_connexion();

$utilisateur = utilisateur_courant($pdo);
if (!$utilisateur) {
    rediriger('/auth/deconnexion.php');
}

// Page reservee au premier enseignant de chaque classe
if (!$utilisateur['est_premier_enseignant']) {
    definir_message('danger', "Cette page est reservee au premier enseignant de la classe.");
    rediriger('/index.php');
}

// Actions : valider ou refuser un compte en attente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && $action === 'valider') {
        $stmt = $pdo->prepare(
            "UPDATE users SET est_valide = 1
             WHERE id = ? AND role = 'enseignant' AND est_valide = 0 AND classe = ?"
        );
        $stmt->execute([$id, $utilisateur['classe']]);
        if ($stmt->rowCount() > 0) {
            definir_message('success', 'Le compte a ete valide.');
        }
    } elseif ($id > 0 && $action === 'refuser') {
        $stmt = $pdo->prepare(
            "DELETE FROM users
             WHERE id = ? AND role = 'enseignant' AND est_valide = 0 AND est_premier_enseignant = 0 AND classe = ?"
        );
        $stmt->execute([$id, $utilisateur['classe']]);
        if ($stmt->rowCount() > 0) {
            definir_message('info', 'Le compte a ete refuse et supprime.');
        }
    }

    rediriger('/admin/valider_enseignants.php');
}

// Enseignants de MA classe en attente de validation
$stmt = $pdo->prepare(
    "SELECT id, nom, prenom, email, universite, filiere, classe, date_inscription
     FROM users
     WHERE role = 'enseignant' AND est_valide = 0 AND classe = ?
     ORDER BY date_inscription ASC"
);
$stmt->execute([$utilisateur['classe']]);
$en_attente = $stmt->fetchAll();

// Enseignants deja valides dans MA classe
$stmt = $pdo->prepare(
    "SELECT nom, prenom, email, universite, est_premier_enseignant
     FROM users
     WHERE role = 'enseignant' AND est_valide = 1 AND classe = ?
     ORDER BY est_premier_enseignant DESC, nom ASC"
);
$stmt->execute([$utilisateur['classe']]);
$valides = $stmt->fetchAll();

$titre_page = 'Validation des enseignants';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="titre-page h3 mb-1">Validation des enseignants</h1>
<p class="text-muted mb-4">Classe : <strong><?= e($utilisateur['classe']) ?></strong></p>

<div class="card-vc p-4 mb-4">
  <h2 class="h5 titre-page mb-3">
    Comptes en attente
    <span class="badge badge-vc ms-1"><?= count($en_attente) ?></span>
  </h2>

  <?php if (!$en_attente): ?>
    <p class="text-muted mb-0">Aucun compte en attente de validation.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Universite</th>
            <th>Filiere / Classe</th>
            <th>Inscrit le</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($en_attente as $enseignant): ?>
            <tr>
              <td><?= e($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></td>
              <td><?= e($enseignant['email']) ?></td>
              <td><?= e($enseignant['universite']) ?></td>
              <td><?= e($enseignant['filiere'] . ' / ' . $enseignant['classe']) ?></td>
              <td><?= e(date('d/m/Y', strtotime($enseignant['date_inscription']))) ?></td>
              <td class="text-end">
                <form method="post" action="" class="d-inline">
                  <input type="hidden" name="id" value="<?= (int) $enseignant['id'] ?>">
                  <button type="submit" name="action" value="valider" class="btn btn-primary btn-sm">Valider</button>
                </form>
                <form method="post" action="" class="d-inline"
                      onsubmit="return confirm('Refuser et supprimer ce compte ?');">
                  <input type="hidden" name="id" value="<?= (int) $enseignant['id'] ?>">
                  <button type="submit" name="action" value="refuser" class="btn btn-outline-danger btn-sm">Refuser</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card-vc p-4">
  <h2 class="h5 titre-page mb-3">Enseignants valides</h2>
  <ul class="list-group list-group-flush">
    <?php foreach ($valides as $enseignant): ?>
      <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
        <span>
          <?= e($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
          <span class="text-muted small">- <?= e($enseignant['email']) ?> - <?= e($enseignant['universite']) ?></span>
        </span>
        <?php if ($enseignant['est_premier_enseignant']): ?>
          <span class="badge badge-vc">Premier enseignant de la classe</span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

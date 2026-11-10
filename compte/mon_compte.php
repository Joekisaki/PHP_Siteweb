<?php
require_once __DIR__ . '/../config/init.php';
exiger_connexion();

$utilisateur = utilisateur_courant($pdo);
if (!$utilisateur) {
    rediriger('/auth/deconnexion.php');
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (!isset($_POST['confirmation'])) {
        $erreur = 'Cochez la case pour confirmer la suppression.';
    } elseif (!password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
        $erreur = 'Mot de passe incorrect.';
    } else {
        try {
            $pdo->beginTransaction();

            // Images des elections creees par cet utilisateur (supprimees avec le compte)
            $stmt = $pdo->prepare('SELECT image FROM elections WHERE createur_id = ? AND image IS NOT NULL');
            $stmt->execute([$utilisateur['id']]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Si c'est le premier enseignant de sa classe, on transmet son role
            // pour que la validation des enseignants de cette classe reste possible
            if ($utilisateur['est_premier_enseignant']) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM users
                     WHERE role = 'enseignant' AND est_valide = 1 AND id <> ? AND classe = ?
                     ORDER BY id ASC LIMIT 1"
                );
                $stmt->execute([$utilisateur['id'], $utilisateur['classe']]);
                $suivant = $stmt->fetchColumn();

                if (!$suivant) {
                    // Aucun enseignant valide : on valide le plus ancien en attente
                    $stmt = $pdo->prepare(
                        "SELECT id FROM users
                         WHERE role = 'enseignant' AND est_valide = 0 AND id <> ? AND classe = ?
                         ORDER BY id ASC LIMIT 1"
                    );
                    $stmt->execute([$utilisateur['id'], $utilisateur['classe']]);
                    $suivant = $stmt->fetchColumn();
                }

                if ($suivant) {
                    $stmt = $pdo->prepare('UPDATE users SET est_valide = 1, est_premier_enseignant = 1 WHERE id = ?');
                    $stmt->execute([$suivant]);
                }
            }

            // Suppression du compte (les elections, candidats et votes lies partent en cascade)
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$utilisateur['id']]);

            $pdo->commit();

            // Suppression des fichiers images
            foreach ($images as $image) {
                $chemin = __DIR__ . '/../assets/uploads/elections/' . basename($image);
                if (is_file($chemin)) {
                    unlink($chemin);
                }
            }

            // Deconnexion automatique
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();

            session_start();
            definir_message('success', 'Votre compte a ete supprime.');
            rediriger('/auth/connexion.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erreur = 'Une erreur est survenue pendant la suppression. Reessayez.';
        }
    }
}

$titre_page = 'Mon compte';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <h1 class="titre-page h3 mb-4">Mon compte</h1>

    <div class="card-vc p-4 mb-4">
      <h2 class="h5 titre-page mb-3">Mes informations</h2>
      <dl class="row mb-0">
        <dt class="col-sm-4">Nom</dt>
        <dd class="col-sm-8"><?= e($utilisateur['nom']) ?></dd>

        <dt class="col-sm-4">Prenom</dt>
        <dd class="col-sm-8"><?= e($utilisateur['prenom']) ?></dd>

        <dt class="col-sm-4">Email</dt>
        <dd class="col-sm-8"><?= e($utilisateur['email']) ?></dd>

        <dt class="col-sm-4">Universite</dt>
        <dd class="col-sm-8"><?= e($utilisateur['universite']) ?></dd>

        <dt class="col-sm-4">Filiere</dt>
        <dd class="col-sm-8"><?= e($utilisateur['filiere']) ?></dd>

        <dt class="col-sm-4">Classe</dt>
        <dd class="col-sm-8"><?= e($utilisateur['classe']) ?></dd>

        <dt class="col-sm-4">Role</dt>
        <dd class="col-sm-8">
          <?= $utilisateur['role'] === 'enseignant' ? 'Enseignant' : 'Etudiant' ?>
          <?php if ($utilisateur['est_premier_enseignant']): ?>
            <span class="badge badge-vc ms-1">Premier enseignant de la classe</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-4">Inscrit le</dt>
        <dd class="col-sm-8 mb-0"><?= e(date('d/m/Y', strtotime($utilisateur['date_inscription']))) ?></dd>
      </dl>
    </div>

    <div class="card-vc p-4 border border-danger-subtle">
      <h2 class="h5 mb-2" style="color: var(--vc-red);">Supprimer mon compte</h2>
      <p class="text-muted">
        Cette action est definitive. Vos votes et les elections que vous avez creees
        (avec leurs candidats et leurs votes) seront aussi supprimes.
      </p>

      <?php if ($erreur): ?>
        <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="mb-3">
          <label for="mot_de_passe" class="form-label">Confirmez avec votre mot de passe</label>
          <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="confirmation" name="confirmation" value="1">
          <label class="form-check-label" for="confirmation">
            Je comprends que cette suppression est irreversible.
          </label>
        </div>

        <button type="submit" class="btn btn-danger">Supprimer definitivement mon compte</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

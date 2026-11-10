<?php
require_once __DIR__ . '/../config/init.php';
exiger_connexion();

$utilisateur = utilisateur_courant($pdo);
if (!$utilisateur) {
    rediriger('/auth/deconnexion.php');
}

// Recuperation de l'election
$election_id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, u.prenom AS createur_prenom, u.nom AS createur_nom
     FROM elections e
     JOIN users u ON u.id = e.createur_id
     WHERE e.id = ?'
);
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    definir_message('danger', 'Election introuvable.');
    rediriger('/index.php');
}

// Droit de participation : publique, ou privee de la meme classe
if ($election['type'] === 'privee' && $election['classe'] !== $utilisateur['classe']) {
    definir_message('danger', "Cette election est privee : elle est reservee a la classe " . $election['classe'] . '.');
    rediriger('/index.php');
}

// Un seul vote par election et par utilisateur
$stmt = $pdo->prepare('SELECT id FROM votes WHERE election_id = ? AND user_id = ?');
$stmt->execute([$election['id'], $utilisateur['id']]);
if ($stmt->fetch()) {
    definir_message('info', 'Vous avez deja vote pour cette election.');
    rediriger('/elections/resultats.php?id=' . (int) $election['id']);
}

// Liste des candidats
$stmt = $pdo->prepare('SELECT id, nom FROM candidats WHERE election_id = ? ORDER BY id');
$stmt->execute([$election['id']]);
$candidats = $stmt->fetchAll();

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidat_id = (int) ($_POST['candidat_id'] ?? 0);

    // Le candidat doit appartenir a cette election
    $ids_valides = array_column($candidats, 'id');
    if (!in_array($candidat_id, array_map('intval', $ids_valides), true)) {
        $erreur = 'Veuillez choisir un candidat.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO votes (election_id, candidat_id, user_id) VALUES (?, ?, ?)');
            $stmt->execute([$election['id'], $candidat_id, $utilisateur['id']]);

            definir_message('success', 'Votre vote a bien ete enregistre.');
            rediriger('/elections/resultats.php?id=' . (int) $election['id']);
        } catch (PDOException $ex) {
            // Code 23000 : la contrainte unique (election, utilisateur) bloque le double vote
            if ($ex->getCode() === '23000') {
                definir_message('info', 'Vous avez deja vote pour cette election.');
                rediriger('/elections/resultats.php?id=' . (int) $election['id']);
            }
            $erreur = "Une erreur est survenue pendant l'enregistrement du vote. Reessayez.";
        }
    }
}

$titre_page = 'Voter';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card-vc overflow-hidden">
      <?php if ($election['image']): ?>
        <img src="<?= BASE_URL ?>/assets/uploads/elections/<?= e($election['image']) ?>"
             alt="<?= e($election['titre']) ?>" class="election-image">
      <?php endif; ?>

      <div class="p-4">
        <div class="mb-2">
          <?php if ($election['type'] === 'publique'): ?>
            <span class="badge badge-vc">Publique</span>
          <?php else: ?>
            <span class="badge text-bg-secondary">Privée : <?= e($election['classe']) ?></span>
          <?php endif; ?>
        </div>

        <h1 class="titre-page h3"><?= e($election['titre']) ?></h1>
        <p class="text-muted small mb-4">
          Creée par <?= e($election['createur_prenom'] . ' ' . $election['createur_nom']) ?>
          le <?= e(date('d/m/Y', strtotime($election['date_creation']))) ?>
        </p>

        <?php if ($erreur): ?>
          <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
        <?php endif; ?>

        <form method="post" action="">
          <p class="form-label">Choisissez votre candidat</p>

          <div class="list-group mb-4">
            <?php foreach ($candidats as $candidat): ?>
              <label class="list-group-item d-flex align-items-center gap-3">
                <input class="form-check-input mt-0" type="radio" name="candidat_id"
                       value="<?= (int) $candidat['id'] ?>" required>
                <span><?= e($candidat['nom']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <p class="text-muted small">Attention : un vote ne peut pas etre modifie une fois envoye.</p>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Voter</button>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">Annuler</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

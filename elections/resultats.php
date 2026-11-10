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

// Meme regle d'acces que pour le vote
if ($election['type'] === 'privee' && $election['classe'] !== $utilisateur['classe']) {
    definir_message('danger', "Cette election est privee : elle est reservee a la classe " . $election['classe'] . '.');
    rediriger('/index.php');
}

// Resultats : nombre de votes par candidat
$stmt = $pdo->prepare(
    'SELECT c.id, c.nom, COUNT(v.id) AS nb_votes
     FROM candidats c
     LEFT JOIN votes v ON v.candidat_id = c.id
     WHERE c.election_id = ?
     GROUP BY c.id, c.nom
     ORDER BY nb_votes DESC, c.nom ASC'
);
$stmt->execute([$election['id']]);
$resultats = $stmt->fetchAll();

$total_votes = 0;
foreach ($resultats as $ligne) {
    $total_votes += (int) $ligne['nb_votes'];
}

// Gagnant(s) : candidats avec le maximum de votes
$max_votes = $resultats ? (int) $resultats[0]['nb_votes'] : 0;
$gagnants = [];
if ($total_votes > 0) {
    foreach ($resultats as $ligne) {
        if ((int) $ligne['nb_votes'] === $max_votes) {
            $gagnants[] = $ligne['nom'];
        }
    }
}

// L'utilisateur a-t-il deja vote ?
$stmt = $pdo->prepare('SELECT id FROM votes WHERE election_id = ? AND user_id = ?');
$stmt->execute([$election['id'], $utilisateur['id']]);
$a_vote = (bool) $stmt->fetch();

$titre_page = 'Resultats';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
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
            <span class="badge text-bg-secondary">Privee : <?= e($election['classe']) ?></span>
          <?php endif; ?>
          <?php if ($a_vote): ?>
            <span class="badge text-bg-success">Déjà voté</span>
          <?php endif; ?>
        </div>

        <h1 class="titre-page h3"><?= e($election['titre']) ?></h1>
        <p class="text-muted small mb-4">
          Creee par <?= e($election['createur_prenom'] . ' ' . $election['createur_nom']) ?>
          le <?= e(date('d/m/Y', strtotime($election['date_creation']))) ?>
          - <strong><?= $total_votes ?></strong> vote<?= $total_votes > 1 ? 's' : '' ?>
        </p>

        <?php if ($total_votes === 0): ?>
          <div class="alert alert-info">Aucun vote pour le moment.</div>
        <?php elseif (count($gagnants) === 1): ?>
          <div class="alert alert-success">En tete : <strong><?= e($gagnants[0]) ?></strong></div>
        <?php else: ?>
          <div class="alert alert-warning">Egalite entre : <strong><?= e(implode(', ', $gagnants)) ?></strong></div>
        <?php endif; ?>

        <?php foreach ($resultats as $ligne): ?>
          <?php
            $nb = (int) $ligne['nb_votes'];
            $pourcentage = $total_votes > 0 ? round($nb * 100 / $total_votes, 1) : 0;
            $en_tete = $total_votes > 0 && $nb === $max_votes;
            $couleur = $en_tete ? 'var(--vc-teal)' : 'var(--vc-navy)';
          ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-semibold"><?= e($ligne['nom']) ?></span>
              <span class="text-muted"><?= $nb ?> vote<?= $nb > 1 ? 's' : '' ?> (<?= $pourcentage ?> %)</span>
            </div>
            <div class="progress" style="height: 14px;" role="progressbar"
                 aria-valuenow="<?= $pourcentage ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: <?= $pourcentage ?>%; background-color: <?= $couleur ?>;"></div>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-4">
          <?php if (!$a_vote): ?>
            <a href="<?= BASE_URL ?>/elections/voter.php?id=<?= (int) $election['id'] ?>" class="btn btn-primary">Voter</a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary">Retour aux elections</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

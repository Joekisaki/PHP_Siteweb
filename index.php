<?php
require_once __DIR__ . '/config/init.php';
exiger_connexion();

$utilisateur = utilisateur_courant($pdo);

// Si le hhh compte a ete supprime entre-temps, on deconnecte
if (!$utilisateur) {
    rediriger('/auth/deconnexion.php');
}

// Elections auxquelles l'utilisateur a le droit de participer :
// - les elections publiques
// - les elections privees de sa propre classe
$stmt = $pdo->prepare(
    "SELECT e.*, u.prenom AS createur_prenom, u.nom AS createur_nom,
            (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id AND v.user_id = ?) AS a_vote
     FROM elections e
     JOIN users u ON u.id = e.createur_id
     WHERE e.type = 'publique' OR (e.type = 'privee' AND e.classe = ?)
     ORDER BY e.date_creation DESC"
);
$stmt->execute([$utilisateur['id'], $utilisateur['classe']]);
$elections = $stmt->fetchAll();

$titre_page = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="titre-page h3 mb-1">Elections</h1>
    <p class="text-muted mb-0">Bonjour <?= e($utilisateur['prenom']) ?>, voici les elections auxquelles vous pouvez participer.</p>
  </div>
  <a href="<?= BASE_URL ?>/elections/creer.php" class="btn btn-primary">+ Creer une election</a>
</div>

<?php if (!$elections): ?>
  <div class="card-vc p-5 text-center">
    <h2 class="h5 titre-page">Aucune election pour le moment</h2>
    <p class="text-muted">Soyez le premier a en creer une.</p>
    <div>
      <a href="<?= BASE_URL ?>/elections/creer.php" class="btn btn-primary">Creer une election</a>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($elections as $election): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card-vc h-100 overflow-hidden d-flex flex-column">
          <?php if ($election['image']): ?>
            <img src="<?= BASE_URL ?>/assets/uploads/elections/<?= e($election['image']) ?>"
                 alt="<?= e($election['titre']) ?>" class="election-image">
          <?php else: ?>
            <div class="election-image election-image-vide">
              <?= e(mb_strtoupper(mb_substr($election['titre'], 0, 1))) ?>
            </div>
          <?php endif; ?>

          <div class="p-3 d-flex flex-column flex-grow-1">
            <div class="mb-2">
              <?php if ($election['type'] === 'publique'): ?>
                <span class="badge badge-vc">Publique</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Privee : <?= e($election['classe']) ?></span>
              <?php endif; ?>
              <?php if ($election['a_vote']): ?>
                <span class="badge text-bg-success">Déjà voté</span>
              <?php endif; ?>
            </div>

            <h2 class="h5 titre-page"><?= e($election['titre']) ?></h2>
            <p class="text-muted small mb-3">
              Par <?= e($election['createur_prenom'] . ' ' . $election['createur_nom']) ?>
              - <?= e(date('d/m/Y', strtotime($election['date_creation']))) ?>
            </p>

            <div class="mt-auto d-flex gap-2">
              <?php if (!$election['a_vote']): ?>
                <a href="<?= BASE_URL ?>/elections/voter.php?id=<?= (int) $election['id'] ?>" class="btn btn-primary btn-sm flex-fill">Voter</a>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/elections/resultats.php?id=<?= (int) $election['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">Resultats</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/init.php';

// Un utilisateur deja connecte n'a rien a faire ici
if (est_connecte()) {
    rediriger('/index.php');
}

$erreurs = [];
$valeurs = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'universite' => '',
    'filiere' => '',
    'classe' => '',
    'role' => 'etudiant',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valeurs as $champ => $defaut) {
        $valeurs[$champ] = trim($_POST[$champ] ?? '');
    }
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // Validation
    foreach (['nom', 'prenom', 'email', 'universite', 'filiere', 'classe'] as $champ) {
        if ($valeurs[$champ] === '') {
            $erreurs[] = 'Tous les champs sont obligatoires.';
            break;
        }
    }
    if ($valeurs['email'] !== '' && !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse email n'est pas valide.";
    }
    if (!in_array($valeurs['role'], ['etudiant', 'enseignant'], true)) {
        $erreurs[] = 'Le role choisi est invalide.';
    }
    if (strlen($mot_de_passe) < 8) {
        $erreurs[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
    }
    if ($mot_de_passe !== $confirmation) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    // Email deja utilise ?
    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$valeurs['email']]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Un compte existe deja avec cette adresse email.';
        }
    }

    // Enregistrement
    if (!$erreurs) {
        $est_valide = 1;
        $est_premier = 0;
        $en_attente = false;

        if ($valeurs['role'] === 'enseignant') {
            // Premier enseignant de LA CLASSE : valide automatiquement
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'enseignant' AND classe = ?");
            $stmt->execute([$valeurs['classe']]);
            if ((int) $stmt->fetchColumn() === 0) {
                $est_premier = 1;
            } else {
                // Les suivants doivent etre valides par le premier enseignant de leur classe
                $est_valide = 0;
                $en_attente = true;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (nom, prenom, email, mot_de_passe, universite, filiere, classe, role, est_valide, est_premier_enseignant)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $valeurs['nom'],
            $valeurs['prenom'],
            $valeurs['email'],
            password_hash($mot_de_passe, PASSWORD_DEFAULT),
            $valeurs['universite'],
            $valeurs['filiere'],
            $valeurs['classe'],
            $valeurs['role'],
            $est_valide,
            $est_premier,
        ]);

        if ($en_attente) {
            definir_message('info', "Compte cree. Il doit etre valide par le premier enseignant de votre classe avant que vous puissiez vous connecter.");
        } else {
            definir_message('success', 'Compte cree avec succes. Vous pouvez vous connecter.');
        }
        rediriger('/auth/connexion.php');
    }
}

$titre_page = 'Inscription';
$page_auth = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
  <div class="card-vc auth-card">
    <div class="auth-logo">Vote<span>Campus</span></div>
    <p class="auth-subtitle">Creez votre compte pour participer aux elections</p>

    <?php if ($erreurs): ?>
      <div class="alert alert-danger" role="alert">
        <?php foreach ($erreurs as $erreur): ?>
          <div><?= e($erreur) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="nom" class="form-label">Nom</label>
          <input type="text" class="form-control" id="nom" name="nom" placeholder="Entrer votre nom" value="<?= e($valeurs['nom']) ?>" required>
        </div>
        <div class="col-md-6">
          <label for="prenom" class="form-label">Prenom</label>
          <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Entrer votre prenom" value="<?= e($valeurs['prenom']) ?>" required>
        </div>

        <div class="col-12">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" placeholder="Entrer votre email" value="<?= e($valeurs['email']) ?>" required>
        </div>

        <div class="col-md-6">
          <label for="mot_de_passe" class="form-label">Mot de passe</label>
          <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" placeholder="Entrer votre Mot de passe" minlength="8" required>
        </div>
        <div class="col-md-6">
          <label for="confirmation" class="form-label">Confirmation</label>
          <input type="password" class="form-control" id="confirmation" name="confirmation" placeholder="Confirmer votre Mot de passe" minlength="8" required>
        </div>

        <div class="col-12">
          <label for="universite" class="form-label">Universite</label>
          <input type="text" class="form-control" id="universite" name="universite" placeholder="Entrer votre université" value="<?= e($valeurs['universite']) ?>" required>
        </div>

        <div class="col-md-6">
          <label for="filiere" class="form-label">Filiere</label>
          <input type="text" class="form-control" id="filiere" name="filiere" placeholder="Entrer votre filière" value="<?= e($valeurs['filiere']) ?>" required>
        </div>
        <div class="col-md-6">
          <label for="classe" class="form-label">Classe</label>
          <input type="text" class="form-control" id="classe" name="classe" placeholder="Entrer votre classe" value="<?= e($valeurs['classe']) ?>" required>
        </div>

        <div class="col-12">
          <label for="role" class="form-label">Role</label>
          <select class="form-select" id="role" name="role">
            <option value="etudiant" <?= $valeurs['role'] === 'etudiant' ? 'selected' : '' ?>>Etudiant</option>
            <option value="enseignant" <?= $valeurs['role'] === 'enseignant' ? 'selected' : '' ?>>Enseignant</option>
          </select>
        </div>

        <div class="col-12 d-grid mt-4">
          <button type="submit" class="btn btn-primary">Creer mon compte</button>
        </div>
      </div>
    </form>

    <p class="text-center mt-4 mb-0">
      Déjà inscrit ? <a href="<?= BASE_URL ?>/auth/connexion.php">Se connecter</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/init.php';

// Un utilisateur deja connecte n'a rien a faire ici
if (est_connecte()) {
    rediriger('/index.php');
}

$erreur = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $mot_de_passe === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur || !password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
            $erreur = 'Email ou mot de passe incorrect.';
        } elseif (!$utilisateur['est_valide']) {
            $erreur = "Votre compte n'a pas encore ete valide par le premier enseignant.";
        } else {
            // Connexion reussie
            session_regenerate_id(true);
            $_SESSION['user_id'] = $utilisateur['id'];
            rediriger('/index.php');
        }
    }
}

$message = recuperer_message();
$titre_page = 'Connexion';
$page_auth = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
  <div class="card-vc auth-card">
    <div class="auth-logo">Vote<span>Campus</span></div>
    <p class="auth-subtitle">Connectez-vous pour acceder aux elections</p>

    <?php if ($message): ?>
      <div class="alert alert-<?= e($message['type']) ?>" role="alert"><?= e($message['texte']) ?></div>
    <?php endif; ?>

    <?php if ($erreur): ?>
      <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="Entrer votre email" value="<?= e($email) ?>" required autofocus>
      </div>

      <div class="mb-4">
        <label for="mot_de_passe" class="form-label">Mot de passe</label>
        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" placeholder="Entrer votre Mot de passe" required>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Se connecter</button>
      </div>
    </form>

    <p class="text-center mt-4 mb-0">
      Pas encore de compte ? <a href="<?= BASE_URL ?>/auth/inscription.php">S'inscrire</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

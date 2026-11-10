<?php
require_once __DIR__ . '/../config/init.php';
exiger_connexion();

$utilisateur = utilisateur_courant($pdo);
if (!$utilisateur) {
    rediriger('/auth/deconnexion.php');
}

$erreurs = [];
$titre = '';
$type = 'publique';
$candidats = ['', ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $type = $_POST['type'] ?? 'publique';

    // Candidats : on retire les champs vides
    $candidats = [];
    foreach (($_POST['candidats'] ?? []) as $nom) {
        $nom = trim((string) $nom);
        if ($nom !== '') {
            $candidats[] = $nom;
        }
    }

    // Validation
    if ($titre === '') {
        $erreurs[] = "Le titre de l'election est obligatoire.";
    } elseif (mb_strlen($titre) > 200) {
        $erreurs[] = 'Le titre est trop long (200 caracteres maximum).';
    }
    if (!in_array($type, ['publique', 'privee'], true)) {
        $erreurs[] = "Le type d'election est invalide.";
    }
    if (count($candidats) < 2) {
        $erreurs[] = "Il faut au moins 2 candidats.";
    }
    foreach ($candidats as $nom) {
        if (mb_strlen($nom) > 150) {
            $erreurs[] = 'Un nom de candidat est trop long (150 caracteres maximum).';
            break;
        }
    }
    $minuscules = array_map('mb_strtolower', $candidats);
    if (count($minuscules) !== count(array_unique($minuscules))) {
        $erreurs[] = 'Deux candidats ne peuvent pas porter le meme nom.';
    }

    // Image optionnelle
    $nom_image = null;
    $chemin_image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fichier = $_FILES['image'];

        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = "Erreur pendant l'envoi de l'image.";
        } elseif ($fichier['size'] > 2 * 1024 * 1024) {
            $erreurs[] = "L'image est trop lourde (2 Mo maximum).";
        } else {
            $infos = @getimagesize($fichier['tmp_name']);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if ($infos === false || !isset($extensions[$infos['mime']])) {
                $erreurs[] = "Le fichier doit etre une image (JPG, PNG, GIF ou WEBP).";
            } else {
                $nom_image = bin2hex(random_bytes(16)) . '.' . $extensions[$infos['mime']];
                $chemin_image = __DIR__ . '/../assets/uploads/elections/' . $nom_image;
            }
        }
    }

    // Enregistrement
    if (!$erreurs) {
        $image_deplacee = false;
        if ($nom_image !== null) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $chemin_image)) {
                $image_deplacee = true;
            } else {
                $erreurs[] = "Impossible d'enregistrer l'image sur le serveur.";
            }
        }
    }

    if (!$erreurs) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO elections (titre, type, classe, image, createur_id) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$titre, $type, $utilisateur['classe'], $nom_image, $utilisateur['id']]);
            $election_id = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO candidats (election_id, nom) VALUES (?, ?)');
            foreach ($candidats as $nom) {
                $stmt->execute([$election_id, $nom]);
            }

            $pdo->commit();

            definir_message('success', 'Election creee avec succes.');
            rediriger('/index.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if (!empty($image_deplacee) && is_file($chemin_image)) {
                unlink($chemin_image);
            }
            $erreurs[] = "Une erreur est survenue pendant l'enregistrement. Reessayez.";
        }
    }

    if (count($candidats) < 2) {
        // On garde deux champs visibles pour la saisie
        $candidats = array_pad($candidats, 2, '');
    }
}

$titre_page = 'Creer une election';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <h1 class="titre-page h3 mb-4">Creer une election</h1>

    <div class="card-vc p-4">
      <?php if ($erreurs): ?>
        <div class="alert alert-danger" role="alert">
          <?php foreach ($erreurs as $erreur): ?>
            <div><?= e($erreur) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="" enctype="multipart/form-data" id="form-election">
        <div class="mb-3">
          <label for="titre" class="form-label">Titre de l'election</label>
          <input type="text" class="form-control" id="titre" name="titre" placeholder="Entrer le titre de l'élection annoncé" value="<?= e($titre) ?>" maxlength="200" required>
        </div>

        <div class="mb-3">
          <label for="type" class="form-label">Type d'election</label>
          <select class="form-select" id="type" name="type">
            <option value="publique" <?= $type === 'publique' ? 'selected' : '' ?>>Publique : tout le monde peut voter</option>
            <option value="privee" <?= $type === 'privee' ? 'selected' : '' ?>>Privee : uniquement ma classe (<?= e($utilisateur['classe']) ?>)</option>
          </select>
        </div>

        <div class="mb-4">
          <label for="image" class="form-label">Image (optionnelle)</label>
          <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
          <div class="form-text">JPG, PNG, GIF ou WEBP. Vous pourrez la centrer et la recadrer avant l'envoi.</div>

          <div id="editeur-image" class="mt-3 d-none">
            <div id="cadre-image" class="crop-frame">
              <img id="image-source" alt="" draggable="false">
            </div>
            <div class="d-flex align-items-center gap-3 mt-2">
              <span class="small text-muted">Zoom</span>
              <input type="range" class="form-range flex-grow-1" id="zoom-image" min="1" max="3" step="0.01" value="1">
              <button type="button" class="btn btn-outline-danger btn-sm" id="retirer-image">Retirer</button>
            </div>
            <div class="form-text">Glissez l'image pour la centrer. Le cadre correspond a l'affichage final.</div>
          </div>
        </div>

        <div class="mb-2 d-flex justify-content-between align-items-center">
          <label class="form-label mb-0">Candidats (2 minimum)</label>
          <button type="button" class="btn btn-outline-primary btn-sm" id="ajouter-candidat">+ Ajouter un candidat</button>
        </div>

        <div id="liste-candidats" class="mb-4">
          <?php foreach ($candidats as $nom): ?>
            <div class="input-group mb-2 ligne-candidat">
              <input type="text" class="form-control" name="candidats[]" value="<?= e($nom) ?>" maxlength="150" placeholder="Nom du candidat">
              <button type="button" class="btn btn-outline-danger retirer-candidat" title="Retirer">&times;</button>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Creer l'election</button>
          <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary">Annuler</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Ajout / retrait de champs candidats
  var liste = document.getElementById('liste-candidats');

  document.getElementById('ajouter-candidat').addEventListener('click', function () {
    var ligne = document.createElement('div');
    ligne.className = 'input-group mb-2 ligne-candidat';
    ligne.innerHTML =
      '<input type="text" class="form-control" name="candidats[]" maxlength="150" placeholder="Nom du candidat">' +
      '<button type="button" class="btn btn-outline-danger retirer-candidat" title="Retirer">&times;</button>';
    liste.appendChild(ligne);
    ligne.querySelector('input').focus();
  });

  liste.addEventListener('click', function (evenement) {
    var bouton = evenement.target.closest('.retirer-candidat');
    if (!bouton) {
      return;
    }
    // On garde toujours au moins 2 champs
    if (liste.querySelectorAll('.ligne-candidat').length > 2) {
      bouton.closest('.ligne-candidat').remove();
    }
  });
</script>

<script>
  // Recadrage de l'image : on glisse pour centrer, on zoome, puis le resultat
  // (16:9, 1280x720) remplace le fichier envoye au serveur
  (function () {
    var formulaire = document.getElementById('form-election');
    var champ = document.getElementById('image');
    var editeur = document.getElementById('editeur-image');
    var cadre = document.getElementById('cadre-image');
    var img = document.getElementById('image-source');
    var zoom = document.getElementById('zoom-image');
    var retirer = document.getElementById('retirer-image');

    var urlObjet = null;
    var prete = false;
    var nw = 0, nh = 0, fw = 0, fh = 0;
    var base = 1, echelle = 1, x = 0, y = 0;

    function limiter() {
      x = Math.min(0, Math.max(fw - nw * echelle, x));
      y = Math.min(0, Math.max(fh - nh * echelle, y));
    }

    function afficher() {
      img.style.width = (nw * echelle) + 'px';
      img.style.height = (nh * echelle) + 'px';
      img.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    }

    function initialiser() {
      fw = cadre.clientWidth;
      fh = cadre.clientHeight;
      nw = img.naturalWidth;
      nh = img.naturalHeight;
      base = Math.max(fw / nw, fh / nh);
      zoom.value = 1;
      echelle = base;
      x = (fw - nw * echelle) / 2;
      y = (fh - nh * echelle) / 2;
      limiter();
      afficher();
    }

    function liberer() {
      if (urlObjet) {
        URL.revokeObjectURL(urlObjet);
        urlObjet = null;
      }
    }

    function reinitialiserChamp() {
      champ.value = '';
      liberer();
      prete = false;
      editeur.classList.add('d-none');
    }

    champ.addEventListener('change', function () {
      liberer();
      prete = false;
      var fichier = champ.files[0];
      if (!fichier) {
        editeur.classList.add('d-none');
        return;
      }
      if (!/^image\/(jpeg|png|gif|webp)$/.test(fichier.type)) {
        alert('Le fichier doit etre une image (JPG, PNG, GIF ou WEBP).');
        reinitialiserChamp();
        return;
      }
      urlObjet = URL.createObjectURL(fichier);
      img.onload = function () {
        editeur.classList.remove('d-none');
        initialiser();
        prete = true;
      };
      img.onerror = function () {
        alert("Impossible de lire cette image.");
        reinitialiserChamp();
      };
      img.src = urlObjet;
    });

    retirer.addEventListener('click', reinitialiserChamp);

    // Zoom (curseur) : on garde le centre du cadre
    zoom.addEventListener('input', function () {
      var centreX = (fw / 2 - x) / echelle;
      var centreY = (fh / 2 - y) / echelle;
      echelle = base * parseFloat(zoom.value);
      x = fw / 2 - centreX * echelle;
      y = fh / 2 - centreY * echelle;
      limiter();
      afficher();
    });

    // Zoom (molette)
    cadre.addEventListener('wheel', function (evenement) {
      evenement.preventDefault();
      var v = parseFloat(zoom.value) - evenement.deltaY * 0.002;
      zoom.value = Math.min(3, Math.max(1, v));
      zoom.dispatchEvent(new Event('input'));
    }, { passive: false });

    // Deplacement : souris et doigt
    var glisse = false, decalageX = 0, decalageY = 0;

    cadre.addEventListener('pointerdown', function (evenement) {
      glisse = true;
      decalageX = evenement.clientX - x;
      decalageY = evenement.clientY - y;
      cadre.setPointerCapture(evenement.pointerId);
      cadre.classList.add('is-dragging');
    });

    cadre.addEventListener('pointermove', function (evenement) {
      if (!glisse) {
        return;
      }
      x = evenement.clientX - decalageX;
      y = evenement.clientY - decalageY;
      limiter();
      afficher();
    });

    function finGlisse() {
      glisse = false;
      cadre.classList.remove('is-dragging');
    }
    cadre.addEventListener('pointerup', finGlisse);
    cadre.addEventListener('pointercancel', finGlisse);

    window.addEventListener('resize', function () {
      if (prete) {
        initialiser();
      }
    });

    // A l'envoi : on remplace le fichier par la version recadree
    formulaire.addEventListener('submit', function (evenement) {
      if (!prete || !champ.files.length) {
        return;
      }
      evenement.preventDefault();

      var canvas = document.createElement('canvas');
      canvas.width = 1280;
      canvas.height = 720;
      var contexte = canvas.getContext('2d');
      contexte.fillStyle = '#ffffff';
      contexte.fillRect(0, 0, 1280, 720);
      contexte.drawImage(img, -x / echelle, -y / echelle, fw / echelle, fh / echelle, 0, 0, 1280, 720);

      canvas.toBlob(function (blob) {
        if (blob) {
          var transfert = new DataTransfer();
          transfert.items.add(new File([blob], 'image.jpg', { type: 'image/jpeg' }));
          champ.files = transfert.files;
        }
        formulaire.submit();
      }, 'image/jpeg', 0.88);
    });
  })();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

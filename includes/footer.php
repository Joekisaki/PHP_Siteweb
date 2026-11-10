<?php
// Pied de page commun. Utilise la meme variable $page_auth que header.php
$page_auth = $page_auth ?? false;
?>
<?php if (!$page_auth): ?>
</main>

<footer class="footer-vc">
  VoteCampus - Elections universitaires
</footer>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/** Copyable Bootstrap 5 social sign-in recipe. Expected: $providers = [['name'=>'Google','url'=>'...'], ...]. */
?>
<div class="d-grid gap-2">
  <?php foreach ($providers as $provider): ?>
    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($provider['url']) ?>">
      Continue with <?= htmlspecialchars($provider['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

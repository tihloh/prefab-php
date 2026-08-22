<?php
/** Copyable Bootstrap 5 recipe. Expected: $log from LogManager::find(). */
$changes = $log['changes'] ?? [];
$metadata = $log['metadata'] ?? [];
?>
<div class="card">
  <div class="card-header"><strong>Log Details</strong></div>
  <div class="card-body">
    <dl class="row">
      <dt class="col-sm-3">Action</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></dd>
      <dt class="col-sm-3">Subject</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($log['subject_type'] ?? '')) ?> #<?= htmlspecialchars((string)($log['subject_id'] ?? '')) ?></dd>
      <dt class="col-sm-3">Actor</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($log['actor_type'] ?? '')) ?> #<?= htmlspecialchars((string)($log['actor_id'] ?? '')) ?></dd>
      <dt class="col-sm-3">Message</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($log['message'] ?? '')) ?></dd>
    </dl>
    <?php if ($changes): ?><h6>Changes</h6><pre class="bg-body-tertiary p-3 rounded"><?= htmlspecialchars(json_encode($changes, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
    <?php if ($metadata): ?><h6>Metadata</h6><pre class="bg-body-tertiary p-3 rounded mb-0"><?= htmlspecialchars(json_encode($metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
  </div>
</div>

<?php
/**
 * Copyable Bootstrap 5 recipe.
 * Expected: $entries = $logs->recent(100);
 */
?>
<div class="card">
    <div class="card-header"><strong>Activity Logs</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Time</th><th>Action</th><th>Subject</th><th>Actor</th><th>Message</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($entry['created_at'] ?? '')) ?></td>
                    <td><code><?= htmlspecialchars((string)($entry['action'] ?? '')) ?></code></td>
                    <td><?= htmlspecialchars((string)($entry['subject_type'] ?? '')) ?> #<?= htmlspecialchars((string)($entry['subject_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($entry['actor_id'] ?? 'system')) ?></td>
                    <td><?= htmlspecialchars((string)($entry['message'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

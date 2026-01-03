<section class="card">
    <div class="flex-between">
        <div>
            <h2>Alerts & Upcoming Digest</h2>
            <p><?= e(date('M d, Y')) ?> · <?= e($user['name'] ?? '') ?></p>
        </div>
        <div class="header-actions">
            <button type="button" class="button ghost" onclick="window.print()">Print</button>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <p>No alerts or upcoming items.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Client</th>
                        <th>Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $t): ?>
                        <?php $client = find_client($t['client_id']); ?>
                        <tr>
                            <td><?= e($t['title_display'] ?? ($t['title'] ?? 'Task')) ?></td>
                            <td><?= e($client['name'] ?? ($t['client_name'] ?? '')) ?></td>
                            <td><?= e(($t['alert']['date'] ?? null) ?: ($t['due_date'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<style>
@media print {
  body { background: #fff; }
  .button, .header-actions { display: none !important; }
}
</style>

<script>
  try { setTimeout(function(){ window.print(); }, 250); } catch(e) {}
</script>

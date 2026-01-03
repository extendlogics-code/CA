<section class="card">
    <div class="flex-between">
        <div>
            <h2>Execution Checklist</h2>
            <p>Leads update items, CEO reviews completion before sign-off.</p>
        </div>
        <form method="GET" action="<?= e(url_for('dashboard')) ?>">
            <button type="submit" class="button ghost">Back to dashboard</button>
        </form>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <?php foreach ($tasks as $task): ?>
        <?php $client = find_client($task['client_id']); ?>
        <?php $lead = find_employee($task['lead_id']); ?>
        <?php $canEdit = $user['role'] === 'ceo' || ($employeeRecord && $employeeRecord['id'] === $task['lead_id']); ?>
        <div class="card" style="margin-bottom:1rem;">
            <div class="flex-between">
                <div>
                    <h3 style="margin:0;">Task #<?= $task['id'] ?> – <?= e($task['title_display'] ?? $task['title']) ?></h3>
                    <small><?= e($client['name'] ?? 'Unknown client') ?> · Lead: <?= e($lead['name'] ?? 'Unassigned') ?></small>
                </div>
                <span class="status-pill <?= strtolower(str_replace(' ', '-', $task['status'])) ?>"><?= e($task['status']) ?></span>
            </div>
            <form method="POST" action="<?= e(url_for('checklist')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                <ul class="checklist">
                    <?php foreach ($task['checklist'] as $index => $item): ?>
                        <li>
                            <input type="checkbox" id="task-<?= $task['id'] ?>-<?= $index ?>" name="completed[<?= $index ?>]" value="1" <?= $item['done'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label for="task-<?= $task['id'] ?>-<?= $index ?>"><?= e($item['item']) ?></label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($canEdit): ?>
                    <button type="submit">Update checklist</button>
                <?php else: ?>
                    <p style="color:var(--muted);">Only the assigned lead can edit this checklist.</p>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</section>

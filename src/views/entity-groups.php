<section class="card">
    <div class="flex-between">
        <div>
            <h2>Entity Groups</h2>
            <p>Manage the list of client entity groups.</p>
        </div>
        <form method="GET" action="<?= e(url_for('clients')) ?>">
            <button type="submit" class="button ghost">Back to Clients</button>
        </form>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url_for('entity-groups')) ?>" class="client-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <label>
            <span>New group name</span>
            <input type="text" name="name" placeholder="e.g., Manufacturing" required>
        </label>
        <button type="submit">Add Group</button>
    </form>

    <div class="table-wrapper" style="margin-top:1rem;">
        <table class="table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach (($items ?? []) as $idx => $item): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <form method="POST" action="<?= e(url_for('entity-groups')) ?>" style="display:flex;gap:0.5rem;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                                <input type="text" name="name" value="<?= e($item['name'] ?? '') ?>" required>
                                <button type="submit" class="button small-button">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="<?= e(url_for('entity-groups')) ?>" onsubmit="return confirm('Delete this group?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                                <button type="submit" class="button danger small-button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items ?? [])): ?>
                    <tr><td colspan="3">No groups found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

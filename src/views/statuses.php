<section class="card">
    <div class="flex-between">
        <div>
            <h2>Task Status Library</h2>
            <p>Define the statuses that appear while creating or updating tasks and services.</p>
        </div>
        <form method="GET" action="<?= e(url_for('tasks')) ?>">
            <button type="submit" class="button ghost">Back to Tasks</button>
        </form>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url_for('statuses')) ?>" class="status-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            <span>New status description</span>
            <input type="text" name="description" placeholder="e.g., Awaiting Client Sign-off" required>
        </label>
        <button type="submit">Add Status</button>
    </form>

    <div class="table-wrapper" style="margin-top:1.5rem;">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($statusRecords)): ?>
                    <?php foreach ($statusRecords as $status): ?>
                        <tr>
                            <td><?= (int) $status['id'] ?></td>
                            <td><?= e($status['description']) ?></td>
                            <td class="status-actions">
                                <form method="POST" action="<?= e(url_for('statuses')) ?>" data-status-desc="<?= e($status['description']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="status_id" value="<?= (int) $status['id'] ?>">
                                    <input type="hidden" name="description" value="">
                                    <button type="button" class="button ghost" onclick="promptStatusEdit(this.form)">Edit</button>
                                </form>
                                <form method="POST" action="<?= e(url_for('statuses')) ?>" data-status-desc="<?= e($status['description']) ?>" onsubmit="return confirmStatusDelete(this);">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="status_id" value="<?= (int) $status['id'] ?>">
                                    <button type="submit" class="button danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No statuses configured yet. Add one above to start using custom statuses.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.status-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}
.status-form label {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.status-form input {
    margin-top: 0.3rem;
    padding: 0.6rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}
.status-actions {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    align-items: center;
}
.status-actions form {
    margin: 0;
}
.status-actions .button {
    padding: 0.45rem 0.9rem;
}
</style>

<script>
function promptStatusEdit(form) {
    if (!form) return;
    var current = form.dataset.statusDesc || '';
    var updated = window.prompt('Update status description', current);
    if (updated === null) {
        return;
    }

    var trimmed = updated.trim();
    if (trimmed === '' || trimmed === current) {
        return;
    }

    var input = form.querySelector('input[name="description"]');
    if (!input) {
        return;
    }
    input.value = trimmed;
    form.submit();
}

function confirmStatusDelete(form) {
    var name = (form && form.dataset.statusDesc) ? form.dataset.statusDesc : 'this status';
    return window.confirm('Delete "' + name + '"? Tasks already using it will keep their current label.');
}
</script>

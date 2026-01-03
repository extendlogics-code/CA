<section class="card">
    <div class="flex-between">
        <div>
            <h2>Service Catalog</h2>
            <p>Manage the list of work streams available on the Task Board.</p>
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

    <form method="POST" action="<?= e(url_for('service-types')) ?>" class="service-type-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            <span>New service name</span>
            <input type="text" name="service_name" placeholder="e.g., GST Return Filing" required>
        </label>
        <button type="submit">Add Service Type</button>
    </form>

    <div class="table-wrapper" style="margin-top:1.5rem;">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <?php
                $serviceRecords = $serviceCatalogRecords ?? [];
                if (empty($serviceRecords) && !empty($serviceCatalog)) {
                    foreach ($serviceCatalog as $index => $name) {
                        $serviceRecords[] = ['id' => $index + 1, 'name' => $name];
                    }
                }
            ?>
            <tbody id="service-type-list">
                <?php if (!empty($serviceRecords)): ?>
                    <?php foreach ($serviceRecords as $index => $service): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($service['name']) ?></td>
                            <td class="service-type-actions">
                                <form method="POST" action="<?= e(url_for('service-types')) ?>" data-service-name="<?= e($service['name']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                    <input type="hidden" name="service_name" value="">
                                    <button type="button" class="button ghost" onclick="promptServiceRename(this.form)">Edit</button>
                                </form>
                                <form method="POST" action="<?= e(url_for('service-types')) ?>" data-service-name="<?= e($service['name']) ?>" onsubmit="return confirmServiceDelete(this);">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                                    <button type="submit" class="button danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No service types yet. Add one above to get started.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.service-type-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}
.service-type-form label {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.service-type-form input {
    margin-top: 0.3rem;
    padding: 0.6rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}
.service-type-actions {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    align-items: center;
}
.service-type-actions form {
    margin: 0;
}
.service-type-actions .button {
    padding: 0.45rem 0.9rem;
}
</style>

<script>
function promptServiceRename(form) {
    if (!form) return;
    var current = form.dataset.serviceName || '';
    var updated = window.prompt('Rename service type', current);
    if (updated === null) {
        return;
    }

    var trimmed = updated.trim();
    if (trimmed === '' || trimmed === current) {
        return;
    }

    var input = form.querySelector('input[name="service_name"]');
    if (!input) {
        return;
    }
    input.value = trimmed;
    form.submit();
}

function confirmServiceDelete(form) {
    var name = (form && form.dataset.serviceName) ? form.dataset.serviceName : 'this service type';
    return window.confirm('Delete "' + name + '" from the catalog? This cannot be undone.');
}
</script>

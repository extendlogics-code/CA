<section class="card">
    <div class="flex-between">
        <div>
            <h2>Template library</h2>
            <p>Store standard risk control matrices, workpapers, and engagement artefacts.</p>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Uploaded by</th>
                    <th>Download</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?= e($template['name']) ?></td>
                            <td><?= e($template['category']) ?></td>
                            <td><?= e($template['uploaded_by']) ?><br><small><?= date('M d, Y', strtotime($template['uploaded_at'])) ?></small></td>
                            <td><a href="<?= e(url_for('templates/download')) ?>?id=<?= $template['id'] ?>">Download</a></td>
                            <td>
                                <div style="display:flex;gap:0.5rem;">
                                    <form method="GET" action="<?= e(url_for('templates')) ?>">
                                        <input type="hidden" name="edit" value="<?= (int) $template['id'] ?>">
                                        <button type="submit" class="button ghost">Edit</button>
                                    </form>
                                    <form method="POST" action="<?= e(url_for('templates')) ?>" onsubmit="return confirm('Delete template <?= e($template['name']) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                                        <button type="submit" class="button danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2><?= !empty($editTemplate) ? 'Edit template' : 'Add template' ?></h2>
    <form method="POST" action="<?= e(url_for('templates')) ?>" enctype="multipart/form-data" class="layout-two-column">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if (!empty($editTemplate)): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="template_id" value="<?= (int) $editTemplate['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="name">Template name</label>
            <input id="name" name="name" required value="<?= e($editTemplate['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <input id="category" name="category" placeholder="Risk Control Matrix" required value="<?= e($editTemplate['category'] ?? '') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label for="template_file">File <?= !empty($editTemplate) ? '(optional to replace)' : '' ?></label>
            <input id="template_file" type="file" name="template_file" <?= !empty($editTemplate) ? '' : 'required' ?>>
        </div>
        <div style="grid-column:1/-1;text-align:right;">
            <button type="submit"><?= !empty($editTemplate) ? 'Update' : 'Upload' ?></button>
        </div>
    </form>
</section>

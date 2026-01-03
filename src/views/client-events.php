<?php $clientId = (string)($clientId ?? ''); $client = $client ?? null; ?>
<section class="card">
    <div class="flex-between">
        <div>
            <h2>Client Events</h2>
            <p><?= e($client ? ($client['name'] ?? ($client['name_of_entity'] ?? '')) : '') ?></p>
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

    <form method="POST" action="<?= e(url_for('client-events')) ?>" class="client-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="client_id" value="<?= e($clientId) ?>">
        <div class="form-grid">
            <label><span>Event name *</span><input type="text" name="name" required></label>
            <label><span>Date</span><input type="date" name="date"></label>
            <label><span>Status</span><input type="text" name="status"></label>
            <label class="full-width"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
        </div>
        <div class="form-actions"><button type="submit">Add Event</button></div>
    </form>

    <div class="table-wrapper" style="margin-top:1rem;">
        <table class="table">
            <thead><tr><th>#</th><th>Name</th><th>Date</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach (($events ?? []) as $idx => $ev): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td colspan="4">
                            <form method="POST" action="<?= e(url_for('client-events')) ?>" style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="event_id" value="<?= (int)($ev['id'] ?? 0) ?>">
                                <input type="hidden" name="client_id" value="<?= e($clientId) ?>">
                                <input type="text" name="name" value="<?= e($ev['name'] ?? '') ?>" required>
                                <input type="date" name="date" value="<?= e($ev['date'] ?? '') ?>">
                                <input type="text" name="status" value="<?= e($ev['status'] ?? '') ?>">
                                <input type="text" name="notes" value="<?= e($ev['notes'] ?? '') ?>">
                                <button type="submit" class="button small-button" style="grid-column:1/-1;justify-self:end;">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="<?= e(url_for('client-events')) ?>" onsubmit="return confirm('Delete this event?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= (int)($ev['id'] ?? 0) ?>">
                                <input type="hidden" name="client_id" value="<?= e($clientId) ?>">
                                <button type="submit" class="button danger small-button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($events ?? [])): ?>
                    <tr><td colspan="6">No events found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

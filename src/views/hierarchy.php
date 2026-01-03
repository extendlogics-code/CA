<?php if (!empty($flashError)): ?>
    <div class="alert error"><?= e($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
    <div class="alert success"><?= e($flashSuccess) ?></div>
<?php endif; ?>

<?php if (user_has_role(['ceo','lead'])): ?>
<section class="card" style="margin-bottom:1.5rem;">
    <div class="flex-between">
        <div>
            <h2><?= !empty($orgEditId) ? 'Edit Org Member' : 'Create Employee Level' ?></h2>
            <p>Update the org structure from this page. Levels with lower order values appear first.</p>
        </div>
    </div>
    <form method="POST" action="<?= e(url_for('hierarchy')) ?>" class="layout-two-column">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= !empty($orgEditId) ? 'org_update' : 'org_create' ?>">
        <?php if (!empty($orgEditId)): ?>
            <input type="hidden" name="member_id" value="<?= (int) $orgEditId ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="org_level">Level name</label>
            <input id="org_level" name="level" value="<?= e($orgFormData['level'] ?? '') ?>" required list="org-level-options">
            <?php if (!empty($orgFormErrors['level'])): ?><p class="form-error"><?= e($orgFormErrors['level']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_level_order">Level order</label>
            <input id="org_level_order" name="level_order" type="number" value="<?= e($orgFormData['level_order'] ?? 0) ?>">
        </div>
        <div class="form-group">
            <label for="org_name">Employee name</label>
            <input id="org_name" name="name" value="<?= e($orgFormData['name'] ?? '') ?>" required>
            <?php if (!empty($orgFormErrors['name'])): ?><p class="form-error"><?= e($orgFormErrors['name']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_display_order">Display order (within level)</label>
            <input id="org_display_order" name="display_order" type="number" value="<?= e($orgFormData['display_order'] ?? 0) ?>" required>
            <?php if (!empty($orgFormErrors['display_order'])): ?><p class="form-error"><?= e($orgFormErrors['display_order']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_title">Title</label>
            <input id="org_title" name="title" value="<?= e($orgFormData['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="org_email">Email</label>
            <input id="org_email" type="email" name="email" value="<?= e($orgFormData['email'] ?? '') ?>">
            <?php if (!empty($orgFormErrors['email'])): ?><p class="form-error"><?= e($orgFormErrors['email']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_location">Location</label>
            <input id="org_location" name="location" value="<?= e($orgFormData['location'] ?? '') ?>" list="tn-city-options">
            <?php if (!empty($orgFormErrors['location'])): ?><p class="form-error"><?= e($orgFormErrors['location']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_state">State</label>
            <input id="org_state" name="state" value="<?= e($orgFormData['state'] ?? '') ?>" list="state-options">
            <?php if (!empty($orgFormErrors['state'])): ?><p class="form-error"><?= e($orgFormErrors['state']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_country">Country</label>
            <input id="org_country" name="country" value="<?= e($orgFormData['country'] ?? '') ?>" list="country-options">
            <?php if (!empty($orgFormErrors['country'])): ?><p class="form-error"><?= e($orgFormErrors['country']) ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="org_tenure">Tenure</label>
            <input id="org_tenure" name="tenure" value="<?= e($orgFormData['tenure'] ?? '') ?>" list="tenure-options">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label for="org_focus">Focus</label>
            <textarea id="org_focus" name="focus" rows="2"><?= e($orgFormData['focus'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label for="org_bio">Bio</label>
            <textarea id="org_bio" name="bio" rows="3"><?= e($orgFormData['bio'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label for="org_skills">Skills (comma separated)</label>
            <input id="org_skills" name="skills" value="<?= e($orgFormData['skills'] ?? '') ?>">
        </div>
        <div style="grid-column:1/-1;text-align:right;">
            <button type="submit"><?= !empty($orgEditId) ? 'Update Level' : 'Create Level' ?></button>
        </div>
    </form>
</section>

<?php if (user_has_role(['ceo']) && !empty($levelOrders ?? [])): ?>
<datalist id="org-level-options">
    <?php foreach ($levelOrders as $levelName => $orderValue): ?>
        <option value="<?= e($levelName) ?>">Order <?= e((string) $orderValue) ?> — <?= e($levelName) ?></option>
    <?php endforeach; ?>
</datalist>
<?php endif; ?>

<?php if (user_has_role(['ceo']) && !empty($tnLocations ?? [])): ?>
<datalist id="tn-city-options">
    <?php foreach (array_keys($tnLocations) as $city): ?>
        <option value="<?= e($city) ?>">
    <?php endforeach; ?>
</datalist>
<?php endif; ?>

<?php if (user_has_role(['ceo']) && !empty($stateOptions ?? [])): ?>
<datalist id="state-options">
    <?php foreach ($stateOptions as $state): ?>
        <option value="<?= e($state) ?>">
    <?php endforeach; ?>
</datalist>
<?php endif; ?>

<?php if (user_has_role(['ceo']) && !empty($countryOptions ?? [])): ?>
<datalist id="country-options">
    <?php foreach ($countryOptions as $country): ?>
        <option value="<?= e($country) ?>">
    <?php endforeach; ?>
</datalist>
<?php endif; ?>

<?php if (user_has_role(['ceo']) && !empty($tenureOptions ?? [])): ?>
<datalist id="tenure-options">
    <?php foreach ($tenureOptions as $tenureValue): ?>
        <option value="<?= e($tenureValue) ?>">
    <?php endforeach; ?>
</datalist>
<?php endif; ?>
<?php endif; ?>

<section class="card org-structure">
    <div class="flex-between">
        <div>
            <h2>Org Structure</h2>
            <p>Browse the leadership tree. Tap any card to see the person’s extended profile.</p>
        </div>
        <div class="org-legend">
            <span class="org-card-chip">Leadership</span>
            <span class="org-card-chip">Delivery</span>
            <span class="org-card-chip">Analyst Guild</span>
        </div>
    </div>

    <?php foreach ($hierarchy as $level => $people): ?>
        <section class="org-level">
            <div class="org-level-head">
                <div>
                    <?php $levelOrder = isset($levelOrders[$level]) ? (int) $levelOrders[$level] : null; ?>
                    <?php if ($levelOrder !== null && $levelOrder > 0): ?>
                        <p class="muted-label">Level</p>
                        <h3>Level <?= e((string) $levelOrder) ?></h3>
                    <?php endif; ?>
                    <p class="muted-label">Level Name</p>
                    <p class="org-level-name"><?= e($level) ?></p>
                </div>
                <span class="muted-label"><?= count($people) ?> member<?= count($people) === 1 ? '' : 's' ?></span>
            </div>
            <div class="org-level-grid">
                <?php foreach ($people as $person): ?>
                    <?php $profile = $profiles[$person] ?? []; ?>
                    <article class="org-card">
                        <span class="org-card-chip"><?= e($level) ?></span>
                        <h4><?= e($person) ?></h4>
                        <p class="org-card-role"><?= e($profile['title'] ?? $level) ?></p>
                        <button type="button" class="org-card-btn" aria-expanded="false">View details</button>
                        <?php if (user_has_role(['ceo'])): ?>
                            <div class="org-card-controls" style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                                <form method="GET" action="<?= e(url_for('hierarchy')) ?>">
                                    <input type="hidden" name="edit_member" value="<?= isset($profile['id']) ? (int) $profile['id'] : 0 ?>">
                                    <button type="submit" class="button ghost">Edit</button>
                                </form>
                                <form method="POST" action="<?= e(url_for('hierarchy')) ?>" onsubmit="return confirm('Delete <?= e($person) ?> from org chart?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="org_delete">
                                    <input type="hidden" name="member_id" value="<?= isset($profile['id']) ? (int) $profile['id'] : 0 ?>">
                                    <button type="submit" class="button danger">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="org-card-details">
                            <p class="org-card-focus"><strong>Focus:</strong> <?= e($profile['focus'] ?? '—') ?></p>
                            <div class="org-card-meta">
                                <span>
                                    <?= e($profile['location'] ?? 'Global') ?>
                                    <?php if (!empty($profile['state'])): ?>, <?= e($profile['state']) ?><?php endif; ?>
                                    <?php if (!empty($profile['country'])): ?>, <?= e($profile['country']) ?><?php endif; ?>
                                </span>
                                <span>Tenure: <?= e($profile['tenure'] ?? '—') ?></span>
                            </div>
                            <p class="muted-label">Email</p>
                            <p><?= e($profile['email'] ?? 'Not shared') ?></p>
                            <?php if (!empty($profile['bio'])): ?>
                                <p class="muted-label">Bio</p>
                                <p><?= e($profile['bio']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($profile['skills'])): ?>
                                <p class="muted-label">Specialisations</p>
                                <div class="tags">
                                    <?php foreach ($profile['skills'] as $skill): ?>
                                        <span class="tag"><?= e($skill) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>

<style>
.form-error {
    color: #dc2626;
    font-size: 0.85rem;
    margin-top: 0.2rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.org-card-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const card = event.currentTarget.closest('.org-card');
            const expanded = card.classList.toggle('open');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.textContent = expanded ? 'Hide details' : 'View details';
        });
    });
});
</script>

<?php if (user_has_role(['ceo','lead'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var levelField = document.getElementById('org_level');
    var orderField = document.getElementById('org_level_order');
    var displayField = document.getElementById('org_display_order');
    var levelData = <?= json_encode($levelOrders ?? new stdClass(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var titleField = document.getElementById('org_title');
    var cityLookup = <?= json_encode($tnLocations ?? new stdClass(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var stateField = document.getElementById('org_state');
    var countryField = document.getElementById('org_country');
    var cityField = document.getElementById('org_location');

    function syncDisplay() {
        if (orderField && displayField) {
            displayField.value = orderField.value;
        }
    }

    if (orderField) {
        orderField.addEventListener('input', syncDisplay);
    }

    if (levelField && orderField) {
        levelField.addEventListener('change', function () {
            var name = levelField.value;
            if (Object.prototype.hasOwnProperty.call(levelData, name)) {
                orderField.value = levelData[name];
            }
            syncDisplay();
            if (titleField && (titleField.value.trim() === '' || titleField.dataset.autofilled === 'true')) {
                titleField.value = name;
                titleField.dataset.autofilled = 'true';
            }
        });
    }

    if (titleField) {
        titleField.addEventListener('input', function () {
            if (titleField.value.trim() !== levelField.value.trim()) {
                titleField.dataset.autofilled = 'false';
            }
        });
    }

    function syncLocation() {
        if (!cityField) return;
        var city = cityField.value;
        if (cityLookup[city]) {
            if (stateField && (stateField.value.trim() === '' || stateField.dataset.autofilled === 'true')) {
                stateField.value = cityLookup[city].state;
                stateField.dataset.autofilled = 'true';
            }
            if (countryField && (countryField.value.trim() === '' || countryField.dataset.autofilled === 'true')) {
                countryField.value = cityLookup[city].country;
                countryField.dataset.autofilled = 'true';
            }
        }
    }

    if (cityField) {
        cityField.addEventListener('change', syncLocation);
    }
    if (stateField) {
        stateField.addEventListener('input', function () {
            stateField.dataset.autofilled = 'false';
        });
    }
    if (countryField) {
        countryField.addEventListener('input', function () {
            countryField.dataset.autofilled = 'false';
        });
    }

    syncLocation();

    syncDisplay();
});
</script>
<?php endif; ?>

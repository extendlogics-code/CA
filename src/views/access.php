<section class="card">
    <h2>Access & Data Handling</h2>
    <style>
    .select-fancy{appearance:none;border:2px solid #e5e7eb;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);box-shadow:0 4px 10px rgba(2,6,23,0.06);transition:box-shadow .15s,border-color .15s}
    .select-fancy:focus{outline:none;box-shadow:0 6px 16px rgba(2,6,23,0.12)}
    .select-superadmin{border-color:#0ea5e9;background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);box-shadow:0 6px 14px rgba(14,165,233,0.18)}
    .select-employee{border-color:#10b981;background:linear-gradient(135deg,#ecfdf5 0%,#dcfce7 100%);box-shadow:0 6px 14px rgba(16,185,129,0.18)}
    .select-customer{border-color:#f59e0b;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);box-shadow:0 6px 14px rgba(245,158,11,0.18)}
    .select-lead{border-color:#06b6d4;background:linear-gradient(135deg,#ecfeff 0%,#cffafe 100%);box-shadow:0 6px 14px rgba(6,182,212,0.18)}
    .select-user{border-color:#8b5cf6;background:linear-gradient(135deg,#faf5ff 0%,#eef2ff 100%);box-shadow:0 6px 14px rgba(139,92,246,0.15)}
    </style>
    <p>
        Employees and customers can review their read/write capabilities in one place.
        <?php if (!empty($canEdit)): ?>
            Super admins can edit the grid below and apply changes instantly.
        <?php endif; ?>
    </p>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (!empty($canEdit)): ?>
        <form method="POST" action="<?= e(url_for('access')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <?php endif; ?>

    <div class="matrix-list">
        <?php foreach ($matrix as $asset => $permissions): ?>
            <div class="card matrix-row" style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem;">
                <div style="flex:0 0 220px;">
                    <strong><?= e(ucwords(str_replace('-', ' ', $asset))) ?></strong>
                    <div><small><?= e($asset) ?></small></div>
                </div>
                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                    <?php foreach (['superadmin' => 'CEO', 'employee' => 'Employee', 'customer' => 'Customer'] as $roleKey => $label): ?>
                        <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:160px;">
                            <span style="font-size:0.8rem;color:var(--muted);"><?= e($label) ?></span>
                            <?php if (!empty($canEdit)): ?>
                                <select class="select-fancy select-<?= e($roleKey) ?>" name="permissions[<?= e($asset) ?>][<?= e($roleKey) ?>]">
                                    <?php foreach (ACCESS_LEVELS as $level): ?>
                                        <option value="<?= e($level) ?>" <?= ($permissions[$roleKey] ?? 'none') === $level ? 'selected' : '' ?>><?= e(ucwords($level)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="display:inline-block;border:1px solid #e5e7eb;padding:0.15rem 0.5rem;border-radius:999px;background:#f8fafc;">
                                    <?= e(ucwords($permissions[$roleKey] ?? 'none')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:160px;">
                        <span style="font-size:0.8rem;color:var(--muted);">Lead</span>
                        <?php $leadLevel = $leadRolePermissions[$asset] ?? 'none'; ?>
                        <?php if (!empty($canEdit)): ?>
                            <select class="select-fancy select-lead" name="lead_permissions[<?= e($asset) ?>]">
                                <?php foreach (ACCESS_LEVELS as $level): ?>
                                    <option value="<?= e($level) ?>" <?= ($leadLevel === $level) ? 'selected' : '' ?>><?= e(ucwords($level)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span style="display:inline-block;border:1px solid #e5e7eb;padding:0.15rem 0.5rem;border-radius:999px;background:#f8fafc;">
                                <?= e(ucwords($leadLevel)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($canEdit)): ?>
            <div class="asset-creator card" style="margin-top:1rem;padding:1rem 1.25rem;border:1px solid #e5e7eb;border-radius:14px;background:linear-gradient(135deg,#f8fafc 0%, #eef2ff 50%, #e0f2fe 100%);box-shadow:0 12px 30px rgba(15,23,42,0.08)">
                <div class="flex-between" style="margin-bottom:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:34px;height:34px;border-radius:999px;background:#0ea5e9;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 6px 14px rgba(14,165,233,0.35);">🧩</div>
                        <div>
                            <h3 style="margin:0;">Add Asset</h3>
                            <p style="margin:0;color:#64748b;font-size:0.9rem;">Define a page/resource and set default access by role</p>
                        </div>
                    </div>
                </div>
                <div class="asset-creator-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.85rem;">
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Menu asset</span>
                        <select name="asset_name_select" style="border:2px solid #8b5cf6;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#faf5ff 0%,#eef2ff 100%);box-shadow:0 6px 14px rgba(139,92,246,0.15)">
                            <option value="">Choose</option>
                            <?php foreach (($availableAssets ?? []) as $opt): ?>
                                <option value="<?= e($opt) ?>"><?= e(ucwords(str_replace('-', ' ', $opt))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Custom asset</span>
                        <input type="text" name="asset_name_custom" placeholder="e.g., reports">
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Super Admin default</span>
                        <select name="default_superadmin" style="border:2px solid #0ea5e9;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);box-shadow:0 6px 14px rgba(14,165,233,0.18)">
                            <?php foreach (ACCESS_LEVELS as $lvl): ?>
                                <option value="<?= e($lvl) ?>"><?= e(ucwords($lvl)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Lead default</span>
                        <select name="default_lead" style="border:2px solid #06b6d4;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#ecfeff 0%,#cffafe 100%);box-shadow:0 6px 14px rgba(6,182,212,0.18)">
                            <?php foreach (ACCESS_LEVELS as $lvl): ?>
                                <option value="<?= e($lvl) ?>"><?= e(ucwords($lvl)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Employee default</span>
                        <select name="default_employee" style="border:2px solid #10b981;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#ecfdf5 0%,#dcfce7 100%);box-shadow:0 6px 14px rgba(16,185,129,0.18)">
                            <?php foreach (ACCESS_LEVELS as $lvl): ?>
                                <option value="<?= e($lvl) ?>"><?= e(ucwords($lvl)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:0.35rem;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:0.6rem;">
                        <span style="font-size:0.8rem;color:#64748b;">Customer default</span>
                        <select name="default_customer" style="border:2px solid #f59e0b;border-radius:12px;padding:0.45rem 0.7rem;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);box-shadow:0 6px 14px rgba(245,158,11,0.18)">
                            <?php foreach (ACCESS_LEVELS as $lvl): ?>
                                <option value="<?= e($lvl) ?>"><?= e(ucwords($lvl)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div style="text-align:right;align-self:end;">
                        <button type="submit" name="action" value="add_asset" style="border-radius:999px;padding:0.5rem 0.9rem;">Add Asset</button>
                    </div>
                </div>
            </div>

            <div style="text-align:right;margin-top:1rem;">
                <button type="submit" name="action" value="matrix">Save Access Levels</button>
            </div>
        </form>

        <hr>
        <h3>Per-user access</h3>
        <form method="GET" action="<?= e(url_for('access')) ?>" style="margin-bottom:0.75rem;">
            <label>Select user:
                <select class="select-fancy select-user" name="user">
                    <option value="">Choose</option>
                    <?php foreach (($allEmployees ?? []) as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= ((int) ($selectedUserId ?? 0) === (int) $emp['id']) ? 'selected' : '' ?>><?= e($emp['name']) ?> (<?= e($emp['title'] ?? $emp['role_type'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Load</button>
            </label>
        </form>
        <?php if (!empty($selectedUserId) && !empty($selectedUser)): ?>
            <div class="card" style="margin-bottom:0.75rem;">
                <strong><?= e($selectedUser['name']) ?></strong>
                <div><small><?= e($selectedUser['email']) ?></small></div>
                <div><small>Employee ID: <?= e($selectedUser['employee_number'] ?? '') ?></small></div>
                <div><small>Role: <?= e($selectedUser['role'] ?? '') ?></small></div>
            </div>
            <form method="POST" action="<?= e(url_for('access')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="user_update">
                <input type="hidden" name="user_id" value="<?= (int) $selectedUserId ?>">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Page</th><th>Access</th><th>Effective</th></tr></thead>
                        <tbody>
                            <?php foreach (($menuAssets ?? []) as $asset): ?>
                                <?php $level = $userPermissions[$asset] ?? 'none'; $effective = $effectivePermissions[$asset] ?? 'none'; ?>
                                <tr>
                                    <td><?= e($asset) ?></td>
                                    <td>
                                        <select class="select-fancy select-user" name="user_permissions[<?= e($asset) ?>]">
                                            <?php foreach (ACCESS_LEVELS as $lvl): ?>
                                                <option value="<?= e($lvl) ?>" <?= $level === $lvl ? 'selected' : '' ?>><?= e(ucwords($lvl)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= e(ucwords($effective)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align:right;margin-top:1rem;">
                    <button type="submit">Save User Access</button>
                </div>
            </form>
        <?php endif; ?>
        <form method="POST" action="<?= e(url_for('cache/clear')) ?>" style="text-align:right;margin-top:0.75rem;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">Clear Cache</button>
        </form>
    <?php endif; ?>
</section>

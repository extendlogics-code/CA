<?php if (!empty($flashError)): ?>
    <div class="alert error"><?= e($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
    <div class="alert success"><?= e($flashSuccess) ?></div>
<?php endif; ?>

<?php $canManageStaff = user_has_role(['ceo']); ?>
<?php $isEditMode = $canManageStaff && !empty($staffEditId ?? 0) && !empty($editingEmployee); ?>
<?php $showCreateForm = $canManageStaff && ($isEditMode || (isset($_GET['create']) && $_GET['create'] === '1')); ?>
<?php if ($canManageStaff): ?>
<section class="card" id="create-staff" <?php if (!$showCreateForm) { echo 'style="display:none;"'; } ?>>
    <div class="flex-between">
        <div>
            <h2><?= $isEditMode ? 'Edit Staff Member' : 'Create Staff Member' ?></h2>
            <p><?= $isEditMode ? 'Update an existing employee profile.' : 'Provision access for any employee level.' ?></p>
        </div>
        <div class="header-actions">
            <button type="button" class="button ghost" onclick="showCreateForm()">Create Staff</button>
            <?php if ($isEditMode): ?>
                <form method="GET" action="<?= e(url_for('staff')) ?>" class="inline"><button type="submit" class="button ghost">Cancel edit</button></form>
            <?php endif; ?>
        </div>
    </div>
    <form method="POST" action="<?= e(url_for('staff')) ?>" class="staff-manage-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $isEditMode ? 'update_staff' : 'create_staff' ?>">
        <?php if ($isEditMode): ?>
            <input type="hidden" name="employee_db_id" value="<?= (int) $staffEditId ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="staff_full_name">Employee name</label>
            <input id="staff_full_name" name="full_name" value="<?= e($staffFormData['full_name'] ?? '') ?>" required>
            <?php if (!empty($staffFormErrors['full_name'])): ?>
                <p class="form-error"><?= e($staffFormErrors['full_name']) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="staff_email">Employee Email</label>
            <input id="staff_email" type="email" name="email" required placeholder="name@example.co.in or name@example.com" pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+(\.co\.in|\.com)$" title="Use a .co.in or .com email like name@example.co.in or name@example.com" value="<?= e($staffFormData['email'] ?? '') ?>">
            <?php if (!empty($staffFormErrors['email'])): ?>
                <p class="form-error"><?= e($staffFormErrors['email']) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="staff_emp_id">Employee Number</label>
            <input id="staff_emp_id" name="emp_id" value="<?= e($staffFormData['employee_number'] ?? ($editingEmployee['employee_number'] ?? '')) ?>" placeholder="Auto when left blank">
            <?php if (!empty($staffFormErrors['emp_id'])): ?>
                <p class="form-error"><?= e($staffFormErrors['emp_id']) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="staff_role_id">Role level</label>
            <select id="staff_role_id" name="role_id" required>
                <option value="">Select role</option>
                <?php foreach ($roles as $role): ?>
                    <?php if ((int) ($role['id'] ?? 0) <= 0) continue; ?>
                    <option value="<?= (int) $role['id'] ?>" <?= ((int) ($staffFormData['role_id'] ?? 0) === (int) $role['id']) ? 'selected' : '' ?>><?= e(ucwords($role['name'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($staffFormErrors['role_id'])): ?>
                <p class="form-error"><?= e($staffFormErrors['role_id']) ?></p>
            <?php endif; ?>
        </div>
        <?php if (!$isEditMode): ?>
            <div class="form-group">
                <label for="staff_password">Temporary password</label>
                <input id="staff_password" type="password" name="password" placeholder="Min 8 characters" required>
                <?php if (!empty($staffFormErrors['password'])): ?>
                    <p class="form-error"><?= e($staffFormErrors['password']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit"><?= $isEditMode ? 'Update staff profile' : 'Create staff profile' ?></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="flex-between">
        <div>
            <h2>All Staff</h2>
            <p>Active employees across every level.</p>
        </div>
        <div class="header-actions">
            <span class="badge"><?= count($allEmployees ?? []) ?> active</span>
        <?php if ($canManageStaff): ?>
                <button type="button" class="button ghost" onclick="showCreateForm()">Create Staff</button>
        <?php endif; ?>
        </div>
    </div>

    <?php if ($canManageStaff): ?>
    <div class="bulk-upload" style="margin-top:0.5rem;">
        <div class="bulk-row" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <strong>Bulk upload staff</strong>
                <form method="GET" action="<?= e(url_for('staff')) ?>" class="inline">
                    <input type="hidden" name="bulk_template" value="csv">
                    <button type="submit" class="button ghost">Download CSV (All)</button>
                </form>
            </div>
            <form method="POST" action="<?= e(url_for('staff')) ?>" enctype="multipart/form-data" class="inline" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:nowrap;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="bulk_upload_staff">
                <input type="file" name="bulk_file" accept=".csv,.xlsx" required>
                <button type="submit" class="button">Bulk Upload</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="staff-filters">
        <input type="text" id="staff_search" placeholder="Search staff">
        <select id="staff_role_filter">
            <option value="">All roles</option>
            <?php foreach ($roles as $role): ?>
                <?php $name = strtolower(str_replace(' ', '-', $role['name'])); ?>
                <option value="<?= e($name) ?>"><?= e(ucwords($role['name'])) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="staff_sort">
            <option value="name">Name</option>
            <option value="role">Role</option>
        </select>
    </div>

    <div id="staff-grid" class="staff-grid">
        <?php $items = $allEmployees ?? []; ?>
        <?php foreach ($items as $e): ?>
            <?php $roleSlug = strtolower(str_replace(' ', '-', (string) ($e['role_type'] ?? ($e['role'] ?? '')))); ?>
            <div class="staff-card" data-role="<?= e($roleSlug) ?>" data-name="<?= e(strtolower((string) ($e['name'] ?? ''))) ?>" data-email="<?= e(strtolower((string) ($e['email'] ?? ''))) ?>">
                <div class="header"><strong><?= e($e['name'] ?? '') ?></strong><span class="chip"><?= e($e['role'] ?? ($e['title'] ?? '')) ?></span></div>
                <div class="sub"><?= e($e['email'] ?? '') ?></div>
                <?php if ($canManageStaff): ?>
                <div class="staff-actions">
                    <form method="GET" action="<?= e(url_for('staff')) ?>#create-staff"><input type="hidden" name="edit" value="<?= (int) ($e['id'] ?? 0) ?>"><button type="submit" class="button ghost">Edit</button></form>
                    <form method="POST" action="<?= e(url_for('staff')) ?>" onsubmit="return confirm('Delete <?= e($e['name'] ?? '') ?>? This will disable their access.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_staff"><input type="hidden" name="emp_id" value="<?= (int) ($e['id'] ?? 0) ?>"><button type="submit" class="button danger">Delete</button></form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<script>
function initStaffFilters(){
  var grid=document.querySelector('.staff-grid'); if(!grid) return;
  var cards=Array.from(document.querySelectorAll('.staff-card'));
  var q=document.getElementById('staff_search');
  var rf=document.getElementById('staff_role_filter');
  var sort=document.getElementById('staff_sort');
  function apply(){
    var query=(q&&q.value||'').toLowerCase();
    var role=rf&&rf.value||'';
    cards.forEach(function(card){
      var ok=true; var t=(card.textContent||'').toLowerCase();
      if(query && t.indexOf(query)===-1) ok=false;
      if(role && card.dataset.role!==role) ok=false;
      card.style.display=ok?'':'none';
    });
    if(sort){
      var by=sort.value; var visible=cards.filter(function(c){return c.style.display!=='none';});
      visible.sort(function(a,b){
        if(by==='name'){return (a.dataset.name||'').localeCompare(b.dataset.name||'');}
        if(by==='role'){return (a.dataset.role||'').localeCompare(b.dataset.role||'');}
        return 0;
      });
      visible.forEach(function(c){grid.appendChild(c);});
    }
  }
  ['input','change'].forEach(function(ev){ if(q) q.addEventListener(ev,apply); if(rf) rf.addEventListener(ev,apply); if(sort) sort.addEventListener(ev,apply); });
  apply();
}
function showCreateForm(){
  var form=document.getElementById('create-staff');
  if(!form) return;
  form.style.display='';
  form.scrollIntoView({behavior:'smooth'});
  form.classList.add('pulse');
  setTimeout(function(){ form.classList.remove('pulse'); }, 800);
}
document.addEventListener('DOMContentLoaded', function(){
  initStaffFilters();
  var form=document.getElementById('create-staff');
  if(form && location.hash==='#create-staff'){
    showCreateForm();
  }
});
</script>

<style>
.staff-filters { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; margin:0.75rem 0; }
.staff-filters input, .staff-filters select { padding:0.5rem 0.75rem; border:1px solid #e5e7eb; border-radius:999px; font-size:0.9rem; }
.staff-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; }
.staff-card { border:1px solid #e5e7eb; border-radius:12px; background:linear-gradient(180deg,#f9fafb 0%,#ffffff 60%); box-shadow:0 12px 30px rgba(15,23,42,0.06); padding:0.75rem 1rem; }
.staff-card .header { display:flex; align-items:center; justify-content:space-between; }
.staff-card .header strong { color:#000; }
.staff-card .sub { color:#000; font-size:0.9rem; }
.staff-card .chip { display:inline-block; border-radius:999px; padding:0.2rem 0.6rem; background:#eef2ff; color:#111827; font-size:0.8rem; }
.form-error { color:#dc2626; font-size:0.85rem; margin-top:0.2rem; }
.staff-actions { display:flex; gap:0.5rem; align-items:center; flex-wrap:nowrap; justify-content:center; margin-top:0.5rem; }
.staff-actions .button { padding:0.35rem 0.75rem; }
.staff-actions form { margin:0; }
.header-actions { display:flex; gap:0.5rem; align-items:center; }

/* Unique, professional manage form */
.staff-manage-form { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-top:0.5rem; }
.staff-manage-form .form-group { background: linear-gradient(180deg, #f3f4f6 0%, #ffffff 80%); border:1px solid #cbd5e1; border-radius:14px; padding:0.8rem 1rem; box-shadow:0 8px 18px rgba(15,23,42,0.08); }
.staff-manage-form label { color:#000; font-weight:800; margin-bottom:0.35rem; }
.staff-manage-form input, .staff-manage-form select { color:#000; padding:0.6rem 0.8rem; border-radius:10px; border:1px solid #cbd5e1; background:#fff; }
.staff-manage-form input:focus, .staff-manage-form select:focus { outline:none; border-color:#1f5eff; box-shadow:0 0 0 3px rgba(31,94,255,0.22); }
.staff-manage-form .form-actions { grid-column:1/-1; display:flex; justify-content:flex-end; }

/* Anchor pulse */
#create-staff.pulse { animation:pulse 0.8s; }
@keyframes pulse { 0%{ box-shadow:0 0 0 0 rgba(31,94,255,0); } 50%{ box-shadow:0 0 0 8px rgba(31,94,255,0.15); } 100%{ box-shadow:0 0 0 0 rgba(31,94,255,0); } }
</style>

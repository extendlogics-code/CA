<?php // /Users/vasan/private-server/workspace/ca/src/views/audits.php ?>
<style>
.audit-shell{display:grid;grid-template-columns:minmax(300px,360px) 1fr;gap:1.2rem;align-items:start}
.audit-panel{background:linear-gradient(135deg,#0b1220 0%,#0f172a 60%);color:#f8fafc;border-radius:18px;padding:1rem;box-shadow:0 20px 40px rgba(2,8,23,.35);backdrop-filter:blur(8px)}
.audit-hero{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem}
.audit-hero .logo{width:34px;height:34px;border-radius:8px;background:radial-gradient(circle at 30% 30%,#22d3ee 0,#22d3ee20 35%,transparent 60%),radial-gradient(circle at 70% 20%,#fbbf24 0,#fbbf2420 45%,transparent 70%)}
.audit-hero h3{margin:0;font-size:1.4rem}
.audit-panel label{font-size:.85rem;color:#cbd5e1}
.audit-panel input,.audit-panel select{width:100%;padding:.65rem .8rem;border-radius:12px;border:1px solid #334155;background:#0b1220;color:#e2e8f0}
.audit-actions{display:flex;gap:.5rem;align-items:center;margin-top:.7rem}
.view-toggle{display:flex;gap:.35rem;margin-left:auto}
.view-toggle .button{padding:.4rem .6rem}
.audit-content{background:#ffffffb3;border:1px solid #e5e7eb;border-radius:18px;padding:1rem;box-shadow:0 20px 40px rgba(2,8,23,.08)}
.audit-table .table thead th{position:sticky;top:0;background:#fff;border-bottom:1px solid #e5e7eb;z-index:1}
.chip{display:inline-block;padding:.25rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600}
.chip.action{background:#eef2ff;color:#3730a3}
.chip.action[data-act="create"]{background:#ecfeff;color:#0e7490}
.chip.action[data-act="update"]{background:#f1f5f9;color:#334155}
.chip.action[data-act="delete"]{background:#fee2e2;color:#b91c1c}
.chip.action[data-act="status_update"]{background:#e0f2fe;color:#075985}
.chip.action[data-act="add_comment"]{background:#f5f3ff;color:#6d28d9}
.chip.action[data-act="upload_attachment"]{background:#fef3c7;color:#b45309}
.chip.action[data-act="update_notice"]{background:#faf5ff;color:#7e22ce}
.chip.action[data-act="update_alert"]{background:#dcfce7;color:#166534}
.role{padding:.15rem .45rem;border-radius:8px;background:#f1f5f9;color:#0f172a;font-size:.75rem}
.audit-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:.85rem}
.audit-card{border:1px solid #e5e7eb;border-radius:14px;padding:.9rem;background:#fff}
.audit-card .meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:.45rem}
.audit-card .title{font-weight:600}
.kv{font-family:ui-monospace,Menlo,monospace;font-size:.9rem}
.pager{display:flex;justify-content:space-between;align-items:center;margin-top:.85rem}
.audit-toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:.6rem}
.audit-toolbar .button{padding:.45rem .7rem}
.button.accent{background:linear-gradient(135deg,#22d3ee,#0ea5e9);color:#fff;border:none;box-shadow:0 10px 20px rgba(14,165,233,.25)}
.audit-panel a, .audit-content a{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .7rem;border-radius:12px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;text-decoration:none;box-shadow:0 8px 18px rgba(2,8,23,.08);transition:transform .08s ease,box-shadow .12s ease,background .12s ease}
.audit-panel a:hover, .audit-content a:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(2,8,23,.12);background:#f8fafc}
.audit-panel a.button.ghost, .audit-content a.button.ghost{background:transparent;border:1px dashed #cbd5e1;color:#334155}
.audit-timeline{display:none;position:relative;padding:.5rem}
.audit-timeline .day{position:relative;padding-left:1.4rem;margin:.6rem 0}
.audit-timeline .day::before{content:'';position:absolute;left:.5rem;top:0;bottom:0;width:2px;background:#e5e7eb}
.audit-timeline .dot{position:absolute;left:.4rem;top:.2rem;width:8px;height:8px;border-radius:50%;background:#22d3ee}
.audit-timeline .item{margin:.35rem 0;padding:.45rem .6rem;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
</style>
<section class="audit-shell">
  <div class="audit-panel">
    <div class="audit-hero"><div class="logo"></div><h3>Audit Trail</h3></div>
    <form method="GET" action="<?= e(url_for('audits')) ?>" class="audit-filters">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem">
        <div>
          <label>Domain</label>
          <select name="domain">
            <?php $domains = ['task','client','staff','service_type','status','template','checklist','task_checklist','hierarchy','access','lead_teams','file']; ?>
            <?php $cur = (string) ($filters['domain'] ?? 'task'); ?>
            <?php foreach ($domains as $d): ?>
              <option value="<?= e($d) ?>" <?= $cur === $d ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$d))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Task ID</label>
          <input type="number" name="task" value="<?= e((string) ($filters['task'] ?? '')) ?>" min="1" step="1">
        </div>
        <div>
          <label>Entity ID</label>
          <input type="number" name="entity" value="<?= e((string) ($filters['entity'] ?? '')) ?>" min="1" step="1">
        </div>
        <div>
          <label>Actor ID</label>
          <input type="number" name="actor" value="<?= e((string) ($filters['actor'] ?? '')) ?>" min="1" step="1">
        </div>
        <div>
          <label>Action</label>
          <select name="action">
            <?php $actions = ['create','update','delete','status_update','add_comment','upload_attachment','update_notice','update_alert']; ?>
            <option value="">Any</option>
            <?php foreach ($actions as $a): ?>
              <option value="<?= e($a) ?>" <?= (($filters['action'] ?? '') === $a) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$a))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>From</label>
          <input type="date" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>">
        </div>
        <div>
          <label>To</label>
          <input type="date" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>">
        </div>
        <div style="grid-column:1/-1">
          <label>Search</label>
          <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search details">
        </div>
        <div>
          <label>Per page</label>
          <select name="per">
            <?php foreach ([25,50,100] as $opt): ?>
              <option value="<?= $opt ?>" <?= ((int) ($per ?? 25) === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="audit-actions">
        <button type="submit" class="button">Apply</button>
        <a class="button ghost" href="<?= e(url_for('audits')) ?>">Reset</a>
      </div>
    </form>
  </div>
  <div class="audit-content">
    <?php if (!empty($flashError)): ?><div class="alert error"><?= e($flashError) ?></div><?php endif; ?>
    <?php if (!empty($flashSuccess)): ?><div class="alert success"><?= e($flashSuccess) ?></div><?php endif; ?>
    <div class="audit-toolbar">
      <div style="display:flex;gap:.5rem;align-items:center;">
        <?php
          $qs = array_merge((array) ($filters ?? []), ['page' => (int) ($page ?? 1), 'per' => (int) ($per ?? 25), 'export' => 'csv']);
          $csvUrl = url_for('audits') . '?' . http_build_query(array_filter($qs, static fn($v) => $v !== null && $v !== ''));
        ?>
        <a class="button accent" href="<?= e($csvUrl) ?>">Export CSV</a>
      </div>
      <div class="view-toggle">
        <button type="button" class="button ghost" data-mode="table">Table</button>
        <button type="button" class="button ghost" data-mode="cards">Cards</button>
        <button type="button" class="button ghost" data-mode="timeline">Timeline</button>
      </div>
    </div>

    <?php if (empty($audits)): ?>
      <p>No audit entries found.</p>
    <?php else: ?>
      <div class="audit-table">
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <?php $isGlobal = ((string) ($filters['domain'] ?? 'task')) !== 'task'; ?>
              <tr>
                <?php if ($isGlobal): ?>
                  <th>ID</th>
                  <th>Domain</th>
                  <th>Entity</th>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Date</th>
                  <th>Details</th>
                <?php else: ?>
                  <th>ID</th>
                  <th>Task</th>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Date</th>
                  <th>Details</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($audits as $row): ?>
                <tr>
                  <td><?= (int) $row['id'] ?></td>
                  <?php if ($isGlobal): ?>
                  <td><?= e($row['domain']) ?></td>
                  <td>#<?= (int) $row['entity_id'] ?></td>
                  <?php else: ?>
                  <td>#<?= (int) $row['task_id'] ?></td>
                  <?php endif; ?>
                  <td><span class="chip action" data-act="<?= e($row['action']) ?>"><?= e(ucwords(str_replace('_',' ',$row['action']))) ?></span></td>
                  <td><?= e($row['actor_name']) ?><?php if (!empty($row['actor_role'])): ?> <span class="role"><?= e(ucwords($row['actor_role'])) ?></span><?php endif; ?></td>
                  <td><?= e($row['created_at']) ?></td>
                  <td>
                    <?php $det = json_decode((string) ($row['details'] ?? ''), true); ?>
                    <?php if (is_array($det)): ?>
                      <details><summary>View</summary><div class="kv">
                        <?php foreach ($det as $k => $v): ?>
                          <div><strong><?= e((string) $k) ?>:</strong> <?= e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></div>
                        <?php endforeach; ?>
                      </div></details>
                    <?php else: ?>
                      <span class="kv"><?= e((string) ($row['details'] ?? '')) ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="audit-timeline">
        <?php
          $groups = [];
          foreach ($audits as $row) {
            $d = substr((string)($row['created_at'] ?? ''), 0, 10);
            $groups[$d][] = $row;
          }
        ?>
        <?php foreach ($groups as $day => $items): ?>
          <div class="day">
            <div class="dot"></div>
            <strong style="color:#334155;"><?= e($day) ?></strong>
            <?php foreach ($items as $row): ?>
              <div class="item">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div><?php if ($isGlobal): ?><?= e($row['domain']) ?>#<?= (int) $row['entity_id'] ?><?php else: ?>#<?= (int) $row['task_id'] ?><?php endif; ?> · <?= e($row['actor_name']) ?><?php if (!empty($row['actor_role'])): ?> <span class="role"><?= e(ucwords($row['actor_role'])) ?></span><?php endif; ?></div>
                  <span class="chip action" data-act="<?= e($row['action']) ?>"><?= e(ucwords(str_replace('_',' ',$row['action']))) ?></span>
                </div>
                <div style="color:#64748b;font-size:.85rem"><?= e($row['created_at']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="audit-cards" style="display:none">
        <?php foreach ($audits as $row): ?>
          <?php $det = json_decode((string) ($row['details'] ?? ''), true); ?>
          <div class="audit-card">
            <div class="meta">
              <div class="title"><?php if ($isGlobal): ?><?= e($row['domain']) ?>#<?= (int) $row['entity_id'] ?><?php else: ?>#<?= (int) $row['task_id'] ?><?php endif; ?> · <?= e($row['actor_name']) ?><?php if (!empty($row['actor_role'])): ?> <span class="role"><?= e(ucwords($row['actor_role'])) ?></span><?php endif; ?></div>
              <span class="chip action" data-act="<?= e($row['action']) ?>"><?= e(ucwords(str_replace('_',' ',$row['action']))) ?></span>
            </div>
            <div style="color:#64748b;font-size:.85rem"><?= e($row['created_at']) ?></div>
            <details style="margin-top:.4rem"><summary>Details</summary>
              <?php if (is_array($det)): ?>
                <div class="kv">
                  <?php foreach ($det as $k => $v): ?>
                    <div><strong><?= e((string) $k) ?>:</strong> <?= e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="kv"><?= e((string) ($row['details'] ?? '')) ?></div>
              <?php endif; ?>
            </details>
          </div>
        <?php endforeach; ?>
      </div>

      <?php
        $pageNum = (int) ($page ?? 1);
        $perNum = (int) ($per ?? 25);
        $hasNext = count($audits) === $perNum;
        $prevPage = max(1, $pageNum - 1);
        $nextPage = $pageNum + 1;
        $base = url_for('audits');
        $common = array_filter(array_merge((array) ($filters ?? []), ['per' => $perNum]), static fn($v) => $v !== null && $v !== '');
      ?>
      <div class="pager">
        <div>Page <?= $pageNum ?></div>
        <div style="display:flex;gap:.5rem;">
          <a class="button ghost" href="<?= e($base . '?' . http_build_query($common + ['page' => $prevPage])) ?>">Previous</a>
          <?php if ($hasNext): ?>
            <a class="button" href="<?= e($base . '?' . http_build_query($common + ['page' => $nextPage])) ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
(function(){
  var btns=document.querySelectorAll('.view-toggle .button');
  var table=document.querySelector('.audit-table');
  var cards=document.querySelector('.audit-cards');
  var timeline=document.querySelector('.audit-timeline');
  var mode=localStorage.getItem('auditViewMode')||'table';
  function show(el,disp){ if(el){ el.style.display=disp; } }
  function apply(m){
    show(table, m==='table'?'block':'none');
    show(cards, m==='cards'?'grid':'none');
    show(timeline, m==='timeline'?'block':'none');
    localStorage.setItem('auditViewMode', m);
  }
  for(var i=0;i<btns.length;i++){
    btns[i].addEventListener('click',function(){ apply(this.getAttribute('data-mode')); });
  }
  apply(mode);
})();
</script>

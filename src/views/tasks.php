<section class="card">
    <div class="flex-between">
        <div>
            <h2>Task Board</h2>
            <p>Create tasks, attach evidences, and keep every stakeholder in sync.</p>
        </div>
        <div class="header-actions" style="display:flex;gap:0.5rem;align-items:center;">
            <?php if (!empty($remindersFired)): ?>
                <span class="badge high"><?= $remindersFired ?> reminder<?= $remindersFired === 1 ? '' : 's' ?> fired today</span>
            <?php endif; ?>
            <?php if (user_has_role(['ceo','superadmin'])): ?>
                <form method="POST" action="<?= e(url_for('cache/clear')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="button ghost">Clear Cache</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <div class="task-filters">
        <input type="text" id="task_search" placeholder="Search tasks">
        <select id="task_status_filter">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= strtolower(str_replace(' ', '-', $statusOption['description'])) ?>"><?= e($statusOption['description']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="task_client_filter">
            <option value="">All clients</option>
            <?php foreach ($clients as $client): ?>
                <?php $clientName = (string) ($client['name'] ?? ($client['name_of_entity'] ?? '')); ?>
                <option value="<?= strtolower($clientName) ?>"><?= e($clientName) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="task_sort">
            <option value="due_asc">Due date ↑</option>
            <option value="due_desc">Due date ↓</option>
            <option value="status">Status</option>
        </select>
        <span class="spacer"></span>
        <?php if (user_has_role(['ceo'])): ?>
        <form method="GET" action="<?= e(url_for('service-types')) ?>">
            <button type="submit" class="btn-pill ghost">Service Catalog</button>
        </form>
        <?php endif; ?> 
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead></thead>
            <tbody>
                <tr><td colspan="7" style="padding:0;border:none;">
                    <div id="task-grid" class="task-grid">
                        <?php foreach ($tasks as $task): ?>
                            <?php $client = find_client($task['client_id']); ?>
                            <?php $lead = find_employee($task['lead_id']); ?>
                            <?php $teamNames = array_map(static function ($id) { $emp = find_employee((int) $id); return $emp['name'] ?? 'Unknown'; }, $task['team_ids']); ?>
                            <?php $statusSlug = strtolower(str_replace(' ', '-', $task['status'])); ?>
                            <?php $days = task_days_remaining($task); ?>
                            <div class="task-card" data-status="<?= e($statusSlug) ?>" data-client="<?= e(strtolower($client['name'] ?? '')) ?>" data-days="<?= (int) $days ?>" data-due="<?= e($task['due_date']) ?>">
                                <div class="header">
                                    <div>
                                        <strong><?= e($task['title_display'] ?? $task['title']) ?></strong>
                                        <div class="sub">Task ID: <?= e($task['code'] ?? ('T' . str_pad((string) $task['id'], 3, '0', STR_PAD_LEFT))) ?></div>
                                        <?php $rm = $task['gst']['return_month'] ?? ''; $sm = $task['gst']['send_month'] ?? ''; ?>
                                        <?php if ($rm && $sm): ?>
                                            <div class="sub">GST filing: <?= e(date('M', strtotime($rm.'-01'))) ?> - <?= e(date('M', strtotime($sm.'-01'))) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($task['reference'])): ?>
                                            <div class="sub">Legacy Ref: <?= e($task['reference']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-pill <?= e($statusSlug) ?>"><?= e($task['status']) ?></span>
                                </div>
                                <?php $editingThis = ((int)($editAlertId ?? 0) === (int) $task['id']); $noticingThis = ((int)($noticeTaskId ?? 0) === (int) $task['id']); ?>
                                <?php if (true): ?>
                                <div class="section">
                                    <div class="row"><span class="label">Client</span><span><?= e($client['name'] ?? 'Unknown client') ?></span></div>
                                    <div class="row">
                                        <span class="label">Due</span>
                                        <span>
                                            <?= e($task['due_date']) ?>
                                            <small><?= $days >= 0 ? $days . ' days left' : abs($days) . ' days overdue' ?></small>
                                        </span>
                                    </div>
                                    <?php if (!empty($task['ecd_tentative'])): ?>
                                        <div class="row"><span class="label">ECD (tent.)</span><span><?= e($task['ecd_tentative']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['ecd_dropdead'])): ?>
                                        <div class="row"><span class="label">ECD (hard)</span><span><?= e($task['ecd_dropdead']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['due_per_notice'])): ?>
                                        <div class="row"><span class="label">Due per notice</span><span><?= e($task['due_per_notice']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($task['notice_date'])): ?>
                                        <div class="row"><span class="label">Notice date</span><span><?= e($task['notice_date']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                <div class="section">
                                    <div class="tags">
                                        <?php foreach ($task['services'] as $service): ?>
                                            <span class="tag"><?= e($service) ?></span>
                                        <?php endforeach; ?>
                                        <?php $rm = $task['gst']['return_month'] ?? ''; $sm = $task['gst']['send_month'] ?? ''; ?>
                                        <?php if ($rm && $sm): ?>
                                            <span class="tag">GST filing: <?= e(date('M', strtotime($rm.'-01'))) ?> - <?= e(date('M', strtotime($sm.'-01'))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="section">
                                    <div class="row"><span class="label">Lead</span><span><?= e($lead['name'] ?? 'Unassigned') ?></span></div>
                                    <div class="row"><span class="label">Team</span><span><?= e(implode(', ', $teamNames)) ?: '—' ?></span></div>
                                </div>
                                <div class="section">
                                    <?php
                                        $currentStatusId = (int) ($task['status_id'] ?? 0);
                                        $currentStatusLabel = $task['status'] ?? '';
                                    ?>
                                    <div class="row" style="align-items:flex-end; justify-content:space-between;">
                                        <div style="flex:1;">
                                            <label class="label" for="status-<?= (int) $task['id'] ?>">Status</label>
                                            <form method="POST" action="<?= e(url_for('tasks')) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <select id="status-<?= (int) $task['id'] ?>" name="status_id" onchange="this.form.submit()">
                                                    <?php foreach ($statusOptions as $statusOption): ?>
                                                        <?php
                                                            $optionId = (int) $statusOption['id'];
                                                            $optionLabel = $statusOption['description'];
                                                            $isSelected = $currentStatusId > 0
                                                                ? $optionId === $currentStatusId
                                                                : ($optionLabel === $currentStatusLabel);
                                                        ?>
                                                        <option value="<?= e($optionId) ?>" <?= $isSelected ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </div>
                                        <span class="status-pill <?= e($statusSlug) ?>"><?= e($task['status']) ?></span>
                                    </div>
                                    <?php if ($user): ?>
                                        <?php $acknowledged = !empty($acknowledgedTasks[$task['id']] ?? false); ?>
                                        <?php $analysis = analyze_service(['due_date' => $task['due_date']]); ?>
                                        <div class="row">
                                            <?php if ($analysis['due_soon'] && !$acknowledged): ?>
                                                <form method="POST" action="<?= e(url_for('reminders/ack')) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <input type="hidden" name="redirect" value="<?= e(url_for('tasks')) ?>">
                                                    <button type="submit" class="secondary small-button">Acknowledge reminder</button>
                                                </form>
                                            <?php elseif ($acknowledged): ?>
                                                <span class="badge success">Acknowledged</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php $editingThis = ((int)($editAlertId ?? 0) === (int) $task['id']); $noticingThis = ((int)($noticeTaskId ?? 0) === (int) $task['id']); ?>
                                <?php if ($editingThis && user_has_role(['ceo'])): ?>
                                    <div class="section">
                                        <div class="row"><span class="label">Modify</span><span></span></div>
                                        <div class="row"><span class="label"></span>
                                            <span>
                                                <form method="POST" action="<?= e(url_for('tasks')) ?>" class="modify-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="update_task">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <div class="nf-grid">
                                                        <div class="nf-group full">
                                                            <label for="mod_title_<?= (int) $task['id'] ?>">Task title</label>
                                                            <input id="mod_title_<?= (int) $task['id'] ?>" type="text" name="title" required value="<?= e($task['title'] ?? '') ?>">
                                                        </div>
                                                        <?php $allowedSet = ['gst','gst filing','itr','itr filing']; ?>
                                                        <?php $selLower = array_map(static function($x){ return strtolower(trim((string)$x)); }, ($task['services'] ?? [])); ?>
                                                        <?php $nonEmptySel = array_filter($selLower, static function($x){ return $x !== ''; }); ?>
                                                        <?php $disallowed = array_diff($nonEmptySel, $allowedSet); ?>
                                                        <?php $allowedOnly = !empty($nonEmptySel) && empty($disallowed); ?>
                                                        <div id="mod_gst_months_<?= (int) $task['id'] ?>" style="display:<?= ($allowedOnly || !empty($task['gst']['filing'] ?? false)) ? 'block' : 'none' ?>;">
                                                            <div class="nf-group">
                                                                <label for="mod_gst_toggle_<?= (int) $task['id'] ?>">GST filing</label>
                                                                <label class="checkbox-label">
                                                                    <input type="checkbox" id="mod_gst_toggle_<?= (int) $task['id'] ?>" name="gst_toggle" value="1" <?= !empty($task['gst']['filing'] ?? false) ? 'checked' : '' ?> onchange="var show=this.checked; ['gst_group_start_<?= (int) $task['id'] ?>','gst_group_end_<?= (int) $task['id'] ?>','gst_group_period_<?= (int) $task['id'] ?>'].forEach(function(i){ var el=document.getElementById(i); if(el){ el.style.display=show?'block':'none'; } }); var s=document.getElementById('mod_gst_start_<?= (int) $task['id'] ?>'); var e=document.getElementById('mod_gst_end_<?= (int) $task['id'] ?>'); if(s){ s.disabled=!show; } if(e){ e.disabled=!show; }">
                                                                    Enable
                                                                </label>
                                                            </div>

                                                            <div class="nf-group" id="gst_group_start_<?= (int) $task['id'] ?>" style="display:<?= !empty($task['gst']['filing'] ?? false) ? 'block' : 'none' ?>;">
                                                            <label for="mod_gst_start_<?= (int) $task['id'] ?>">GST start month</label>
                                                            <input id="mod_gst_start_<?= (int) $task['id'] ?>" type="month" name="gst_return_month" value="<?= e($task['gst']['return_month'] ?? '') ?>" <?= empty($task['gst']['filing'] ?? false) ? 'disabled' : '' ?> onchange="var v=this.value; if(v){ var x=v.split('-'); var yy=parseInt(x[0],10)||0; var mm=parseInt(x[1],10)||0; var nm=mm+1; var ny=yy; if(nm>12){ nm=1; ny=yy+1; } var s=(nm<10?'0'+nm:nm); var out=ny+'-'+s; var t=document.getElementById('mod_gst_end_<?= (int) $task['id'] ?>'); if(t){ t.value=out; } }">
                                                            </div>
                                                            <div class="nf-group" id="gst_group_end_<?= (int) $task['id'] ?>" style="display:<?= !empty($task['gst']['filing'] ?? false) ? 'block' : 'none' ?>;">
                                                            <label for="mod_gst_end_<?= (int) $task['id'] ?>">GST end month</label>
                                                            <input id="mod_gst_end_<?= (int) $task['id'] ?>" type="month" name="gst_send_month" value="<?= e($task['gst']['send_month'] ?? '') ?>" readonly <?= empty($task['gst']['filing'] ?? false) ? 'disabled' : '' ?>>
                                                            </div>
                                                        </div>
    
                                                        <div class="nf-group">
                                                            <label for="mod_task_year_<?= (int) $task['id'] ?>">Year</label>
                                                            <input id="mod_task_year_<?= (int) $task['id'] ?>" type="number" name="task_year" min="2000" max="2100" step="1" required value="<?= e((string) ($task['year'] ?? date('Y'))) ?>">
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="mod_client_<?= (int) $task['id'] ?>">Client</label>
                                                            <select id="mod_client_<?= (int) $task['id'] ?>" name="client_id" required>
                                                                <option value="">Select client</option>
                                                                <?php foreach ($clients as $client): ?>
                                                                    <?php $cid = (string) ($client['c_id'] ?? ($client['id'] ?? '')); ?>
                                                                    <?php $cname = (string) ($client['name'] ?? ($client['name_of_entity'] ?? '')); ?>
                                                                    <option value="<?= $cid ?>" <?= (string) $cid === (string) ($task['client_id'] ?? 0) ? 'selected' : '' ?>><?= e($cname) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="nf-group full">
                                                            <label for="mod_services_<?= (int) $task['id'] ?>">Services</label>
                                                            <select id="mod_services_<?= (int) $task['id'] ?>" name="services[]" multiple size="4" required>
                                                                <?php foreach ($serviceCatalog as $service): ?>
                                                                    <option value="<?= e($service) ?>" <?= in_array($service, ($task['services'] ?? []), true) ? 'selected' : '' ?>><?= e($service) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="mod_lead_<?= (int) $task['id'] ?>">Lead</label>
                                                            <select id="mod_lead_<?= (int) $task['id'] ?>" name="lead_id" required>
                                                                <option value="">Select lead</option>
                                                                <?php foreach ($leads as $lead): ?>
                                                                    <option value="<?= $lead['id'] ?>" <?= (string) $lead['id'] === (string) ($task['lead_id'] ?? 0) ? 'selected' : '' ?>><?= e($lead['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="mod_team_<?= (int) $task['id'] ?>">Team members</label>
                                                            <select id="mod_team_<?= (int) $task['id'] ?>" name="team_ids[]" multiple size="4">
                                                                <?php foreach ($teamMembers as $member): ?>
                                                                    <option value="<?= $member['id'] ?>" <?= in_array((int) $member['id'], ($task['team_ids'] ?? []), true) ? 'selected' : '' ?>><?= e($member['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="mod_status_<?= (int) $task['id'] ?>">Status</label>
                                                            <select id="mod_status_<?= (int) $task['id'] ?>" name="status_id" required>
                                                                <?php foreach ($statusOptions as $statusOption): ?>
                                                                    <?php $sid = (int) $statusOption['id']; ?>
                                                                    <option value="<?= e($sid) ?>" <?= $sid === (int) ($task['status_id'] ?? 0) ? 'selected' : '' ?>><?= e($statusOption['description']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="mod_due_<?= (int) $task['id'] ?>">Due date</label>
                                                            <input id="mod_due_<?= (int) $task['id'] ?>" type="date" name="due_date" required value="<?= e($task['due_date'] ?? date('Y-m-d')) ?>">
                                                        </div>
                                                        
                                                        <?php if (user_has_role(['ceo'])): ?>
                                                        <div class="nf-group">
                                                            <strong><label for="alert_date_<?= (int) $task['id'] ?>">Alert date</label></strong>
                                                            <?php $alertDate = $task['alert']['date'] ?? ''; ?>
                                                            <input id="alert_date_<?= (int) $task['id'] ?>" type="date" name="alert_date" value="<?= e($alertDate) ?>">
                                                        </div>
                                                        <?php endif; ?>
                                                          <div class="comment-list">
                                                        <?php $comments = $task['comments'] ?? []; ?>
                                                        <?php foreach ($comments as $c): ?>
                                                            <div class="comment"><strong><?= e($c['author']) ?></strong> <small class="muted"><?= e($c['created_at']) ?></small><div><?= e($c['body']) ?></div></div>
                                                        <?php endforeach; ?>
                                                        <?php if (empty($comments)): ?><span class="muted">No comments</span><?php endif; ?>
                                                    </div>
                                                        <div class="nf-group full">
                                                            <label for="modify_comment_<?= (int) $task['id'] ?>">Comments</label>
                                                            <input id="modify_comment_<?= (int) $task['id'] ?>" type="text" name="update_comment" placeholder="Add an update">
                                                        </div>
                                                        <div class="nf-actions">
                                                            <button type="submit" class="btn-pill">Save Changes</button>
                                                        </div>
                                                    </div>
                                                </form>
                                                <script>
                                                (function(){
                                                    var sel=document.getElementById('mod_services_<?= (int) $task['id'] ?>');
                                                    var box=document.getElementById('mod_gst_months_<?= (int) $task['id'] ?>');
                                                    var cb=document.getElementById('mod_gst_toggle_<?= (int) $task['id'] ?>');
                                                    function allowedOnly(){
                                                        if(!sel) return false;
                                                        var allowed={'gst':1,'gst filing':1,'itr':1,'itr filing':1};
                                                        var any=false, dis=false;
                                                        for(var i=0;i<sel.options.length;i++){
                                                            var o=sel.options[i];
                                                            if(o.selected){ any=true; var v=(String(o.value||'').toLowerCase()).trim(); if(!allowed[v]) dis=true; }
                                                        }
                                                        return any && !dis;
                                                    }
                                                    function update(){
                                                        var vis=allowedOnly();
                                                        if(!box) return;
                                                        box.style.display=vis?'block':'none';
                                                        if(!vis && cb){ cb.checked=false; }
                                                        var s=document.getElementById('mod_gst_start_<?= (int) $task['id'] ?>');
                                                        var e=document.getElementById('mod_gst_end_<?= (int) $task['id'] ?>');
                                                        if(s) s.disabled=!vis;
                                                        if(e) e.disabled=!vis;
                                                        ['gst_group_start_<?= (int) $task['id'] ?>','gst_group_end_<?= (int) $task['id'] ?>','gst_group_period_<?= (int) $task['id'] ?>'].forEach(function(i){ var el=document.getElementById(i); if(el){ el.style.display=vis && cb && cb.checked ? 'block' : 'none'; } });
                                                    }
                                                    update();
                                                    if(sel){ sel.addEventListener('change', update); }
                                                })();
                                                </script>
                                            </span>
                                        </div>
                                    </div>
                                <?php elseif ($noticingThis): ?>
                                    <div class="section">
                                        <div class="row"><span class="label">Notice</span><span></span></div>
                                        <div class="row"><span class="label"></span>
                                            <span>
                                                <form method="POST" action="<?= e(url_for('tasks')) ?>" enctype="multipart/form-data" class="notice-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="update_notice">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <div class="nf-grid">
                                                        <div class="nf-group">
                                                            <label for="notice_date_<?= (int) $task['id'] ?>">Notice date</label>
                                                            <input id="notice_date_<?= (int) $task['id'] ?>" type="date" name="notice_date" value="<?= e($task['notice_date'] ?? '') ?>">
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="due_per_notice_<?= (int) $task['id'] ?>">Notice end date</label>
                                                            <input id="due_per_notice_<?= (int) $task['id'] ?>" type="date" name="due_per_notice" value="<?= e($task['due_per_notice'] ?? '') ?>">
                                                        </div>
                                                        <div class="nf-group">
                                                            <label for="notice_pdf_<?= (int) $task['id'] ?>">Attachment</label>
                                                            <input id="notice_pdf_<?= (int) $task['id'] ?>" type="file" name="attachment" accept=".pdf">
                                                        </div>
                                                        <div class="nf-group">
                                                            <?php $ack = !empty($acknowledgedTasks[$task['id']] ?? false); ?>
                                                            <label for="ack_<?= (int) $task['id'] ?>" class="checkbox-label">
                                                                <input type="checkbox" id="ack_<?= (int) $task['id'] ?>" <?= $ack ? 'checked disabled' : '' ?> onchange="if(this.checked){ var f=document.getElementById('ack_form_<?= (int) $task['id'] ?>'); if(f) f.submit(); }">
                                                                Acknowledge Notice
                                                            </label>
                                                        </div>
                                                        <div class="comment-list">
                                                        <?php $comments = $task['comments'] ?? []; ?>
                                                        <?php foreach ($comments as $c): ?>
                                                            <div class="comment"><strong><?= e($c['author']) ?></strong> <small class="muted"><?= e($c['created_at']) ?></small><div><?= e($c['body']) ?></div></div>
                                                        <?php endforeach; ?>
                                                        <?php if (empty($comments)): ?><span class="muted">No comments</span><?php endif; ?>
                                                        </div>
                                                        <div class="nf-group full">
                                                            <label for="comment_<?= (int) $task['id'] ?>">Comments</label>
                                                            <input id="comment_<?= (int) $task['id'] ?>" type="text" name="comment" placeholder="Add a comment">
                                                        </div>
                                                        <div class="nf-actions">
                                                            <button type="submit" class="btn-pill">Save Notice</button>
                                                        </div>
                                                    </div>
                                                    
                                                </form>
                                                <form id="ack_form_<?= (int) $task['id'] ?>" method="POST" action="<?= e(url_for('reminders/ack')) ?>" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                    <input type="hidden" name="redirect" value="<?= e(url_for('tasks')) ?>">
                                                </form>
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="section">
                                        <div class="row"><span class="label">Reminder</span>
                                            <span>
                                                <?php $ack = !empty($acknowledgedTasks[$task['id']] ?? false); $analysis = analyze_service(['due_date' => $task['due_date']]); ?>
                                                <?php if (!$ack && ($analysis['due_soon'] || !empty($task['notice_date']))): ?>
                                                    <form method="POST" action="<?= e(url_for('reminders/ack')) ?>" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                        <input type="hidden" name="redirect" value="<?= e(url_for('tasks')) ?>">
                                                        <button type="submit" class="secondary small-button">Acknowledge</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="muted">Acknowledged</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php $viewId = (int) ($viewTaskId ?? 0); ?>
                                <?php if ($viewId !== (int) $task['id']): ?>
                                <div class="card-actions">
                                    <?php if (user_has_role(['ceo'])): ?>
                                    <form method="GET" action="<?= e(url_for('tasks')) ?>">
                                        <input type="hidden" name="edit_alert" value="<?= $task['id'] ?>">
                                        <button type="submit" class="button ghost small-button">Modify</button>
                                    </form>
                                    <?php endif; ?>
                                    <span class="action-sep" aria-hidden="true"></span>
                                    <?php if (user_has_role(['ceo'])): ?>
                                    <form method="POST" action="<?= e(url_for('tasks')) ?>" onsubmit="return confirm('Delete Task #<?= $task['id'] ?>? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" class="button danger small-button">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                    <span class="action-sep" aria-hidden="true"></span>
                                    <form method="GET" action="<?= e(url_for('tasks')) ?>">
                                        <input type="hidden" name="notice_task" value="<?= $task['id'] ?>">
                                        <button type="submit" class="secondary small-button">Notice</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align:center;margin-top:0.5rem;"><button id="load-more-tasks" class="button ghost small-button">Load more</button></div>
                    <script>
                    (function(){
                      var page=2, limit=50, loading=false, done=false;
                      var grid=document.getElementById('task-grid');
                      var btn=document.getElementById('load-more-tasks');
                      if(!grid||!btn) return;
                      function pill(slug,label){ return '<span class="status-pill '+slug+'">'+label+'</span>'; }
                      function tag(s){ return '<span class="tag">'+s+'</span>'; }
                      function card(t){
                        var slug=(t.status||'').toLowerCase().replace(/\s+/g,'-');
                        var clientName=(t.client_name||t.client||'');
                        var clientSlug=clientName.toLowerCase();
                        var services=(t.services||[]).map(tag).join('');
                        var days='';
                        if(t.due_date){
                          try{ var d=new Date(t.due_date); var td=new Date(); td.setHours(0,0,0,0); var diff=Math.round((d-td)/86400000); days=diff>=0?diff+' days left':Math.abs(diff)+' days overdue'; }catch(e){}
                        }
                        return '<div class="task-card" data-status="'+slug+'" data-client="'+clientSlug+'" data-days="" data-due="'+(t.due_date||'')+'">'
                          +'<div class="header"><div><strong>'+(t.title_display||t.title||'')+'</strong><div class="sub">Task ID: '+(t.code||('T'+String(t.id||'')))+'</div></div>'+pill(slug,(t.status||''))+'</div>'
                          +'<div class="section"><div class="row"><span class="label">Client</span><span>'+clientName+'</span></div><div class="row"><span class="label">Due</span><span>'+(t.due_date||'')+' <small>'+days+'</small></span></div></div>'
                          +'<div class="section"><div class="tags">'+services+'</div></div>'
                          +'</div>';
                      }
                      function fetchPage(){
                        if(loading||done) return; loading=true; btn.disabled=true;
                        fetch('<?= e(url_for('tasks.json')) ?>?page='+page+'&limit='+limit, { headers: { 'Accept': 'application/json' }}).then(function(r){return r.json();}).then(function(data){
                          var items=Array.isArray(data)?data:(data.items||[]);
                          var html=items.map(card).join('');
                          grid.insertAdjacentHTML('beforeend', html);
                          if(!Array.isArray(data) && data.next_page){ page=data.next_page; btn.disabled=false; btn.style.display=''; }
                          else if(Array.isArray(data) && items.length===limit){ page+=1; btn.disabled=false; btn.style.display=''; }
                          else { done=true; btn.style.display='none'; }
                          var sortEl=document.getElementById('task_sort'); if(sortEl){ sortEl.dispatchEvent(new Event('change')); }
                          var qEl=document.getElementById('task_search'); if(qEl){ qEl.dispatchEvent(new Event('input')); }
                        }).catch(function(){ btn.disabled=false; }).finally(function(){ loading=false; });
                      }
                      btn.addEventListener('click', fetchPage);
                    })();
                    </script>
                </td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <?php
        $editingTask = $editTask ?? null;
        $isEditingTask = is_array($editingTask) && !empty($editingTask);
        $formTitle = $isEditingTask ? ($editingTask['title'] ?? '') : '';
        $formYear = $isEditingTask ? ($editingTask['year'] ?? date('Y')) : date('Y');
        $formClientId = $isEditingTask ? (int) ($editingTask['client_id'] ?? 0) : 0;
        $formServices = $isEditingTask ? ($editingTask['services'] ?? []) : [];
        $formLeadId = $isEditingTask ? (int) ($editingTask['lead_id'] ?? 0) : 0;
        if (!$isEditingTask && $formLeadId === 0 && user_has_role('lead')) {
            $formLeadId = (int) ($user['id'] ?? 0);
        }
        $formTeamIds = $isEditingTask ? array_map('intval', $editingTask['team_ids'] ?? []) : [];
        $formStatusId = $isEditingTask ? (int) ($editingTask['status_id'] ?? 0) : null;
        if (!$isEditingTask && !empty($statusOptions)) {
            $firstStatus = $statusOptions[0]['id'] ?? null;
            if ($firstStatus !== null) {
                $formStatusId = (int) $firstStatus;
            }
        }
        $formDueDate = $isEditingTask
            ? ($editingTask['due_date'] ?? date('Y-m-d'))
            : date('Y-m-d', strtotime('+7 days'));
        $formTaskId = $isEditingTask ? (int) $editingTask['id'] : null;
    ?><section class="card creative-form" style="background:linear-gradient(180deg,#0f172a 0%,#111827 100%);border:1px solid rgba(99,102,241,.25);border-radius:18px;padding:1rem;box-shadow:0 25px 50px rgba(17,24,39,.35);">
    <div class="creative-header" style="display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 60%);padding:1rem 1.25rem;border-radius:14px;box-shadow:0 18px 40px rgba(14,165,233,.25);color:#f8fafc;">
        <div>
            <h2 style="margin:0;font-size:1.35rem;letter-spacing:.3px;"><?= $isEditingTask ? 'Update task' : 'Create task' ?></h2>
            <small style="opacity:.9;display:block;">Engagement details, owners, and timeline</small>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;">
            <?php if ($isEditingTask): ?>
                <a href="<?= e(url_for('tasks')) ?>" class="button ghost" style="border-radius:999px;">Cancel edit</a>
            <?php endif; ?>
            <?php if (user_has_permission('services','read')): ?>
                <button type="button" class="secondary" onclick="toggleServicePanel()" style="border-radius:999px;">Service engagements</button>
            <?php endif; ?>
        </div>
    </div>
    <style>
        .creative-form .form-group label{color:#000;font-weight:600}
        .creative-form input,.creative-form select,.creative-form textarea{border:1px solid rgba(148,163,184,.3);background:#0b1220;color:#f8fafc;border-radius:12px;padding:.6rem .75rem}
        .creative-form input:focus,.creative-form select:focus,.creative-form textarea:focus{outline:none;border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.2)}
        .creative-form .creative-grid{gap:1rem}
        .creative-form .btn-pill{border-radius:999px;padding:.6rem 1.2rem;font-weight:600}
    </style>
    <?php if (user_has_role(['ceo','lead'])): ?>
    <form method="POST" action="<?= e(url_for('tasks')) ?>" class="task-create-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $isEditingTask ? 'update_task' : 'create_task' ?>">
        <?php if ($isEditingTask): ?>
            <input type="hidden" name="task_id" value="<?= $formTaskId ?>">
        <?php endif; ?>

        <?php $taskCfg = $isEditingTask && $formTaskId ? fetch_task_config($formTaskId) : []; ?>
        <?php $gstRet = $taskCfg['gst']['return_month'] ?? date('Y-m'); ?>
        <?php $gstSend = date('Y-m', strtotime('+1 month', strtotime($gstRet.'-01'))); ?>
        <div class="form-group" style="grid-column:1/-1;">
            <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1 1 380px;">
                    <label for="title">Task title</label>
                    <input id="title" name="title" required value="<?= e($formTitle) ?>">
                </div>
                <?php $allowedSet = ['gst','gst filing','itr','itr filing']; ?>
                <?php $selLower = array_map(static function($x){ return strtolower(trim((string)$x)); }, $formServices); ?>
                <?php $nonEmptySel = array_filter($selLower, static function($x){ return $x !== ''; }); ?>
                <?php $disallowed = array_diff($nonEmptySel, $allowedSet); ?>
                <?php $allowedOnly = !empty($nonEmptySel) && empty($disallowed); ?>
                <div id="gst-months" style="display:<?= ($allowedOnly || !empty($taskCfg['gst']['filing'] ?? false)) ? 'block' : 'none' ?>;flex:0 1 360px;">
                    <div class="nf-group">
                        <label for="create_gst_toggle">GST filing</label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="create_gst_toggle" name="gst_toggle" value="1" <?= !empty($taskCfg['gst']['filing'] ?? false) ? 'checked' : '' ?> onchange="var show=this.checked; ['gst_group_start','gst_group_end','gst_group_period'].forEach(function(i){ var el=document.getElementById(i); if(el){ el.style.display=show?'block':'none'; } }); var s=document.getElementById('create_gst_start'); var e=document.getElementById('create_gst_end'); if(s){ s.disabled=!show; } if(e){ e.disabled=!show; }">
                            Enable
                        </label>
                    </div>
                    <div class="nf-group" id="gst_group_start" style="display:<?= !empty($taskCfg['gst']['filing'] ?? false) ? 'block' : 'none' ?>;">
                        <label for="create_gst_start">GST start month</label>
                        <input id="create_gst_start" type="month" name="gst_return_month" value="<?= e($gstRet) ?>" <?= empty($taskCfg['gst']['filing'] ?? false) ? 'disabled' : '' ?> onchange="var v=this.value; var out=''; if(v){ var x=v.split('-'); var yy=parseInt(x[0],10)||0; var mm=parseInt(x[1],10)||0; var nm=mm+1; var ny=yy; if(nm>12){ nm=1; ny=yy+1; } var s=(nm<10?'0'+nm:nm); out=ny+'-'+s; } var t=document.getElementById('create_gst_end'); if(t){ t.value=out; } var p=document.getElementById('gst_period_text'); var b=document.getElementById('gst_group_period'); if(p){ p.textContent=(v? (new Date(v+'-01')).toLocaleString('en',{month:'short'})+' - '+(new Date(out+'-01')).toLocaleString('en',{month:'short'}) : ''); } if(b){ b.style.display=(v?'block':'none'); }">
                    </div>
                    <div class="nf-group" id="gst_group_end" style="display:<?= !empty($taskCfg['gst']['filing'] ?? false) ? 'block' : 'none' ?>;">
                        <label for="create_gst_end">GST end month</label>
                        <input id="create_gst_end" type="month" name="gst_send_month" value="<?= e($gstSend) ?>" readonly <?= empty($taskCfg['gst']['filing'] ?? false) ? 'disabled' : '' ?>>
                    </div>
                    <div class="nf-group full" id="gst_group_period" style="display:<?= (!empty($taskCfg['gst']['filing'] ?? false) && !empty($gstRet) && !empty($gstSend)) ? 'block' : 'none' ?>;">
                        <label>GST period</label>
                        <span class="tag" id="gst_period_text">
                            <?= (!empty($gstRet) && !empty($gstSend)) ? e(date('M', strtotime($gstRet.'-01')) . ' - ' . date('M', strtotime($gstSend.'-01'))) : '' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="task_year">Year</label>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <input id="task_year" name="task_year" type="number" min="2000" max="2100" step="1" value="<?= e((string) $formYear) ?>" required>
                <span class="tag" id="fy_display">FY: <?= e((string) $formYear) ?> - <?= e((string) ($formYear + 1)) ?></span>
            </div>
        </div>
        <div class="form-group">
            <label for="client_id">Client</label>
            <select id="client_id" name="client_id" required>
                <option value="">Select client</option>
                <?php foreach ($clients as $client): ?>
                    <?php $cid = (string) ($client['c_id'] ?? ($client['id'] ?? '')); ?>
                    <?php $cname = (string) ($client['name'] ?? ($client['name_of_entity'] ?? '')); ?>
                    <option value="<?= $cid ?>" <?= (string) $cid === (string) $formClientId ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label for="services">Services</label>
            <select id="services" name="services[]" multiple size="4" required data-service-source="<?= e(url_for('service-types.json')) ?>">
                <?php foreach ($serviceCatalog as $service): ?>
                    <option value="<?= e($service) ?>" <?= in_array($service, $formServices, true) ? 'selected' : '' ?>><?= e($service) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Select one or more services.</small>
            <div class="service-types-list" id="service-types-display" style="display:none;"></div>
        </div>
        <div class="form-group">
            <label for="lead_id">Lead</label>
            <select id="lead_id" name="lead_id" required>
                <option value="">Select lead</option>
                <?php foreach ($leads as $lead): ?>
                    <option value="<?= $lead['id'] ?>" <?= (string) $lead['id'] === (string) $formLeadId ? 'selected' : '' ?>><?= e($lead['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <div class="form-row-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
                <label for="team_ids" style="margin:0;font-weight:600;">Team members</label>
                <div class="category-filter" style="display:flex;align-items:center;gap:0.5rem;">
                    <span style="color:var(--muted);font-size:0.9rem;">Category</span>
                    <?php $teamCategory = strtolower($teamCategory ?? 'employee'); ?>
                    <select id="team_category" name="team_category" style="min-width:140px;" onchange="var u='<?= e(url_for('tasks')) ?>?team_category='+encodeURIComponent(this.value);<?php if ($isEditingTask): ?>u+='&edit_task=<?= (int) $formTaskId ?>';<?php endif; ?>location.href=u;">
                        <option value="employee" <?= $teamCategory === 'employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="team" <?= $teamCategory === 'team' ? 'selected' : '' ?>>Team</option>
                        <option value="lead" <?= $teamCategory === 'lead' ? 'selected' : '' ?>>Lead</option>
                    </select>
                </div>
            </div>
            <select id="team_ids" name="team_ids[]" multiple size="4">
                <?php foreach ($teamMembers as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= in_array((int) $member['id'], $formTeamIds, true) ? 'selected' : '' ?>><?= e($member['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="due_date">Expected completion date (EDC)</label>
            <input id="due_date" name="due_date" type="date" required value="<?= e($formDueDate) ?>">
        </div>
        <div class="form-group">
            <div class="status-manage-row">
                <label for="status_id">Status</label>
                <?php if (user_has_role(['ceo'])): ?>
                    <button type="button" class="button ghost status-manage-button" onclick="location.href='<?= e(url_for('statuses')) ?>'">Manage</button>
                <?php endif; ?>
            </div>
            <select id="status_id" name="status_id" required>
                <?php foreach ($statusOptions as $statusOption): ?>
                    <?php $isSelectedStatus = (string) $formStatusId === (string) $statusOption['id']; ?>
                    <option value="<?= e($statusOption['id']) ?>" <?= $isSelectedStatus ? 'selected' : '' ?>><?= e($statusOption['description']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (user_has_role(['ceo'])): ?>
            <div class="form-group">
                <label for="alert_date">Alert date (CEO-only)</label>
                <?php $alertDate = $taskCfg['alert']['date'] ?? ''; ?>
                <input id="alert_date" name="alert_date" type="date" value="<?= e($alertDate) ?>">
                <small>Shows dashboard alerts if not Completed before this date.</small>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="notice_date">Notice date</label>
            <input id="notice_date" name="notice_date" type="date" value="<?= e($isEditingTask ? ($editingTask['notice_date'] ?? '') : '') ?>">
        </div>
        <div class="form-group">
            <label for="due_per_notice">Notice end date</label>
            <input id="due_per_notice" name="due_per_notice" type="date" value="<?= e($isEditingTask ? ($editingTask['due_per_notice'] ?? '') : '') ?>">
        </div>
        <?php if ($isEditingTask): ?>
            <div class="form-group" style="grid-column:1/-1;">
                <label for="update_comment">Update comment</label>
                <textarea id="update_comment" name="update_comment" maxlength="<?= TASK_COMMENT_LIMIT ?>" rows="2" placeholder="Add an update for this task"></textarea>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label for="notice_pdf">Upload notice PDF</label>
                <input id="notice_pdf" name="notice_pdf" type="file" accept=".pdf">
            </div>
        <?php else: ?>
            <div class="form-group" style="grid-column:1/-1;">
                <label for="initial_comment">Initial comment</label>
                <textarea id="initial_comment" name="initial_comment" maxlength="<?= TASK_COMMENT_LIMIT ?>" rows="2" placeholder="Kick-off update"></textarea>
            </div>
        <?php endif; ?>
        <div class="form-group create-actions">
            <button type="submit" class="btn-pill"><?= $isEditingTask ? 'Update task' : 'Create task' ?></button>
        </div>
    </form>
    <?php endif; ?>
    <script>
    (function(){
        function allowedOnlySelected(){
            var s=document.getElementById('services');
            if(!s) return false;
            var allowed={'gst':1,'gst filing':1,'itr':1,'itr filing':1};
            var hasAny=false; var hasDisallowed=false;
            for(var i=0;i<s.options.length;i++){
                var o=s.options[i];
                if(o.selected){
                    var v=(String(o.value||'').toLowerCase()).trim();
                    if(v){ hasAny=true; if(!allowed[v]) { hasDisallowed=true; } }
                }
            }
            return hasAny && !hasDisallowed;
        }
        function updateGst(){
            var container=document.getElementById('gst-months');
            var cb=document.getElementById('create_gst_toggle');
            var visible = allowedOnlySelected() || (cb && cb.checked);
            if(container){ container.style.display = visible ? 'block' : 'none'; }
            var s=document.getElementById('create_gst_start');
            var e=document.getElementById('create_gst_end');
            var period=document.getElementById('gst_group_period');
            if(s) s.disabled = !visible;
            if(e) e.disabled = !visible;
            if(period && !visible) period.style.display='none';
        }
        function updateServiceDisplay(){
            var s=document.getElementById('services');
            var el=document.getElementById('service-types-display');
            if(!s||!el) return;
            var sel=[]; for(var i=0;i<s.options.length;i++){ var o=s.options[i]; if(o.selected){ sel.push(o.value); } }
            el.style.display = sel.length ? 'block' : 'none';
            el.innerHTML = sel.map(function(x){ return '<span class="tag">'+x+'</span>'; }).join('');
        }
        function updateSendMonth(){
            var s=document.getElementById('create_gst_start');
            var e=document.getElementById('create_gst_end');
            var t=document.getElementById('gst_period_text');
            var b=document.getElementById('gst_group_period');
            var v=s && s.value; var out='';
            if(v){ var x=v.split('-'); var yy=parseInt(x[0],10)||0; var mm=parseInt(x[1],10)||0; var nm=mm+1; var ny=yy; if(nm>12){ nm=1; ny=yy+1; } var s2=(nm<10?'0'+nm:nm); out=ny+'-'+s2; }
            if(e) e.value=out;
            if(t) t.textContent = v ? (new Date(v+'-01')).toLocaleString('en',{month:'short'})+' - '+(new Date(out+'-01')).toLocaleString('en',{month:'short'}) : '';
            if(b) b.style.display = v ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded',function(){
            var s=document.getElementById('services');
            var cb=document.getElementById('create_gst_toggle');
            var year=document.getElementById('task_year'); var fy=document.getElementById('fy_display');
            function updateFy(){ if(!year||!fy) return; var y=parseInt(year.value,10); if(!isNaN(y)){ fy.textContent='FY: '+y+' - '+(y+1); } }
            updateGst(); updateServiceDisplay(); updateSendMonth(); updateFy();
            if(s){ s.addEventListener('change', function(){ updateGst(); updateServiceDisplay(); }); }
            if(cb){ cb.addEventListener('change', updateGst); }
            var gstStart=document.getElementById('create_gst_start');
            if(gstStart){ gstStart.addEventListener('change', updateSendMonth); }
            if(year){ year.addEventListener('input', function(){ updateFy(); updateSendMonth(); }); }
        });
    })();
    </script>
</section>

<?php if (user_has_permission('services','read')): ?>
<section class="card" id="embedded-services" style="display:none;">
    <div class="flex-between">
        <div>
            <h2>Service Assignments</h2>
            <p>Manage client engagements without leaving the Task Board.</p>
        </div>
        <div class="service-actions"></div>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Work Stream</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <?php $analysis = analyze_service($service); ?>
                    <tr>
                        <td><?= e($service['client']) ?></td>
                        <td><?= e($service['work_stream']) ?></td>
                        <td><?= e($service['employee']) ?></td>
                        <td><?= e($service['status']) ?></td>
                        <td><?= e($service['progress']) ?>%</td>
                        <td>
                            <?= e($service['due_date']) ?>
                            <?php if ($analysis['due_soon']): ?>
                                <span class="badge high">Due soon</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 style="margin-top:1.5rem;">Create / Update Engagement</h3>
    <form method="POST" action="<?= e(url_for('services')) ?>" class="layout-two-column">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
            <label for="service_client_id">Client</label>
            <select id="service_client_id" name="client_id" required>
                <option value="">Select client</option>
                <?php foreach ($clients as $client): ?>
                    <?php $cid = (string) ($client['id'] ?? ($client['c_id'] ?? '')); ?>
                    <?php $cname = (string) ($client['name'] ?? ($client['name_of_entity'] ?? '')); ?>
                    <option value="<?= $cid ?>"><?= e($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="service_lead_id">Assigned Lead</label>
            <select id="service_lead_id" name="lead_id" required>
                <option value="">Select lead</option>
                <?php foreach ($leads as $lead): ?>
                    <option value="<?= $lead['id'] ?>"><?= e($lead['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="service_work_stream">Work Stream</label>
            <input id="service_work_stream" name="work_stream" required>
        </div>
        <div class="form-group">
            <label for="service_task_year">Year</label>
            <input id="service_task_year" name="task_year" type="number" min="2000" max="2100" step="1" value="<?= date('Y') ?>" required>
        </div>
        <div class="form-group">
            <div class="status-manage-row">
                <label for="service_status_id">Status</label>
                <?php if (user_has_role(['ceo'])): ?>
                    <form method="GET" action="<?= e(url_for('statuses')) ?>" class="inline"><button type="submit" class="button ghost status-manage-button">Manage</button></form>>
                <?php endif; ?>
            </div>
            <select id="service_status_id" name="status_id" required>
                <?php foreach ($statusOptions as $statusOption): ?>
                    <option value="<?= e($statusOption['id']) ?>"><?= e($statusOption['description']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="service_progress">Progress (%)</label>
            <input id="service_progress" name="progress" type="number" min="0" max="100" value="0" required>
        </div>
        <div class="form-group">
            <label for="service_due_date">Due Date</label>
            <input id="service_due_date" name="due_date" type="date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        </div>
        <div class="form-group">
            <label for="service_priority">Priority</label>
            <select id="service_priority" name="priority">
                <option>Critical</option>
                <option selected>High</option>
                <option>Medium</option>
                <option>Low</option>
            </select>
        </div>
        <div style="grid-column: 1 / -1; text-align:right;">
            <button type="submit">Save Service</button>
        </div>
    </form>
</section>
<?php endif; ?>

<script>
function toggleServicePanel() {
    var panel = document.getElementById('embedded-services');
    if (!panel) return;
    var isHidden = panel.style.display === 'none' || panel.style.display === '';
    panel.style.display = isHidden ? 'block' : 'none';
}

function syncServiceCatalog() {
    var select = document.getElementById('services');
    if (!select) return;
    var source = select.dataset.serviceSource;
    if (!source) return;

    var display = document.getElementById('service-types-display');

    fetch(source, { headers: { 'Accept': 'application/json' }})
        .then(function(response) {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(function(items) {
            if (!Array.isArray(items)) {
                throw new Error('Invalid payload');
            }
            var currentSelections = Array.from(select.selectedOptions).map(function(opt) { return opt.value; });
            select.innerHTML = '';
            items.forEach(function(name) {
                var option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                if (currentSelections.includes(name)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (display) {
                if (items.length === 0) {
                    display.innerHTML = '<strong>Available services:</strong> <span>No service types defined yet. Add one from the Service engagements panel.</span>';
                } else {
                    display.innerHTML = '<strong>Available services:</strong> <span>' + items.join(', ') + '</span>';
                }
            }
        })
        .catch(function() {
            if (display) {
                display.innerHTML = '<strong>Available services:</strong> <span>Unable to load service catalog.</span>';
            }
        });
}

function initTaskFilters() {
    var grid = document.querySelector('.task-grid');
    if (!grid) return;
    var q = document.getElementById('task_search');
    var sf = document.getElementById('task_status_filter');
    var cf = document.getElementById('task_client_filter');
    var sort = document.getElementById('task_sort');
    function getCards(){ return Array.from(document.querySelectorAll('.task-card')); }
    function apply() {
        var cards = getCards();
        var query = (q && q.value || '').toLowerCase();
        var status = sf && sf.value || '';
        var client = cf && cf.value || '';
        cards.forEach(function(card){
            var ok = true;
            var t = (card.textContent || '').toLowerCase();
            if (query && t.indexOf(query) === -1) ok = false;
            if (status && card.dataset.status !== status) ok = false;
            if (client && card.dataset.client !== client) ok = false;
            card.style.display = ok ? '' : 'none';
        });
        if (sort) {
            var by = sort.value;
            var visible = cards.filter(function(c){ return c.style.display !== 'none'; });
            visible.sort(function(a,b){
                if (by === 'due_asc' || by === 'due_desc') {
                    var ca = Date.parse(a.dataset.due || '') || 0;
                    var cb = Date.parse(b.dataset.due || '') || 0;
                    return by === 'due_asc' ? ca - cb : cb - ca;
                } else if (by === 'status') {
                    return (a.dataset.status || '').localeCompare(b.dataset.status || '');
                }
                return 0;
            });
            visible.forEach(function(c){ grid.appendChild(c); });
        }
    }
    ['input','change'].forEach(function(ev){
        if (q) q.addEventListener(ev, apply);
        if (sf) sf.addEventListener(ev, apply);
        if (cf) cf.addEventListener(ev, apply);
        if (sort) sort.addEventListener(ev, apply);
    });
    apply();
}

document.addEventListener('DOMContentLoaded', function(){ syncServiceCatalog(); initTaskFilters(); });

</script>

<style>
#embedded-services .service-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}
#embedded-services .quick-service-form {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.task-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    min-width: 240px;
}
.task-actions form {
    margin: 0;
    display: inline-flex;
}
.task-actions .small-button {
    width: auto;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    border-radius: 999px;
}
.card-actions form {
    margin: 0;
    display: inline-flex;
}
.card-actions .small-button {
    width: auto;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    border-radius: 999px;
}
.card-actions .action-sep {
    display: inline-block;
    width: 1px;
    height: 24px;
    background: #e5e7eb;
    margin: 0 0.5rem;
}
.card-actions .first-action { margin-right: 1rem; }
.status-manage-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.status-manage-button {
    padding: 0.3rem 0.9rem;
    font-size: 0.85rem;
}
.btn-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: linear-gradient(120deg, #4f46e5, #ec4899);
    color: #fff;
    padding: 0.45rem 0.95rem;
    border-radius: 999px;
    font-size: 0.9rem;
    text-decoration: none;
    box-shadow: 0 12px 18px rgba(79, 70, 229, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 24px rgba(79, 70, 229, 0.3);
}
.btn-pill.ghost {
    background: transparent;
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.4);
    box-shadow: none;
}
.btn-pill.ghost:hover {
    background: rgba(79, 70, 229, 0.08);
    box-shadow: none;
}
.task-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; }

/* Professional, high-contrast create-task form */
.task-create-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.task-create-form .form-group {
    background: linear-gradient(180deg, #f3f4f6 0%, #ffffff 80%);
    border: 1px solid #cbd5e1;
    border-left: 4px solid #4f46e5;
    border-radius: 14px;
    padding: 0.9rem 1rem;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.task-create-form .form-group:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(15,23,42,0.12); }
.task-create-form label { color:#0f172a; font-weight: 800; margin-bottom: 0.4rem; display:flex; align-items:center; gap:0.4rem; }
.task-create-form input,
.task-create-form select,
.task-create-form textarea {
    color:#0f172a;
    padding: 0.7rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 0.95rem;
    background: #fff;
}
.task-create-form input::placeholder,
.task-create-form textarea::placeholder { color:#111827; opacity:0.7; }
.task-create-form input:focus,
.task-create-form select:focus,
.task-create-form textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25); }
.task-create-form small { color: #0f172a; }

/* Icon labels removed */

/* Actions */
.task-create-form .btn-pill {
    background: linear-gradient(120deg, #1f5eff, #4f46e5);
    color: #fff;
    box-shadow: 0 12px 22px rgba(31, 94, 255, 0.3);
}
.task-create-form .create-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
}

.task-card { display:grid; grid-template-columns: 1fr; border:1px solid #e5e7eb; border-left:4px solid #6366f1; border-radius:12px; background:linear-gradient(180deg,#f9fafb 0%,#ffffff 60%); box-shadow:0 12px 30px rgba(15,23,42,0.06); overflow:hidden; }
.task-card .header { grid-column: 1 / -1; display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem; border-bottom:1px solid #f3f4f6; }
.task-card .header .sub { color:#000; font-size:0.85rem; }
.task-card .header strong { font-size:1.05rem; letter-spacing:.2px; }
.task-card .section { grid-column: 1; padding:0.75rem 1rem; border-top:1px dashed #f3f4f6; }
.task-card .row { display:flex; gap:0.75rem; align-items:center; margin:0.25rem 0; }
.task-card .row .label { width:110px; color:#000; font-size:0.85rem; }
@media (max-width: 768px) {
  .task-card .header { flex-direction: column; align-items: flex-start; gap: .25rem; }
  .task-card .row { flex-direction: column; align-items: flex-start; }
  .task-card .row .label { width: auto; margin-bottom: .25rem; }
  .task-filters { flex-direction: column; align-items: stretch; }
  .task-filters .spacer { display: none; }
  .task-filters input, .task-filters select { width: 100%; }
}

.task-card .tags { display:flex; flex-wrap:wrap; gap:0.4rem; background:#ffffff; }
.task-card .card-actions { grid-column: 1; display:flex; flex-direction:row; flex-wrap:nowrap; gap:0.5rem; align-items:center; justify-content:center; padding:0.75rem 1rem; border-top:1px solid #f3f4f6; }
.table-wrapper, .table { background:transparent; border:none; box-shadow:none; }
.table-wrapper { padding:0; }
.creative-form { background:#ffffff !important; border:1px solid #e5e7eb !important; box-shadow:none !important; }
.creative-header { background:#f8fafc !important; color:#0f172a !important; box-shadow:none !important; }
.task-filters { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; margin:0.75rem 0; }
.task-filters input, .task-filters select { padding:0.5rem 0.75rem; border:1px solid #e5e7eb; border-radius:999px; font-size:0.9rem; }
.task-filters .spacer { flex:1; }
.notice-form, .modify-form { background: linear-gradient(180deg,#f8fafc 0%,#ffffff 80%); border:1px solid #e5e7eb; border-radius:14px; padding:1rem; box-shadow:0 10px 22px rgba(15,23,42,0.08); }
.notice-form .nf-grid, .modify-form .nf-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:0.8rem; align-items:flex-end; }
.notice-form .nf-group label, .modify-form .nf-group label { display:block; font-weight:700; color:#0f172a; margin-bottom:0.35rem; }
.notice-form .nf-group input[type="date"], .modify-form .nf-group input[type="date"], .notice-form .nf-group input[type="month"], .modify-form .nf-group input[type="month"], .notice-form .nf-group input[type="text"], .modify-form .nf-group input[type="text"], .notice-form .nf-group input[type="file"], .modify-form .nf-group input[type="file"], .notice-form .nf-group select, .modify-form .nf-group select { padding:0.55rem 0.75rem; border:1px solid #cbd5e1; border-radius:10px; background:#fff; color:#0f172a; }
.notice-form .nf-group.full, .modify-form .nf-group.full { grid-column:1 / -1; }
.notice-form .nf-actions, .modify-form .nf-actions { grid-column:1 / -1; text-align:right; margin-top:0.25rem; }
.notice-form .checkbox-label, .modify-form .checkbox-label { display:inline-flex; gap:0.5rem; align-items:center; font-weight:600; }
.notice-form .comment-list, .modify-form .comment-list { margin-top:0.6rem; }
</style>

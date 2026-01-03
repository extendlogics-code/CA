<section class="card">
    <div class="flex-between">
        <div>
            <h2>Welcome back, <?= e($user['name']) ?></h2>
            <p>Role: <?= e(role_label($user['role'])) ?>. Monitoring <?= count($tasks) ?> active task<?= count($tasks) === 1 ? '' : 's' ?>.</p>
        </div>
        <div class="header-actions" style="display:flex;gap:.5rem;align-items:center;">
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert success" style="margin:0;"><?= e($flashSuccess) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="layout-two-column" style="margin-top:1rem;">
        <?php foreach ($statusSummary as $label => $count): ?>
            <?php $slug = strtolower(str_replace(' ', '-', $label)); ?>
            <div class="card" style="background:#f8fafc;box-shadow:none;">
                <p style="margin:0;font-size:0.85rem;color:var(--muted);"><?= e($label) ?></p>
                <strong style="font-size:1.8rem;"><?= $count ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($remindersFired)): ?>
    <section class="card alert success">
        <strong><?= $remindersFired ?> reminder<?= $remindersFired === 1 ? '' : 's' ?> sent</strong> to CEO and task owners in the last sync.
    </section>
<?php endif; ?>

<section class="card">
    <div class="flex-between">
        <h2>Upcoming deadlines</h2>
        <form method="GET" action="<?= e(url_for('tasks')) ?>">
            <button type="submit" class="button ghost">Go to Task Board →</button>
        </form>
    </div>
    <?php if (empty($upcoming)): ?>
        <p>All clear for the coming week.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Client</th>
                        <th>Lead</th>
                        <th>Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $task): ?>
                        <?php $client = find_client($task['client_id']); ?>
                        <?php $lead = find_employee($task['lead_id']); ?>
                        <?php $days = task_days_remaining($task); ?>
                        <tr>
                            <td>
                                <a href="<?= e(url_for('tasks')) ?>?view_task=<?= (int) $task['id'] ?>" style="text-decoration:none;">
                                    <?= e($task['title_display'] ?? $task['title']) ?>
                                </a>
                                <?php if (!empty($task['reference'])): ?>
                                    <br><small>Ref <?= e($task['reference']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($client['name'] ?? 'Unknown') ?></td>
                            <td><?= e($lead['name'] ?? 'Unassigned') ?></td>
                            <td><?= e($task['due_date']) ?> (<?= $days ?> days)</td>
                            <?php $slug = strtolower(str_replace(' ', '-', $task['status'])); ?>
                            <td><span class="status-pill <?= e($slug) ?>"><?= e($task['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="flex-between">
        <h2>Alerts</h2>
    </div>
    <?php $alerts = array_filter($tasks, function($t){
        $date = $t['alert']['date'] ?? null; if (!$date) return false; $st = strtolower($t['status'] ?? ''); if ($st === 'completed') return false;
        $dr = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date)); $days = (int) $dr->format('%r%a');
        return $days <= 3; }); ?>
    <?php if (empty($alerts)): ?>
        <p>No alerts configured.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($alerts as $t): ?>
                <?php $client = find_client($t['client_id']); ?>
                <?php $date = $t['alert']['date']; $days = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date))->format('%r%a'); ?>
                <li>
                    <a href="<?= e(url_for('tasks')) ?>?view_task=<?= (int) $t['id'] ?>" class="alert-link" data-task-id="<?= (int) $t['id'] ?>">
                        <strong><?= e($t['title_display'] ?? $t['title']) ?></strong> for <?= e($client['name'] ?? 'Unknown') ?> — <?= $days < 0 ? 'Past alert (' . abs($days) . ' days ago)' : ($days === 0 ? 'Alert today' : ('Alert in ' . $days . ' day' . ($days===1?'':'s'))) ?>.
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div id="alertModal" style="position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:1000;">
  <div style="background:#fff;padding:1rem;border-radius:8px;max-width:680px;width:92vw;max-height:80vh;overflow:auto;">
    <div class="flex-between">
      <h3 id="am_title" style="margin:0;"></h3>
      <button type="button" class="button ghost" id="am_close">Close</button>
    </div>
    <p style="margin:.25rem 0;color:var(--muted);"><span id="am_client"></span> · Lead: <span id="am_lead"></span></p>
    <div class="grid-two" style="gap:.5rem;">
      <div><strong>Status</strong><div id="am_status"></div></div>
      <div><strong>Due</strong><div id="am_due"></div></div>
    </div>
    <div style="margin-top:.5rem;"><strong>Progress</strong><div id="am_progress"></div></div>
    <div style="margin-top:.5rem;"><strong>Comments</strong><ul id="am_comments"></ul></div>
    <div style="margin-top:.5rem;"><strong>Attachments</strong><ul id="am_attachments"></ul></div>
    <div style="margin-top:.5rem;"><form method="GET" action="<?= e(url_for('tasks')) ?>"><button type="submit" class="button">Go to Task Board →</button></form></div>
  </div>
</div>
<script id="alert_data" type="application/json">
<?= json_encode(array_map(function($t){
  $client=find_client($t['client_id']);
  $lead=find_employee($t['lead_id']);
  return [
    'id'=>$t['id'],
    'title'=>$t['title_display'] ?? $t['title'],
    'client'=>$client['name'] ?? 'Unknown',
    'lead'=>$lead['name'] ?? 'Unassigned',
    'due_date'=>$t['due_date'] ?? '',
    'status'=>$t['status'] ?? '',
    'progress'=>(int)($t['progress'] ?? 0),
    'comments'=>$t['comments'] ?? [],
    'attachments'=>$t['attachments'] ?? [],
  ];
}, $alerts), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<script id="reminders_data" type="application/json">
<?= json_encode(array_map(function($t){
  $client=find_client($t['client_id']);
  $lead=find_employee($t['lead_id']);
  return [
    'id'=>$t['id'],
    'title'=>$t['title_display'] ?? $t['title'],
    'client'=>$client['name'] ?? 'Unknown',
    'lead'=>$lead['name'] ?? 'Unassigned',
    'due_date'=>$t['due_date'] ?? '',
    'status'=>$t['status'] ?? '',
    'progress'=>(int)($t['progress'] ?? 0),
    'comments'=>$t['comments'] ?? [],
    'attachments'=>$t['attachments'] ?? [],
  ];
}, $userReminders ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<script>
(function(){
  var dataEl=document.getElementById('alert_data');
  var remEl=document.getElementById('reminders_data');
  var alerts=dataEl?JSON.parse(dataEl.textContent||'[]'):[];
  var reminders=remEl?JSON.parse(remEl.textContent||'[]'):[];
  var seen={};
  var queue=[];
  alerts.concat(reminders).forEach(function(it){ var id=String(it.id); if(!seen[id]){ seen[id]=true; queue.push(it); } });
  if(queue.length===0) return;
  var map={}; for(var i=0;i<queue.length;i++){map[String(queue[i].id)]=queue[i];}
  var modal=document.getElementById('alertModal');
  var closeBtn=document.getElementById('am_close');
  function fill(t){
    document.getElementById('am_title').textContent=t.title||'';
    document.getElementById('am_client').textContent=t.client||'';
    document.getElementById('am_lead').textContent=t.lead||'';
    document.getElementById('am_status').textContent=t.status||'';
    document.getElementById('am_due').textContent=t.due_date||'';
    document.getElementById('am_progress').textContent=(t.progress||0)+'%';
    var c=document.getElementById('am_comments'); c.innerHTML='';
    (t.comments||[]).forEach(function(cm){ var li=document.createElement('li'); li.textContent=(cm.author?cm.author+': ':'')+(cm.body||''); c.appendChild(li); });
    var a=document.getElementById('am_attachments'); a.innerHTML='';
    (t.attachments||[]).forEach(function(att){ var li=document.createElement('li'); li.textContent=(att.name||att.path||''); a.appendChild(li); });
  }
  var idx=0;
  function openItem(item){ fill(item); modal.style.display='flex'; }
  function openNext(){ if(idx>=queue.length) return; openItem(queue[idx]); }
  function close(){ modal.style.display='none'; idx++; openNext(); }
  closeBtn&&closeBtn.addEventListener('click', close);
  modal&&modal.addEventListener('click', function(e){ if(e.target===modal) close(); });
  var links=document.querySelectorAll('.alert-link');
  for(var i=0;i<links.length;i++){
    links[i].addEventListener('click', function(e){ e.preventDefault(); var it=map[String(this.getAttribute('data-task-id'))]; if(it){ idx=queue.indexOf(it); openItem(it);} });
  }
  openNext();
})();

// Lazy rendering for Upcoming and Overdue using enriched client_name and lead_name
try{
  fetch('<?= e(url_for('dashboard.json')) ?>')
    .then(function(r){ return r.json(); })
    .then(function(data){
      var sections=document.querySelectorAll('section.card');
      var upcomingSection=null, overdueSection=null;
      for (var i=0;i<sections.length;i++){
        var h=sections[i].querySelector('h2');
        var txt=h && h.textContent.trim();
        if(txt && txt.indexOf('Upcoming deadlines')===0){ upcomingSection=sections[i]; }
        else if(txt && txt==='Overdue'){ overdueSection=sections[i]; }
      }
      var upcoming=data.upcoming||[];
      if(upcomingSection){
        var tbody=upcomingSection.querySelector('tbody');
        if(tbody){
          tbody.innerHTML=upcoming.map(function(t){
            var client=(t.client_name||t.client||'');
            var lead=(t.lead_name||t.lead||'');
            var statusSlug=(t.status||'').toLowerCase().replace(/\s+/g,'-');
            var ref=t.reference?('<br><small>Ref '+t.reference+'</small>'):'';
            var days=(typeof t.days_remaining==='number')?(' ('+t.days_remaining+' days)'):'';
            return '<tr>'
              +'<td><a href="<?= e(url_for('tasks')) ?>?view_task='+(t.id||'')+'" style="text-decoration:none;">'+(t.title_display||t.title||'')+'</a>'+ref+'</td>'
              +'<td>'+client+'</td>'
              +'<td>'+lead+'</td>'
              +'<td>'+(t.due_date||'')+days+'</td>'
              +'<td><span class="status-pill '+statusSlug+'">'+(t.status||'')+'</span></td>'
              +'</tr>';
          }).join('');
        }
      }
      var overdue=data.overdue||[];
      if(overdueSection){
        var list=overdueSection.querySelector('ul');
        if(list){
          list.innerHTML=overdue.map(function(t){
            var client=(t.client_name||t.client||'');
            var days=Math.abs(parseInt(t.days_remaining!=null?t.days_remaining:0,10));
            var suffix=(days===1)?'':'s';
            return '<li><a href="<?= e(url_for('tasks')) ?>?view_task='+(t.id||'')+'" style="text-decoration:none;"><strong>'+(t.title_display||t.title||'')+'</strong></a> for '+client+' is overdue by '+days+' day'+suffix+'.</li>';
          }).join('');
        }
      }
    });
}catch(e){}
</script>

<section class="card">
    <div class="flex-between">
        <h2>Overdue</h2>
    </div>
    <?php if (empty($overdue)): ?>
        <p>No overdue tasks 🎉</p>
    <?php else: ?>
        <ul>
                <?php foreach ($overdue as $task): ?>
                <?php $client = find_client($task['client_id']); ?>
                <?php $days = abs(task_days_remaining($task)); ?>
                <li>
                    <a href="<?= e(url_for('tasks')) ?>?view_task=<?= (int) $task['id'] ?>" style="text-decoration:none;">
                        <strong><?= e($task['title_display'] ?? $task['title']) ?></strong>
                    </a> for <?= e($client['name'] ?? 'Unknown') ?> is overdue by <?= $days ?> day<?= $days === 1 ? '' : 's' ?>.
                </li>
                <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card">
    <div class="flex-between">
        <h2>Client coverage</h2>
        <form method="GET" action="<?= e(url_for('clients')) ?>">
            <button type="submit" class="button ghost">Update client-service map</button>
        </form>
    </div>
    <div class="grid-two">
        <?php foreach ($clients as $client): ?>
            <?php $address = $client['address'] ?? []; ?>
            <div style="border:1px solid #e5e7eb;padding:1rem;border-radius:10px;">
                <strong><?= e($client['name']) ?></strong>
                <p style="margin:0;color:var(--muted);">
                    <?= e($client['entity_type'] ?? $client['industry'] ?? '') ?>
                    <?php if (!empty($client['entity_subtype'])): ?>
                        · <?= e($client['entity_subtype']) ?>
                    <?php endif; ?>
                </p>
                <p style="margin:0;color:var(--muted);">
                    Primary contact: <?= e($client['primary_contact']) ?><?php if (!empty($client['email'])): ?> (<?= e($client['email']) ?>)<?php endif; ?>
                </p>
                <?php if (!empty($address)): ?>
                    <p style="margin:0.25rem 0;color:var(--muted);">
                        <?= e($address['line1'] ?? '') ?> <?= e($address['line2'] ?? '') ?> <?= e($address['line3'] ?? '') ?><br>
                        <?= e($address['city'] ?? '') ?> <?= e($address['state'] ?? '') ?> <?= e($address['pin'] ?? '') ?>
                    </p>
                <?php endif; ?>
                <div class="tags" style="margin-top:0.5rem;">
                    <?php foreach ($client['services'] as $service): ?>
                        <span class="tag"><?= e($service) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

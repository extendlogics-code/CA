<?php
$clientFormData = $clientFormData ?? [];
$clientFormErrors = $clientFormErrors ?? [];
$clientFormServices = $clientFormServices ?? [];
$isEditMode = !empty($clientFormData['edit_id'] ?? null);
?>

<section class="card">
    <h2><?= $isEditMode ? 'Edit Client' : 'Create Client' ?></h2>
    <p><?= $isEditMode ? 'Update client details and services.' : 'Add a new engagement and optionally tag initial services.' ?></p>
    <div style="margin:0.5rem 0; display:flex; gap:0.5rem; align-items:center;">
        <form method="GET" action="<?= e(url_for('clients')) ?>">
            <input type="hidden" name="bulk_template" value="csv">
            <button type="submit" class="button ghost">Download CSV template</button>
        </form>
    </div>
    <form method="POST" action="<?= e(url_for('clients')) ?>" class="client-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($isEditMode): ?><input type="hidden" name="client_id" value="<?= e($clientFormData['edit_id']) ?>"><?php endif; ?>
        <input type="hidden" name="action" value="<?= e($isEditMode ? 'update_client' : 'create_client') ?>">
        <div class="form-grid">
            <label>
                <span>Client name *</span>
                <input type="text" name="name" id="client_name" value="<?= e($clientFormData['name'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['name'])): ?><small class="error"><?= e($clientFormErrors['name']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Client ID</span>
                <input type="text" name="client_code" id="client_code" value="<?= e($clientFormData['client_code'] ?? '') ?>" maxlength="5" pattern="^[A-Z][0-9]{4}$" title="Format: L0001 (first letter + 4 digits)">
                <?php if (!empty($clientFormErrors['client_code'])): ?><small class="error"><?= e($clientFormErrors['client_code']) ?></small><?php endif; ?>
            </label>
            <label>
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:nowrap;">
                    <span>Entity Group *</span>
                    <?php if (user_has_role(['ceo'])): ?>
                        <a href="<?= e(url_for('entity-groups')) ?>" class="btn-pill ghost small-button" title="Manage groups">Manage</a>
                    <?php endif; ?>
                </div>
                <select name="entity_type" required>
                    <option value="">Select group</option>
                    <?php foreach (($entityGroupCatalog ?? []) as $group): ?>
                        <option value="<?= e($group) ?>" <?= ((string)($clientFormData['entity_type'] ?? '') === (string)$group) ? 'selected' : '' ?>><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($clientFormErrors['entity_type'])): ?><small class="error"><?= e($clientFormErrors['entity_type']) ?></small><?php endif; ?>
            </label>
            <label>
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:nowrap;">
                    <span>Entity subtype</span>
                    <?php if (user_has_role(['ceo'])): ?>
                        <a href="<?= e(url_for('entity-subtypes')) ?>" class="btn-pill ghost small-button" title="Manage subtypes">Manage</a>
                    <?php endif; ?>
                </div>
                <select name="entity_subtype">
                    <option value="">Select subtype</option>
                    <?php foreach (($entitySubtypeCatalog ?? []) as $subtype): ?>
                        <option value="<?= e($subtype) ?>" <?= ((string)($clientFormData['entity_subtype'] ?? '') === (string)$subtype) ? 'selected' : '' ?>><?= e($subtype) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span>POC name *</span>
                </div>
                <input type="text" name="primary_contact" value="<?= e($clientFormData['primary_contact'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['primary_contact'])): ?><small class="error"><?= e($clientFormErrors['primary_contact']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>PCO email *</span>
                <input type="email" name="contact_email" value="<?= e($clientFormData['contact_email'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['contact_email'])): ?><small class="error"><?= e($clientFormErrors['contact_email']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Address line 1 *</span>
                <input type="text" name="address1" value="<?= e($clientFormData['address1'] ?? '') ?>" required>
            </label>
            <label>
                <span>Address line 2 *</span>
                <input type="text" name="address2" value="<?= e($clientFormData['address2'] ?? '') ?>" required>
            </label>
            <label>
                <span>City *</span>
                <input type="text" name="city" value="<?= e($clientFormData['city'] ?? '') ?>" required>
            </label>
            <label>
                <span>State *</span>
                <input type="text" name="state" value="<?= e($clientFormData['state'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['state'])): ?><small class="error"><?= e($clientFormErrors['state']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>PIN *</span>
                <input type="text" name="pin" value="<?= e($clientFormData['pin'] ?? '') ?>" required>
            </label>
            <label>
                <span>CEO name</span>
                <input type="text" name="ceo_name" value="<?= e($clientFormData['ceo_name'] ?? '') ?>">
                <?php if (!empty($clientFormErrors['ceo_name'])): ?><small class="error"><?= e($clientFormErrors['ceo_name']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>CEO email</span>
                <input type="email" name="ceo_email" value="<?= e($clientFormData['ceo_email'] ?? '') ?>">
            </label>
            <label>
                <span>PAN *</span>
                <input type="text" name="pan" value="<?= e($clientFormData['pan'] ?? '') ?>" required pattern="^[A-Z]{5}[0-9]{4}[A-Z]$" maxlength="10" title="Format: AAAAA1111A">
                <?php if (!empty($clientFormErrors['pan'])): ?><small class="error"><?= e($clientFormErrors['pan']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>TAN *</span>
                <input type="text" name="tan" value="<?= e($clientFormData['tan'] ?? '') ?>" required pattern="^[A-Z]{5}[0-9]{4}[A-Z]$" maxlength="10" title="Format: AAAAA1111A">
                <?php if (!empty($clientFormErrors['tan'])): ?><small class="error"><?= e($clientFormErrors['tan']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>GST Regn No *</span>
                <input type="text" name="gst" value="<?= e($clientFormData['gst'] ?? '') ?>" required pattern="^\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z]{2}$" maxlength="15" title="Format: 11AAAAA111A1AA">
                <?php if (!empty($clientFormErrors['gst'])): ?><small class="error"><?= e($clientFormErrors['gst']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Aadhaar *</span>
                <input type="text" name="aadhaar" value="<?= e($clientFormData['aadhaar'] ?? '') ?>" required pattern="^\d{4} \d{4} \d{4}$" inputmode="numeric" title="Format: 1111 1111 1111">
                <?php if (!empty($clientFormErrors['aadhaar'])): ?><small class="error"><?= e($clientFormErrors['aadhaar']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Incorporated on *</span>
                <input type="date" name="incorporated_on" value="<?= e($clientFormData['incorporated_on'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['incorporated_on'])): ?><small class="error"><?= e($clientFormErrors['incorporated_on']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Contact tel number *</span>
                <input type="tel" name="pco_phone" value="<?= e($clientFormData['pco_phone'] ?? '') ?>" required>
                <?php if (!empty($clientFormErrors['pco_phone'])): ?><small class="error"><?= e($clientFormErrors['pco_phone']) ?></small><?php endif; ?>
            </label>
            <label>
                <span>Log</span>
                <input type="text" name="login_id" value="<?= e($clientFormData['login_id'] ?? '') ?>">
            </label>
            <label>
                <span>Credentials</span>
                <input type="text" name="credentials" value="<?= e($clientFormData['credentials'] ?? '') ?>">
            </label>
            <label>
                <span>Invoiced?</span>
                <input type="checkbox" name="invoiced" value="1" <?= !empty($clientFormData['invoiced']) ? 'checked' : '' ?>>
            </label>
            <label class="full-width">
                <span>Initial services *</span>
                <select name="services[]" multiple size="4" required>
                <?php foreach ($serviceCatalog as $service): ?>
                    <option value="<?= e($service) ?>" <?= in_array($service, $clientFormServices, true) ? 'selected' : '' ?>><?= e($service) ?></option>
                <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit">Create client</button>
        </div>
    </form>
    <script>
    (function(){
      var nameEl=document.getElementById('client_name');
      var codeEl=document.getElementById('client_code');
      var cache=null;
      function firstLetter(name){
        var t=(name||'').trim(); var f='';
        for(var i=0;i<t.length;i++){ var ch=t[i]; if(/[A-Za-z0-9]/.test(ch)){ f=ch.toUpperCase(); break; } }
        if(f===''){ f='X'; }
        return f;
      }
      function nextDigits(first,list){
        var max=0;
        (list||[]).forEach(function(c){ var code=(c.code||'')+''; var m=code.match(new RegExp('^'+first+'(\\d{4})$')); if(m){ var n=parseInt(m[1],10)||0; if(n>max) max=n; } });
        var next=(max+1).toString().padStart(4,'0');
        return next;
      }
      function sync(){
        if(!nameEl||!codeEl) return;
        var f=firstLetter(nameEl.value||'');
        var curr=(codeEl.value||'').toUpperCase();
        var digits=curr.slice(1).replace(/[^0-9]/g,'').slice(0,4);
        if(digits.length===0){
          function compute(list){ digits=nextDigits(f,list); codeEl.value=f+digits; }
          if(cache){ compute(cache); } else {
            fetch('<?= e(url_for('clients.json')) ?>').then(function(r){ return r.json(); }).then(function(data){ var list=Array.isArray(data)?data:(data.items||data.clients||[]); cache=list; compute(list); }).catch(function(){ codeEl.value=f+'0001'; });
          }
        } else {
          codeEl.value=f+digits;
        }
      }
      if(nameEl){ nameEl.addEventListener('input', sync); nameEl.addEventListener('blur', sync); }
      if(codeEl){
        codeEl.addEventListener('input', function(){
          var f=firstLetter(nameEl?nameEl.value:'');
          var curr=(codeEl.value||'').toUpperCase();
          var digits=curr.slice(1).replace(/[^0-9]/g,'').slice(0,4);
          codeEl.value=f+digits;
        });
        codeEl.addEventListener('blur', function(){
          var f=firstLetter(nameEl?nameEl.value:'');
          var curr=(codeEl.value||'').toUpperCase();
          var digits=curr.slice(1).replace(/[^0-9]/g,'').slice(0,4);
          if(digits.length>0){ digits=digits.padStart(4,'0'); }
          codeEl.value=f+(digits||'0001');
        });
      }
      sync();
    })();
    </script>
</section>

<section class="card">
    <h2>Bulk upload clients</h2>
    <p>Upload a CSV or XLSX using the template. Services should be pipe-separated (example: GST filing|Accounting).</p>
    <form method="POST" action="<?= e(url_for('clients')) ?>" enctype="multipart/form-data" class="client-form" id="client-bulk-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="bulk_upload_clients">
        <label>
            <span>Upload file *</span>
            <input type="file" name="bulk_file" accept=".csv,.xlsx" required>
        </label>
        <label>
            <span>On duplicates</span>
            <select name="dup_strategy">
                <option value="skip">Retain existing (alert)</option>
                <option value="overwrite">Overwrite existing</option>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit">Upload</button>
        </div>
    </form>
    <div id="dup-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3>Upload Status</h3>
            <p id="dup-msg"></p>
            <table class="table" id="dup-table" style="width:100%;">
                <thead><tr><th>Name</th><th>PAN</th><th>TAN</th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="form-actions" style="display:flex;gap:0.5rem;justify-content:flex-end;">
                <form method="POST" action="<?= e(url_for('clients')) ?>" id="dup-update-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_update_duplicates">
                    <button type="submit" class="button">Proceed</button>
                </form>
                <form method="POST" action="<?= e(url_for('clients')) ?>" id="dup-cancel-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="bulk_clear_duplicates">
                    <button type="submit" class="button ghost">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if (!user_has_role(['lead'])): ?>
<section class="card">
    <div class="flex-between">
        <div>
            <h2>Client & Service Registry</h2>
            <p>Assign one or more services for every client engagement.</p>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <form method="GET" action="<?= e(url_for('tasks')) ?>">
                <button type="submit" class="button ghost">Back to Task Board</button>
            </form>
            <?php if (user_has_role(['ceo'])): ?>
            <form method="POST" action="<?= e(url_for('clients')) ?>" onsubmit="return confirm('Delete ALL clients? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_all_clients">
                <button type="submit" class="button danger">Delete All</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($flashError)): ?>
        <div class="alert error" style="display:none;" id="flash-error-text"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert success" style="display:none;" id="flash-success-text"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.5rem;">
        <input type="text" id="client-search" placeholder="Search name, PAN, TAN, service, due date">
    </div>
    <div class="table-wrapper" style="overflow-x:auto;">
        <table class="table" id="client-table" style="min-width:1600px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>PAN</th>
                    <th>TAN</th>
                    <th>GST</th>
                    <th>Aadhaar</th>
                    <th>Incorporated On</th>
                    <th>Industry</th>
                    <th>Entity Type</th>
                    <th>Entity Subtype</th>
                    <th>Address1</th>
                    <th>Address2</th>
                    <th>City</th>
                    <th>State</th>
                    <th>PIN</th>
                    <th>CEO Name</th>
                    <th>CEO Email</th>
                    <th>POC Name</th>
                    <th>POC Email</th>
                    <th>Contact tel number</th>
                    <th>Login ID</th>
                    <th>Credentials</th>
                    <th>Invoiced?</th>
                    <th>Services</th>
                    <th>Next Due</th>
                    <th>Delete</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    
    <div id="client-grid" class="client-grid" style="display:none;"><p>Loading clients…</p></div>
    <div class="pager" id="client-pager" style="display:none;justify-content:center;gap:0.5rem;margin-top:0.5rem;align-items:center;">
        <button id="client-prev" class="button ghost">Previous</button>
        <span id="client-page">Page 1</span>
        <button id="client-next" class="button">Next</button>
    </div>
    <script>
    (function(){
      var page=1, limit=10, loading=false, done=false;
      var catalog = <?= json_encode(array_values($serviceCatalog ?? [])) ?>;
      var csrf = '<?= e(csrf_token()) ?>';
      var flashErrorTextEl=document.getElementById('flash-error-text');
      var flashSuccessTextEl=document.getElementById('flash-success-text');
      var flashError=(flashErrorTextEl?flashErrorTextEl.textContent.trim():'');
      var flashSuccess=(flashSuccessTextEl?flashSuccessTextEl.textContent.trim():'');
      var grid=document.getElementById('client-grid');
      var pagerEl=document.getElementById('client-pager');
      var prevBtn=document.getElementById('client-prev');
      var nextBtn=document.getElementById('client-next');
      var pageLabel=document.getElementById('client-page');
      var table=document.getElementById('client-table');
      var tbody=table.querySelector('tbody');
      var search=document.getElementById('client-search');
      var dupModal=document.getElementById('dup-modal');
      var dupTable=document.getElementById('dup-table');
      var dupMsg=document.getElementById('dup-msg');
      function esc(s){ return (s||'').replace(/[&<>"']/g,function(m){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];}); }
      function opt(service, selected){ var e=esc(service); return '<option value="'+e+'"'+(selected?' selected':'')+'>'+e+'</option>'; }
      function deleteCell(c){
        var html='';
        <?php if (user_has_role(['ceo'])): ?>
        html='<form method="POST" action="<?= e(url_for('clients')) ?>" class="inline" onsubmit="return confirm(\'Delete this client? This cannot be undone.\');">'
          +'<input type="hidden" name="csrf_token" value="'+csrf+'">'
          +'<input type="hidden" name="action" value="delete_client">'
          +'<input type="hidden" name="client_id" value="'+esc(c.c_id||'')+'">'
          +'<button type="submit" class="button danger">Delete</button></form>';
        <?php endif; ?>
        return html || '';
      }
      function editCell(c){
        var html='';
        <?php if (user_has_role(['ceo'])): ?>
        html='<form method="POST" action="<?= e(url_for('clients')) ?>" class="inline">'
          +'<input type="hidden" name="csrf_token" value="'+csrf+'">'
          +'<input type="hidden" name="action" value="edit_client_start">'
          +'<input type="hidden" name="client_id" value="'+esc(c.c_id||'')+'">'
          +'<button type="submit" class="button ghost">Edit</button></form>';
        <?php endif; ?>
        return html || '';
      }
      function toRow(c){
        var id=esc(c.c_id||'');
        var code=esc(c.code||'');
        var name=esc(c.name||'');
        var pan=esc(c.pan||'');
        var tan=esc(c.tan||'');
        var gst=esc(c.gst||'');
        var aadhaar=esc(c.aadhaar||'');
        var inc=esc(c.incorporated_on||'');
        var industry=esc(c.industry||'');
        var et=esc(c.entity_type||'');
        var est=esc(c.entity_subtype||'');
        var a1=esc((c.address||{}).line1||'');
        var a2=esc((c.address||{}).line2||'');
        var city=esc((c.address||{}).city||'');
        var state=esc((c.address||{}).state||'');
        var pin=esc((c.address||{}).pin||'');
        var ceo=esc(c.ceo_name||'');
        var ceoEmail=esc(c.ceo_email||'');
        var pocName=esc(c.poc_name||'');
        var pocEmail=esc(c.poc_email||'');
        var pocPhone=esc(c.poc_phone||'');
        var loginId=esc(c.login_id||'');
        var credentials=esc(c.credentials||'');
        var invoiced=!!c.invoiced ? 'Yes' : 'No';
        var services=esc((c.services||[]).join(', '));
        var due=esc(c.next_due_date||'');
        var del=deleteCell(c);
        var edit=editCell(c);
        return '<tr>'
          +'<td>'+id+'</td>'
          +'<td>'+code+'</td>'
          +'<td>'+name+'</td>'
          +'<td>'+pan+'</td>'
          +'<td>'+tan+'</td>'
          +'<td>'+gst+'</td>'
          +'<td>'+aadhaar+'</td>'
          +'<td>'+inc+'</td>'
          +'<td>'+industry+'</td>'
          +'<td>'+et+'</td>'
          +'<td>'+est+'</td>'
          +'<td>'+a1+'</td>'
          +'<td>'+a2+'</td>'
          +'<td>'+city+'</td>'
          +'<td>'+state+'</td>'
          +'<td>'+pin+'</td>'
          +'<td>'+ceo+'</td>'
          +'<td>'+ceoEmail+'</td>'
          +'<td>'+pocName+'</td>'
          +'<td>'+pocEmail+'</td>'
          +'<td>'+pocPhone+'</td>'
          +'<td>'+loginId+'</td>'
          +'<td>'+credentials+'</td>'
          +'<td>'+invoiced+'</td>'
          +'<td>'+services+'</td>'
          +'<td>'+due+'</td>'
          +'<td>'+del+'</td>'
          +'<td>'+edit+'</td>'
          +'</tr>';
      }
      var cacheItems=[];
      function renderList(list){
        cacheItems=list;
        var q=(search.value||'').toLowerCase();
        var filtered=list.filter(function(c){
          var s=(c.services||[]).join(' ');
          var due=c.next_due_date||'';
          var hay=(c.name||'')+' '+(c.pan||'')+' '+(c.tan||'')+' '+s+' '+due;
          return hay.toLowerCase().indexOf(q)!==-1;
        });
        tbody.innerHTML = filtered.map(toRow).join('') || '<tr><td colspan="6">No matching clients</td></tr>';
      }
      search && search.addEventListener('input', function(){ renderList(cacheItems); });
      function card(c){
        var tags = (c.services||[]).map(function(s){return '<span class="tag">'+esc(s)+'</span>';}).join('');
        var sel = '<select name="services[]" multiple size="4">'+catalog.map(function(s){return opt(s,(c.services||[]).indexOf(s)!==-1);}).join('')+'</select>';
        var actions = '<?php if (user_has_permission('clients','write')): ?>'
          +'<form method="POST" action="<?= e(url_for('clients')) ?>" class="inline">'
          +'<input type="hidden" name="csrf_token" value="'+csrf+'">'
          +'<input type="hidden" name="action" value="update_services">'
          +'<input type="hidden" name="client_id" value="'+esc(c.c_id||'')+'">'+sel+'<button type="submit">Save</button></form>'
          +'<?php endif; ?>'
          +'<?php if (user_has_role(['ceo'])): ?>'
          +'<form method="POST" action="<?= e(url_for('clients')) ?>" class="inline" onsubmit="return confirm(\'Delete this client? This cannot be undone.\');">'
          +'<input type="hidden" name="csrf_token" value="'+csrf+'">'
          +'<input type="hidden" name="action" value="delete_client">'
          +'<input type="hidden" name="client_id" value="'+esc(c.c_id||'')+'">'
          +'<button type="submit" class="button danger">Delete</button></form>'
          +'<form method="POST" action="<?= e(url_for('clients')) ?>" class="inline">'
          +'<input type="hidden" name="csrf_token" value="'+csrf+'">'
          +'<input type="hidden" name="action" value="edit_client_start">'
          +'<input type="hidden" name="client_id" value="'+esc(c.c_id||'')+'">'
          +'<button type="submit" class="button ghost">Edit</button></form>'
          +'<?php endif; ?>';
        var address=c.address||{};
        var id = esc(c.c_id||c.code||'');
        var name = esc(c.name||'');
        var inc = esc(c.incorporated_on||'');
        var industry = esc(c.industry||'');
        var entity_type = esc(c.entity_type||'');
        var entity_subtype = esc(c.entity_subtype||'');
        var loc = [esc(address.line1||''), esc(address.line2||''), esc(address.line3||'')].filter(Boolean).join(' ');
        var city = esc(address.city||'');
        var state = esc(address.state||'');
        var pin = esc(address.pin||'');
        var pan = esc(c.pan||'');
        var tan = esc(c.tan||'');
        var gst = esc(c.gst||'');
        var aadhaar = esc(c.aadhaar||'');
        var ceo_name = esc(c.ceo_name||'');
        var ceo_email = esc(c.ceo_email||'');
        var primary_contact = esc(c.primary_contact||'');
        var email = esc(c.email||'');
        var poc_phone = esc(c.poc_phone||'');
        var due = esc(c.next_due_date||'');
        return '<div class="client-card">'
          +'<div class="header"><div><strong>'+name+'</strong><div class="sub">ID '+id+'</div></div>'
          +'<div class="meta">'
          +(inc?('<span class="tag small">Incorporated '+inc+'</span>'):'')
          +(due?('<span class="tag small">Next due '+due+'</span>'):'')
          +'</div></div>'
          +'<div class="section">'
          + (industry?('<div class="row"><span class="label">Industry</span><span>'+industry+'</span></div>'):'')
          + '<div class="row"><span class="label">Entity</span><span>'+entity_type+(entity_subtype?(' · '+entity_subtype):'')+'</span></div>'
          + ((loc||city||state||pin)?('<div class="row"><span class="label">Location</span><span>'+loc+' · '+city+' '+state+' '+pin+'</span></div>'):'')
          +'</div>'
          +'<div class="section">'
          + (pan?('<div class="row"><span class="label">PAN</span><span>'+pan+'</span></div>'):'')
          + (tan?('<div class="row"><span class="label">TAN</span><span>'+tan+'</span></div>'):'')
          + (gst?('<div class="row"><span class="label">GST</span><span>'+gst+'</span></div>'):'')
          + (aadhaar?('<div class="row"><span class="label">Aadhaar</span><span>'+aadhaar+'</span></div>'):'')
          +'</div>'
          +'<div class="section">'
          + (ceo_name?('<div class="row"><span class="label">CEO</span><span>'+ceo_name+(ceo_email?(' · '+ceo_email):'')+'</span></div>'):'')
          + '<div class="row"><span class="label">POC</span><span>'+primary_contact+(email?(' · '+email):'')+(poc_phone?(' · '+poc_phone):'')+'</span></div>'
          +'</div>'
          +'<div class="section"><div class="tags">'+tags+'</div></div>'
          +'<div class="client-actions">'+actions+'</div>'
          +'</div>';
      }
      
      function renderPage(p){
        if(loading) return; loading=true; prevBtn && (prevBtn.disabled=true); nextBtn && (nextBtn.disabled=true);
        fetch('<?= e(url_for('clients.json')) ?>?page='+p+'&limit='+limit).then(function(r){return r.json();}).then(function(data){
          var list = Array.isArray(data)?data:(data.items||data.clients||[]);
          var total = (data.total||list.length);
          renderList(list);
          var maxPage = Math.max(1, Math.ceil(total/limit));
          page = Math.min(Math.max(1,p), maxPage);
          if(pageLabel) pageLabel.textContent = 'Page '+page;
          if(total > limit){ if(pagerEl) pagerEl.style.display='flex'; } else { if(pagerEl) pagerEl.style.display='none'; }
          if(prevBtn){ prevBtn.disabled = (page<=1); prevBtn.style.display=''; }
          if(nextBtn){ nextBtn.disabled = (page>=maxPage); nextBtn.style.display=''; }
        }).catch(function(){
          if(pagerEl) pagerEl.style.display='none';
        }).finally(function(){ loading=false; });
      }
      prevBtn && prevBtn.addEventListener('click', function(){ if(page>1) renderPage(page-1); });
      nextBtn && nextBtn.addEventListener('click', function(){ renderPage(page+1); });
      renderPage(1);
      (function(){
        if(flashError){
          dupMsg.textContent = flashError;
          dupTable.style.display='none';
          document.getElementById('dup-update-form').style.display='none';
          document.getElementById('dup-cancel-form').style.display='';
          dupModal.style.display='block';
        }
      })();

      var bulkForm=document.getElementById('client-bulk-form');
      if(bulkForm){
        bulkForm.addEventListener('submit', function(ev){
          ev.preventDefault();
          var fd=new FormData(bulkForm);
          fd.append('ajax','1');
          fetch('<?= e(url_for('clients')) ?>', { method:'POST', body: fd }).then(function(r){ return r.json(); }).then(function(data){
            var msg=(data && typeof data.message==='string') ? data.message.trim() : '';
            if(msg){
              dupMsg.textContent=msg;
              dupTable.style.display='none';
              document.getElementById('dup-update-form').style.display='none';
              document.getElementById('dup-cancel-form').style.display='';
              dupModal.style.display='block';
            }
            renderPage(1);
          }).catch(function(){
            dupMsg.textContent='Upload error';
            dupTable.style.display='none';
            dupModal.style.display='block';
          });
        });
      }
    })();
    </script>
</section>
<?php endif; ?>

<style>
.client-form .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.client-form label {
    display: flex;
    flex-direction: column;
    font-size: 0.9rem;
    color: #374151;
}
.client-form input,
.client-form select {
    margin-top: 0.3rem;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}
.client-form label.full-width {
    grid-column: 1 / -1;
}
.client-form .error {
    color: #b91c1c;
    margin-top: 0.25rem;
}
.client-form .form-actions {
    margin-top: 1rem;
    text-align: right;
}
.client-grid { display:grid; grid-template-columns: 1fr; gap:1rem; }
.client-card { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:linear-gradient(180deg,#f9fafb 0%,#ffffff 60%); }
.client-card .header { display:flex; align-items:flex-start; justify-content:space-between; padding:0.75rem 1rem; border-bottom:1px solid #f3f4f6; }
.client-card .header .sub { color:#6b7280; font-size:0.85rem; }
.client-card .header .meta { display:flex; gap:0.5rem; align-items:center; }
.client-card .section { padding:0.75rem 1rem; border-top:1px dashed #f3f4f6; }
.client-card .row { display:flex; gap:0.75rem; align-items:center; margin:0.25rem 0; }
.client-card .row .label { width:92px; color:#6b7280; font-size:0.85rem; }
.client-actions { display:flex; gap:6rem; align-items:flex-start; padding:0.75rem 1rem; border-top:1px solid #f3f4f6; }
.client-actions .inline { display:flex; gap:0.5rem; align-items:center; }
.client-actions select { min-width:220px; }
.table-wrapper { overflow-x: auto; }
.table th, .table td { white-space: nowrap; }
</style>

<?php
declare(strict_types=1);

// Only allow logged-in users with role_id = 1 (CEO/Admin)
if (!user_has_role(['ceo', 'superadmin'])) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}

$license = get_license_info();
?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">System License Status</h1>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl">
        <?php if ($license): ?>
            <div class="space-y-4">
                <div class="flex items-center p-4 <?php echo $license['is_valid'] ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?> border rounded-md">
                    <div class="flex-shrink-0 mr-3">
                        <?php if ($license['is_valid']): ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?php else: ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg"><?php echo $license['is_valid'] ? 'License Valid' : 'License Invalid'; ?></h3>
                        <p class="text-sm"><?php echo $license['is_valid'] ? 'This system is authorized to run this application.' : 'Hardware mismatch detected.'; ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    <div class="p-4 bg-gray-50 rounded border">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Registered At</label>
                        <div class="mt-1 text-gray-900 font-mono"><?php echo htmlspecialchars($license['registered_at'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="p-4 bg-gray-50 rounded border">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Registered Fingerprint</label>
                        <div class="mt-1 text-gray-900 font-mono text-xs break-all"><?php echo htmlspecialchars($license['license_hash'] ?? 'N/A'); ?></div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded border">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Current Machine Fingerprint</label>
                        <div class="mt-1 text-gray-900 font-mono text-xs break-all"><?php echo htmlspecialchars($license['current_fingerprint'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t">
                    <h4 class="text-sm font-bold text-gray-700 mb-2">Troubleshooting</h4>
                    <p class="text-sm text-gray-600">
                        If the fingerprints do not match, the application will not run. This usually happens if the database was copied to a new machine without resetting the license.
                        To fix this on a new deployment, run <code>php bin/reset_license.php</code> from the command line.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p>No license information found. The system will register on the next page load.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/license';
        $fileAudits = fetch_file_audits(['q' => $currentPath], 200, 0);
        $taskAudits = fetch_task_audits(['q' => $currentPath], 50, 0);
        $globalAudits = fetch_global_audits(['q' => $currentPath], 50, 0);
        $allAudits = array_merge($fileAudits, $taskAudits, $globalAudits);
    ?>
    <div class="bg-white rounded-lg shadow-md p-6 mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Audit Logs for This Page</h2>
            <div class="flex gap-3">
                <a class="text-sm text-blue-600 underline" href="/audits?domain=file&q=<?php echo urlencode($currentPath); ?>">Open full audit viewer</a>
                <a class="text-sm text-blue-600 underline" href="/audits?domain=file&export=csv&q=<?php echo urlencode($currentPath); ?>">Download CSV</a>
            </div>
        </div>
        <?php if (!empty($allAudits)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 border text-left">Created</th>
                            <th class="px-3 py-2 border text-left">Scope</th>
                            <th class="px-3 py-2 border text-left">Entity</th>
                            <th class="px-3 py-2 border text-left">Action</th>
                            <th class="px-3 py-2 border text-left">Actor</th>
                            <th class="px-3 py-2 border text-left">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAudits as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 border font-mono text-xs"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                                <td class="px-3 py-2 border"><?php echo htmlspecialchars($row['domain'] ?? ($row['task_id'] ?? null ? 'task' : 'global')); ?></td>
                                <td class="px-3 py-2 border"><?php echo htmlspecialchars((string) ($row['entity_id'] ?? ($row['task_id'] ?? ''))); ?></td>
                                <td class="px-3 py-2 border"><?php echo htmlspecialchars($row['action'] ?? ''); ?></td>
                                <td class="px-3 py-2 border"><?php echo htmlspecialchars(($row['actor_name'] ?? '') . (($row['actor_role'] ?? '') ? ' (' . $row['actor_role'] . ')' : '')); ?></td>
                                <td class="px-3 py-2 border font-mono text-xs break-all"><?php echo htmlspecialchars($row['details'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-xs text-gray-500">
                File source: <?php echo htmlspecialchars(audit_log_path()); ?>
            </div>
        <?php else: ?>
            <div class="p-4 bg-yellow-50 text-yellow-800 border border-yellow-200 rounded">
                <p class="text-sm">No audit entries found for this page. You can view file logs directly or export from the audit viewer.</p>
                <div class="mt-2 flex gap-4">
                    <span class="text-xs text-gray-700">File path: <code class="font-mono"><?php echo htmlspecialchars(audit_log_path()); ?></code></span>
                    <a class="text-sm text-blue-600 underline" href="/audits?domain=file&q=<?php echo urlencode($currentPath); ?>">Open full audit viewer</a>
                    <a class="text-sm text-blue-600 underline" href="/audits?domain=file&export=csv&q=<?php echo urlencode($currentPath); ?>">Download CSV</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>

<?php
require __DIR__ . '/bootstrap.php';
require_role(['owner', 'admin']);
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Save New Workflow Automation Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_automation') {
    verify_csrf();
    $name = trim($_POST['name'] ?? 'New Workflow Rule');
    $trigger = $_POST['trigger_event'] ?? 'invoice_created';
    $targetWebhookUrl = trim($_POST['webhook_url'] ?? '');
    $emailRecipient = trim($_POST['email_recipient'] ?? '');

    if ($targetWebhookUrl !== '' && !\Core\Security::isPublicUrl($targetWebhookUrl)) {
        flash('error', 'Invalid webhook URL. Webhook targets must resolve to public HTTP/HTTPS endpoints.');
        redirect('automation.php');
    }

    $conditions = [];
    if (!empty($_POST['condition_field']) && !empty($_POST['condition_value'])) {
        $conditions[] = [
            'field' => $_POST['condition_field'],
            'operator' => $_POST['condition_op'] ?? '=',
            'value' => $_POST['condition_value']
        ];
    }

    $actions = [];
    if ($targetWebhookUrl) {
        $actions[] = ['type' => 'webhook', 'url' => $targetWebhookUrl];
    }
    if ($emailRecipient) {
        $actions[] = ['type' => 'send_email', 'email' => $emailRecipient];
    }
    $actions[] = ['type' => 'slack'];

    $st = $pdo->prepare("INSERT INTO automations (tenant_id, name, trigger_event, conditions_json, actions_json, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $st->execute([$tid, $name, $trigger, json_encode($conditions), json_encode($actions)]);

    log_audit($pdo, 'create_automation', 'automations', (int)$pdo->lastInsertId(), "Created workflow automation $name");
    flash('success', "n8n-Style Workflow Automation rule '$name' created and activated successfully!");
    redirect('automation.php');
}

// Toggle Automation Rule Status
if (isset($_GET['toggle_id'])) {
    verify_csrf();
    $autoId = (int)$_GET['toggle_id'];
    $stToggle = $pdo->prepare("UPDATE automations SET is_active = NOT is_active WHERE id = ? AND tenant_id = ?");
    $stToggle->execute([$autoId, $tid]);
    flash('success', 'Workflow automation status updated.');
    redirect('automation.php');
}

$stAuto = $pdo->prepare("SELECT * FROM automations WHERE tenant_id = ? ORDER BY id DESC");
$stAuto->execute([$tid]);
$automations = $stAuto->fetchAll();

$stLogs = $pdo->prepare("SELECT al.*, a.name auto_name FROM automation_logs al LEFT JOIN automations a ON a.id = al.automation_id WHERE al.tenant_id = ? ORDER BY al.id DESC LIMIT 30");
$stLogs->execute([$tid]);
$logs = $stLogs->fetchAll();

page_start('n8n Workflow Automation Engine');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">n8n Workflow Automation Engine</h1>
        <p class="mt-1 text-sm text-slate-500">Configure visual trigger-condition-action automation nodes for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="document.getElementById('new-workflow-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-md transition-all">
        <i class="fa-solid fa-diagram-project mr-2"></i>+ Build New Workflow Node
    </button>
</div>

<!-- Active Workflow Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <?php if (empty($automations)): ?>
        <div class="md:col-span-3 bg-white rounded-2xl p-12 border border-slate-200 shadow-sm text-center">
            <div class="h-16 w-16 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
                <i class="fa-solid fa-diagram-project"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900">No Automation Workflows Configured</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 mb-4">Build n8n-style node workflows to automatically send client receipts, trigger webhooks, post to Slack, or dispatch emails when events occur.</p>
            <button onclick="document.getElementById('new-workflow-modal').classList.remove('hidden')" class="px-4 py-2 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl">Create First Automation Rule</button>
        </div>
    <?php endif; ?>
    <?php foreach ($automations as $a): ?>
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-purple-50 text-purple-700">TRIGGER: <?=strtoupper(e($a['trigger_event']))?></span>
                    <a href="automation.php?toggle_id=<?=$a['id']?>&csrf=<?=e(csrf_token())?>" class="px-2.5 py-0.5 rounded-full text-xs font-bold <?=$a['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'?>">
                        <?=$a['is_active'] ? 'ACTIVE' : 'PAUSED'?>
                    </a>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2"><?=e($a['name'])?></h3>
                
                <!-- Node Visual Representation -->
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-2 mb-4">
                    <div class="flex items-center text-slate-700 font-semibold">
                        <i class="fa-solid fa-bolt text-amber-500 w-5"></i>
                        <span>Node 1: Trigger (<?=e($a['trigger_event'])?>)</span>
                    </div>
                    <div class="flex items-center text-slate-700 font-semibold pl-2 border-l-2 border-slate-300 ml-2">
                        <i class="fa-solid fa-filter text-blue-500 w-5"></i>
                        <span>Node 2: Evaluate Conditions</span>
                    </div>
                    <div class="flex items-center text-slate-700 font-semibold pl-2 border-l-2 border-slate-300 ml-2">
                        <i class="fa-solid fa-paper-plane text-emerald-500 w-5"></i>
                        <span>Node 3: Execute Webhook & Email Actions</span>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
                <span class="text-slate-400">Created <?=e(date('d M Y', strtotime($a['created_at'])))?></span>
                <a href="automation.php?toggle_id=<?=$a['id']?>&csrf=<?=e(csrf_token())?>" class="font-bold text-purple-600 hover:underline">Toggle Rule</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Execution Log Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">n8n Execution Audit Logs (Latest 30 Runs)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Execution Time</th>
                    <th class="px-6 py-3.5">Workflow Name</th>
                    <th class="px-6 py-3.5">Trigger Event</th>
                    <th class="px-6 py-3.5">Status</th>
                    <th class="px-6 py-3.5">Action Output Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No workflow executions logged yet. Execution logs trigger automatically when business events occur.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500"><?=e(date('d M Y H:i:s', strtotime($l['executed_at'])))?></td>
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($l['auto_name'] ?: 'Workflow Rule')?></td>
                        <td class="px-6 py-4 font-mono text-xs text-purple-600"><?=e($l['trigger_event'])?></td>
                        <td class="px-6 py-4">
                            <?php
                            $stClass = $l['status'] === 'success' ? 'bg-emerald-100 text-emerald-800' : ($l['status'] === 'skipped' ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-800');
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold <?=$stClass?>"><?=strtoupper(e($l['status']))?></span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-600"><?=e($l['details'])?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Build New Workflow Node -->
<div id="new-workflow-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-diagram-project text-purple-600 mr-2"></i>n8n Visual Workflow Builder
            </h3>
            <button onclick="document.getElementById('new-workflow-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="save_automation">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Workflow Rule Name *</label>
                <input type="text" name="name" placeholder="e.g. Auto Webhook on Paid Invoices" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
            </div>

            <!-- Node 1: Trigger -->
            <div class="bg-purple-50/60 p-4 rounded-xl border border-purple-100 space-y-2">
                <label class="block text-xs font-bold text-purple-900 uppercase flex items-center">
                    <i class="fa-solid fa-bolt text-amber-500 mr-1.5"></i> Node 1: Select Event Trigger
                </label>
                <select name="trigger_event" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-900">
                    <option value="invoice_created">On Invoice Created (New Billing Document)</option>
                    <option value="invoice_paid">On Invoice Paid (Payment Cleared)</option>
                    <option value="invoice_overdue">On Invoice Overdue (Collection Required)</option>
                    <option value="quote_created">On Proposal Created (Client Estimate)</option>
                    <option value="expense_created">On Expense Recorded (Vendor Bill)</option>
                </select>
            </div>

            <!-- Node 2: Condition -->
            <div class="bg-blue-50/60 p-4 rounded-xl border border-blue-100 space-y-2">
                <label class="block text-xs font-bold text-blue-900 uppercase flex items-center">
                    <i class="fa-solid fa-filter text-blue-500 mr-1.5"></i> Node 2: Optional Condition Filter
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <select name="condition_field" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                        <option value="">No Condition</option>
                        <option value="total">Total Amount</option>
                        <option value="currency">Currency</option>
                        <option value="status">Status</option>
                    </select>
                    <select name="condition_op" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                        <option value="=">=</option>
                        <option value=">">></option>
                        <option value="<"><</option>
                    </select>
                    <input type="text" name="condition_value" placeholder="e.g. 1000" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                </div>
            </div>

            <!-- Node 3: Action -->
            <div class="bg-emerald-50/60 p-4 rounded-xl border border-emerald-100 space-y-3">
                <label class="block text-xs font-bold text-emerald-900 uppercase flex items-center">
                    <i class="fa-solid fa-paper-plane text-emerald-500 mr-1.5"></i> Node 3: Execute Action
                </label>
                <div>
                    <span class="text-2xs font-bold text-slate-600 block mb-1">Target n8n / Zapier / Custom Webhook URL:</span>
                    <input type="url" name="webhook_url" placeholder="https://n8n.yourdomain.com/webhook/invoice-event" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-mono">
                </div>
                <div>
                    <span class="text-2xs font-bold text-slate-600 block mb-1">Notification Email Recipient:</span>
                    <input type="email" name="email_recipient" placeholder="accounts@company.com" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold">
                </div>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-workflow-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-md">Activate Workflow Node</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>

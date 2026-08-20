<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Run Migrations to ensure recurring_invoices table exists
require_once __DIR__ . '/migrate.php';
run_migrations($pdo);

$message = '';
$error = '';

// Handle Manual Cron Execution Request
if (isset($_GET['run_cron'])) {
    require __DIR__ . '/cron_recurring.php';
    flash('success', 'Auto-Subscription Cron Worker executed! Generated due invoices & dispatched notifications.');
    redirect('recurring_invoices.php');
}

// Handle Status Toggle (Pause / Resume / Cancel)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    verify_csrf();
    $id = (int)$_GET['id'];
    $newStatus = $_GET['toggle_status'];
    if (in_array($newStatus, ['active', 'paused', 'cancelled'])) {
        $st = $pdo->prepare("UPDATE recurring_invoices SET status = ? WHERE id = ? AND tenant_id = ?");
        $st->execute([$newStatus, $id, $tid]);
        log_audit($pdo, 'toggle_recurring_status', 'recurring_invoices', $id, "Updated recurring invoice schedule #$id status to $newStatus");
        flash('success', "Subscription schedule status updated to " . strtoupper($newStatus));
    }
    redirect('recurring_invoices.php');
}

// Handle New Recurring Subscription Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_schedule') {
    verify_csrf();

    $clientId    = (int)($_POST['client_id'] ?? 0);
    $frequency   = $_POST['frequency'] ?? 'monthly';
    $nextDate    = $_POST['next_issue_date'] ?? date('Y-m-d');
    $itemDesc    = trim($_POST['item_description'] ?? 'Subscription Services');
    $unitPrice   = (float)($_POST['unit_price'] ?? 0);
    $vatPercent  = (float)($_POST['vat_percent'] ?? 5);
    $currency    = $_POST['currency'] ?? 'AED';
    $notes       = trim($_POST['notes'] ?? 'Auto-generated recurring subscription invoice');

    if ($clientId <= 0 || $unitPrice <= 0) {
        $error = 'Please select a valid client and enter a positive subscription billing amount.';
    } else {
        $taxAmount = round($unitPrice * ($vatPercent / 100), 2);
        $total = $unitPrice + $taxAmount;

        $templateData = [
            'currency' => $currency,
            'subtotal' => $unitPrice,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'notes' => $notes,
            'items' => [
                [
                    'description' => $itemDesc,
                    'qty' => 1,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice
                ]
            ]
        ];

        $st = $pdo->prepare("
            INSERT INTO recurring_invoices (tenant_id, client_id, frequency, next_issue_date, status, template_json, created_at)
            VALUES (?, ?, ?, ?, 'active', ?, NOW())
        ");
        $st->execute([$tid, $clientId, $frequency, $nextDate, json_encode($templateData)]);
        $schedId = (int)$pdo->lastInsertId();

        log_audit($pdo, 'create_recurring_schedule', 'recurring_invoices', $schedId, "Created $frequency recurring subscription schedule for client #$clientId");
        flash('success', 'New Auto-Subscription Billing schedule created successfully!');
        redirect('recurring_invoices.php');
    }
}

// Fetch All Subscription Schedules
$stSchedules = $pdo->prepare("
    SELECT r.*, c.company_name, c.email as client_email 
    FROM recurring_invoices r 
    JOIN clients c ON c.id = r.client_id 
    WHERE r.tenant_id = ? 
    ORDER BY r.id DESC
");
$stSchedules->execute([$tid]);
$schedules = $stSchedules->fetchAll();

// Fetch Client list for dropdown
$stClients = $pdo->prepare("SELECT id, company_name FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stClients->execute([$tid]);
$clients = $stClients->fetchAll();

require __DIR__ . '/layout.php';
page_start('Auto-Subscription Billing Manager');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-black text-xs uppercase tracking-wider">Automated Billing</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Auto-Subscription Billing Engine</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Automate recurring tax invoices, double-entry accounting journals, and client PDF emails for <strong><?=e($activeTenant['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex space-x-3">
        <a href="recurring_invoices?run_cron=1" onclick="return confirm('Trigger background subscription cron worker now?')" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
            <i class="fa-solid fa-play text-emerald-400 mr-1.5"></i>Run Cron Worker Now
        </a>
        <button onclick="document.getElementById('create-schedule-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
            <i class="fa-solid fa-plus mr-1.5 text-amber-300"></i>+ Create Subscription Schedule
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-6 bg-rose-50 border border-rose-300 text-rose-900 rounded-2xl p-4 text-xs font-bold flex items-center space-x-2">
        <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
        <span><?=e($error)?></span>
    </div>
<?php endif; ?>

<!-- Cron Worker Background Command Box -->
<div class="bg-slate-900 text-white rounded-2xl p-5 border border-slate-800 shadow-xl mb-8 space-y-3 font-mono text-xs">
    <div class="flex items-center justify-between font-sans">
        <div class="flex items-center space-x-2 text-amber-400 font-extrabold">
            <i class="fa-solid fa-robot text-base"></i>
            <span>Automated Server Cron Job Command</span>
        </div>
        <span class="text-3xs font-black uppercase px-2 py-0.5 bg-emerald-500/20 text-emerald-300 rounded-full border border-emerald-500/30">Background Ready</span>
    </div>
    
    <p class="text-slate-300 font-sans text-2xs">Set this command in your cPanel / Linux crontab to run daily at midnight:</p>
    
    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex items-center justify-between text-2xs text-amber-300">
        <code>0 0 * * * php <?=@realpath(__DIR__ . '/cron_recurring.php')?></code>
        <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); alert('Cron Command Copied!')" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-3xs font-sans font-bold">Copy Command</button>
    </div>
</div>

<!-- Active Schedules Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Active Subscription Billing Schedules (<?=count($schedules)?>)</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-5 py-3">Client Company</th>
                    <th class="px-5 py-3">Frequency</th>
                    <th class="px-5 py-3">Next Issue Date</th>
                    <th class="px-5 py-3 text-right">Billing Value</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-rotate text-4xl mb-3 text-slate-300 block"></i>
                            <span class="font-bold text-slate-700 block mb-1">No active subscription schedules configured.</span>
                            <button onclick="document.getElementById('create-schedule-modal').classList.remove('hidden')" class="text-xs font-bold text-purple-600 hover:underline">Click here to add your first recurring client subscription →</button>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($schedules as $s): ?>
                    <?php
                    $tData = json_decode($s['template_json'], true) ?: [];
                    $val = (float)($tData['total'] ?? 0);
                    $curr = $tData['currency'] ?? 'AED';
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-5 py-4 font-bold text-slate-900">
                            <?=e($s['company_name'])?>
                            <div class="text-2xs text-slate-400 font-normal"><?=e($s['client_email'] ?: '')?></div>
                        </td>
                        <td class="px-5 py-4 font-bold uppercase text-xs text-purple-700">
                            <span class="px-2.5 py-0.5 rounded-full bg-purple-50 border border-purple-200"><?=e($s['frequency'])?></span>
                        </td>
                        <td class="px-5 py-4 text-xs font-mono font-bold text-slate-700">
                            <?=e(date('d M Y', strtotime($s['next_issue_date'])))?>
                        </td>
                        <td class="px-5 py-4 text-right font-extrabold text-slate-900 font-mono">
                            <?=money($val, $curr)?>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <?php
                            $stMap = [
                                'active' => 'bg-emerald-100 text-emerald-800',
                                'paused' => 'bg-amber-100 text-amber-900',
                                'cancelled' => 'bg-rose-100 text-rose-800'
                            ];
                            $cClass = $stMap[$s['status']] ?? 'bg-slate-100 text-slate-800';
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-2xs font-black uppercase <?=$cClass?>"><?=e($s['status'])?></span>
                        </td>
                        <td class="px-5 py-4 text-right space-x-1.5">
                            <?php if ($s['status'] === 'active'): ?>
                                <a href="recurring_invoices?toggle_status=paused&id=<?=$s['id']?>&csrf=<?=e(csrf_token())?>" class="px-2 py-1 text-2xs font-bold bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-md border border-amber-200">Pause</a>
                            <?php else: ?>
                                <a href="recurring_invoices?toggle_status=active&id=<?=$s['id']?>&csrf=<?=e(csrf_token())?>" class="px-2 py-1 text-2xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-md border border-emerald-200">Activate</a>
                            <?php endif; ?>
                            <a href="recurring_invoices?toggle_status=cancelled&id=<?=$s['id']?>&csrf=<?=e(csrf_token())?>" onclick="return confirm('Cancel this subscription schedule?')" class="px-2 py-1 text-2xs font-bold bg-slate-100 text-rose-600 hover:bg-rose-50 rounded-md border border-slate-200">Cancel</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create New Subscription Schedule -->
<div id="create-schedule-modal" class="hidden fixed inset-0 z-[100] overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h3 class="text-lg font-black text-slate-900 flex items-center">
                <i class="fa-solid fa-rotate text-purple-600 mr-2"></i>New Subscription Schedule
            </h3>
            <button type="button" onclick="document.getElementById('create-schedule-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="create_schedule">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Client Account *</label>
                <select name="client_id" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-900">
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?=$c['id']?>"><?=e($c['company_name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Billing Cycle *</label>
                    <select name="frequency" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-900">
                        <option value="weekly">Weekly</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start / Next Date *</label>
                    <input type="date" name="next_issue_date" value="<?=date('Y-m-d')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subscription Service Description *</label>
                <input type="text" name="item_description" value="Monthly Software Maintenance & Subscription" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-900">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Price *</label>
                    <input type="number" step="0.01" name="unit_price" placeholder="1000.00" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">VAT %</label>
                    <input type="number" step="0.01" name="vat_percent" value="5" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Currency</label>
                    <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900">
                        <option value="AED">AED</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('create-schedule-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-extrabold rounded-xl shadow-md">Create Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>

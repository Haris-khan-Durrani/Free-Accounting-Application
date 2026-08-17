<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $companyName = trim($_POST['company_name'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $taxNumber = trim($_POST['tax_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? 'United Arab Emirates');
    $currency = $_POST['currency'] ?? 'AED';

    if (!$companyName) {
        flash('error', 'Company name is required.');
        redirect('clients');
    }

    if ($id > 0) {
        $st = $pdo->prepare('UPDATE clients SET company_name=?, contact_name=?, email=?, phone=?, tax_number=?, address=?, country=?, currency=? WHERE id=? AND tenant_id=?');
        $st->execute([$companyName, $contactName, $email, $phone, $taxNumber, $address, $country, $currency, $id, $tid]);
        log_audit($pdo, 'update_client', 'clients', $id, "Updated client $companyName");
        flash('success', 'Client profile updated.');
    } else {
        $st = $pdo->prepare('INSERT INTO clients (tenant_id, company_name, contact_name, email, phone, tax_number, address, country, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$tid, $companyName, $contactName, $email, $phone, $taxNumber, $address, $country, $currency]);
        log_audit($pdo, 'create_client', 'clients', (int)$pdo->lastInsertId(), "Created client $companyName");
        flash('success', 'New client profile added.');
    }
    redirect('clients');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND tenant_id = ?');
    $st->execute([(int)$_GET['edit'], $tid]);
    $edit = $st->fetch();
}

// Search & Pagination Logic
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClause = "WHERE c.tenant_id = ?";
$queryParams = [$tid];

if (!empty($search)) {
    $whereClause .= " AND (c.company_name LIKE ? OR c.contact_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.tax_number LIKE ?)";
    $sTerm = "%$search%";
    $queryParams = array_merge($queryParams, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
}

// Count total matching records
$stCount = $pdo->prepare("SELECT COUNT(*) FROM clients c $whereClause");
$stCount->execute($queryParams);
$totalRecords = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Fetch paginated records
$stClients = $pdo->prepare("
    SELECT c.*, COUNT(i.id) invoice_count 
    FROM clients c 
    LEFT JOIN invoices i ON i.client_id = c.id 
    $whereClause 
    GROUP BY c.id 
    ORDER BY c.company_name ASC 
    LIMIT $perPage OFFSET $offset
");
$stClients->execute($queryParams);
$clients = $stClients->fetchAll();

page_start('Client Directory');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Client Directory</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Manage client accounts, TRN tax numbers, and billing currencies for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex items-center space-x-3">
        <a href="client_import" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-1.5">
            <i class="fa-solid fa-file-import text-amber-300"></i>
            <span>Import Zoho / QB / CSV</span>
        </a>
        <a href="client_export" class="inline-flex items-center px-3.5 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-xs transition-all space-x-1">
            <i class="fa-solid fa-file-csv text-emerald-600"></i>
            <span>Export CSV</span>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Client Form -->
    <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-base sm:text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center">
            <i class="fa-solid fa-user-plus text-emerald-500 mr-2"></i><?=$edit ? 'Edit Client Profile' : 'Add New Client'?>
        </h2>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=e((string)($edit['id'] ?? ''))?>">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Company Name *</label>
                <input type="text" name="company_name" value="<?=e($edit['company_name'] ?? '')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Primary Contact</label>
                <input type="text" name="contact_name" value="<?=e($edit['contact_name'] ?? '')?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Email Address</label>
                <input type="email" name="email" value="<?=e($edit['email'] ?? '')?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Phone Number</label>
                <input type="text" name="phone" value="<?=e($edit['phone'] ?? '')?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tax / TRN Registration No</label>
                <input type="text" name="tax_number" value="<?=e($edit['tax_number'] ?? '')?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Billing Currency</label>
                <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                        <option value="<?=$curr?>" <?=($edit['currency'] ?? 'AED')===$curr?'selected':''?>><?=$curr?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Country</label>
                <input type="text" name="country" value="<?=e($edit['country'] ?? 'United Arab Emirates')?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Full Address</label>
                <textarea name="address" rows="2" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"><?=e($edit['address'] ?? '')?></textarea>
            </div>
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-bold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
                <i class="fa-solid fa-floppy-disk mr-2"></i><?=$edit ? 'Update Client' : 'Save Client Profile'?>
            </button>
        </form>
    </div>

    <!-- Client Directory List (Desktop Table + Mobile Touch Cards) -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden h-fit mb-12">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="text-base font-bold text-slate-900">
                Active Client Accounts <span class="text-xs font-semibold text-slate-400">(<?=$totalRecords?> total)</span>
            </h2>

            <!-- Real-time Search Form -->
            <form method="get" class="flex items-center space-x-2">
                <div class="relative flex-grow">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?=e($search)?>" placeholder="Search company, contact, TRN, email..." class="pl-8 pr-4 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 w-48 sm:w-64">
                </div>
                <?php if ($search): ?>
                    <a href="clients" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all">Clear</a>
                <?php endif; ?>
                <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-xs">Search</button>
            </form>
        </div>

        <!-- Desktop Table View (Hidden on Mobile) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <th class="px-5 py-3">Company</th>
                        <th class="px-5 py-3">Contact</th>
                        <th class="px-5 py-3">Tax ID</th>
                        <th class="px-5 py-3">Currency</th>
                        <th class="px-5 py-3 text-right">Invoices</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No clients registered yet. Use the form to add a client.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($clients as $c): ?>
                        <tr class="hover:bg-slate-50/80 transition-all">
                            <td class="px-5 py-4 font-bold text-slate-900"><?=e($c['company_name'])?></td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                <div><?=e($c['contact_name'] ?: '--')?></div>
                                <div class="text-slate-400"><?=e($c['email'] ?: '')?></div>
                            </td>
                            <td class="px-5 py-4 text-xs font-mono text-slate-500"><?=e($c['tax_number'] ?: 'N/A')?></td>
                            <td class="px-5 py-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700"><?=e($c['currency'] ?: 'AED')?></span>
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-slate-700"><?=e((string)$c['invoice_count'])?></td>
                            <td class="px-5 py-4 text-right space-x-1.5">
                                <a href="client_statement?client_id=<?=$c['id']?>" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200">Statement</a>
                                <a href="clients?edit=<?=$c['id']?>" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Cards View (Visible only on Mobile) -->
        <div class="sm:hidden divide-y divide-slate-100">
            <?php if (empty($clients)): ?>
                <div class="p-6 text-center text-slate-400">
                    <i class="fa-solid fa-users text-3xl mb-2 text-slate-300 block"></i>
                    <span class="font-bold text-slate-700 block text-xs">No clients registered yet.</span>
                </div>
            <?php endif; ?>
            <?php foreach ($clients as $c): ?>
                <div class="p-4 hover:bg-slate-50 transition-colors">
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="font-black text-slate-900 text-sm"><?=e($c['company_name'])?></div>
                        <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-blue-50 text-blue-700"><?=e($c['currency'] ?: 'AED')?></span>
                    </div>
                    <div class="flex justify-between items-end text-xs">
                        <div>
                            <div class="text-slate-600 font-semibold"><?=e($c['contact_name'] ?: '--')?></div>
                            <div class="text-2xs text-slate-400"><?=e($c['email'] ?: '')?></div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xs font-mono text-slate-400">TRN: <?=e($c['tax_number'] ?: 'N/A')?></div>
                            <a href="clients?edit=<?=$c['id']?>" class="mt-1 inline-block px-3 py-1 bg-slate-100 text-slate-700 text-2xs font-extrabold rounded-lg">Edit Profile</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
                <div>
                    Showing <strong><?=min($offset + 1, $totalRecords)?></strong> to <strong><?=min($offset + $perPage, $totalRecords)?></strong> of <strong><?=$totalRecords?></strong> clients
                </div>
                
                <div class="flex items-center space-x-1.5">
                    <?php if ($page > 1): ?>
                        <a href="clients?page=<?=$page - 1?><?=!empty($search) ? '&search=' . urlencode($search) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                            <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="clients?page=<?=$p?><?=!empty($search) ? '&search=' . urlencode($search) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $page ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' ?>">
                            <?=$p?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="clients?page=<?=$page + 1?><?=!empty($search) ? '&search=' . urlencode($search) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                            Next<i class="fa-solid fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php page_end(); ?>

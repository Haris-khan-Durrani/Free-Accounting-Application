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
        flash('success', "Client profile '$companyName' updated successfully.");
    } else {
        $st = $pdo->prepare('INSERT INTO clients (tenant_id, company_name, contact_name, email, phone, tax_number, address, country, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$tid, $companyName, $contactName, $email, $phone, $taxNumber, $address, $country, $currency]);
        log_audit($pdo, 'create_client', 'clients', (int)$pdo->lastInsertId(), "Created client $companyName");
        flash('success', "New client '$companyName' created successfully.");
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

<!-- Executive Header & Global Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-address-book"></i>
            <span>Account Management</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Client Directory & Accounts</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-2xl">
            Manage corporate billing accounts, TRN tax registration IDs, and regional currency profiles for <strong><?=e(tenant()['name'])?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <a href="client_import" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-file-import text-amber-400 text-sm"></i>
            <span>Import Zoho / QB / CSV</span>
        </a>
        <a href="client_export" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8 items-start">
    <!-- Client Form (Left Column on XL, Full Width on Mobile/Tablet) -->
    <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-sm font-bold">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h2 class="text-base font-bold text-slate-900">
                    <?=$edit ? 'Edit Client Profile' : 'Add New Client'?>
                </h2>
            </div>
            <?php if ($edit): ?>
                <a href="clients" class="text-xs font-bold text-rose-600 hover:underline">Cancel Edit</a>
            <?php endif; ?>
        </div>

        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="<?=e((string)($edit['id'] ?? ''))?>">
            
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Company Name / Account Title <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="company_name" value="<?=e($edit['company_name'] ?? '')?>" required placeholder="e.g. 360 Business Consultants" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Primary Contact</label>
                    <input type="text" name="contact_name" value="<?=e($edit['contact_name'] ?? '')?>" placeholder="John Doe" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input type="email" name="email" value="<?=e($edit['email'] ?? '')?>" placeholder="billing@client.com" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="text" name="phone" value="<?=e($edit['phone'] ?? '')?>" placeholder="+971 50 123 4567" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">TRN / Tax Reg No</label>
                    <input type="text" name="tax_number" value="<?=e($edit['tax_number'] ?? '')?>" placeholder="100293847500003" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Billing Currency</label>
                    <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                        <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                            <option value="<?=$curr?>" <?=($edit['currency'] ?? 'AED')===$curr?'selected':''?>><?=$curr?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Country</label>
                    <input type="text" name="country" value="<?=e($edit['country'] ?? 'United Arab Emirates')?>" placeholder="United Arab Emirates" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Billing Address</label>
                <textarea name="address" rows="2" placeholder="Suite 101, Business Bay, Dubai..." class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"><?=e($edit['address'] ?? '')?></textarea>
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-5 py-3 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
                <i class="fa-solid fa-floppy-disk mr-2"></i><?=$edit ? 'Update Client Account' : 'Save Client Profile'?>
            </button>
        </form>
    </div>

    <!-- Client Directory List (Right Column on XL - 2 Spans) -->
    <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12 w-full">
        <!-- Table Topbar -->
        <div class="p-5 bg-slate-50/60 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-2.5">
                <h2 class="text-base font-bold text-slate-900">
                    Active Client Accounts
                </h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-500/10 text-amber-700 border border-amber-500/20">
                    <?=$totalRecords?> Registered
                </span>
            </div>

            <!-- Search Form -->
            <form method="get" class="flex items-center space-x-2 w-full sm:w-auto">
                <div class="relative flex-grow">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?=e($search)?>" placeholder="Search company, contact, TRN..." class="pl-9 pr-4 py-2 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 w-full sm:w-64 transition-all">
                </div>
                <?php if ($search): ?>
                    <a href="clients" class="px-3 py-2 bg-slate-200/80 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-extrabold transition-all">Clear</a>
                <?php endif; ?>
                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-xs transition-all shrink-0">Search</button>
            </form>
        </div>

        <!-- Desktop & Tablet Responsive Table View -->
        <div class="hidden md:block overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                        <th class="px-5 py-3.5 whitespace-nowrap min-w-[200px]">Company Account</th>
                        <th class="px-5 py-3.5 whitespace-nowrap min-w-[160px]">Contact Details</th>
                        <th class="px-5 py-3.5 whitespace-nowrap min-w-[130px]">TRN / Tax ID</th>
                        <th class="px-5 py-3.5 whitespace-nowrap min-w-[90px]">Currency</th>
                        <th class="px-5 py-3.5 whitespace-nowrap text-center min-w-[90px]">Invoices</th>
                        <th class="px-5 py-3.5 whitespace-nowrap text-right min-w-[170px]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-users-slash text-3xl mb-2 text-slate-300 block"></i>
                                <span class="font-bold text-slate-600">No client accounts found matching your search.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($clients as $c): ?>
                        <?php 
                            $nameParts = explode(' ', trim($c['company_name']));
                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1)));
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-all">
                            <!-- Company -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 text-amber-400 flex items-center justify-center font-black text-xs shrink-0 shadow-inner border border-slate-700/50">
                                        <?=$initials?>
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-900 tracking-tight leading-snug"><?=e($c['company_name'])?></div>
                                        <div class="text-2xs text-slate-400 font-medium flex items-center mt-0.5 whitespace-nowrap">
                                            <i class="fa-solid fa-location-dot text-slate-300 mr-1"></i><?=e($c['country'] ?: 'UAE')?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact -->
                            <td class="px-5 py-4 text-xs whitespace-nowrap">
                                <?php if ($c['contact_name']): ?>
                                    <div class="font-bold text-slate-800"><?=e($c['contact_name'])?></div>
                                <?php endif; ?>
                                <?php if ($c['email']): ?>
                                    <a href="mailto:<?=e($c['email'])?>" class="text-amber-600 hover:underline font-semibold block text-2xs"><?=$c['email']?></a>
                                <?php endif; ?>
                                <?php if (!$c['contact_name'] && !$c['email']): ?>
                                    <span class="text-slate-400 italic">No contact recorded</span>
                                <?php endif; ?>
                            </td>

                            <!-- Tax / TRN ID -->
                            <td class="px-5 py-4 text-xs whitespace-nowrap">
                                <?php if ($c['tax_number']): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 font-mono font-bold border border-slate-200/80 text-2xs">
                                        <?=e($c['tax_number'])?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 font-medium text-2xs">--</span>
                                <?php endif; ?>
                            </td>

                            <!-- Currency -->
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-2xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200/80">
                                    <?=e($c['currency'] ?: 'AED')?>
                                </span>
                            </td>

                            <!-- Invoices Count -->
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <a href="index.php?client_id=<?=$c['id']?>" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-slate-100 hover:bg-amber-500/10 text-slate-700 hover:text-amber-700 border border-slate-200/80 hover:border-amber-500/30 transition-all">
                                    <i class="fa-solid fa-file-invoice text-amber-500 mr-1 text-3xs"></i>
                                    <span><?=e((string)$c['invoice_count'])?></span>
                                </a>
                            </td>

                            <!-- Action Buttons -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="client_statement?client_id=<?=$c['id']?>" class="px-3 py-1.5 text-xs font-extrabold rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 border border-amber-500/30 transition-all flex items-center space-x-1 shadow-3xs">
                                        <i class="fa-solid fa-file-contract text-2xs"></i>
                                        <span>Statement</span>
                                    </a>
                                    <a href="clients?edit=<?=$c['id']?>" class="px-3 py-1.5 text-xs font-extrabold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-all flex items-center space-x-1">
                                        <i class="fa-solid fa-pen-to-square text-2xs text-slate-500"></i>
                                        <span>Edit</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Touch Cards View (visible on < 768px screens) -->
        <div class="md:hidden divide-y divide-slate-100">
            <?php if (empty($clients)): ?>
                <div class="p-6 text-center text-slate-400">
                    <i class="fa-solid fa-users text-3xl mb-2 text-slate-300 block"></i>
                    <span class="font-bold text-slate-700 block text-xs">No clients registered yet.</span>
                </div>
            <?php endif; ?>
            <?php foreach ($clients as $c): ?>
                <?php 
                    $nameParts = explode(' ', trim($c['company_name']));
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : substr($nameParts[0], 1, 1)));
                ?>
                <div class="p-4 hover:bg-slate-50 transition-colors space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 text-amber-400 flex items-center justify-center font-black text-xs shrink-0 shadow-inner border border-slate-700/50">
                                <?=$initials?>
                            </div>
                            <div>
                                <div class="font-extrabold text-slate-900 text-sm leading-tight"><?=e($c['company_name'])?></div>
                                <div class="text-2xs text-slate-400 font-medium"><i class="fa-solid fa-location-dot mr-1"></i><?=e($c['country'] ?: 'UAE')?></div>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200"><?=e($c['currency'] ?: 'AED')?></span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100">
                        <div>
                            <span class="text-3xs uppercase font-extrabold text-slate-400 block mb-0.5">Contact</span>
                            <div class="text-slate-700 font-semibold truncate"><?=e($c['contact_name'] ?: 'No contact')?></div>
                            <?php if ($c['email']): ?>
                                <div class="text-2xs text-amber-600 truncate"><?=e($c['email'])?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-3xs uppercase font-extrabold text-slate-400 block mb-0.5">TRN / Tax ID</span>
                            <div class="text-2xs font-mono font-bold text-slate-800"><?=e($c['tax_number'] ?: 'N/A')?></div>
                            <div class="text-2xs text-slate-500 mt-0.5"><i class="fa-solid fa-file-invoice text-amber-500 mr-1"></i><strong><?=e((string)$c['invoice_count'])?></strong> Invoices</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                        <a href="client_statement?client_id=<?=$c['id']?>" class="flex-1 py-1.5 text-center text-xs font-extrabold rounded-xl bg-amber-50 text-amber-700 border border-amber-200">
                            Statement
                        </a>
                        <a href="clients?edit=<?=$c['id']?>" class="flex-1 py-1.5 text-center text-xs font-extrabold rounded-xl bg-slate-100 text-slate-700 border border-slate-200">
                            Edit Profile
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs font-bold text-slate-600">
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
                        <a href="clients?page=<?=$p?><?=!empty($search) ? '&search=' . urlencode($search) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $page ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-300 hover:bg-slate-100 text-slate-700' ?>">
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

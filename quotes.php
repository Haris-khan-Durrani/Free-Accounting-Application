<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Search & Pagination Logic for Proposals / Quotes
$qSearch = trim($_GET['search'] ?? '');
$qPage = max(1, (int)($_GET['page'] ?? 1));
$qPerPage = 15;
$qOffset = ($qPage - 1) * $qPerPage;

$qWhere = "WHERE q.tenant_id = ?";
$qParams = [$tid];

if (!empty($qSearch)) {
    $qWhere .= " AND (q.quote_number LIKE ? OR c.company_name LIKE ? OR q.notes LIKE ?)";
    $sT = "%$qSearch%";
    $qParams = array_merge($qParams, [$sT, $sT, $sT]);
}

$stCountQ = $pdo->prepare("SELECT COUNT(*) FROM quotes q JOIN clients c ON c.id = q.client_id $qWhere");
$stCountQ->execute($qParams);
$totalQRecords = (int)$stCountQ->fetchColumn();
$totalQPages = max(1, (int)ceil($totalQRecords / $qPerPage));

$st = $pdo->prepare("
    SELECT q.*, c.company_name 
    FROM quotes q 
    JOIN clients c ON c.id = q.client_id 
    $qWhere 
    ORDER BY q.id DESC 
    LIMIT $qPerPage OFFSET $qOffset
");
$st->execute($qParams);
$quotes = $st->fetchAll();

page_start('Proposals & Estimates');
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Commercial Proposals & Quotes</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Draft proposals and client estimates with 1-click conversion to Tax Invoices.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <a href="quote_form" class="inline-flex items-center px-4 py-2.5 border border-transparent shadow-md text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 transition-all">
            <i class="fa-solid fa-plus mr-1.5"></i>+ Create New Proposal
        </a>
    </div>
</div>

<!-- Proposals Table (Desktop) + Touch Cards (Mobile) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">Proposals Log <span class="text-xs font-semibold text-slate-400">(<?=$totalQRecords?> total)</span></h2>
        
        <form method="get" class="flex items-center space-x-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?=e($qSearch)?>" placeholder="Search quote #, client..." class="pl-8 pr-4 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 w-48 sm:w-64">
            </div>
            <?php if ($qSearch): ?>
                <a href="quotes" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all">Clear</a>
            <?php endif; ?>
            <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-xs">Search</button>
        </form>
    </div>

    <!-- Desktop Table View (Hidden on Mobile) -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Quote #</th>
                    <th class="px-6 py-3.5">Client Name</th>
                    <th class="px-6 py-3.5">Issue Date</th>
                    <th class="px-6 py-3.5">Valid Until</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5 text-right">Total Value</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($quotes)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-file-signature text-4xl mb-3 text-slate-300 block"></i>
                            <span class="font-bold text-slate-700 block mb-1">No proposals created yet.</span>
                            <a href="quote_form" class="text-xs font-bold text-amber-600 hover:underline">Click here to issue your first commercial proposal →</a>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($quotes as $q): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-mono font-extrabold text-amber-600">
                            <a href="quote_view?id=<?=$q['id']?>" class="hover:underline"><?=e($q['quote_number'])?></a>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($q['company_name'])?></td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500"><?=e(date('d M Y', strtotime($q['quote_date'])))?></td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500"><?=e(date('d M Y', strtotime($q['valid_until'])))?></td>
                        <td class="px-6 py-4 text-center">
                            <?php
                            $stClasses = [
                                'accepted' => 'bg-emerald-100 text-emerald-800',
                                'sent' => 'bg-blue-100 text-blue-800',
                                'draft' => 'bg-sky-100 text-sky-800',
                                'rejected' => 'bg-rose-100 text-rose-800'
                            ];
                            $c = $stClasses[$q['status']] ?? 'bg-slate-100 text-slate-800';
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold <?=$c?>"><?=strtoupper(e($q['status']))?></span>
                        </td>
                        <td class="px-6 py-4 text-right font-extrabold text-slate-900 font-mono"><?=money((float)$q['total'], $q['currency'] ?: $activeTenant['currency'])?></td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="quote_view?id=<?=$q['id']?>" class="text-xs font-bold text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-blue-50 px-2.5 py-1.5 rounded-lg">View</a>
                            <a href="quote_convert?id=<?=$q['id']?>" onclick="return confirm('Convert this proposal into a Tax Invoice?')" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg">→ Convert to Invoice</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch Cards View (Visible only on Mobile) -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php if (empty($quotes)): ?>
            <div class="p-6 text-center text-slate-400">
                <i class="fa-solid fa-file-signature text-3xl mb-2 text-slate-300 block"></i>
                <span class="font-bold text-slate-700 block text-xs">No proposals created yet.</span>
                <a href="quote_form" class="text-xs font-bold text-amber-600 hover:underline mt-1 block">+ Create New Proposal</a>
            </div>
        <?php endif; ?>
        <?php foreach ($quotes as $q): ?>
            <div class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <a href="quote_view?id=<?=$q['id']?>" class="font-mono font-black text-amber-600 text-sm"><?=e($q['quote_number'])?></a>
                    <?php
                    $stClasses = [
                        'accepted' => 'bg-emerald-100 text-emerald-800',
                        'sent' => 'bg-blue-100 text-blue-800',
                        'draft' => 'bg-sky-100 text-sky-800',
                        'rejected' => 'bg-rose-100 text-rose-800'
                    ];
                    $c = $stClasses[$q['status']] ?? 'bg-slate-100 text-slate-800';
                    ?>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-black <?=$c?>"><?=strtoupper(e($q['status']))?></span>
                </div>
                <div class="flex justify-between items-end">
                    <div>
                        <div class="font-extrabold text-slate-900 text-sm"><?=e($q['company_name'])?></div>
                        <div class="text-2xs text-slate-400 font-semibold mt-0.5"><i class="fa-regular fa-clock mr-1"></i>Valid: <?=e(date('d M Y', strtotime($q['valid_until'])))?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-slate-900 text-base font-mono"><?=money((float)$q['total'], $q['currency'] ?: $activeTenant['currency'])?></div>
                        <div class="mt-1 flex justify-end space-x-1.5">
                            <a href="quote_view?id=<?=$q['id']?>" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 text-2xs font-extrabold rounded-lg">View</a>
                            <a href="quote_convert?id=<?=$q['id']?>" onclick="return confirm('Convert to Invoice?')" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-2xs font-extrabold rounded-lg">→ Convert</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Proposals Pagination Controls -->
    <?php if ($totalQPages > 1): ?>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
            <div>
                Showing <strong><?=min($qOffset + 1, $totalQRecords)?></strong> to <strong><?=min($qOffset + $qPerPage, $totalQRecords)?></strong> of <strong><?=$totalQRecords?></strong> proposals
            </div>
            
            <div class="flex items-center space-x-1.5">
                <?php if ($qPage > 1): ?>
                    <a href="quotes?page=<?=$qPage - 1?><?=!empty($qSearch) ? '&search=' . urlencode($qSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                    </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalQPages; $p++): ?>
                    <a href="quotes?page=<?=$p?><?=!empty($qSearch) ? '&search=' . urlencode($qSearch) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $qPage ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' ?>">
                        <?=$p?>
                    </a>
                <?php endfor; ?>

                <?php if ($qPage < $totalQPages): ?>
                    <a href="quotes?page=<?=$qPage + 1?><?=!empty($qSearch) ? '&search=' . urlencode($qSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_end(); ?>

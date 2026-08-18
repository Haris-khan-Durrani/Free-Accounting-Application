<!-- Quick Add Catalog Item Modal Component -->
<div id="addItemModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0" id="addItemModalBackdrop" onclick="closeAddItemModal()"></div>

    <!-- Modal Box -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 scale-95 opacity-0 duration-300" id="addItemModalBox">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-4 text-white flex items-center justify-between border-b border-slate-700">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-bold text-sm shadow-inner">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Add Product / Service to Catalog</h3>
                        <p class="text-2xs text-slate-400">Save new item to re-use across invoices and proposals</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddItemModal()" class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700/50">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="quickAddItemForm" onsubmit="submitQuickAddItem(event)" class="p-6 space-y-4">
                <?=csrf_field()?>
                
                <!-- Feedback Alert -->
                <div id="quickAddItemAlert" class="hidden rounded-xl p-3 text-xs font-semibold flex items-center space-x-2"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Type <span class="text-rose-500">*</span></label>
                        <select name="type" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                            <option value="service">Service</option>
                            <option value="product">Physical Product</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">SKU / Item Code</label>
                        <input type="text" name="sku" placeholder="PRD-101" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product / Service Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="quick_item_name" required placeholder="e.g. Executive Consulting Services" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Standard Price <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="unit_price" required value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Unit of Measure</label>
                        <input type="text" name="unit" value="unit" placeholder="hrs, pcs, unit" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Description / Scope</label>
                    <textarea name="description" rows="2" placeholder="Item description details for invoice line..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"></textarea>
                </div>

                <!-- Form Footer Actions -->
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeAddItemModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="quickAddItemSubmitBtn" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white text-xs font-black shadow-md transition-all flex items-center space-x-2">
                        <i class="fa-solid fa-check"></i>
                        <span id="quickAddItemBtnText">Save & Apply Item</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let targetRowForNewItem = null;

function openAddItemModal(targetRowSelect = null) {
    targetRowForNewItem = targetRowSelect;
    const modal = document.getElementById('addItemModal');
    const backdrop = document.getElementById('addItemModalBackdrop');
    const box = document.getElementById('addItemModalBox');
    const alert = document.getElementById('quickAddItemAlert');
    
    document.getElementById('quickAddItemForm').reset();
    alert.classList.add('hidden');

    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        backdrop.classList.remove('opacity-0');
        box.classList.remove('scale-95', 'opacity-0');
        box.classList.add('scale-100', 'opacity-100');
        document.getElementById('quick_item_name').focus();
    });
}

function closeAddItemModal() {
    const modal = document.getElementById('addItemModal');
    const backdrop = document.getElementById('addItemModalBackdrop');
    const box = document.getElementById('addItemModalBox');

    backdrop.classList.add('opacity-0');
    box.classList.remove('scale-100', 'opacity-100');
    box.classList.add('scale-95', 'opacity-0');

    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function submitQuickAddItem(e) {
    e.preventDefault();
    const form = document.getElementById('quickAddItemForm');
    const alert = document.getElementById('quickAddItemAlert');
    const submitBtn = document.getElementById('quickAddItemSubmitBtn');
    const btnText = document.getElementById('quickAddItemBtnText');

    alert.classList.add('hidden');
    submitBtn.disabled = true;
    btnText.textContent = 'Saving Item...';

    const formData = new FormData(form);

    fetch('item_save_ajax.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        btnText.textContent = 'Save & Apply Item';

        if (!data.success) {
            alert.className = 'rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 text-rose-700 border border-rose-200';
            alert.innerHTML = `<i class="fa-solid fa-circle-exclamation text-rose-500 text-sm"></i><span>${data.message}</span>`;
            alert.classList.remove('hidden');
            return;
        }

        // Successfully created item
        const newItem = data.item;
        if (typeof catalogItems !== 'undefined') {
            catalogItems.push(newItem);
        }

        // Update all catalog selects across line item rows
        document.querySelectorAll('.catalog-item-select').forEach(select => {
            const opt = document.createElement('option');
            opt.value = newItem.id;
            opt.textContent = `${newItem.sku ? '[' + newItem.sku + '] ' : ''}${newItem.name} — ${parseFloat(newItem.unit_price).toFixed(2)}`;
            
            // Insert before the last option (+ Add New Item)
            const addNewOpt = select.querySelector('option[value="__add_new_item__"]');
            if (addNewOpt) {
                select.insertBefore(opt, addNewOpt);
            } else {
                select.appendChild(opt);
            }
        });

        // If triggered from a specific row dropdown, select it!
        if (targetRowForNewItem) {
            targetRowForNewItem.value = newItem.id;
            handleCatalogItemChange(targetRowForNewItem);
        }

        closeAddItemModal();
    })
    .catch(err => {
        submitBtn.disabled = false;
        btnText.textContent = 'Save & Apply Item';
        alert.className = 'rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 text-rose-700 border border-rose-200';
        alert.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-rose-500 text-sm"></i><span>Network error while saving catalog item.</span>`;
        alert.classList.remove('hidden');
    });
}
</script>

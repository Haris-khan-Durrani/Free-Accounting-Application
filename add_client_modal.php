<!-- Quick Add Client Modal Component -->
<div id="addClientModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0" id="addClientModalBackdrop" onclick="closeAddClientModal()"></div>

    <!-- Modal Box -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 scale-95 opacity-0 duration-300" id="addClientModalBox">
            
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-6 py-4 text-white flex items-center justify-between border-b border-slate-700">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center font-bold text-sm shadow-inner">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight" id="modal-title">Add New Client Account</h3>
                        <p class="text-2xs text-slate-400">Fed client details to create and auto-select</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddClientModal()" class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-700/50">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="quickAddClientForm" onsubmit="submitQuickAddClient(event)" class="p-6 space-y-4">
                <?=csrf_field()?>
                
                <!-- Feedback Alert -->
                <div id="quickAddClientAlert" class="hidden rounded-xl p-3 text-xs font-semibold flex items-center space-x-2"></div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Company Name / Client Title <span class="text-rose-500">*</span></label>
                    <input type="text" name="company_name" id="quick_client_company_name" required placeholder="e.g. 360 Business Consultants" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Primary Contact</label>
                        <input type="text" name="contact_name" placeholder="John Doe" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" name="email" placeholder="billing@company.com" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                        <input type="text" name="phone" placeholder="+971 50 123 4567" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tax / TRN Reg No</label>
                        <input type="text" name="tax_number" placeholder="100293847500003" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Billing Currency</label>
                        <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                            <option value="AED" selected>AED - UAE Dirham</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="SAR">SAR - Saudi Riyal</option>
                            <option value="INR">INR - Indian Rupee</option>
                            <option value="CAD">CAD - Canadian Dollar</option>
                            <option value="AUD">AUD - Australian Dollar</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Country</label>
                        <input type="text" name="country" value="United Arab Emirates" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Address / Billing Details</label>
                    <textarea name="address" rows="2" placeholder="Suite 101, Business Bay, Dubai..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></textarea>
                </div>

                <!-- Form Footer Actions -->
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeAddClientModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="quickAddClientSubmitBtn" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-xs font-black shadow-md transition-all flex items-center space-x-2">
                        <i class="fa-solid fa-check"></i>
                        <span id="quickAddClientBtnText">Save & Select Client</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating Toast Container -->
<div id="quickClientToast" class="fixed bottom-5 right-5 z-50 transform translate-y-10 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-2xl border border-slate-700 flex items-center space-x-3 text-xs font-bold">
        <div class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <i class="fa-solid fa-check text-sm"></i>
        </div>
        <span id="quickClientToastMsg">Client added successfully</span>
    </div>
</div>

<script>
let lastSelectedClientValue = '';

function trackClientSelectState(selectElem) {
    if (selectElem.value !== '__add_new__') {
        selectElem.dataset.lastSelected = selectElem.value;
    }
}

// Attach listener to all client selects when page loads
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('select[name="client_id"]').forEach(sel => {
        sel.dataset.lastSelected = sel.value;
        sel.addEventListener("focus", () => {
            if (sel.value !== '__add_new__') {
                sel.dataset.lastSelected = sel.value;
            }
        });
    });
});

function handleClientSelectChange(selectElem) {
    if (selectElem.value === '__add_new__') {
        openAddClientModal(selectElem);
    } else {
        selectElem.dataset.lastSelected = selectElem.value;
    }
}

function openAddClientModal(triggerSelect = null) {
    if (triggerSelect && triggerSelect.dataset.lastSelected !== undefined) {
        lastSelectedClientValue = triggerSelect.dataset.lastSelected;
    } else {
        const activeSel = document.querySelector('select[name="client_id"]');
        if (activeSel) lastSelectedClientValue = activeSel.dataset.lastSelected || activeSel.value || '';
    }

    const modal = document.getElementById("addClientModal");
    const backdrop = document.getElementById("addClientModalBackdrop");
    const box = document.getElementById("addClientModalBox");
    const alertBox = document.getElementById("quickAddClientAlert");

    alertBox.classList.add("hidden");
    alertBox.innerHTML = "";

    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";

    setTimeout(() => {
        backdrop.classList.remove("opacity-0");
        backdrop.classList.add("opacity-100");
        box.classList.remove("scale-95", "opacity-0");
        box.classList.add("scale-100", "opacity-100");
        
        const firstInput = document.getElementById("quick_client_company_name");
        if (firstInput) firstInput.focus();
    }, 20);
}

function closeAddClientModal() {
    const modal = document.getElementById("addClientModal");
    const backdrop = document.getElementById("addClientModalBackdrop");
    const box = document.getElementById("addClientModalBox");

    backdrop.classList.remove("opacity-100");
    backdrop.classList.add("opacity-0");
    box.classList.remove("scale-100", "opacity-100");
    box.classList.add("scale-95", "opacity-0");

    setTimeout(() => {
        modal.classList.add("hidden");
        document.body.style.overflow = "auto";
        document.getElementById("quickAddClientForm").reset();

        // Revert any client select that has __add_new__ back to previous selection
        document.querySelectorAll('select[name="client_id"]').forEach(sel => {
            if (sel.value === '__add_new__') {
                sel.value = sel.dataset.lastSelected || '';
            }
        });
    }, 250);
}

function submitQuickAddClient(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById("quickAddClientSubmitBtn");
    const btnText = document.getElementById("quickAddClientBtnText");
    const alertBox = document.getElementById("quickAddClientAlert");

    const formData = new FormData(form);
    
    submitBtn.disabled = true;
    submitBtn.classList.add("opacity-75", "cursor-not-allowed");
    btnText.textContent = "Saving Client...";

    alertBox.classList.add("hidden");

    fetch("client_save_ajax.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("opacity-75", "cursor-not-allowed");
        btnText.textContent = "Save & Select Client";

        if (data.success && data.client) {
            const newClient = data.client;
            
            // Add new option to all client_id selects on the page
            document.querySelectorAll('select[name="client_id"]').forEach(sel => {
                let existingOpt = sel.querySelector(`option[value="${newClient.id}"]`);
                if (!existingOpt) {
                    const opt = document.createElement("option");
                    opt.value = newClient.id;
                    opt.textContent = newClient.company_name;

                    // Insert after __add_new__ option if present, else append
                    const addNewOpt = sel.querySelector('option[value="__add_new__"]');
                    if (addNewOpt && addNewOpt.nextSibling) {
                        sel.insertBefore(opt, addNewOpt.nextSibling);
                    } else {
                        sel.appendChild(opt);
                    }
                }
                
                // Auto-select newly created client
                sel.value = newClient.id;
                sel.dataset.lastSelected = newClient.id;
            });

            // Auto-update currency select if available on page
            const currSelect = document.querySelector('select[name="currency"]');
            if (currSelect && newClient.currency) {
                const optToSelect = currSelect.querySelector(`option[value="${newClient.currency}"]`);
                if (optToSelect) {
                    currSelect.value = newClient.currency;
                }
            }

            // Close modal
            closeAddClientModal();

            // Show Toast Notification
            showQuickClientToast(`Client "${newClient.company_name}" created & selected!`);
        } else {
            alertBox.className = "rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 border border-rose-200 text-rose-800";
            alertBox.innerHTML = `<i class="fa-solid fa-circle-exclamation text-rose-500"></i> <span>${data.message || 'Error saving client.'}</span>`;
            alertBox.classList.remove("hidden");
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("opacity-75", "cursor-not-allowed");
        btnText.textContent = "Save & Select Client";

        alertBox.className = "rounded-xl p-3 text-xs font-semibold flex items-center space-x-2 bg-rose-50 border border-rose-200 text-rose-800";
        alertBox.innerHTML = `<i class="fa-solid fa-circle-exclamation text-rose-500"></i> <span>Server error. Please try again.</span>`;
        alertBox.classList.remove("hidden");
    });
}

function showQuickClientToast(msg) {
    const toast = document.getElementById("quickClientToast");
    const toastMsg = document.getElementById("quickClientToastMsg");
    toastMsg.textContent = msg;

    toast.classList.remove("translate-y-10", "opacity-0", "pointer-events-none");
    toast.classList.add("translate-y-0", "opacity-100");

    setTimeout(() => {
        toast.classList.remove("translate-y-0", "opacity-100");
        toast.classList.add("translate-y-10", "opacity-0", "pointer-events-none");
    }, 4000);
}
</script>

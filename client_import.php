<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

$message = '';
$error = '';
$importStats = null;

// Download Sample CSV template
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sample_clients_import_zoho_qb.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Company Name', 'Contact Name', 'Email', 'Phone', 'TRN Tax Number', 'Address', 'City', 'Currency']);
    fputcsv($out, ['Acme Logistics FZE', 'John Doe', 'john@acmelogistics.ae', '+971501234567', '100293847500003', 'Business Bay Tower 402', 'Dubai', 'AED']);
    fputcsv($out, ['Global Tech Solutions LLC', 'Sarah Smith', 'sarah@globaltech.ae', '+97140000000', '100492837400003', 'Downtown Blvd, Suite 12', 'Dubai', 'AED']);
    fclose($out);
    exit;
}

// Handle Import Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    verify_csrf();

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading CSV file. Please select a valid file.';
    } else {
        $fileTmp = $_FILES['csv_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'])) {
            $error = 'Invalid file format. Please upload a CSV file.';
        } else {
            $handle = fopen($fileTmp, 'r');
            if ($handle === false) {
                $error = 'Unable to read the uploaded file.';
            } else {
                $headers = fgetcsv($handle);
                if (!$headers) {
                    $error = 'CSV file is empty or corrupted.';
                } else {
                    // Smart Column Mapper for Zoho, QuickBooks, Xero, FreshBooks & Custom CSV
                    $normalize = fn($str) => strtolower(preg_replace('/[^a-z0-9]/i', '', $str));
                    $headerMap = [];
                    foreach ($headers as $idx => $h) {
                        $norm = $normalize($h);
                        if (in_array($norm, ['companyname', 'customername', 'displayname', 'accountname', 'name', 'company'])) {
                            $headerMap['company_name'] = $idx;
                        } elseif (in_array($norm, ['contactname', 'primarycontact', 'contactperson', 'contact', 'person'])) {
                            $headerMap['contact_name'] = $idx;
                        } elseif (in_array($norm, ['email', 'emailaddress', 'customeremail', 'mail'])) {
                            $headerMap['email'] = $idx;
                        } elseif (in_array($norm, ['phone', 'mobile', 'phonenumber', 'contactnumber', 'telephone'])) {
                            $headerMap['phone'] = $idx;
                        } elseif (in_array($norm, ['trntaxnumber', 'trn', 'taxnumber', 'vatnumber', 'gstin', 'taxregno', 'taxno'])) {
                            $headerMap['tax_number'] = $idx;
                        } elseif (in_array($norm, ['address', 'billingaddress', 'street', 'addressline1'])) {
                            $headerMap['address'] = $idx;
                        } elseif (in_array($norm, ['city', 'billingcity', 'emirate'])) {
                            $headerMap['city'] = $idx;
                        } elseif (in_array($norm, ['currency', 'currencycode'])) {
                            $headerMap['currency'] = $idx;
                        }
                    }

                    if (!isset($headerMap['company_name'])) {
                        $error = 'Could not find a Company Name column in the CSV file. Required column header: "Company Name" or "Customer Name".';
                    } else {
                        $importedCount = 0;
                        $updatedCount = 0;
                        $skippedCount = 0;

                        $stCheck = $pdo->prepare("SELECT id FROM clients WHERE tenant_id = ? AND (company_name = ? OR (email != '' AND email = ?))");
                        $stIns   = $pdo->prepare("INSERT INTO clients (tenant_id, company_name, contact_name, email, phone, tax_number, address, city, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stUpd   = $pdo->prepare("UPDATE clients SET contact_name = ?, email = ?, phone = ?, tax_number = ?, address = ?, city = ?, currency = ? WHERE id = ? AND tenant_id = ?");

                        while (($row = fgetcsv($handle)) !== false) {
                            if (empty(array_filter($row))) continue; // skip empty rows

                            $compName = trim($row[$headerMap['company_name']] ?? '');
                            if (empty($compName)) {
                                $skippedCount++;
                                continue;
                            }

                            $contactName = trim($row[$headerMap['contact_name']] ?? '');
                            $email       = strtolower(trim($row[$headerMap['email']] ?? ''));
                            $phone       = trim($row[$headerMap['phone']] ?? '');
                            $taxNumber   = trim($row[$headerMap['tax_number']] ?? '');
                            $address     = trim($row[$headerMap['address']] ?? '');
                            $city        = trim($row[$headerMap['city']] ?? 'Dubai');
                            $currency    = strtoupper(trim($row[$headerMap['currency']] ?? 'AED')) ?: 'AED';

                            // Check existing
                            $stCheck->execute([$tid, $compName, $email]);
                            $existingId = $stCheck->fetchColumn();

                            if ($existingId) {
                                // Update existing client profile
                                $stUpd->execute([$contactName, $email, $phone, $taxNumber, $address, $city, $currency, $existingId, $tid]);
                                $updatedCount++;
                            } else {
                                // Insert new client
                                $stIns->execute([$tid, $compName, $contactName, $email, $phone, $taxNumber, $address, $city, $currency]);
                                $importedCount++;
                            }
                        }

                        fclose($handle);

                        log_audit($pdo, 'import_clients', 'clients', $tid, "Imported $importedCount new clients, updated $updatedCount existing records");
                        $importStats = [
                            'imported' => $importedCount,
                            'updated'  => $updatedCount,
                            'skipped'  => $skippedCount
                        ];
                        $message = "Migration Complete! Successfully imported $importedCount new clients and updated $updatedCount existing records.";
                    }
                }
            }
        }
    }
}

require __DIR__ . '/layout.php';
page_start('Universal Client Import Wizard');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 font-black text-xs uppercase tracking-wider">Migration Tool</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Import Clients from Zoho, QuickBooks & CSV</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Migrate your entire client and customer directory seamlessly from Zoho Books, QuickBooks, Xero, FreshBooks, or Excel CSV.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex space-x-3">
        <a href="client_import?download_sample=1" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition-all">
            <i class="fa-solid fa-download mr-1.5 text-amber-500"></i>Download Sample CSV Template
        </a>
        <a href="clients" class="inline-flex items-center px-4 py-2 bg-slate-900 text-white hover:bg-slate-800 text-xs font-bold rounded-xl transition-all shadow-sm">
            <i class="fa-solid fa-users mr-1.5 text-blue-400"></i>Back to CRM Directory
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-300 text-emerald-900 rounded-2xl p-5 text-xs font-semibold space-y-2">
        <div class="flex items-center space-x-2 text-emerald-800 font-black text-sm">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span><?=e($message)?></span>
        </div>
        <?php if ($importStats): ?>
            <div class="grid grid-cols-3 gap-3 pt-2 text-2xs">
                <div class="bg-emerald-100/70 p-2.5 rounded-xl text-emerald-900">New Clients Created: <strong><?=$importStats['imported']?></strong></div>
                <div class="bg-blue-100/70 p-2.5 rounded-xl text-blue-900">Existing Records Updated: <strong><?=$importStats['updated']?></strong></div>
                <div class="bg-slate-200/70 p-2.5 rounded-xl text-slate-700">Empty Rows Skipped: <strong><?=$importStats['skipped']?></strong></div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 bg-rose-50 border border-rose-300 text-rose-900 rounded-2xl p-4 text-xs font-bold flex items-center space-x-2">
        <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
        <span><?=e($error)?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- CSV Upload Box -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
            <form method="post" enctype="multipart/form-data" class="space-y-6">
                <?=csrf_field()?>

                <div>
                    <label class="block text-xs font-black uppercase text-slate-900 tracking-wider mb-2">
                        Select CSV File to Upload <span class="text-rose-500">*</span>
                    </label>

                    <div class="border-2 border-dashed border-slate-300 hover:border-blue-500 rounded-2xl p-8 text-center bg-slate-50/50 hover:bg-blue-50/20 transition-all cursor-pointer relative" onclick="document.getElementById('csv_input').click()">
                        <input type="file" name="csv_file" id="csv_input" accept=".csv,.txt" required class="hidden" onchange="document.getElementById('fileName').innerText = this.files[0].name">
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-2xl font-bold mx-auto mb-3">
                            <i class="fa-solid fa-file-csv"></i>
                        </div>
                        <div class="text-xs font-extrabold text-slate-900" id="fileName">Click to choose CSV file or drag and drop here</div>
                        <p class="text-3xs text-slate-400 mt-1">Supports standard CSV exports from Zoho Books, QuickBooks, Xero, FreshBooks, or Excel.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="client_import?download_sample=1" class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center space-x-1">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <span>Download Sample Template</span>
                    </a>

                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all flex items-center space-x-2">
                        <i class="fa-solid fa-file-import"></i>
                        <span>Start Migration Import</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Supported Platforms List -->
    <div class="space-y-6">
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-3xl p-6 border border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center space-x-3 text-amber-400">
                <i class="fa-solid fa-bolt text-xl"></i>
                <h3 class="text-sm font-black">Auto-Column Mapping</h3>
            </div>
            
            <p class="text-xs text-slate-300 leading-relaxed">
                Our smart importer automatically detects and maps column headers from popular accounting platforms:
            </p>

            <div class="space-y-2 text-2xs text-slate-300">
                <div class="p-2.5 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center space-x-2">
                    <i class="fa-solid fa-check text-emerald-400"></i>
                    <span><strong>Zoho Books:</strong> Customer Name, Email, Phone, TRN Number</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center space-x-2">
                    <i class="fa-solid fa-check text-emerald-400"></i>
                    <span><strong>QuickBooks:</strong> Display Name, Primary Contact, Email, Billing Address</span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-800/80 border border-slate-700 flex items-center space-x-2">
                    <i class="fa-solid fa-check text-emerald-400"></i>
                    <span><strong>Xero / FreshBooks:</strong> Contact Name, Tax Reg No, City, Currency</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php page_end(); ?>

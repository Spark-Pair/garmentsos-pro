<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BiltyController;
use App\Http\Controllers\BranchLogoController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CRController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DailyLedgerController;
use App\Http\Controllers\Developer\BackupController;
use App\Http\Controllers\Developer\BranchController;
use App\Http\Controllers\Developer\LicenseController;
use App\Http\Controllers\Developer\RestoreController;
use App\Http\Controllers\Developer\SettingsController;
use App\Http\Controllers\Developer\UpdateController;
use App\Http\Controllers\DRController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FabricController;
use App\Http\Controllers\FirstRunSetupController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModuleBranchPreferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhysicalQuantityController;
use App\Http\Controllers\PaymentProgramController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PermissionReportController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StatementAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UtilityAccountController;
use App\Http\Controllers\UtilityBillController;
use App\Http\Controllers\VoucherController;
use App\Services\Updater\UpdateLockService;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware('setup.incomplete')->group(function () {
    Route::get('setup', [FirstRunSetupController::class, 'index'])->name('setup.index');
    Route::post('setup', [FirstRunSetupController::class, 'store'])->name('setup.store');
});

Route::middleware('setup.complete')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login', [AuthController::class, 'loginPost'])->name('loginPost');
});
Route::get('subscription-expired', function () {
    return view('subscription-expired'); // ya controller agar chahiye
})->name('subscription-expired');

Route::get('branch-logos/{branch}', [BranchLogoController::class, 'show'])->name('branch-logos.show');

Route::get('', function (\App\Services\Setup\SetupStatusService $setup) {
    if ($setup->requiresFirstRunSetup()) {
        return redirect()->route('setup.index');
    }

    return auth()->check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware(['setup.complete', 'signed'])->group(function () {
    Route::get('developer/updater/update-request/signed', [UpdateController::class, 'signedUpdateRequest'])->name('developer.updater.update-request.signed');
    Route::get('developer/updater/update-lock-failed/signed', [UpdateController::class, 'signedUpdateFailed'])->name('developer.updater.update-lock-failed.signed');
});

Route::get('updating', function (UpdateLockService $locks) {
    $lock = $locks->activeLock();
    if ($lock === null) {
        return redirect('/');
    }

    return view('updater.updating', ['updateLock' => $lock]);
})->middleware(['setup.complete', 'auth', 'activeSession'])->name('updating');

Route::group(['middleware' => ['setup.complete', 'auth', 'activeSession', 'ensureLicense', 'readonly', 'blockWhenUpdating']], function () {
    Route::get('developer/backups', [BackupController::class, 'index'])->name('developer.backups');
    Route::post('developer/backups', [BackupController::class, 'store'])->name('developer.backups.store');
    Route::post('developer/backups/run-migrations', [BackupController::class, 'runMigrations'])->name('developer.backups.run-migrations');
    Route::post('developer/backups/repair-storage', [BackupController::class, 'repairStorage'])->name('developer.backups.repair-storage');
    Route::post('developer/backups/{backupLog}/verify', [BackupController::class, 'verify'])->name('developer.backups.verify');
    Route::get('developer/backups/{backupLog}/download', [BackupController::class, 'download'])->name('developer.backups.download');
    Route::get('developer/backups/{backupLog}/restore', [RestoreController::class, 'show'])->name('developer.backups.restore.show');
    Route::post('developer/backups/{backupLog}/restore', [RestoreController::class, 'store'])->name('developer.backups.restore.store');
    Route::post('developer/backups/restore-upload', [RestoreController::class, 'upload'])->name('developer.backups.restore-upload');
    Route::post('developer/backups/restore-upload/{jobId}/run-now', [RestoreController::class, 'runUploadNow'])->name('developer.backups.restore-upload.run-now');
    Route::get('developer/backups/restore-upload/{jobId}', [RestoreController::class, 'uploadStatus'])->name('developer.backups.restore-upload.status');
    Route::post('module-branch-preferences', [ModuleBranchPreferenceController::class, 'store'])->name('module-branch-preferences.store');
    Route::get('developer/branches', [BranchController::class, 'index'])->name('developer.branches.index');
    Route::get('developer/branches/create', [BranchController::class, 'create'])->name('developer.branches.create');
    Route::post('developer/branches', [BranchController::class, 'store'])->name('developer.branches.store');
    Route::get('developer/branches/modules', [BranchController::class, 'modules'])->name('developer.branches.modules.index');
    Route::post('developer/branches/modules/manage', [BranchController::class, 'updateModuleAcrossBranches'])->name('developer.branches.modules.manage');
    Route::get('developer/branches/access', [BranchController::class, 'access'])->name('developer.branches.access.index');
    Route::get('developer/branches/{branch}', [BranchController::class, 'show'])->name('developer.branches.show');
    Route::get('developer/branches/{branch}/edit', [BranchController::class, 'edit'])->name('developer.branches.edit');
    Route::put('developer/branches/{branch}', [BranchController::class, 'update'])->name('developer.branches.update');
    Route::patch('developer/branches/{branch}/status', [BranchController::class, 'updateStatus'])->name('developer.branches.status');
    Route::post('developer/branches/modules', [BranchController::class, 'updateModules'])->name('developer.branches.modules');
    Route::post('developer/branches/access', [BranchController::class, 'updateAccess'])->name('developer.branches.access');
    Route::get('developer/updater', [UpdateController::class, 'index'])->name('developer.updater');
    Route::get('developer/updater/update-request', [UpdateController::class, 'updateRequest'])->name('developer.updater.update-request');
    Route::get('developer/updater/launcher-handoff', [UpdateController::class, 'launcherHandoff'])->name('developer.updater.launcher-handoff');
    Route::post('developer/updater/launcher-handoff/start', [UpdateController::class, 'startLauncherHandoff'])->name('developer.updater.launcher-handoff.start');
    Route::get('developer/updater/update-lock-status', [UpdateController::class, 'updateLockStatus'])->name('developer.updater.update-lock-status');
    Route::post('developer/updater/clear-update-lock', [UpdateController::class, 'clearUpdateLock'])->name('developer.updater.clear-update-lock');
    Route::post('developer/updater/set-stable-feed', [UpdateController::class, 'setStableFeed'])->name('developer.updater.set-stable-feed');
    Route::post('developer/updater/check', [UpdateController::class, 'check'])->name('developer.updater.check');
    Route::post('developer/updater/apply', [UpdateController::class, 'apply'])->name('developer.updater.apply');
    Route::get('developer/license', [LicenseController::class, 'status'])->name('developer.license.status');
    Route::get('developer/license/activate', [LicenseController::class, 'activate'])->name('developer.license.activate');
    Route::post('developer/license/activate', [LicenseController::class, 'activatePost'])->name('developer.license.activate.post');
    Route::post('developer/license/request-demo', [LicenseController::class, 'requestDemo'])->name('developer.license.request-demo');
    Route::post('developer/license/register', [LicenseController::class, 'registerDevice'])->name('developer.license.register');
    Route::post('developer/license/check', [LicenseController::class, 'checkDevice'])->name('developer.license.check');
    Route::post('developer/license/run-migrations', [LicenseController::class, 'runMigrations'])->name('developer.license.run-migrations');
    Route::get('developer/license/offline', [LicenseController::class, 'offline'])->name('developer.license.offline');
    Route::post('developer/license/offline/import', [LicenseController::class, 'importOffline'])->name('developer.license.offline.import');
    Route::post('developer/license/refresh', [LicenseController::class, 'refresh'])->name('developer.license.refresh');
    Route::post('developer/license/reactivation-request', [LicenseController::class, 'reactivationRequest'])->name('developer.license.reactivation-request');
    Route::get('developer/audit-logs', [LicenseController::class, 'auditLogs'])->name('developer.audit-logs');
});

Route::group(['middleware' => ['setup.complete', 'auth', 'activeSession', 'ensureLicense', 'readonly', 'blockWhenUpdating', 'appPermission', 'dbTransaction']], function () {
    Route::get('home', [Controller::class, 'home'])->name('home');

    Route::resource('users', UserController::class)->except(['show']);
    Route::post('update-user-status', [UserController::class, 'updateStatus'])->name('update-user-status');
    Route::post('users.reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::post('update-theme', [AuthController::class, 'updateTheme']);

    Route::resource('setups', SetupController::class);
    Route::get('permissions-report', [PermissionReportController::class, 'index'])->name('permissions-report');
    Route::get('developer/settings', [SettingsController::class, 'index'])->name('developer.settings');
    Route::post('developer/settings/labels', [SettingsController::class, 'saveLabel'])->name('developer.settings.labels.save');
    Route::post('developer/settings/labels/{key}/reset', [SettingsController::class, 'resetLabel'])->name('developer.settings.labels.reset');
    Route::post('developer/settings/branding', [SettingsController::class, 'saveBranding'])->name('developer.settings.branding.save');
    Route::post('developer/settings/branding/{key}/reset', [SettingsController::class, 'resetBranding'])->name('developer.settings.branding.reset');
    Route::post('developer/settings/modules', [SettingsController::class, 'saveModule'])->name('developer.settings.modules.save');

    Route::resource('suppliers', SupplierController::class)->middleware('moduleEnabled:suppliers');
    Route::post('update-supplier-category', [SupplierController::class, 'updateSupplierCategory'])->middleware('moduleEnabled:suppliers')->name('update-supplier-category');

    Route::resource('customers', CustomerController::class)->middleware('moduleEnabled:customers');

    Route::resource('articles', ArticleController::class)->middleware('moduleEnabled:articles');
    Route::post('update-image', [ArticleController::class, 'updateImage'])->middleware('moduleEnabled:articles')->name('update-image');
    Route::post('add-rate', [ArticleController::class, 'addRate'])->middleware('moduleEnabled:articles')->name('add-rate');

    Route::get('orders/customer-article-history', [OrderController::class, 'customerArticleHistory'])->name('orders.customer-article-history');
    Route::resource('orders', OrderController::class);

    Route::resource('shipments', ShipmentController::class);

    Route::resource('physical-quantities', PhysicalQuantityController::class);

    Route::resource('inventory', InventoryController::class)->except(['show'])->middleware('moduleEnabled:inventory');

    Route::resource('invoices', InvoiceController::class);
    Route::get('print-invoices', [InvoiceController::class, 'print'])->name('invoices.print');

    Route::resource('customer-payments', CustomerPaymentController::class);
    Route::post('customer-payments/{id}/clear', [CustomerPaymentController::class, 'clear'])->name('customer-payments.clear');
    Route::post('customer-payments/{payment}/split', [CustomerPaymentController::class, 'split'])->name('customer-payments.split');

    Route::resource('supplier-payments', SupplierPaymentController::class);

    Route::get('payment-programs/customer-summary', [PaymentProgramController::class, 'CustomerSummary'])->name('payment-programs.customer-summary');
    Route::get('payment-programs/supplier-summary', [PaymentProgramController::class, 'SupplierSummary'])->name('payment-programs.supplier-summary');
    Route::resource('payment-programs', PaymentProgramController::class);
    Route::post('payment-programs.update-program', [PaymentProgramController::class, 'updateProgram'])->name('payment-programs.update-program');
    Route::post('payment-programs/{id}/mark-paid', [PaymentProgramController::class, 'markPaid'])->name('payment-programs.mark-paid');

    Route::resource('bank-accounts', BankAccountController::class);
    Route::post('update-bank-account-status', [BankAccountController::class, 'updateStatus'])->name('update-bank-account-status');
    Route::put('bank-accounts/{account}/update-serial', [BankAccountController::class, 'updateSerial'])->name('bank-accounts.update-serial');
    Route::post('bank-accounts/{account}/update-serial', [BankAccountController::class, 'updateSerial'])->name('bank-accounts.update-serial-post');

    Route::resource('cargos', CargoController::class);

    Route::resource('bilties', BiltyController::class);

    Route::resource('expenses', ExpenseController::class);

    Route::resource('vouchers', VoucherController::class);

    Route::get('fabrics/issue', [FabricController::class, 'issue'])->name('fabrics.issue');
    Route::post('fabrics/issuePost', [FabricController::class, 'issuePost'])->name('fabrics.issuePost');
    Route::get('fabrics/issued/{issuedFabric}/edit', [FabricController::class, 'editIssued'])->name('fabrics.issued.edit');
    Route::put('fabrics/issued/{issuedFabric}', [FabricController::class, 'updateIssued'])->name('fabrics.issued.update');
    Route::delete('fabrics/issued/{issuedFabric}', [FabricController::class, 'destroyIssued'])->name('fabrics.issued.destroy');
    Route::get('fabrics/return', [FabricController::class, 'return'])->name('fabrics.return');
    Route::post('fabrics/returnPost', [FabricController::class, 'returnPost'])->name('fabrics.returnPost');
    Route::get('fabrics/returned/{returnFabric}/edit', [FabricController::class, 'editReturn'])->name('fabrics.returned.edit');
    Route::put('fabrics/returned/{returnFabric}', [FabricController::class, 'updateReturn'])->name('fabrics.returned.update');
    Route::delete('fabrics/returned/{returnFabric}', [FabricController::class, 'destroyReturn'])->name('fabrics.returned.destroy');
    Route::resource('fabrics', FabricController::class);

    Route::resource('rates', RateController::class)->middleware('moduleEnabled:rates');

    Route::get('productions/availability/parts', [ProductionController::class, 'availability'])->name('productions.availability');
    Route::get('productions/availability/works', [ProductionController::class, 'availableWorks'])->name('productions.availability.works');
    Route::resource('productions', ProductionController::class);

    Route::resource('employees', EmployeeController::class);
    Route::post('update-employee-status', [EmployeeController::class, 'updateStatus'])->name('update-employee-status');

    Route::resource('employee-payments', EmployeePaymentController::class);

    Route::resource('cr', CRController::class);

    Route::get('dr/get-payments', [DRController::class, 'getPayments']);
    Route::resource('dr', DRController::class);

    Route::resource('daily-ledger', DailyLedgerController::class);

    Route::resource('sales-returns', SalesReturnController::class);
    Route::post('sales-returns/get-details', [SalesReturnController::class, 'getDetails'])->name('sales-returns.get-details');
    Route::resource('statement-adjustments', StatementAdjustmentController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('statement-adjustments/first-transaction-date', [StatementAdjustmentController::class, 'firstTransactionDate'])->name('statement-adjustments.first-transaction-date');

    Route::get('attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
    Route::post('attendances/store', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::get('attendances/manage-salary', [AttendanceController::class, 'manageSalary'])->name('attendances.manage-salary');
    Route::post('attendances/manage-salary', [AttendanceController::class, 'manageSalaryPost'])->name('attendances.manage-salary-post');
    Route::get('attendances/generate-slip', [AttendanceController::class, 'generateSlip'])->name('attendances.generate-slip');
    Route::post('attendances/generate-slip', [AttendanceController::class, 'generateSlipPost'])->name('attendances.generate-slip-post');

    Route::resource('utility-bills', UtilityBillController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::put('utility-bills/{utilityBill}/mark-paid', [UtilityBillController::class, 'markPaid'])->name('utility-bills.mark-paid');

    Route::resource('utility-accounts', UtilityAccountController::class);

    Route::post('get-order-details', [Controller::class, 'getOrderDetails'])->name('get-order-details');
    Route::post('get-category-data', [Controller::class, 'getCategoryData'])->name('get-category-data');
    Route::post('change-data-layout', [Controller::class, 'changeDataLayout'])->name('change-data-layout');
    Route::post('get-program-details', [Controller::class, 'getProgramDetails'])->name('get-program-details');
    Route::post('set-invoice-type', [Controller::class, 'setInvoiceType'])->name('set-invoice-type');
    Route::post('get-shipment-details', [Controller::class, 'getShipmentDetails'])->name('get-shipment-details');
    Route::post('set-voucher-type', [Controller::class, 'setVoucherType'])->name('set-voucher-type');
    Route::post('set-production-type', [Controller::class, 'setProductionType'])->name('set-production-type');
    Route::post('get-voucher-details', [Controller::class, 'getVoucherDetails'])->name('get-voucher-details');
    Route::post('get-payments-by-method', [Controller::class, 'getPaymentsByMethod'])->name('get-payments-by-method');
    Route::post('get-employees-by-category', [Controller::class, 'getEmployeesByCategory'])->name('get-employees-by-category');
    Route::post('set-daily-ledger-type', [Controller::class, 'setDailyLedgerType'])->name('set-daily-ledger-type');
    Route::post('get-utility-accounts', [Controller::class, 'getUtilityAccounts'])->name('get-utility-accounts');
    Route::post('set-cr-type', [Controller::class, 'setCRType'])->name('set-cr-type');
    Route::post('set-statement-type', [Controller::class, 'setStatementType'])->name('set-statement-type');
    Route::post('set-physical-quantity-report-type', [Controller::class, 'setPhysicalQuantityReportType'])->name('set-physical-quantity-report-type');
    Route::post('set-fabric-report-type', [Controller::class, 'setFabricReportType'])->name('set-fabric-report-type');

    Route::get('reports/statement', [ReportController::class, 'statement'])->middleware('moduleEnabled:reports')->name('reports.statement');
    Route::post('reports/statement/get-names', [ReportController::class, 'getNames'])->name('reports.statement.get-names');
    Route::get('reports/statement/record-details', [ReportController::class, 'statementRecordDetails'])->middleware('moduleEnabled:reports')->name('reports.statement.record-details');
    Route::get('reports/pending-payments', [ReportController::class, 'pendingPayments'])->middleware('moduleEnabled:reports')->name('reports.pending-payments');
    Route::get('reports/article', [ReportController::class, 'article'])->middleware('moduleEnabled:reports')->name('reports.article');
    Route::get('reports/fabric', [ReportController::class, 'fabric'])->middleware('moduleEnabled:reports')->name('reports.fabric');
    Route::get('reports/physical-quantity', [ReportController::class, 'physicalQuantity'])->middleware('moduleEnabled:reports')->name('reports.physical-quantity');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('update-last-activity', [AuthController::class, 'updateLastActivity'])->name('update-last-activity');
    Route::post('update-menu-shortcuts', [AuthController::class, 'updateMenuShortcuts'])->name('updateMenuShortcuts');
});

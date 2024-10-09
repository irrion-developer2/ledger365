<?php

use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\SuperAdmin\TallyController;

use Laravel\Jetstream\Http\Controllers\CurrentTeamController;
use Laravel\Jetstream\Http\Controllers\Inertia\TeamController;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController;

use App\Http\Controllers\App\AnalyticController;
use App\Http\Controllers\App\CompanyController;
use App\Http\Controllers\App\CustomerController;
use App\Http\Controllers\App\SupplierController;
use App\Http\Controllers\App\StockItemController;
use App\Http\Controllers\App\SalesController;
use App\Http\Controllers\App\ColumnarController;
use App\Http\Controllers\App\EmployeeController;
use App\Http\Controllers\App\BankReconciliationController;
use App\Http\Controllers\App\Reports\ReportController;
use App\Http\Controllers\App\Reports\ReportCashBankController;
use App\Http\Controllers\App\Reports\ReportGeneralLedgerController;
use App\Http\Controllers\App\Reports\ReportDayBookController;
use App\Http\Controllers\App\Reports\ReportPaymentRegisterController;
use App\Http\Controllers\App\Reports\ReportReceiptRegisterController;
use App\Http\Controllers\App\Reports\ReportCustomerGroupController;
use App\Http\Controllers\App\Reports\ReportItemGroupController;
use App\Http\Controllers\App\Reports\ReportBalanceSheetController;
use App\Http\Controllers\App\Reports\ReportBalanceSheetProfitLossController;
use App\Http\Controllers\App\Reports\ReportBalanceSheetAssetStockController;
use App\Http\Controllers\App\Reports\ReportBalanceSheetLiabilityController;
use App\Http\Controllers\App\Reports\ReportCancelledController;
use App\Http\Controllers\App\Reports\ReportOptionalController;
use Illuminate\Http\Request;

Route::get('/home',function(){
    return view('welcome');
});

Route::get('/',function(){
  return redirect('/login');
});

Route::post('/send-otp', [AuthController::class, 'sendOTP'])->name('send-otp');
Route::post('/verify-otp', [AuthController::class, 'verifyOTP'])->name('verify-otp');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
]
)->group(function () {

    Route::post('/set-company-session', function (Request $request) {
        $request->validate([
            'company_id' => 'required|integer',
            'company_name' => 'required|string',
        ]);
    
        session(['selected_company_id' => $request->company_id]);
        session(['selected_company_name' => $request->company_name]);
    
        session()->save();  // Explicitly save the session
    
        return response()->json(['success' => true, 'company' => $request->company_name]);
    });
    

    Route::get('/get-filtered-data', [HomeController::class, 'getFilteredData']);

    Route::get('/dashboard', [HomeController::class, 'index']);

    Route::group(['middleware' => 'checkUserRoleAndStatus'], function () {

        Route::get('/analytics', [AnalyticController::class, 'index'])->name('analytics.index');

        Route::get('/fetch-company-data/{company_id}', [CompanyController::class, 'fetchCompanyData'])->name('fetch.company.data');

        Route::resource('tenants', TenantController::class);

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/get-data', [CustomerController::class, 'getData'])->name('customers.get-data');
        Route::get('otherLedgers', [CustomerController::class, 'otherLedgers'])->name('otherLedgers.index');
        Route::get('/otherLedgers/get-data', [CustomerController::class, 'ledgergetData'])->name('otherLedgers.get-data');

        Route::get('/ledgerView/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/data', [CustomerController::class, 'getCustomerData'])->name('customers.data');
        Route::get('customers/{customer}/vouchers', [CustomerController::class, 'getVoucherEntries'])->name('customers.vouchers');

        Route::get('otherCustomers', [CustomerController::class, 'otherCustomers'])->name('otherCustomers.index');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/get-data', [SupplierController::class, 'getData'])->name('suppliers.get-data');

        Route::get('/stock-items', [StockItemController::class, 'index'])->name('stock-items.index');
        Route::get('/stock-items/get-data', [StockItemController::class, 'getData'])->name('StockItem.get-data');
        Route::get('stock-items/StockItem/{StockItem}', [StockItemController::class, 'AllStockItemReports'])->name('StockItem.items');
        Route::get('stock-items/SaleStockItem/{SaleStockItem}', [StockItemController::class, 'AllSaleStockItemReports'])->name('SaleStockItem.items');
        Route::get('stock-items/SaleStockItem/data/{saleStockItemId}', [StockItemController::class, 'getSaleStockItemData'])->name('stock-items.SaleStockItem.data');

        Route::resource('reports', ReportController::class)->except(['show']);

        Route::get('reports/DayBook', [ReportDayBookController::class, 'index'])->name('reports.daybook');
        Route::get('/daybook/get-data', [ReportDayBookController::class, 'getData'])->name('daybook.get-data');

        Route::get('reports/GeneralLedger', [ReportGeneralLedgerController::class, 'index'])->name('reports.GeneralLedger');
        Route::get('reports/GeneralLedger/{GeneralLedger}', [ReportGeneralLedgerController::class, 'AllGeneralLedgerReports'])->name('reports.GeneralLedger.details');
        Route::get('reports/GeneralLedger/data/{generalLedgerId}', [ReportGeneralLedgerController::class, 'getGeneralLedgerData'])->name('reports.GeneralLedger.data');
        Route::get('reports/GeneralGroupLedger/{GeneralLedger}', [ReportGeneralLedgerController::class, 'AllGeneralGroupLedgerReports'])->name('reports.GeneralGroupLedger.details');
        Route::get('reports/GeneralGroupLedger/data/{generalLedgerId}', [ReportGeneralLedgerController::class, 'getGeneralGroupLedgerData'])->name('reports.GeneralGroupLedger.data');

        Route::get('reports/CashBank', [ReportCashBankController::class, 'index'])->name('reports.CashBank');
        Route::get('reports/CashBank/{CashBank}', [ReportCashBankController::class, 'AllCashBankReports'])->name('reports.CashBank.details');
        Route::get('reports/CashBank/data/{cashBankId}', [ReportCashBankController::class, 'getCashBankData'])->name('reports.CashBank.data');

        Route::get('reports/PaymentRegister', [ReportPaymentRegisterController::class, 'index'])->name('reports.PaymentRegister');

        Route::get('reports/ReceiptRegister', [ReportReceiptRegisterController::class, 'index'])->name('reports.ReceiptRegister');

        Route::get('reports/VoucherHead/{VoucherHead}', [ReportController::class, 'AllVoucherHeadReports'])->name('reports.VoucherHead');
        Route::get('reports/VoucherHead/data/{VoucherHeadId}', [ReportController::class, 'getVoucherHeadData'])->name('reports.VoucherHead.data');

        Route::get('reports/VoucherItem/{VoucherItem}', [ReportController::class, 'AllVoucherItemReports'])->name('reports.VoucherItem');
        Route::get('reports/VoucherItem/data/{VoucherItemId}', [ReportController::class, 'getVoucherItemData'])->name('reports.VoucherItem.data');
        Route::get('reports/VoucherItemPayment/{VoucherItem}', [ReportController::class, 'AllVoucherItemPaymentReports'])->name('reports.VoucherItemPayment');
        Route::get('reports/VoucherItemReceipt/{VoucherItem}', [ReportController::class, 'AllVoucherItemReceiptReports'])->name('reports.VoucherItemReceipt');
        Route::get('reports/VoucherItemTax/data/{VoucherItemId}', [ReportController::class, 'getVoucherItemTaxData'])->name('reports.VoucherItemTax.data');
        Route::get('reports/VoucherItemReceipt/data/{VoucherItemId}', [ReportController::class, 'getVoucherItemReceiptData'])->name('reports.VoucherItemReceipt.data');
        Route::get('reports/VoucherItemReceiptInvoice/data/{VoucherItemId}', [ReportController::class, 'getVoucherItemReceiptInvoiceData'])->name('reports.VoucherItemReceiptInvoice.data');

        Route::get('reports/CustomerGroup', [ReportCustomerGroupController::class, 'index'])->name('reports.CustomerGroup');
        Route::get('/reports/CustomerGroup/get-data', [ReportCustomerGroupController::class, 'getData'])->name('reports.CustomerGroup.get-data');
        Route::get('reports/CustomerGroupLedger/{CustomerGroupLedger}', [ReportCustomerGroupController::class, 'AllCustomerGroupLedgerReports'])->name('reports.CustomerGroupLedger');
        Route::get('reports/CustomerGroupLedger/data/{customerGroupLedgerId}', [ReportCustomerGroupController::class, 'getCustomerGroupLedgerData'])->name('reports.CustomerGroupLedger.data');

        Route::get('reports/ItemGroup', [ReportItemGroupController::class, 'index'])->name('reports.ItemGroup');
        Route::get('/reports/ItemGroup/get-data', [ReportItemGroupController::class, 'getData'])->name('reports.ItemGroup.get-data');
        Route::get('reports/ItemGroupLedger/{ItemGroupLedger}', [ReportItemGroupController::class, 'AllItemGroupLedgerReports'])->name('reports.ItemGroupLedger');
        Route::get('/reports/ItemGroup/ItemGroupLedger/{itemGroupLedgerId}/get-data', [ReportItemGroupController::class, 'ledgergetData'])->name('reports.ItemGroupLedger.get-data');

        Route::get('reports/BalanceSheet', [ReportBalanceSheetController::class, 'index'])->name('reports.BalanceSheet');
        Route::get('/reports/BalanceSheet/get-data', [ReportBalanceSheetController::class, 'getData'])->name('reports.BalanceSheet.get-data');
        Route::get('/reports/BalanceAssetSheet/get-data', [ReportBalanceSheetController::class, 'getAssetData'])->name('reports.BalanceAssetSheet.get-data');
        Route::get('/reports/BalanceAssetSheetItem/get-data', [ReportBalanceSheetController::class, 'getAssetItemData'])->name('reports.BalanceAssetSheetItem.get-data');

        Route::get('reports/BalanceSheetProfitLoss', [ReportBalanceSheetProfitLossController::class, 'index'])->name('reports.BalanceSheetProfitLoss');
        Route::get('/reports/BalanceSheetProfitLoss/get-data', [ReportBalanceSheetProfitLossController::class, 'getData'])->name('reports.BalanceSheetProfitLoss.get-data');
        Route::get('/reports/BalanceSheetProfitLossExpense/get-data', [ReportBalanceSheetProfitLossController::class, 'getExpenseData'])->name('reports.BalanceSheetProfitLossExpense.get-data');
        Route::get('/reports/BalanceSheetProfitLossClosingStock/get-data', [ReportBalanceSheetProfitLossController::class, 'getClosingStockData'])->name('reports.BalanceSheetProfitLossClosingStock.get-data');

        Route::get('reports/BalanceSheetLiability/{Liability}', [ReportBalanceSheetLiabilityController::class, 'AllLiabilityReports'])->name('reports.BalanceSheet.Liability');
        Route::get('reports/BalanceSheetLiabilityDebitCredit/data/{LiabilityId}', [ReportBalanceSheetLiabilityController::class, 'getLiabilityData'])->name('reports.BalanceSheetLiability.get-data');

        Route::get('reports/BalanceSheetAssetStock', [ReportBalanceSheetAssetStockController::class, 'index'])->name('reports.BalanceSheetAssetStock');
        Route::get('/reports/BalanceSheetAssetStock/get-data', [ReportBalanceSheetAssetStockController::class, 'getData'])->name('reports.BalanceSheetAssetStock.get-data');

        Route::get('reports/cancelled', [ReportCancelledController::class, 'index'])->name('reports.cancelled');
        Route::get('/cancelled/get-data', [ReportCancelledController::class, 'getData'])->name('cancelled.get-data');

        Route::get('reports/optional', [ReportOptionalController::class, 'index'])->name('reports.optional');
        Route::get('/optional/get-data', [ReportOptionalController::class, 'getData'])->name('optional.get-data');

        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/get-data', [SalesController::class, 'getData'])->name('sales.get-data');
        Route::get('sales/Item/{SaleItem}', [SalesController::class, 'AllSaleItemReports'])->name('sales.items');
        Route::get('sales/SaleItem/data/{SaleItemId}', [SalesController::class, 'getSaleItemData'])->name('sales.SaleItem.data');

        Route::get('/columnar', [ColumnarController::class, 'index'])->name('columnar.index');
        Route::get('/columnar/get-data', [ColumnarController::class, 'getData'])->name('columnar.get-data');

        Route::get('/BankReconciliation', [BankReconciliationController::class, 'index'])->name('BankReconciliation.index');
        Route::post('/BankReconciliation/import', [BankReconciliationController::class, 'import'])->name('BankReconciliation.import');
        Route::get('/BankReconciliation/get-data', [BankReconciliationController::class, 'getData'])->name('BankReconciliation.get-data');

        Route::post('/upload-pdf', [BankReconciliationController::class, 'uploadPdf']);


        Route::resource('/settings', SettingController::class);
        Route::post('/settings/license', [SettingController::class, 'saveLicense'])->name('settings.license.save');


        Route::resource('employees', EmployeeController::class);
        Route::get('/employees/employees/get-data', [EmployeeController::class, 'getData'])->name('employees.get-data');
        Route::get('/employees/employees/add', [EmployeeController::class, 'add'])->name('employees.add');
        Route::post('/employees/save', [EmployeeController::class, 'saveEmployee'])->name('employees.save');
    });

    Route::group(['middleware' => 'checkAdminRoleAndStatus'], function () {

        Route::resource('users', UserController::class);
        Route::post('/update-user-status', [UserController::class, 'updateStatus'])->name('update.user.status');
        
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{users}/get-data', [UserController::class, 'getData'])->name('users-company.get-data');

        Route::post('/users/company/delete', [UserController::class, 'deleteCompany'])->name('users-company.delete');

        // Route::resource('/settings', SettingController::class);
        // Route::post('/settings/license', [SettingController::class, 'saveLicense'])->name('settings.license.save');

    });

    //  JET STREAM
    require __DIR__ . '/auth.php';
});



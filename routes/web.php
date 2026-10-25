<?php

use App\Http\Controllers\{AccountController, AuditLogController, AttendanceController, BackupController, BranchController, CurrencyController, CustomerExchangeController, DashboardController, DesignationController, EmployeeController, ExchangeRateController, ExpenseTypeController, FxRateController, IncomeSourceController, JournalApprovalController, LiabilityTypeController, MonitoringController, PayrollController, PermissionController, ProfileController, RemittanceController, RoleController, SettingController, ShareholderController, TransactionController, UserController};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\FinancialReports\AccountStatementController;
use App\Http\Controllers\FinancialReports\BalanceSheetController;
use App\Http\Controllers\FinancialReports\TrialBalanceController;
use App\Http\Controllers\FinancialReports\ProfitLossController;
use App\Http\Controllers\FinancialReports\ExchangeReportController;
use App\Http\Controllers\FinancialReports\AuctionReportController;
use App\Http\Controllers\FinancialReports\RemittanceReportController;
use App\Http\Controllers\FinancialReports\CommissionReportController;
use App\Http\Controllers\FinancialReports\CustomerReportController;
use App\Http\Controllers\Monitoring\DeletedTransactionController;
use App\Http\Controllers\FinancialReports\OpenFxPositionController;

use App\Http\Controllers\Accounting\{
    CapitalController,
    AssetController,
    LiabilityController,
    ExpenseController,
    DepreciationController,
    AmortizationController,
    AuctionController,
    ExpensePaymentController,
    LiabilityPaymentController
};

Route::get('lang/{lang}', function (string $lang) {
    abort_unless(in_array($lang, ['en', 'fa', 'ps'], true), 404);
    session(['locale' => $lang]);
    return redirect()->back();
})->name('lang.switch');
Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});


Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::prefix('profile')->name('profile.')->middleware('auth')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        Route::post('/refresh-system', [ProfileController::class, 'refreshSystem'])->middleware('role:Super Admin')->name('refresh-system');
    });

    // Accounts
    Route::prefix('accounts')->name('accounts.')->middleware(['auth', 'role:Super Admin|Admin|Branch'])->group(function () {
        // All Customers
        Route::prefix('all-customers')->name('all_customers.')->group(function () {
            Route::get('/', [AccountController::class, 'allCustomers'])->name('index');
            Route::get('/data', [AccountController::class, 'allCustomersData'])->name('data');
            Route::get('/export', [AccountController::class, 'allCustomersExport'])->name('export');
        });
        // Regular Customers
        Route::prefix('regular-customers')->name('regular_customers.')->group(function () {
            Route::get('/', [AccountController::class, 'regularCustomers'])->name('index');
            Route::get('/data', [AccountController::class, 'regularCustomersData'])->name('data');
            Route::get('/count', [AccountController::class, 'countRegularCustomers'])->name('count');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}', [AccountController::class, 'show'])->name('show');
            Route::get('{id}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });

        // Corporate Customers
        Route::prefix('corporate-customers')->name('corporate_customers.')->group(function () {
            Route::get('/', [AccountController::class, 'corporateCustomers'])->name('index');
            Route::get('/data', [AccountController::class, 'corporateCustomersData'])->name('data');
            Route::get('/count', [AccountController::class, 'countCorporateCustomers'])->name('count');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}', [AccountController::class, 'show'])->name('show');
            Route::get('{id}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });

        // Temporary Customers
        Route::prefix('temporary-customers')->name('temporary_customers.')->group(function () {
            Route::get('/', [AccountController::class, 'temporaryCustomers'])->name('index');
            Route::get('/data', [AccountController::class, 'temporaryCustomersData'])->name('data');
            Route::get('/count', [AccountController::class, 'countTemporaryCustomers'])->name('count');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}', [AccountController::class, 'show'])->name('show');
            Route::get('{id}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('street-vendors')->name('street_vendors.')->group(function () {
            Route::get('/', [AccountController::class, 'streetVendors'])->name('index');
            Route::get('/data', [AccountController::class, 'streetVendorsData'])->name('data');
            Route::get('/count', [AccountController::class, 'countstreetVendors'])->name('count');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}', [AccountController::class, 'show'])->name('show');
            Route::get('{id}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });

        // Branches
        Route::prefix('branches-accounts')->name('branches_accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'allBranches'])->name('index');
            Route::get('/data', [AccountController::class, 'allBranchesData'])->name('data');
        });

        // Banks
        Route::prefix('banks')->name('banks.')->group(function () {
            Route::get('/', [AccountController::class, 'allBanks'])->name('index');
            Route::get('/data', [AccountController::class, 'allBanksData'])->name('data');
            Route::get('/count', [AccountController::class, 'countBanks'])->name('count');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('{id}', [AccountController::class, 'show'])->name('show');
            Route::get('{id}/edit', [AccountController::class, 'edit'])->name('edit');

            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{id}', [AccountController::class, 'destroy'])->name('destroy');
        });
    });

    //Configuration
    Route::prefix('configuration')->name('configuration.')->middleware('auth')->group(function () {
        // Settings
        Route::prefix('settings')->name('settings.')->middleware('role:Super Admin')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index');
            Route::put('/', [SettingController::class, 'update'])->name('update');
        });

        //Users
        Route::prefix('users')->name('users.')->middleware('auth')->middleware('role:Super Admin')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/data', [UserController::class, 'data'])->name('data');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        //Roles
        Route::prefix('roles')->name('roles.')->middleware(['auth'])->middleware('role:Super Admin')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::get('/data', [RoleController::class, 'data'])->name('data');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('/{id}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
        });

        //Permissions
        Route::prefix('permissions')->name('permissions.')->middleware(['auth'])->middleware('role:Super Admin')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])->name('index');
            Route::get('/data', [PermissionController::class, 'data'])->name('data');
            Route::post('/', [PermissionController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
            Route::put('/{id}', [PermissionController::class, 'update'])->name('permissions.update');
            Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
        });

        //Branches
        Route::prefix('branches')->name('branches.')->middleware('role:Super Admin|Admin')->group(function () {
            Route::get('/', [BranchController::class, 'index'])->name('index');
            Route::get('/data', [BranchController::class, 'data'])->name('data');
            Route::post('/', [BranchController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [BranchController::class, 'edit'])->name('edit');
            Route::put('/{id}', [BranchController::class, 'update'])->name('update');
            Route::delete('/{id}', [BranchController::class, 'destroy'])->name('destroy');
        });

        //Currency
        Route::prefix('currencies')->name('currencies.')->middleware(['auth', 'role:Super Admin'])->group(function () {
            Route::get('/', [CurrencyController::class, 'index'])->name('index');
            Route::post('/', [CurrencyController::class, 'store'])->name('store');
            Route::get('/data', [CurrencyController::class, 'data'])->name('data');
            Route::get('/{currency}/edit', [CurrencyController::class, 'edit'])->name('edit');
            Route::put('/{currency}', [CurrencyController::class, 'update'])->name('update');
            Route::delete('/{currency}', [CurrencyController::class, 'destroy'])->name('destroy');
            Route::post('/{currency}/toggle', [CurrencyController::class, 'toggleActive'])->name('currencies.toggle');
        });

        //Designations
        Route::prefix('designations')->name('designations.')->middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
            Route::get('/', [DesignationController::class, 'index'])->name('index');
            Route::get('/data', [DesignationController::class, 'data'])->name('data');
            Route::post('/', [DesignationController::class, 'store'])->name('store');
            Route::put('/{designation}', [DesignationController::class, 'store'])->name('update');
            Route::get('/{designation}/edit', [DesignationController::class, 'edit'])->name('edit');
            Route::delete('/{designation}', [DesignationController::class, 'destroy'])->name('destroy');
        });

        //Expense Types
        Route::prefix('expense-types')->name('expense-types.')->middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
            Route::get('/', [ExpenseTypeController::class, 'index'])->name('index');
            Route::get('/data', [ExpenseTypeController::class, 'data'])->name('data');
            Route::post('/', [ExpenseTypeController::class, 'store'])->name('store');
            Route::put('/{expenseType}', [ExpenseTypeController::class, 'store'])->name('update');
            Route::get('/{expenseType}/edit', [ExpenseTypeController::class, 'edit'])->name('edit');
            Route::delete('/{expenseType}', [ExpenseTypeController::class, 'destroy'])->name('destroy');
        });

        //Liability Types
        Route::prefix('liability-types')->name('liability-types.')->middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
            Route::get('/', [LiabilityTypeController::class, 'index'])->name('index');
            Route::get('/data', [LiabilityTypeController::class, 'data'])->name('data');
            Route::post('/', [LiabilityTypeController::class, 'store'])->name('store');
            Route::put('/{liabilityType}', [LiabilityTypeController::class, 'store'])->name('update');
            Route::get('/{liabilityType}/edit', [LiabilityTypeController::class, 'edit'])->name('edit');
            Route::delete('/{liabilityType}', [LiabilityTypeController::class, 'destroy'])->name('destroy');
        });

        //Income Source
        Route::prefix('income-sources')->name('income-sources.')->middleware('auth')->group(function () {
            Route::get('/', [IncomeSourceController::class, 'index'])->name('index');
            Route::get('/data', [IncomeSourceController::class, 'data'])->name('data');
            Route::post('/', [IncomeSourceController::class, 'store'])->name('store');
            Route::put('/{incomeSource}', [IncomeSourceController::class, 'store'])->name('update');
            Route::get('/{incomeSource}/edit', [IncomeSourceController::class, 'edit'])->name('edit');
            Route::delete('/{incomeSource}', [IncomeSourceController::class, 'destroy'])->name('destroy');
        });

        //Backup
        Route::prefix('backups')->name('backups.')->middleware('role:Super Admin')->group(function () { Route::get('/', [BackupController::class, 'index'])->name('index'); Route::get('/download', [BackupController::class, 'download'])->name('download'); Route::post('/restore', [BackupController::class, 'restore'])->name('restore'); Route::post('/restore/apply', [BackupController::class, 'applyRestore'])->name('restore.apply'); });

        //Audit Log
        Route::prefix('auditlogs')->name('auditlogs.')->middleware('auth')->group(function () {
            Route::get('/', [AuditLogController::class, 'index'])->name('index');
            Route::get('/data', [AuditLogController::class, 'data'])->name('data');
        });
    });

    // Human Resource
    Route::prefix('hr')->name('hr.')->group(function () {
        //Shareholders
        Route::prefix('shareholders')->name('shareholders.')->middleware('auth')->group(function () {
            Route::get('/', [ShareholderController::class, 'index'])->name('index');
            Route::get('/data', [ShareholderController::class, 'data'])->name('data');
            Route::post('/', [ShareholderController::class, 'store'])->name('store');
            Route::put('/{shareholder}', [ShareholderController::class, 'store'])->name('update');
            Route::get('/{shareholder}/edit', [ShareholderController::class, 'edit'])->name('edit');
            Route::delete('/{shareholder}', [ShareholderController::class, 'destroy'])->name('destroy');
        });

        //Employees
        Route::prefix('employees')->name('employees.')->middleware('auth')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::get('/data', [EmployeeController::class, 'data'])->name('data');
            Route::post('/', [EmployeeController::class, 'store'])->name('store');
            Route::put('/{employee}', [EmployeeController::class, 'store'])->name('update');
            Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
            Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
        });
    });

    // Journal
    Route::prefix('journal')->name('journal.')->group(function () {
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionController::class, 'index'])->name('index');
            Route::get('/account-ledgers', [TransactionController::class, 'accountLedgers'])->name('account-ledgers');
            Route::post('/account-ledgers', [TransactionController::class, 'storeAccountLedgerEntry'])->name('account-ledgers.store');
            Route::post('/{linked}/reverse', [\App\Http\Controllers\JournalReversalController::class, 'store'])->name('reverse');
            Route::get('/data', [TransactionController::class, 'data'])->name('data');
            Route::get('/summary', [TransactionController::class, 'summary'])->name('summary');
            Route::get('/export', [TransactionController::class, 'export'])->name('export');
        });
        Route::get('/approvals', [JournalApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{linked}/approve', [JournalApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{linked}/reject', [JournalApprovalController::class, 'reject'])->name('approvals.reject');
    });

    Route::prefix('accounting/branch-capital')->name('accounting.branch-capital.')->controller(\App\Http\Controllers\Accounting\BranchCapitalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{branchCapital}', 'show')->name('show');
        Route::post('/{branchCapital}/repay', 'repay')->name('repay');
    });

    // Accounting
    Route::prefix('accounting')->name('accounting.')->group(function () {
        // Capital
        Route::prefix('capital')->name('capital.')->group(function () {
            Route::get('/', [CapitalController::class, 'index'])->name('index');
            Route::get('/data', [CapitalController::class, 'data'])->name('data');

            Route::post('/', [CapitalController::class, 'store'])->name('store');
            Route::get('/{capital}', [CapitalController::class, 'show']);
            Route::put('/{capital}', [CapitalController::class, 'update'])->name('update');
            Route::delete('/{capital}', [CapitalController::class, 'destroy'])->name('destroy');
        });

        // Assets
        Route::prefix('assets')->name('assets.')->group(function () {
            Route::get('/', [AssetController::class, 'index'])->name('index');
            Route::get('/data', [AssetController::class, 'data'])->name('data');

            Route::post('/', [AssetController::class, 'store'])->name('store');
            Route::get('/{asset}', [AssetController::class, 'show']);
            Route::put('/{asset}', [AssetController::class, 'update'])->name('update');
            Route::delete('/{asset}', [AssetController::class, 'destroy'])->name('destroy');
        });

        // Liabilities
        Route::prefix('liabilities')->name('liabilities.')->controller(LiabilityController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/data', 'data')->name('data');
            Route::get('/{id}/payments', 'payments')->name('payments');

            Route::get('/summary', 'summary')->name('summary');

            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/{id}/update', 'update')->name('update');
            Route::delete('/{id}', 'destroy')->name('destroy');
        });


        // Liability Payments
        Route::prefix('liability-payments')->name('liability-payments.')->controller(LiabilityPaymentController::class)->group(function () {
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit');
            Route::post('/{id}/update', 'update')->name('update');
            Route::delete('/{id}', 'destroy')->name('destroy');
        });

        // Expenses
        Route::prefix('expenses')->name('expenses.')->controller(ExpenseController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
            Route::post('/store', 'store')->name('store');
            Route::get('/{id}/edit', 'edit')->name('edit'); // <-- used for AJAX Edit
            Route::post('/{id}/update', 'update')->name('update'); // <-- used for AJAX Update
            Route::delete('/{id}', 'destroy')->name('destroy');
        });

        Route::post('expense-payments/store', [ExpensePaymentController::class, 'store'])->name('expense_payments.store');



        // Depreciation
        Route::prefix('depreciations')->name('depreciations.')->group(function () {
            Route::get('/', [DepreciationController::class, 'index'])->name('index');
            Route::get('/data', [DepreciationController::class, 'data'])->name('data');

            Route::post('/', [DepreciationController::class, 'store'])->name('store');
            Route::get('/{depreciation}', [DepreciationController::class, 'show']);
            Route::put('/{depreciation}', [DepreciationController::class, 'update'])->name('update');
            Route::delete('/{depreciation}', [DepreciationController::class, 'destroy'])->name('destroy');
        });

        // Amortization
        Route::prefix('amortizations')->name('amortizations.')->group(function () {
            Route::get('/', [AmortizationController::class, 'index'])->name('index');
            Route::get('/data', [AmortizationController::class, 'data'])->name('data');

            Route::post('/', [AmortizationController::class, 'store'])->name('store');
            Route::get('/{amortization}', [AmortizationController::class, 'show']);
            Route::put('/{amortization}', [AmortizationController::class, 'update'])->name('update');
            Route::delete('/{amortization}', [AmortizationController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('exchange')->name('exchange.')->group(function () {
        Route::prefix('auctions')->name('auctions.')->group(function () {

            // List + form page
            Route::get('/',            [AuctionController::class, 'index'])
                ->name('index');

            // DataTables JSON
            Route::get('/data',        [AuctionController::class, 'data'])
                ->name('data');

            // Create
            Route::post('/',           [AuctionController::class, 'store'])
                ->name('store');

            // Show single (for edit modal/form fill)
            Route::get('/{auction}',   [AuctionController::class, 'show'])
                ->name('show')
                ->whereNumber('auction');

            // Update
            Route::put('/{auction}',   [AuctionController::class, 'update'])
                ->name('update')
                ->whereNumber('auction');

            // Delete
            Route::delete('/{auction}', [AuctionController::class, 'destroy'])
                ->name('destroy')
                ->whereNumber('auction');
        });
    });

    Route::get('financial-reports/historical-integrity/export', [\App\Http\Controllers\HistoricalIntegrityController::class, 'export'])->name('financial_reports.historical-integrity.export');
    Route::get('financial-reports/historical-integrity', [\App\Http\Controllers\HistoricalIntegrityController::class, 'index'])->name('financial_reports.historical-integrity.index');

    Route::prefix('fx')->name('fx.')->group(function () {
        Route::get('/rates/meta',  [FxRateController::class, 'meta'])->name('rates.meta');   // defaults for date/branch
        Route::post('/rates/open', [FxRateController::class, 'setOpen'])->name('rates.open');
        Route::post('/rates/close', [FxRateController::class, 'setClose'])->name('rates.close'); // also builds snapshot
    });

    Route::get('exchange/customer-details/{account}', [CustomerExchangeController::class, 'customerDetails'])->name('exchange.customers.details');
    Route::get('exchange/customer-id/{account}', [CustomerExchangeController::class, 'customerIdCard'])->name('exchange.customers.id-card');

    // Customer Exchange and branch rates
    Route::get('exchange/customers', [CustomerExchangeController::class, 'index'])->name('exchange.customers.index');
    Route::get('exchange/customers/quote', [CustomerExchangeController::class, 'quote'])->name('exchange.customers.quote');
    Route::post('exchange/customers', [CustomerExchangeController::class, 'store'])->name('exchange.customers.store');
    Route::delete('exchange/customers/bulk', [CustomerExchangeController::class, 'bulkDestroy'])->name('exchange.customers.bulk-destroy');
    Route::post('exchange/customers/{exchange}/reverse', [CustomerExchangeController::class, 'reverse'])->name('exchange.customers.reverse');
    Route::delete('exchange/customers/{exchange}', [CustomerExchangeController::class, 'destroy'])->name('exchange.customers.destroy');
    Route::get('exchange/rates', [ExchangeRateController::class, 'index'])->name('exchange.rates.index');
    Route::post('exchange/rates', [ExchangeRateController::class, 'store'])->name('exchange.rates.store');

    // Remittances
    Route::prefix('remittances')->name('remittances.')->group(function () {
        Route::get('/', [RemittanceController::class, 'index'])->name('index');
        Route::get('/create', [RemittanceController::class, 'create'])->name('create');
        Route::get('/customers', [RemittanceController::class, 'customers'])->name('customers');
        Route::get('/{remittance}', [RemittanceController::class, 'show'])->name('show')->whereNumber('remittance');
        Route::post('/{remittance}/legs/{leg}/settle', [RemittanceController::class, 'settle'])->name('settle');
        Route::get('/direction/{status}', [RemittanceController::class, 'index'])->name('direction')->whereIn('status',['outgoing','incoming','transfer']);
        Route::get('/status/{status}', [RemittanceController::class, 'index'])->name('status')->whereIn('status',['pending','rejected','approved','completed']);
        Route::post('/', [RemittanceController::class, 'store'])->name('store');
        Route::post('/{remittance}/approve', [RemittanceController::class, 'approve'])->name('approve');
        Route::post('/{remittance}/reject', [RemittanceController::class, 'reject'])->name('reject');
        Route::post('/{remittance}/complete', [RemittanceController::class, 'complete'])->name('complete');
        Route::get('/{remittance}/slip', [RemittanceController::class, 'slip'])->name('slip');
    });

    // Attendance and payroll
    Route::get('hr/attendance', [AttendanceController::class, 'index'])->name('hr.attendance.index');
    Route::post('hr/attendance', [AttendanceController::class, 'store'])->name('hr.attendance.store');
    Route::get('hr/payroll', [PayrollController::class, 'index'])->name('hr.payroll.index');
    Route::post('hr/payroll/generate', [PayrollController::class, 'generate'])->name('hr.payroll.generate');
    Route::get('hr/payroll/{payroll}', [PayrollController::class, 'show'])->name('hr.payroll.show');
    Route::put('hr/payroll-items/{item}', [PayrollController::class, 'updateItem'])->name('hr.payroll.items.update');
    Route::post('hr/payroll/{payroll}/post', [PayrollController::class, 'post'])->name('hr.payroll.post');

    Route::get('monitoring/deleted-transactions', [DeletedTransactionController::class, 'index'])->name('monitoring.deleted-transactions');
    Route::get('monitoring/modified-transactions', [\App\Http\Controllers\Monitoring\ModifiedTransactionController::class, 'index'])->name('monitoring.modified-transactions');

    Route::get('monitoring/reviews', [\App\Http\Controllers\Monitoring\ReportController::class, 'history'])->name('monitoring.reviews');
    Route::get('monitoring/reports/{report}', [\App\Http\Controllers\Monitoring\ReportController::class, 'index'])->name('monitoring.report');
    Route::post('monitoring/reports/{report}/review', [\App\Http\Controllers\Monitoring\ReportController::class, 'review'])->name('monitoring.report.review');

    // DAB monitoring
    Route::get('monitoring/approvals', [MonitoringController::class, 'approvals'])->name('monitoring.approvals');
    Route::get('monitoring/lctr', [MonitoringController::class, 'lctr'])->name('monitoring.lctr');
    Route::get('monitoring/suspicious', [MonitoringController::class, 'suspicious'])->name('monitoring.suspicious');
    Route::post('monitoring/{transaction}/flag', [MonitoringController::class, 'flag'])->name('monitoring.flag');

    // Financial Reports
    Route::prefix('financial-reports')->name('financial_reports.')->group(function () {
        // Default route (Account Statement)
        Route::get('account-statement', [AccountStatementController::class, 'index'])->name('account-statement.index');

        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])->name('trial-balance.index');
        Route::get('profit-loss', [ProfitLossController::class, 'index'])->name('profit-loss.index');
        Route::get('open-fx-position', [OpenFxPositionController::class, 'index'])->name('open-fx-position.index');

        Route::get('exchange', [ExchangeReportController::class, 'index'])->name('exchange.index');
        Route::get('customers', [CustomerReportController::class, 'index'])->name('customers.index');
        Route::get('auctions', [AuctionReportController::class, 'index'])->name('auctions.index');
        Route::get('remittances', [RemittanceReportController::class, 'index'])->name('remittances.index');
        Route::get('commissions', [CommissionReportController::class, 'index'])->name('commissions.index');

        Route::get('deleted-transactions', fn (\Illuminate\Http\Request $request) => redirect()->route('monitoring.deleted-transactions', $request->query()))->name('deleted-transactions.index');
    });
});

require __DIR__ . '/auth.php';

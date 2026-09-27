<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DividendController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlAccountController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsProductController;
use App\Http\Controllers\SharesAccountController;
use App\Http\Controllers\SharesProductController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| SECURITY: only /auth/login and the M-Pesa callback are public.
| Everything else sits behind 'auth:sanctum', which validates the
| bearer token issued at login on EVERY request. Previously all of
| these were wide open to anyone who knew the URL.
|
| The M-Pesa callback stays public deliberately -- Safaricom's servers
| call it and cannot present a user token. In production this should be
| additionally protected by IP-allowlisting Safaricom's ranges.
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/mpesa/stk-callback', [MpesaController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/users/{userId}/change-password', [AuthController::class, 'changePassword']);

    Route::get('/branches', [BranchController::class, 'index']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);

    Route::get('/wallets/client/{clientId}', [WalletController::class, 'show']);
    Route::post('/wallets/{walletId}/deposit', [WalletController::class, 'deposit']);
    Route::post('/wallets/{walletId}/withdraw', [WalletController::class, 'withdraw']);
    Route::post('/wallets/transfer', [WalletController::class, 'transfer']);

    Route::get('/savings-products', [SavingsProductController::class, 'index']);
    Route::get('/savings-accounts', [SavingsAccountController::class, 'index']);
    Route::post('/savings-accounts', [SavingsAccountController::class, 'store']);
    Route::post('/savings-accounts/{id}/deposit', [SavingsAccountController::class, 'deposit']);
    Route::post('/savings-accounts/{id}/close', [SavingsAccountController::class, 'close']);

    Route::get('/shares-products', [SharesProductController::class, 'index']);
    Route::get('/shares-accounts', [SharesAccountController::class, 'index']);
    Route::post('/shares-accounts', [SharesAccountController::class, 'store']);
    Route::post('/shares-accounts/{id}/deposit', [SharesAccountController::class, 'deposit']);
    Route::post('/shares-accounts/{id}/close', [SharesAccountController::class, 'close']);

    Route::get('/loan-products', [LoanProductController::class, 'index']);
    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans/{loanId}/guarantors/{guarantorId}/approve', [LoanController::class, 'approveGuarantor']);
    Route::post('/loans/{id}/request-approval', [LoanController::class, 'requestApproval']);
    Route::post('/loans/{id}/disburse', [LoanController::class, 'disburse']);
    Route::post('/loans/{loanId}/mpesa/initiate-repayment', [MpesaController::class, 'initiateRepayment']);

    Route::get('/gl-accounts', [GlAccountController::class, 'index']);
    Route::post('/gl-accounts', [GlAccountController::class, 'store']);
    Route::get('/journal-entries', [JournalEntryController::class, 'index']);
    Route::post('/journal-entries', [JournalEntryController::class, 'store']);
    Route::get('/journal-entries/{id}', [JournalEntryController::class, 'show']);

    Route::get('/dividends', [DividendController::class, 'index']);
    Route::get('/dividends/{id}', [DividendController::class, 'show']);
    Route::post('/dividends/declare', [DividendController::class, 'declare']);
    Route::post('/dividends/{id}/pay', [DividendController::class, 'pay']);

    Route::get('/budget-allocations', [BudgetController::class, 'index']);
    Route::post('/budget-allocations', [BudgetController::class, 'store']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);

    Route::get('/communication/campaigns', [CommunicationController::class, 'campaigns']);
    Route::post('/communication/campaigns', [CommunicationController::class, 'storeCampaign']);
    Route::get('/communication/logs', [CommunicationController::class, 'logs']);
    Route::post('/communication/send', [CommunicationController::class, 'send']);

    Route::get('/reports/loan-portfolio', [ReportController::class, 'loanPortfolio']);
    Route::get('/reports/loan-arrears', [ReportController::class, 'loanArrears']);
    Route::get('/reports/disbursements', [ReportController::class, 'disbursements']);
    Route::get('/reports/savings-balances', [ReportController::class, 'savingsBalances']);

    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::post('/approvals/{id}/decide', [ApprovalController::class, 'decide']);
});

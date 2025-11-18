<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Broadcasting auth routes (must be before other middleware)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::post('/validate-receiver', [TransactionController::class, 'validateReceiver']);
        Route::post('/', [TransactionController::class, 'store']);
        Route::get('/stats', [TransactionController::class, 'stats']); // Must be before /{uuid}
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{uuid}', [TransactionController::class, 'show']);
    });

    // User balance
    Route::get('/balance', function () {
        return response()->json([
            'balance' => auth()->user()->balance,
            'balance_dollars' => auth()->user()->balance_in_dollars,
        ]);
    });
});

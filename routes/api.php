<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectionPointController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\RedemptionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WasteTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::get('/jenis-sampah', [WasteTypeController::class, 'index']);
Route::get('/jenis-sampah/{jenis_sampah}', [WasteTypeController::class, 'show']);
Route::get('/hadiah', [RewardController::class, 'index']);
Route::get('/hadiah/{hadiah}', [RewardController::class, 'show']);
Route::get('/titik-setor', [CollectionPointController::class, 'index']);
Route::get('/titik-setor/{titik_setor}', [CollectionPointController::class, 'show']);

Route::middleware('api.token')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/jenis-sampah', [WasteTypeController::class, 'store']);
        Route::put('/jenis-sampah/{jenis_sampah}', [WasteTypeController::class, 'update']);
        Route::patch('/jenis-sampah/{jenis_sampah}', [WasteTypeController::class, 'update']);
        Route::delete('/jenis-sampah/{jenis_sampah}', [WasteTypeController::class, 'destroy']);

        Route::post('/hadiah', [RewardController::class, 'store']);
        Route::put('/hadiah/{hadiah}', [RewardController::class, 'update']);
        Route::patch('/hadiah/{hadiah}', [RewardController::class, 'update']);
        Route::delete('/hadiah/{hadiah}', [RewardController::class, 'destroy']);

        Route::post('/titik-setor', [CollectionPointController::class, 'store']);
        Route::put('/titik-setor/{titik_setor}', [CollectionPointController::class, 'update']);
        Route::patch('/titik-setor/{titik_setor}', [CollectionPointController::class, 'update']);
        Route::delete('/titik-setor/{titik_setor}', [CollectionPointController::class, 'destroy']);

        Route::get('/nasabah', [MemberController::class, 'index']);
        Route::patch('/nasabah/{nasabah}/role', [MemberController::class, 'updateRole']);
        Route::get('/laporan/bulanan', [ReportController::class, 'monthly']);
    });

    Route::get('/nasabah/{nasabah}', [MemberController::class, 'show']);
    Route::patch('/nasabah/{nasabah}', [MemberController::class, 'update']);
    Route::get('/nasabah/{nasabah}/saldo', [MemberController::class, 'balance']);
    Route::get('/nasabah/{nasabah}/transaksi', [MemberController::class, 'transactions']);

    Route::get('/setoran', [DepositController::class, 'index']);
    Route::get('/setoran/{setoran}', [DepositController::class, 'show']);
    Route::post('/setoran', [DepositController::class, 'store'])->middleware('role:petugas,admin');

    Route::get('/transaksi', [TransactionController::class, 'index']);
    Route::get('/transaksi/{transaksi}', [TransactionController::class, 'show']);

    Route::get('/penukaran', [RedemptionController::class, 'index']);
    Route::get('/penukaran/{penukaran}', [RedemptionController::class, 'show']);
    Route::post('/penukaran', [RedemptionController::class, 'store'])->middleware('role:nasabah');
});

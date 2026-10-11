<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}/branches', [BranchController::class, 'index']);
        Route::post('/companies/{company}/branches', [BranchController::class, 'store']);
        Route::get('/companies/{company}/branches/{branch}', [BranchController::class, 'show']);
        Route::put('/companies/{company}/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/companies/{company}/branches/{branch}', [BranchController::class, 'destroy']);
        Route::get('/companies/{company}/categories', [CategoryController::class, 'index']);
        Route::post('/companies/{company}/categories', [CategoryController::class, 'store']);
        Route::get('/companies/{company}/categories/{category}', [CategoryController::class, 'show']);
        Route::put('/companies/{company}/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/companies/{company}/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/companies/{company}/items', [ItemController::class, 'index']);
        Route::post('/companies/{company}/items', [ItemController::class, 'store']);
        Route::get('/companies/{company}/items/{item}', [ItemController::class, 'show']);
        Route::put('/companies/{company}/items/{item}', [ItemController::class, 'update']);
        Route::delete('/companies/{company}/items/{item}', [ItemController::class, 'destroy']);
        Route::put('/companies/{company}/items/{item}/prices/{branch}', [ItemController::class, 'setPrice']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

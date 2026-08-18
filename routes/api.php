<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\PermissionCatalogController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
        });
    });

    Route::middleware(['auth:sanctum', 'clinic.resolve'])->group(function (): void {
        Route::get('clinics/current', [ClinicController::class, 'current'])
            ->middleware('permission:clinics.view');

        Route::get('clinics', [ClinicController::class, 'index'])
            ->middleware('permission:clinics.manage');
        Route::post('clinics', [ClinicController::class, 'store'])
            ->middleware('permission:clinics.manage');
        Route::get('clinics/{clinic}', [ClinicController::class, 'show'])
            ->middleware('permission:clinics.manage');
        Route::put('clinics/{clinic}', [ClinicController::class, 'update'])
            ->middleware('permission:clinics.manage');

        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.create');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view');
        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete');

        Route::get('permissions', [PermissionCatalogController::class, 'permissions'])
            ->middleware('permission:permissions.view');
        Route::get('roles', [PermissionCatalogController::class, 'roles'])
            ->middleware('permission:roles.manage');
    });
});

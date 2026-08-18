<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Catalog\BrandController;
use App\Http\Controllers\Api\V1\Catalog\ProductTypeController;
use App\Http\Controllers\Api\V1\Catalog\UnitOfMeasureController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\PermissionCatalogController;
use App\Http\Controllers\Api\V1\ProductController;
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

        Route::get('product-types', [ProductTypeController::class, 'index'])
            ->middleware('permission:product_types.manage');
        Route::post('product-types', [ProductTypeController::class, 'store'])
            ->middleware('permission:product_types.manage');
        Route::get('product-types/{product_type}', [ProductTypeController::class, 'show'])
            ->middleware('permission:product_types.manage');
        Route::put('product-types/{product_type}', [ProductTypeController::class, 'update'])
            ->middleware('permission:product_types.manage');
        Route::delete('product-types/{product_type}', [ProductTypeController::class, 'destroy'])
            ->middleware('permission:product_types.manage');

        Route::get('brands', [BrandController::class, 'index'])
            ->middleware('permission:brands.manage');
        Route::post('brands', [BrandController::class, 'store'])
            ->middleware('permission:brands.manage');
        Route::get('brands/{brand}', [BrandController::class, 'show'])
            ->middleware('permission:brands.manage');
        Route::put('brands/{brand}', [BrandController::class, 'update'])
            ->middleware('permission:brands.manage');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])
            ->middleware('permission:brands.manage');

        Route::get('units-of-measure', [UnitOfMeasureController::class, 'index'])
            ->middleware('permission:units.manage');
        Route::post('units-of-measure', [UnitOfMeasureController::class, 'store'])
            ->middleware('permission:units.manage');
        Route::get('units-of-measure/{unit_of_measure}', [UnitOfMeasureController::class, 'show'])
            ->middleware('permission:units.manage');
        Route::put('units-of-measure/{unit_of_measure}', [UnitOfMeasureController::class, 'update'])
            ->middleware('permission:units.manage');
        Route::delete('units-of-measure/{unit_of_measure}', [UnitOfMeasureController::class, 'destroy'])
            ->middleware('permission:units.manage');

        Route::get('products', [ProductController::class, 'index'])
            ->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('permission:products.create');
        Route::get('products/{product}', [ProductController::class, 'show'])
            ->middleware('permission:products.view');
        Route::put('products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:products.delete');
        Route::post('products/{product}/stock-movements', [ProductController::class, 'adjustStock'])
            ->middleware('permission:products.adjust_stock');
    });
});

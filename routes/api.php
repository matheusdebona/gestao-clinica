<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\Catalog\BrandController;
use App\Http\Controllers\Api\V1\Catalog\ProductTypeController;
use App\Http\Controllers\Api\V1\Catalog\UnitOfMeasureController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientOriginController;
use App\Http\Controllers\Api\V1\ClinicBrandingController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\Payments\CardBrandController;
use App\Http\Controllers\Api\V1\Payments\CardFeeRuleController;
use App\Http\Controllers\Api\V1\Payments\CardOperatorController;
use App\Http\Controllers\Api\V1\Payments\PaymentMethodController;
use App\Http\Controllers\Api\V1\PermissionCatalogController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProtocolController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\TreatmentController;
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

        Route::get('clinic/branding', [ClinicBrandingController::class, 'show'])
            ->middleware('permission:clinics.branding|clinics.manage');
        Route::put('clinic/branding', [ClinicBrandingController::class, 'update'])
            ->middleware('permission:clinics.branding|clinics.manage');
        Route::post('clinic/branding/logo', [ClinicBrandingController::class, 'uploadLogo'])
            ->middleware('permission:clinics.branding|clinics.manage');
        Route::delete('clinic/branding/logo', [ClinicBrandingController::class, 'deleteLogo'])
            ->middleware('permission:clinics.branding|clinics.manage');

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

        Route::get('protocols', [ProtocolController::class, 'index'])
            ->middleware('permission:protocols.view');
        Route::post('protocols', [ProtocolController::class, 'store'])
            ->middleware('permission:protocols.create');
        Route::get('protocols/{protocol}', [ProtocolController::class, 'show'])
            ->middleware('permission:protocols.view');
        Route::put('protocols/{protocol}', [ProtocolController::class, 'update'])
            ->middleware('permission:protocols.update');
        Route::delete('protocols/{protocol}', [ProtocolController::class, 'destroy'])
            ->middleware('permission:protocols.delete');
        Route::put('protocols/{protocol}/items', [ProtocolController::class, 'syncItems'])
            ->middleware('permission:protocols.update');
        Route::post('protocols/{protocol}/recalculate', [ProtocolController::class, 'recalculate'])
            ->middleware('permission:protocols.update');

        Route::get('clients', [ClientController::class, 'index'])
            ->middleware('permission:clients.view');
        Route::post('clients', [ClientController::class, 'store'])
            ->middleware('permission:clients.create');
        Route::get('clients/{client}', [ClientController::class, 'show'])
            ->middleware('permission:clients.view');
        Route::put('clients/{client}', [ClientController::class, 'update'])
            ->middleware('permission:clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])
            ->middleware('permission:clients.delete');

        Route::get('client-origins', [ClientOriginController::class, 'index'])
            ->middleware('permission:client_origins.manage');
        Route::post('client-origins', [ClientOriginController::class, 'store'])
            ->middleware('permission:client_origins.manage');
        Route::get('client-origins/{client_origin}', [ClientOriginController::class, 'show'])
            ->middleware('permission:client_origins.manage');
        Route::put('client-origins/{client_origin}', [ClientOriginController::class, 'update'])
            ->middleware('permission:client_origins.manage');
        Route::delete('client-origins/{client_origin}', [ClientOriginController::class, 'destroy'])
            ->middleware('permission:client_origins.manage');

        Route::get('campaigns', [CampaignController::class, 'index'])
            ->middleware('permission:campaigns.manage');
        Route::post('campaigns', [CampaignController::class, 'store'])
            ->middleware('permission:campaigns.manage');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])
            ->middleware('permission:campaigns.manage');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])
            ->middleware('permission:campaigns.manage');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])
            ->middleware('permission:campaigns.manage');

        Route::get('payment-methods', [PaymentMethodController::class, 'index'])
            ->middleware('permission:payment_methods.manage');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])
            ->middleware('permission:payment_methods.manage');
        Route::get('payment-methods/{payment_method}', [PaymentMethodController::class, 'show'])
            ->middleware('permission:payment_methods.manage');
        Route::put('payment-methods/{payment_method}', [PaymentMethodController::class, 'update'])
            ->middleware('permission:payment_methods.manage');
        Route::delete('payment-methods/{payment_method}', [PaymentMethodController::class, 'destroy'])
            ->middleware('permission:payment_methods.manage');

        Route::get('card-operators', [CardOperatorController::class, 'index'])
            ->middleware('permission:card_operators.manage');
        Route::post('card-operators', [CardOperatorController::class, 'store'])
            ->middleware('permission:card_operators.manage');
        Route::get('card-operators/{card_operator}', [CardOperatorController::class, 'show'])
            ->middleware('permission:card_operators.manage');
        Route::put('card-operators/{card_operator}', [CardOperatorController::class, 'update'])
            ->middleware('permission:card_operators.manage');
        Route::delete('card-operators/{card_operator}', [CardOperatorController::class, 'destroy'])
            ->middleware('permission:card_operators.manage');

        Route::get('card-brands', [CardBrandController::class, 'index'])
            ->middleware('permission:card_brands.manage');
        Route::post('card-brands', [CardBrandController::class, 'store'])
            ->middleware('permission:card_brands.manage');
        Route::get('card-brands/{card_brand}', [CardBrandController::class, 'show'])
            ->middleware('permission:card_brands.manage');
        Route::put('card-brands/{card_brand}', [CardBrandController::class, 'update'])
            ->middleware('permission:card_brands.manage');
        Route::delete('card-brands/{card_brand}', [CardBrandController::class, 'destroy'])
            ->middleware('permission:card_brands.manage');

        Route::get('card-fee-rules', [CardFeeRuleController::class, 'index'])
            ->middleware('permission:card_fees.manage');
        Route::post('card-fee-rules', [CardFeeRuleController::class, 'store'])
            ->middleware('permission:card_fees.manage');
        Route::get('card-fee-rules/{card_fee_rule}', [CardFeeRuleController::class, 'show'])
            ->middleware('permission:card_fees.manage');
        Route::put('card-fee-rules/{card_fee_rule}', [CardFeeRuleController::class, 'update'])
            ->middleware('permission:card_fees.manage');
        Route::delete('card-fee-rules/{card_fee_rule}', [CardFeeRuleController::class, 'destroy'])
            ->middleware('permission:card_fees.manage');

        Route::get('sales', [SaleController::class, 'index'])
            ->middleware('permission:sales.view');
        Route::post('sales', [SaleController::class, 'store'])
            ->middleware('permission:sales.create');
        Route::get('sales/{sale}', [SaleController::class, 'show'])
            ->middleware('permission:sales.view');
        Route::patch('sales/{sale}', [SaleController::class, 'update'])
            ->middleware('permission:sales.update');
        Route::put('sales/{sale}/items', [SaleController::class, 'syncItems'])
            ->middleware('permission:sales.update');
        Route::post('sales/{sale}/apply-protocol', [SaleController::class, 'applyProtocol'])
            ->middleware('permission:sales.update');
        Route::put('sales/{sale}/payments', [SaleController::class, 'syncPayments'])
            ->middleware('permission:sales.update');
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm'])
            ->middleware('permission:sales.confirm');
        Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])
            ->middleware('permission:sales.cancel');

        Route::get('budgets', [BudgetController::class, 'index'])
            ->middleware('permission:budgets.view');
        Route::get('budgets/{budget}', [BudgetController::class, 'show'])
            ->middleware('permission:budgets.view');
        Route::get('sales/{sale}/budgets', [BudgetController::class, 'indexForSale'])
            ->middleware('permission:budgets.view');
        Route::post('sales/{sale}/budgets', [BudgetController::class, 'store'])
            ->middleware('permission:budgets.create');
        Route::patch('budgets/{budget}', [BudgetController::class, 'update'])
            ->middleware('permission:budgets.update');
        Route::post('budgets/{budget}/send', [BudgetController::class, 'send'])
            ->middleware('permission:budgets.update');
        Route::post('budgets/{budget}/accept', [BudgetController::class, 'accept'])
            ->middleware('permission:budgets.convert');
        Route::post('budgets/{budget}/reject', [BudgetController::class, 'reject'])
            ->middleware('permission:budgets.update');
        Route::post('budgets/{budget}/expire', [BudgetController::class, 'expire'])
            ->middleware('permission:budgets.update');

        Route::post('budgets/{budget}/pdf', [DocumentController::class, 'generateBudgetPdf'])
            ->middleware('permission:documents.generate');
        Route::get('documents', [DocumentController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::get('documents/{document}', [DocumentController::class, 'show'])
            ->middleware('permission:documents.view');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->middleware('permission:documents.view');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
            ->middleware('permission:documents.delete');

        Route::get('metrics/commercial', [MetricsController::class, 'commercial'])
            ->middleware('permission:metrics.view');

        Route::get('treatments', [TreatmentController::class, 'index'])
            ->middleware('permission:treatments.view');
        Route::post('sales/{sale}/treatments', [TreatmentController::class, 'store'])
            ->middleware('permission:treatments.start');
        Route::get('treatments/{treatment}', [TreatmentController::class, 'show'])
            ->middleware('permission:treatments.view');
        Route::get('treatments/{treatment}/fulfillment', [TreatmentController::class, 'fulfillment'])
            ->middleware('permission:treatments.view');
        Route::post('treatments/{treatment}/complete', [TreatmentController::class, 'complete'])
            ->middleware('permission:treatments.complete');
        Route::post('treatments/{treatment}/cancel', [TreatmentController::class, 'cancel'])
            ->middleware('permission:treatments.cancel');

        Route::get('treatments/{treatment}/appointments', [AppointmentController::class, 'indexForTreatment'])
            ->middleware('permission:treatments.view');
        Route::post('treatments/{treatment}/appointments', [AppointmentController::class, 'store'])
            ->middleware('permission:treatments.manage');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])
            ->middleware('permission:treatments.view');
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update'])
            ->middleware('permission:treatments.manage');
        Route::post('appointments/{appointment}/start', [AppointmentController::class, 'start'])
            ->middleware('permission:treatments.start');
        Route::put('appointments/{appointment}/consumptions', [AppointmentController::class, 'syncConsumptions'])
            ->middleware('permission:treatments.manage');
        Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])
            ->middleware('permission:treatments.complete');
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('permission:treatments.cancel');
    });
});

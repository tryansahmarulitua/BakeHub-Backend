<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WasteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('raw-materials', RawMaterialController::class);
    Route::apiResource('products', ProductController::class);

    // Purchase Orders
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->except(['destroy']);
    Route::post('purchase-orders/{purchaseOrder}/submit',  [PurchaseOrderController::class, 'submit']);
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('purchase-orders/{purchaseOrder}/cancel',  [PurchaseOrderController::class, 'cancel']);

    // Goods Receipts
    Route::apiResource('goods-receipts', GoodsReceiptController::class)->except(['update', 'destroy']);
    Route::post('goods-receipts/{goodsReceipt}/complete', [GoodsReceiptController::class, 'complete']);

    // Inventory
    Route::get('inventory/raw-materials', [InventoryController::class, 'rawMaterials']);
    Route::get('inventory/products', [InventoryController::class, 'products']);
    Route::get('inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('inventory/expiring', [InventoryController::class, 'expiring']);
    Route::get('inventory/transactions', [InventoryController::class, 'transactions']);
    Route::post('inventory/adjustment', [InventoryController::class, 'adjustment']);
    Route::get('inventory/stock-card', [InventoryController::class, 'stockCard']);

    // Recipes / BOM
    Route::apiResource('recipes', RecipeController::class);
    Route::post('recipes/{recipe}/calculate', [RecipeController::class, 'calculate']);

    // Productions
    Route::apiResource('productions', ProductionController::class)->except(['update', 'destroy']);
    Route::post('productions/{production}/start',    [ProductionController::class, 'start']);
    Route::post('productions/{production}/complete', [ProductionController::class, 'complete']);
    Route::post('productions/{production}/cancel',   [ProductionController::class, 'cancel']);

    // Sales
    Route::apiResource('sales', SalesController::class)->except(['update', 'destroy']);
    Route::post('sales/{sale}/cancel', [SalesController::class, 'cancel']);

    // Returns
    Route::apiResource('returns', ReturnController::class)->except(['update', 'destroy']);
    Route::post('returns/{return}/approve',  [ReturnController::class, 'approve']);
    Route::post('returns/{return}/complete', [ReturnController::class, 'complete']);

    // Wastes
    Route::apiResource('wastes', WasteController::class)->except(['update', 'destroy']);

    // Reports
    Route::get('reports/dashboard',   [ReportController::class, 'dashboard']);
    Route::get('reports/sales',       [ReportController::class, 'sales']);
    Route::get('reports/inventory',   [ReportController::class, 'inventory']);
    Route::get('reports/production',  [ReportController::class, 'production']);
    Route::get('reports/waste',       [ReportController::class, 'waste']);

    // Report Exports
    Route::get('reports/sales/pdf',        [ReportController::class, 'exportSalesPdf']);
    Route::get('reports/sales/excel',      [ReportController::class, 'exportSalesExcel']);
    Route::get('reports/inventory/pdf',    [ReportController::class, 'exportInventoryPdf']);
    Route::get('reports/inventory/excel',  [ReportController::class, 'exportInventoryExcel']);
    Route::get('reports/production/pdf',   [ReportController::class, 'exportProductionPdf']);
    Route::get('reports/production/excel', [ReportController::class, 'exportProductionExcel']);
    Route::get('reports/waste/pdf',        [ReportController::class, 'exportWastePdf']);
    Route::get('reports/waste/excel',      [ReportController::class, 'exportWasteExcel']);
});

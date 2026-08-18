<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Products\AdjustStockRequest;
use App\Http\Requests\Api\V1\Products\StoreProductRequest;
use App\Http\Requests\Api\V1\Products\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\StockMovementResource;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['productType', 'brand', 'unitOfMeasure'])
            ->orderBy('name');

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'min_stock');
        }

        if ($request->filled('product_type_id')) {
            $query->where('product_type_id', $request->integer('product_type_id'));
        }

        return ProductResource::collection($query->paginate(20));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $initialStock = (float) ($data['stock_quantity'] ?? 0);
        $initialCost = (float) ($data['cost'] ?? 0);

        unset($data['stock_quantity'], $data['cost']);
        $data['stock_quantity'] = 0;
        $data['cost'] = 0;

        $product = Product::query()->create($data);

        if ($initialStock > 0) {
            $this->stockService->move(
                product: $product,
                type: StockMovementType::In,
                quantity: (string) $initialStock,
                unitCost: (string) $initialCost,
                reason: 'opening_balance',
                user: $request->user(),
            );
        } elseif ($initialCost > 0) {
            $product->update(['cost' => number_format($initialCost, 4, '.', '')]);
        }

        return (new ProductResource($product->fresh()->load(['productType', 'brand', 'unitOfMeasure'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['productType', 'brand', 'unitOfMeasure']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->fresh()->load(['productType', 'brand', 'unitOfMeasure']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return response()->json(['message' => 'Product deactivated.']);
    }

    public function adjustStock(AdjustStockRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        try {
            $movement = $this->stockService->move(
                product: $product,
                type: StockMovementType::from($data['type']),
                quantity: (string) $data['quantity'],
                unitCost: isset($data['unit_cost']) ? (string) $data['unit_cost'] : null,
                reason: $data['reason'] ?? null,
                notes: $data['notes'] ?? null,
                user: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['quantity' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'data' => [
                'product' => new ProductResource($product->fresh()->load(['productType', 'brand', 'unitOfMeasure'])),
                'movement' => new StockMovementResource($movement),
            ],
        ]);
    }
}

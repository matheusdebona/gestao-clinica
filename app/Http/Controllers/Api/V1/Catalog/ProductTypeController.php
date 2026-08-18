<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreProductTypeRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateProductTypeRequest;
use App\Http\Resources\Api\V1\ProductTypeResource;
use App\Models\ProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductTypeResource::collection(
            ProductType::query()->orderBy('name')->paginate(50)
        );
    }

    public function store(StoreProductTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $type = ProductType::query()->create($data);

        return (new ProductTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductType $productType): ProductTypeResource
    {
        return new ProductTypeResource($productType);
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType): ProductTypeResource
    {
        $data = $request->validated();
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $productType->update($data);

        return new ProductTypeResource($productType->fresh());
    }

    public function destroy(ProductType $productType): JsonResponse
    {
        $productType->update(['is_active' => false]);

        return response()->json(['message' => 'Product type deactivated.']);
    }
}

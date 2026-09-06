<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreBrandRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateBrandRequest;
use App\Http\Resources\Api\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Brand::query()->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return BrandResource::collection($query->paginate(50));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::query()->create($request->validated());

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $brand->update($request->validated());

        return new BrandResource($brand->fresh());
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->update(['is_active' => false]);

        return response()->json(['message' => 'Brand deactivated.']);
    }
}

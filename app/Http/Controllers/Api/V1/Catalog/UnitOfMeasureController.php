<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreUnitOfMeasureRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateUnitOfMeasureRequest;
use App\Http\Resources\Api\V1\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitOfMeasureController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UnitOfMeasureResource::collection(
            UnitOfMeasure::query()->orderBy('name')->paginate(50)
        );
    }

    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        $unit = UnitOfMeasure::query()->create($request->validated());

        return (new UnitOfMeasureResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        return new UnitOfMeasureResource($unitOfMeasure);
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): UnitOfMeasureResource
    {
        $unitOfMeasure->update($request->validated());

        return new UnitOfMeasureResource($unitOfMeasure->fresh());
    }

    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $unitOfMeasure->update(['is_active' => false]);

        return response()->json(['message' => 'Unit of measure deactivated.']);
    }
}

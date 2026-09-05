<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\StoreCardBrandRequest;
use App\Http\Requests\Api\V1\Payments\UpdateCardBrandRequest;
use App\Http\Resources\Api\V1\CardBrandResource;
use App\Models\CardBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CardBrandController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CardBrand::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CardBrandResource::collection($query->paginate(50));
    }

    public function store(StoreCardBrandRequest $request): JsonResponse
    {
        $brand = CardBrand::query()->create($request->validated());

        return (new CardBrandResource($brand))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CardBrand $cardBrand): CardBrandResource
    {
        return new CardBrandResource($cardBrand);
    }

    public function update(UpdateCardBrandRequest $request, CardBrand $cardBrand): CardBrandResource
    {
        $cardBrand->update($request->validated());

        return new CardBrandResource($cardBrand->fresh());
    }

    public function destroy(CardBrand $cardBrand): JsonResponse
    {
        $cardBrand->update(['is_active' => false]);

        return response()->json(['message' => 'Card brand deactivated.']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\StoreCardOperatorRequest;
use App\Http\Requests\Api\V1\Payments\UpdateCardOperatorRequest;
use App\Http\Resources\Api\V1\CardOperatorResource;
use App\Models\CardOperator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CardOperatorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CardOperator::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CardOperatorResource::collection($query->paginate(50));
    }

    public function store(StoreCardOperatorRequest $request): JsonResponse
    {
        $operator = CardOperator::query()->create($request->validated());

        return (new CardOperatorResource($operator))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CardOperator $cardOperator): CardOperatorResource
    {
        return new CardOperatorResource($cardOperator);
    }

    public function update(UpdateCardOperatorRequest $request, CardOperator $cardOperator): CardOperatorResource
    {
        $cardOperator->update($request->validated());

        return new CardOperatorResource($cardOperator->fresh());
    }

    public function destroy(CardOperator $cardOperator): JsonResponse
    {
        $cardOperator->update(['is_active' => false]);

        return response()->json(['message' => 'Card operator deactivated.']);
    }
}

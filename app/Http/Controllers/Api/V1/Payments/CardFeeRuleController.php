<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\StoreCardFeeRuleRequest;
use App\Http\Requests\Api\V1\Payments\UpdateCardFeeRuleRequest;
use App\Http\Resources\Api\V1\CardFeeRuleResource;
use App\Models\CardFeeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CardFeeRuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CardFeeRule::query()
            ->with(['paymentMethod', 'cardOperator', 'cardBrand'])
            ->orderBy('installments');

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->integer('payment_method_id'));
        }

        if ($request->filled('card_operator_id')) {
            $query->where('card_operator_id', $request->integer('card_operator_id'));
        }

        if ($request->filled('card_brand_id')) {
            $query->where('card_brand_id', $request->integer('card_brand_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CardFeeRuleResource::collection($query->paginate(50));
    }

    public function store(StoreCardFeeRuleRequest $request): JsonResponse
    {
        $rule = CardFeeRule::query()->create($request->validated());
        $rule->load(['paymentMethod', 'cardOperator', 'cardBrand']);

        return (new CardFeeRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CardFeeRule $cardFeeRule): CardFeeRuleResource
    {
        $cardFeeRule->load(['paymentMethod', 'cardOperator', 'cardBrand']);

        return new CardFeeRuleResource($cardFeeRule);
    }

    public function update(UpdateCardFeeRuleRequest $request, CardFeeRule $cardFeeRule): CardFeeRuleResource
    {
        $cardFeeRule->update($request->validated());
        $cardFeeRule->load(['paymentMethod', 'cardOperator', 'cardBrand']);

        return new CardFeeRuleResource($cardFeeRule->fresh()->load(['paymentMethod', 'cardOperator', 'cardBrand']));
    }

    public function destroy(CardFeeRule $cardFeeRule): JsonResponse
    {
        $cardFeeRule->update(['is_active' => false]);

        return response()->json(['message' => 'Card fee rule deactivated.']);
    }
}

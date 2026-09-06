<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Protocols\StoreProtocolRequest;
use App\Http\Requests\Api\V1\Protocols\SyncProtocolItemsRequest;
use App\Http\Requests\Api\V1\Protocols\UpdateProtocolRequest;
use App\Http\Resources\Api\V1\ProtocolResource;
use App\Models\Protocol;
use App\Services\ProtocolPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProtocolController extends Controller
{
    public function __construct(private readonly ProtocolPricingService $pricing) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Protocol::query()
            ->with(['items.product.unitOfMeasure'])
            ->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where('name', 'like', $term);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ProtocolResource::collection($query->paginate(20));
    }

    public function store(StoreProtocolRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['items']);
        $items = $request->validated('items') ?? [];

        $protocol = Protocol::query()->create([
            ...$data,
            'total_cost' => 0,
            'products_sale_total' => 0,
            'suggested_price' => $data['suggested_price'] ?? 0,
            'suggested_price_is_manual' => array_key_exists('suggested_price', $data),
            'min_price' => $data['min_price'] ?? 0,
            'min_price_is_manual' => array_key_exists('min_price', $data),
            'special_price' => $data['special_price'] ?? null,
        ]);

        if ($items !== []) {
            $protocol = $this->pricing->syncItems($protocol, $items);
        } else {
            $protocol = $this->pricing->recalculate($protocol);
        }

        return (new ProtocolResource($protocol->load(['items.product.unitOfMeasure', 'items.product.brand'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Protocol $protocol): ProtocolResource
    {
        return new ProtocolResource($protocol->load(['items.product.productType', 'items.product.brand', 'items.product.unitOfMeasure']));
    }

    public function update(UpdateProtocolRequest $request, Protocol $protocol): ProtocolResource
    {
        $data = $request->safe()->except([
            'recalculate_from_products',
            'reset_suggested_price',
            'reset_min_price',
        ]);

        if (array_key_exists('suggested_price', $data)) {
            $data['suggested_price_is_manual'] = true;
        }

        if (array_key_exists('min_price', $data)) {
            $data['min_price_is_manual'] = true;
        }

        if ($data !== []) {
            $protocol->update($data);
        }

        if ($request->boolean('recalculate_from_products')) {
            $protocol = $this->pricing->recalculate(
                $protocol,
                forceSuggested: $request->boolean('reset_suggested_price'),
                forceMin: $request->boolean('reset_min_price'),
            );
        }

        return new ProtocolResource($protocol->fresh()->load(['items.product.unitOfMeasure', 'items.product.brand']));
    }

    public function destroy(Protocol $protocol): JsonResponse
    {
        $protocol->update(['is_active' => false]);

        return response()->json(['message' => 'Protocol deactivated.']);
    }

    public function syncItems(SyncProtocolItemsRequest $request, Protocol $protocol): ProtocolResource
    {
        $protocol = $this->pricing->syncItems($protocol, $request->validated('items'));

        return new ProtocolResource($protocol->load(['items.product.unitOfMeasure', 'items.product.brand']));
    }

    public function recalculate(Protocol $protocol): ProtocolResource
    {
        $protocol = $this->pricing->recalculate(
            $protocol,
            forceSuggested: true,
            forceMin: true,
        );

        return new ProtocolResource($protocol->load(['items.product.unitOfMeasure', 'items.product.brand']));
    }
}

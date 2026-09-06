<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sales\ApplyProtocolToSaleRequest;
use App\Http\Requests\Api\V1\Sales\ConfirmSaleRequest;
use App\Http\Requests\Api\V1\Sales\StoreSaleRequest;
use App\Http\Requests\Api\V1\Sales\SyncSaleItemsRequest;
use App\Http\Requests\Api\V1\Sales\SyncSalePaymentsRequest;
use App\Http\Requests\Api\V1\Sales\UpdateSaleRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Protocol;
use App\Models\Sale;
use App\Services\SalePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(private readonly SalePricingService $pricing) {}

    /**
     * List sales in the current clinic.
     *
     * Query: `q` (client name or WhatsApp), `status` (draft|confirmed|cancelled), `client_id`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sale::query()
            ->with(['client', 'items.product.unitOfMeasure', 'payments.paymentMethod', 'treatment'])
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->whereHas('client', function ($builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('whatsapp', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        return SaleResource::collection($query->paginate(20));
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = Sale::query()->create([
            ...$request->validated(),
            'sold_by_user_id' => $request->user()->id,
            'sold_at' => $request->validated('sold_at') ?? now(),
            'status' => Sale::STATUS_DRAFT,
        ]);

        return (new SaleResource($sale->load(['client', 'items', 'payments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load(['treatment']));
    }

    public function update(UpdateSaleRequest $request, Sale $sale): SaleResource
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled sales cannot be updated.'],
            ]);
        }

        if ($sale->isConfirmed()) {
            if ($request->exists('effective_amount') || $request->exists('sold_at')) {
                throw ValidationException::withMessages([
                    'status' => ['Confirmed sales only allow notes updates.'],
                ]);
            }

            $sale->update($request->safe()->only(['notes']));

            return new SaleResource($sale->fresh());
        }

        $data = $request->safe()->except(['effective_amount']);
        if ($data !== []) {
            $sale->update($data);
        }

        if ($request->exists('effective_amount')) {
            $sale = $this->pricing->setEffectiveAmount($sale, $request->input('effective_amount'));
        } else {
            $sale = $sale->fresh(['items.product', 'items.sourceProtocol', 'payments.paymentMethod', 'client', 'soldByUser']);
        }

        return new SaleResource($sale);
    }

    public function syncItems(SyncSaleItemsRequest $request, Sale $sale): SaleResource
    {
        $sale = $this->pricing->syncItems($sale, $request->validated('items'));

        return new SaleResource($sale);
    }

    public function applyProtocol(ApplyProtocolToSaleRequest $request, Sale $sale): SaleResource
    {
        $protocol = Protocol::query()->findOrFail($request->integer('protocol_id'));
        $sale = $this->pricing->applyProtocol($sale, $protocol);

        return new SaleResource($sale);
    }

    public function syncPayments(SyncSalePaymentsRequest $request, Sale $sale): SaleResource
    {
        $sale = $this->pricing->syncPayments($sale, $request->validated('payments'));

        return new SaleResource($sale);
    }

    public function confirm(ConfirmSaleRequest $request, Sale $sale): SaleResource
    {
        $sale = $this->pricing->confirm($sale, $request->boolean('confirm_below_minimum'));

        return new SaleResource($sale);
    }

    public function cancel(Sale $sale): SaleResource
    {
        $sale = $this->pricing->cancel($sale);

        return new SaleResource($sale);
    }
}

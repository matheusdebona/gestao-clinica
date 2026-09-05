<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Treatments\StoreTreatmentRequest;
use App\Http\Resources\Api\V1\TreatmentResource;
use App\Models\Sale;
use App\Models\Treatment;
use App\Services\TreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentService $treatments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Treatment::query()
            ->with(['client', 'sale'])
            ->orderByDesc('id');

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->integer('sale_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return TreatmentResource::collection($query->paginate(20));
    }

    public function store(StoreTreatmentRequest $request, Sale $sale): JsonResponse
    {
        $treatment = $this->treatments->openFromSale(
            $sale,
            $request->user(),
            $request->validated('notes'),
        );

        return (new TreatmentResource($treatment->load(['client', 'sale', 'appointments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Treatment $treatment): TreatmentResource
    {
        return new TreatmentResource(
            $treatment->load(['client', 'sale.items', 'appointments.consumptions', 'openedByUser'])
        );
    }

    public function fulfillment(Treatment $treatment): JsonResponse
    {
        return response()->json([
            'data' => [
                'treatment_id' => $treatment->id,
                'sale_id' => $treatment->sale_id,
                'items' => $this->treatments->fulfillment($treatment),
            ],
        ]);
    }

    public function complete(Treatment $treatment): TreatmentResource
    {
        return new TreatmentResource($this->treatments->complete($treatment));
    }

    public function cancel(Treatment $treatment): TreatmentResource
    {
        return new TreatmentResource($this->treatments->cancel($treatment));
    }
}

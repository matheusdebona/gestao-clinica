<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\StorePaymentMethodRequest;
use App\Http\Requests\Api\V1\Payments\UpdatePaymentMethodRequest;
use App\Http\Resources\Api\V1\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PaymentMethod::query()->orderBy('name');

        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return PaymentMethodResource::collection($query->paginate(50));
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $method = PaymentMethod::query()->create($request->validated());

        return (new PaymentMethodResource($method))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        return new PaymentMethodResource($paymentMethod);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $paymentMethod->update($request->validated());

        return new PaymentMethodResource($paymentMethod->fresh());
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update(['is_active' => false]);

        return response()->json(['message' => 'Payment method deactivated.']);
    }
}

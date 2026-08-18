<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClinicResource;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClinicController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->clinic_id === null) {
            return response()->json([
                'message' => 'No clinic bound to this user.',
                'data' => null,
            ]);
        }

        $clinic = Clinic::query()->findOrFail($user->clinic_id);

        return response()->json([
            'data' => new ClinicResource($clinic),
            'resolved_clinic_id' => CurrentClinic::id(),
        ]);
    }

    public function index(): AnonymousResourceCollection
    {
        $clinics = Clinic::query()->orderBy('name')->paginate(20);

        return ClinicResource::collection($clinics);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $clinic = Clinic::query()->create($data);

        return (new ClinicResource($clinic))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Clinic $clinic): ClinicResource
    {
        return new ClinicResource($clinic);
    }

    public function update(Request $request, Clinic $clinic): ClinicResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $clinic->update($data);

        return new ClinicResource($clinic->fresh());
    }
}

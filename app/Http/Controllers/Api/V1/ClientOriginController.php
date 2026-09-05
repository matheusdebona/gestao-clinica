<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClientOrigins\StoreClientOriginRequest;
use App\Http\Requests\Api\V1\ClientOrigins\UpdateClientOriginRequest;
use App\Http\Resources\Api\V1\ClientOriginResource;
use App\Models\ClientOrigin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientOriginController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ClientOrigin::query()->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return ClientOriginResource::collection($query->paginate(50));
    }

    public function store(StoreClientOriginRequest $request): JsonResponse
    {
        $origin = ClientOrigin::query()->create($request->validated());

        return (new ClientOriginResource($origin))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ClientOrigin $clientOrigin): ClientOriginResource
    {
        return new ClientOriginResource($clientOrigin);
    }

    public function update(UpdateClientOriginRequest $request, ClientOrigin $clientOrigin): ClientOriginResource
    {
        $clientOrigin->update($request->validated());

        return new ClientOriginResource($clientOrigin->fresh());
    }

    public function destroy(ClientOrigin $clientOrigin): JsonResponse
    {
        $clientOrigin->update(['is_active' => false]);

        return response()->json(['message' => 'Client origin deactivated.']);
    }
}

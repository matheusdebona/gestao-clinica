<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clients\StoreClientRequest;
use App\Http\Requests\Api\V1\Clients\UpdateClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('whatsapp', 'like', $term);
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ClientResource::collection(
            $query->with(['clientOrigin', 'campaign'])->paginate(20)
        );
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::query()->create($request->validated());

        return (new ClientResource($client->load(['clientOrigin', 'campaign'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load(['clientOrigin', 'campaign']));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->fresh()->load(['clientOrigin', 'campaign']));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->update(['is_active' => false]);

        return response()->json([
            'message' => 'Client deactivated.',
        ]);
    }
}

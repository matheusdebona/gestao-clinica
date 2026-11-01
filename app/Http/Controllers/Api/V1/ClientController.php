<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clients\StoreClientRequest;
use App\Http\Requests\Api\V1\Clients\UpdateClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Services\PatientTierService;
use App\Support\CaseInsensitiveSearch;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(private readonly PatientTierService $tiers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tiers = $this->tiers->tiers();
        $query = $this->tiers->withSpend(Client::query(), (int) CurrentClinic::id())->orderBy('clients.name');

        if ($request->filled('q')) {
            CaseInsensitiveSearch::whereContains(
                $query,
                ['name', 'whatsapp'],
                $request->string('q')->toString(),
            );
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Faixa: ?tiers[]=3&tiers[]=none (ou "3,none"). Vazio = Todos.
        $this->tiers->applyFilter($query, $this->tierTokens($request), $tiers);

        $page = $query->with(['clientOrigin', 'campaign'])->paginate(20);
        $page->getCollection()->each(function (Client $client) use ($tiers): void {
            $client->setRelation('patientTier', $this->tiers->tierFor($client->getAttribute('purchases_12m'), $tiers));
        });

        return ClientResource::collection($page);
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
        $this->attachTier($client);

        return new ClientResource($client->load([
            'clientOrigin',
            'campaign',
            'lead.status',
            'lead.clientOrigin',
            'lead.createdBy',
            'lead.activities.user',
            'lead.activities.fromStatus',
            'lead.activities.toStatus',
        ]));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        $fresh = $client->fresh();
        $this->attachTier($fresh);

        return new ClientResource($fresh->load(['clientOrigin', 'campaign']));
    }

    private function attachTier(Client $client): void
    {
        $spend = $this->tiers->spendFor($client);
        $client->setAttribute('purchases_12m', $spend);
        $client->setRelation('patientTier', $this->tiers->tierFor($spend, $this->tiers->tiers()));
    }

    /** @return list<int|string> */
    private function tierTokens(Request $request): array
    {
        $raw = $request->input('tiers', []);
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === PatientTierService::FILTER_NONE) {
                $tokens[] = PatientTierService::FILTER_NONE;
            } elseif (ctype_digit($part) && (int) $part > 0) {
                $tokens[] = (int) $part;
            }
        }

        return array_values(array_unique($tokens, SORT_REGULAR));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->update(['is_active' => false]);

        return response()->json([
            'message' => 'Client deactivated.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Api\V1\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Campaign::query()
            ->with('clientOrigin')
            ->orderBy('name');

        if ($request->filled('client_origin_id')) {
            $query->where('client_origin_id', $request->integer('client_origin_id'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return CampaignResource::collection($query->paginate(50));
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::query()->create($request->validated());

        return (new CampaignResource($campaign->load('clientOrigin')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        return new CampaignResource($campaign->load('clientOrigin'));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $campaign->update($request->validated());

        return new CampaignResource($campaign->fresh()->load('clientOrigin'));
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->update(['is_active' => false]);

        return response()->json(['message' => 'Campaign deactivated.']);
    }
}

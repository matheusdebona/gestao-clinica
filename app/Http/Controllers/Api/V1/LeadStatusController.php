<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leads\LeadStatusRequest;
use App\Http\Resources\Api\V1\LeadStatusResource;
use App\Models\LeadStatus;
use App\Support\EnsureDefaultLeadStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadStatusController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        EnsureDefaultLeadStatuses::run((int) $request->user()->clinic_id);

        $query = LeadStatus::query()->orderBy('sort_order')->orderBy('id')->withCount('leads');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return LeadStatusResource::collection($query->get());
    }

    public function store(LeadStatusRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = DB::transaction(function () use ($data, $request): LeadStatus {
            if (! array_key_exists('sort_order', $data)) {
                $data['sort_order'] = ((int) LeadStatus::query()->max('sort_order')) + 10;
            }
            $status = LeadStatus::query()->create([
                ...$data,
                'clinic_id' => $request->user()->clinic_id,
            ]);
            if ($status->is_default) {
                $this->makeSoleDefault($status);
            }

            return $status;
        });

        return (new LeadStatusResource($status->fresh()))->response()->setStatusCode(201);
    }

    public function show(LeadStatus $leadStatus): LeadStatusResource
    {
        return new LeadStatusResource($leadStatus->loadCount('leads'));
    }

    public function update(LeadStatusRequest $request, LeadStatus $leadStatus): LeadStatusResource
    {
        $data = $request->validated();

        if ($leadStatus->isSystem()) {
            if (($data['is_active'] ?? true) === false) {
                throw ValidationException::withMessages([
                    'is_active' => ['Status do sistema não pode ser desativado.'],
                ]);
            }
            if (($data['is_default'] ?? false) === true) {
                throw ValidationException::withMessages([
                    'is_default' => ['Status do sistema não pode ser o status inicial.'],
                ]);
            }
        }

        if ($leadStatus->is_default && (($data['is_active'] ?? true) === false || ($data['is_default'] ?? true) === false)) {
            throw ValidationException::withMessages([
                'is_default' => ['Escolha outro status inicial antes de alterar este.'],
            ]);
        }

        if (($data['is_default'] ?? false) === true && ($data['is_active'] ?? $leadStatus->is_active) === false) {
            throw ValidationException::withMessages([
                'is_default' => ['O status inicial precisa estar ativo.'],
            ]);
        }

        DB::transaction(function () use ($leadStatus, $data): void {
            $leadStatus->update($data);
            if ($leadStatus->is_default) {
                $this->makeSoleDefault($leadStatus);
            }
        });

        return new LeadStatusResource($leadStatus->fresh()->loadCount('leads'));
    }

    /**
     * Unused custom statuses are deleted; statuses in use are only deactivated.
     */
    public function destroy(LeadStatus $leadStatus): JsonResponse
    {
        if ($leadStatus->isSystem()) {
            throw ValidationException::withMessages([
                'lead_status' => ['Status do sistema não pode ser removido.'],
            ]);
        }

        if ($leadStatus->is_default) {
            throw ValidationException::withMessages([
                'lead_status' => ['Escolha outro status inicial antes de remover este.'],
            ]);
        }

        if ($leadStatus->leads()->exists()) {
            $leadStatus->update(['is_active' => false]);

            return response()->json(['message' => 'Status desativado (há leads com ele).', 'deleted' => false]);
        }

        $leadStatus->delete();

        return response()->json(['message' => 'Status removido.', 'deleted' => true]);
    }

    private function makeSoleDefault(LeadStatus $status): void
    {
        LeadStatus::query()
            ->where('clinic_id', $status->clinic_id)
            ->whereKeyNot($status->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leads\ChangeLeadStatusRequest;
use App\Http\Requests\Api\V1\Leads\ConvertLeadRequest;
use App\Http\Requests\Api\V1\Leads\LeadRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Http\Resources\Api\V1\ClientResource;
use App\Http\Resources\Api\V1\LeadActivityResource;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadService;
use App\Support\CaseInsensitiveSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    private const LIST_RELATIONS = ['status', 'clientOrigin', 'createdBy', 'client'];

    private const DETAIL_RELATIONS = [
        'status',
        'clientOrigin',
        'createdBy',
        'client',
        'activities.user',
        'activities.fromStatus',
        'activities.toStatus',
    ];

    public function __construct(private readonly LeadService $leads) {}

    /**
     * Query: q (name/phone), lead_status_id, client_origin_id, created_by_user_id, from, to (created_at, Y-m-d), converted.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        $query = Lead::query()->latest('created_at')->latest('id');

        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $query->where(function ($builder) use ($term, $digits): void {
                CaseInsensitiveSearch::whereContains($builder, ['name'], $term);
                if (strlen($digits) >= 3) {
                    $builder->orWhere('leads.phone', 'like', '%'.$digits.'%');
                }
            });
        }

        // Accepts a single id, a comma list ("1,2") or an array (lead_status_id[]=1).
        foreach (['lead_status_id', 'client_origin_id', 'created_by_user_id'] as $filter) {
            $ids = $this->idList($request->input($filter));
            if ($ids !== []) {
                $query->whereIn($filter, $ids);
            }
        }

        if ($request->filled('converted')) {
            $request->boolean('converted') ? $query->whereNotNull('client_id') : $query->whereNull('client_id');
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return LeadResource::collection($query->with(self::LIST_RELATIONS)->withCount('comments')->paginate($perPage));
    }

    public function store(LeadRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertManualStatusAllowed($data['lead_status_id'] ?? null, null);

        $lead = $this->leads->create($data, $request->user());

        return (new LeadResource($lead->load(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($lead->load(self::DETAIL_RELATIONS));
    }

    public function update(LeadRequest $request, Lead $lead): LeadResource
    {
        $data = $request->validated();
        if (array_key_exists('lead_status_id', $data) && $data['lead_status_id'] !== null) {
            $this->assertManualStatusAllowed((int) $data['lead_status_id'], $lead);
        }

        $lead = $this->leads->update($lead, $data, $request->user());

        return new LeadResource($lead->fresh()->load(self::DETAIL_RELATIONS));
    }

    public function changeStatus(ChangeLeadStatusRequest $request, Lead $lead): LeadResource
    {
        $statusId = $request->integer('lead_status_id');
        $this->assertManualStatusAllowed($statusId, $lead);

        $lead = $this->leads->changeStatus($lead, $statusId, $request->input('note'), $request->user());

        return new LeadResource($lead->fresh()->load(self::DETAIL_RELATIONS));
    }

    /**
     * Leads are never deleted; cancelling is final and keeps them in list, history and metrics.
     */
    public function cancel(Request $request, Lead $lead): LeadResource
    {
        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->leads->cancel($lead, $data['reason'] ?? null, $request->user());

        return new LeadResource($lead->fresh()->load(self::DETAIL_RELATIONS));
    }

    /**
     * Comments are history: immutable, never deleted, allowed on cancelled and converted leads too.
     */
    public function comment(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Escreva o comentário.',
            'body.max' => 'Comentário muito longo.',
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => ['Escreva o comentário.']]);
        }

        $activity = $lead->activities()->create([
            'user_id' => $request->user()->id,
            'type' => LeadActivity::TYPE_COMMENT,
            'note' => $body,
        ]);

        return (new LeadActivityResource($activity->load('user')))->response()->setStatusCode(201);
    }

    /**
     * Live duplicate check for the form: phone, optional lead_id (the lead being edited).
     */
    public function phoneCheck(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'lead_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $ignore = isset($data['lead_id']) ? Lead::query()->find($data['lead_id']) : null;
        $digits = preg_replace('/\D+/', '', $data['phone']) ?? '';

        return response()->json([
            'data' => strlen($digits) >= 10
                ? $this->leads->phoneConflict((int) $request->user()->clinic_id, $digits, $ignore)
                : null,
        ]);
    }

    public function matchingClients(Lead $lead): AnonymousResourceCollection
    {
        return ClientResource::collection($this->leads->matchingClients($lead));
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $result = $this->leads->convert($lead, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'lead' => new LeadResource($result['lead']->fresh()->load(self::DETAIL_RELATIONS)),
                'client' => new ClientResource($result['client']->fresh()->load(['clientOrigin'])),
                'appointment' => new AppointmentResource($result['appointment']->load(['client', 'professionalUser', 'treatment'])),
                'client_created' => $result['client_created'],
            ],
        ], 201);
    }

    /**
     * Clinic users for the "Cadastrado por" filter (no users.view needed).
     */
    public function creators(Request $request): JsonResponse
    {
        $users = User::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()]);
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map('intval', $parts), fn (int $id) => $id > 0)));
    }

    /**
     * System statuses are set only by their flows ("Agendar", "Cancelar lead") and are final.
     */
    private function assertManualStatusAllowed(?int $statusId, ?Lead $lead): void
    {
        if ($statusId === null) {
            return;
        }

        $status = LeadStatus::query()->find($statusId);
        $isConvertedStatus = $status?->system_key === LeadStatus::SYSTEM_CONVERTED;

        if ($lead?->isCancelled()) {
            throw ValidationException::withMessages([
                'lead_status_id' => ['Lead cancelado é definitivo e não muda de status.'],
            ]);
        }

        if ($status?->system_key === LeadStatus::SYSTEM_CANCELLED && (int) $lead?->lead_status_id !== $statusId) {
            throw ValidationException::withMessages([
                'lead_status_id' => ['Use “Cancelar lead” para cancelar.'],
            ]);
        }

        if ($lead?->isConverted()) {
            if ((int) $lead->lead_status_id !== $statusId) {
                throw ValidationException::withMessages([
                    'lead_status_id' => ['Lead que virou paciente mantém o status “'.($lead->status?->name ?? 'Virou paciente').'”.'],
                ]);
            }

            return;
        }

        if ($isConvertedStatus) {
            throw ValidationException::withMessages([
                'lead_status_id' => ['Use “Agendar” para transformar o lead em paciente.'],
            ]);
        }
    }
}

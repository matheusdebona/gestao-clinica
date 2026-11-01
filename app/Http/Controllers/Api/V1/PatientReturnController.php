<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PatientReturn;
use App\Services\PatientReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientReturnController extends Controller
{
    public function __construct(private readonly PatientReturnService $returns) {}

    /**
     * Query: filter = due (default: overdue + due within 7 days) | overdue | upcoming (all pending) | done; client_id.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'nullable', Rule::in(['due', 'overdue', 'upcoming', 'done'])],
            'client_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $filter = $data['filter'] ?? 'due';
        $today = PatientReturnService::today();

        $query = match ($filter) {
            'due' => $this->returns->dueQuery($today),
            'overdue' => $this->returns->dueQuery($today)->whereDate('patient_returns.due_on', '<', $today->toDateString()),
            'upcoming' => PatientReturn::query()->where('status', PatientReturn::STATUS_PENDING)->orderBy('due_on')->orderBy('id'),
            'done' => PatientReturn::query()->where('status', PatientReturn::STATUS_DONE)->orderByDesc('done_at')->orderByDesc('id'),
        };
        if (! empty($data['client_id'])) {
            $query->where('patient_returns.client_id', (int) $data['client_id']);
        }

        $counts = [
            'due' => $this->returns->dueQuery($today)->count(),
            'overdue' => $this->returns->dueQuery($today)->whereDate('patient_returns.due_on', '<', $today->toDateString())->count(),
        ];

        $items = $query->with('client')->limit(200)->get()
            ->map(fn (PatientReturn $r) => $this->returns->present($r, $today))->values();

        return response()->json(['data' => $items, 'meta' => ['filter' => $filter, 'today' => $today->toDateString(), 'counts' => $counts]]);
    }

    public function done(Request $request, PatientReturn $patientReturn): JsonResponse
    {
        if ($patientReturn->isPending()) {
            $this->returns->markDone($patientReturn, $request->user());
        }

        return response()->json(['data' => $this->returns->present($patientReturn->load('client'))]);
    }

    public function whatsapp(Request $request, PatientReturn $patientReturn): JsonResponse
    {
        $this->returns->logWhatsApp($patientReturn, $request->user());

        return response()->json(['data' => $this->returns->present($patientReturn->load('client'))]);
    }
}

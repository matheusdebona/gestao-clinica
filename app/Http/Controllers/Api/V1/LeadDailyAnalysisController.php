<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeadDailyCheckin;
use App\Models\User;
use App\Services\LeadDailyAnalysisService;
use App\Support\CurrentClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resumo do dia (auto análise diária). Everyone with leads.view sees their own day;
 * metrics.view (gestão) sees every user and the team comparison.
 */
class LeadDailyAnalysisController extends Controller
{
    public function __construct(private readonly LeadDailyAnalysisService $analysis) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'stale_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $team = $this->isTeam($user);
        [$from, $to] = $this->range($request);

        $filters = [
            'user_ids' => $team ? $this->idList($request->input('user_ids')) : [$user->id],
            'origin_ids' => $this->idList($request->input('origin_ids')),
            'status_ids' => $this->idList($request->input('status_ids')),
            'stale_days' => $request->filled('stale_days') ? $request->integer('stale_days') : null,
        ];

        $data = $this->analysis->analyze($from, $to, $filters, $team, $user);
        $data['filters'] = $filters;
        $data['users'] = ($team ? $this->analysis->teamUsers(CurrentClinic::id()) : collect([$user]))
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values();
        $data['viewer_id'] = $user->id;

        return response()->json(['data' => $data]);
    }

    public function checkins(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = $this->range($request);
        $ids = $this->isTeam($user) ? $this->idList($request->input('user_ids')) : [$user->id];

        $items = LeadDailyCheckin::query()->with('user')
            ->whereBetween('day', [$from, $to])
            ->when($ids !== [], fn ($q) => $q->whereIn('user_id', $ids))
            ->orderByDesc('day')->orderBy('user_id')
            ->limit(500)
            ->get()
            ->map(fn (LeadDailyCheckin $c) => $this->present($c));

        return response()->json(['data' => $items]);
    }

    public function showCheckin(Request $request, string $day): JsonResponse
    {
        $this->assertDay($day);
        $checkin = LeadDailyCheckin::query()->with('user')
            ->where('user_id', $request->user()->id)
            ->whereDate('day', $day)
            ->first();

        return response()->json([
            'data' => $checkin ? $this->present($checkin) : null,
            'editable' => $day === LeadDailyAnalysisService::today(),
        ]);
    }

    public function saveCheckin(Request $request, string $day): JsonResponse
    {
        $this->assertDay($day);
        if ($day !== LeadDailyAnalysisService::today()) {
            throw ValidationException::withMessages(['day' => ['Só dá para editar o check-in de hoje.']]);
        }

        $data = $request->validate([
            'worked' => ['nullable', 'string', 'max:2000'],
            'change' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            '*.max' => 'Texto muito longo (máximo de 2000 caracteres).',
        ]);
        $clean = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, [
            'worked' => $data['worked'] ?? null,
            'change' => $data['change'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        if (array_filter($clean) === []) {
            throw ValidationException::withMessages(['worked' => ['Escreva pelo menos um campo.']]);
        }

        $existing = LeadDailyCheckin::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('day', $day)
            ->first();
        $checkin = $existing ?? new LeadDailyCheckin([
            'clinic_id' => CurrentClinic::id(),
            'user_id' => $request->user()->id,
            'day' => $day,
        ]);
        $checkin->fill($clean)->save();

        return response()->json(['data' => $this->present($checkin->fresh('user'))], $existing ? 200 : 201);
    }

    private function isTeam(User $user): bool
    {
        return $user->can('metrics.view');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $today = LeadDailyAnalysisService::today();
        $from = $request->input('from') ?: $today;
        $to = $request->input('to') ?: $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function assertDay(string $day): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeadDailyCheckin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'day' => $checkin->day->toDateString(),
            'user' => $checkin->user ? ['id' => $checkin->user->id, 'name' => $checkin->user->name] : null,
            'worked' => $checkin->worked,
            'change' => $checkin->change,
            'notes' => $checkin->notes,
            'updated_at' => $checkin->updated_at,
        ];
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
}

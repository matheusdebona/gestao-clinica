<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Metas mensais: a administração (users.update) define; cada colaborador vê só a própria
 * meta; a visão da equipe exige users.view.
 */
class UserGoalController extends Controller
{
    public function __construct(private readonly UserGoalService $goals) {}

    public function me(Request $request): JsonResponse
    {
        $month = $this->month($request);

        return response()->json(['data' => $this->goals->progress($request->user(), $month, $request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        $month = $this->month($request);

        return response()->json([
            'data' => $this->goals->team($month, $request->user()),
            'meta' => ['month' => $month, 'can_edit' => $request->user()->can('users.update')],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'appointments_target' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
            'sales_target' => ['present', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'approached_target' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'month.date_format' => 'Informe o mês no formato AAAA-MM.',
            '*.min' => 'Informe um valor a partir de zero.',
            '*.integer' => 'Informe um número inteiro.',
            '*.numeric' => 'Informe um valor válido.',
        ]);

        if (! $this->goals->teamUsers()->contains('id', $user->id)) {
            abort(422, 'Este usuário não trabalha com leads ou vendas.');
        }

        $this->goals->save($user, $data['month'], $data, $request->user());

        return response()->json(['data' => $this->goals->progress($user, $data['month'], $request->user())]);
    }

    private function month(Request $request): string
    {
        $request->validate(['month' => ['sometimes', 'nullable', 'date_format:Y-m']]);

        return $request->filled('month') ? $request->string('month')->toString() : UserGoalService::currentMonth();
    }
}

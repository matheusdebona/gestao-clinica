<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ClientOrigin;
use App\Models\EquipmentAsset;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RecurringExpense;
use App\Services\ExpenseGenerator;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseGenerator $generator) {}

    /* ---------------- categorias ---------------- */

    public function categories(): JsonResponse
    {
        $categories = ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'data' => $categories->map(fn (ExpenseCategory $c) => $this->categoryPayload($c))->values(),
            'meta' => [
                'origins' => ClientOrigin::query()->orderBy('name')->get(['id', 'name']),
                'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'client_origin_id']),
            ],
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('expense_categories', 'name')->where('clinic_id', CurrentClinic::id())],
            'kind' => ['required', Rule::in(ExpenseCategory::KINDS)],
        ], ['name.unique' => 'Já existe uma categoria com esse nome.']);

        $max = (int) ExpenseCategory::query()->max('sort_order');
        $category = ExpenseCategory::query()->create([...$data, 'sort_order' => $max + 10]);

        return response()->json(['data' => $this->categoryPayload($category)], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('expense_categories', 'name')->where('clinic_id', CurrentClinic::id())->ignore($expenseCategory->id)],
            'kind' => ['sometimes', Rule::in(ExpenseCategory::KINDS)],
            'is_active' => ['sometimes', 'boolean'],
        ], ['name.unique' => 'Já existe uma categoria com esse nome.']);

        $expenseCategory->update($data);

        return response()->json(['data' => $this->categoryPayload($expenseCategory->fresh())]);
    }

    /* ---------------- lançamentos ---------------- */

    public function index(Request $request): JsonResponse
    {
        $month = $this->month($request->validate(['month' => ['required', 'date_format:Y-m']])['month']);

        if ($month->lte(CarbonImmutable::now()->startOfMonth())) {
            $this->generator->generateMonth((int) CurrentClinic::id(), $month);
        }

        $expenses = Expense::query()
            ->whereDate('competence_month', $month->toDateString())
            ->with(['category', 'clientOrigin:id,name', 'campaign:id,name'])
            ->orderBy('expense_category_id')
            ->orderBy('description')
            ->get();

        $byKind = [];
        foreach (ExpenseCategory::KINDS as $kind) {
            $byKind[$kind] = 0.0;
        }
        foreach ($expenses as $e) {
            $byKind[$e->category->kind] = ($byKind[$e->category->kind] ?? 0) + (float) $e->amount;
        }

        return response()->json([
            'data' => $expenses->map(fn (Expense $e) => $this->expensePayload($e))->values(),
            'meta' => [
                'month' => $month->format('Y-m'),
                'total' => $this->money((float) $expenses->sum('amount')),
                'paid' => $this->money((float) $expenses->whereNotNull('paid_on')->sum('amount')),
                'by_kind' => array_map(fn ($v) => $this->money($v), $byKind),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateExpense($request);
        $expense = Expense::query()->create([...$data, 'created_by_user_id' => $request->user()?->id]);

        return response()->json(['data' => $this->expensePayload($expense->load(['category', 'clientOrigin:id,name', 'campaign:id,name']))], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $expense->update($this->validateExpense($request, partial: true));

        return response()->json(['data' => $this->expensePayload($expense->fresh(['category', 'clientOrigin:id,name', 'campaign:id,name']))]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['message' => 'Despesa excluída.']);
    }

    /* ---------------- recorrentes ---------------- */

    public function recurring(): JsonResponse
    {
        $rows = RecurringExpense::query()
            ->with(['category', 'clientOrigin:id,name', 'campaign:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('description')
            ->get();

        return response()->json(['data' => $rows->map(fn (RecurringExpense $r) => $this->recurringPayload($r))->values()]);
    }

    public function storeRecurring(Request $request): JsonResponse
    {
        $recurring = RecurringExpense::query()->create($this->validateRecurring($request));
        $this->generateUntilNow($recurring->starts_month);

        return response()->json(['data' => $this->recurringPayload($recurring->load(['category', 'clientOrigin:id,name', 'campaign:id,name']))], 201);
    }

    public function updateRecurring(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        $recurringExpense->update($this->validateRecurring($request, partial: true));
        $this->generateUntilNow($recurringExpense->starts_month);

        return response()->json(['data' => $this->recurringPayload($recurringExpense->fresh(['category', 'clientOrigin:id,name', 'campaign:id,name']))]);
    }

    /* ---------------- equipamentos ---------------- */

    public function equipment(): JsonResponse
    {
        $rows = EquipmentAsset::query()->with('category')->orderByDesc('is_active')->orderBy('name')->get();

        return response()->json(['data' => $rows->map(fn (EquipmentAsset $a) => $this->equipmentPayload($a))->values()]);
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $asset = EquipmentAsset::query()->create($this->validateEquipment($request));
        $this->generateUntilNow($asset->depreciation_start_month);

        return response()->json(['data' => $this->equipmentPayload($asset->load('category'))], 201);
    }

    public function updateEquipment(Request $request, EquipmentAsset $equipmentAsset): JsonResponse
    {
        $equipmentAsset->update($this->validateEquipment($request, partial: true));
        $this->generateUntilNow($equipmentAsset->depreciation_start_month);

        return response()->json(['data' => $this->equipmentPayload($equipmentAsset->fresh('category'))]);
    }

    /* ---------------- helpers ---------------- */

    private function generateUntilNow(?\DateTimeInterface $start): void
    {
        if ($start === null) {
            return;
        }
        $now = CarbonImmutable::now()->startOfMonth();
        // Gera os meses passados também, para o DRE histórico refletir o cadastro (limite de 5 anos).
        $from = CarbonImmutable::instance($start)->startOfMonth()->max($now->subYears(5));
        if ($from->lte($now)) {
            $this->generator->generateRange((int) CurrentClinic::id(), $from, $now);
        }
    }

    private function month(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
    }

    /**
     * @return array<string, mixed>
     */
    private function commonRefs(Request $request): array
    {
        $clinicId = CurrentClinic::id();

        return [
            'expense_category_id' => ['integer', Rule::exists('expense_categories', 'id')->where('clinic_id', $clinicId)],
            'client_origin_id' => ['nullable', 'integer', Rule::exists('client_origins', 'id')->where('clinic_id', $clinicId)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('clinic_id', $clinicId)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExpense(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $refs = $this->commonRefs($request);
        $data = $request->validate([
            'expense_category_id' => [$req, ...$refs['expense_category_id']],
            'description' => [$req, 'string', 'max:160'],
            'amount' => [$req, 'numeric', 'gt:0', 'max:9999999999'],
            'competence_month' => [$req, 'date_format:Y-m'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'client_origin_id' => $refs['client_origin_id'],
            'campaign_id' => $refs['campaign_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['amount.gt' => 'Informe um valor maior que zero.']);

        if (isset($data['competence_month'])) {
            $data['competence_month'] = $this->month($data['competence_month'])->toDateString();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRecurring(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $refs = $this->commonRefs($request);
        $data = $request->validate([
            'expense_category_id' => [$req, ...$refs['expense_category_id']],
            'description' => [$req, 'string', 'max:160'],
            'amount' => [$req, 'numeric', 'gt:0', 'max:9999999999'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'starts_month' => [$req, 'date_format:Y-m'],
            'ends_month' => ['nullable', 'date_format:Y-m'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'client_origin_id' => $refs['client_origin_id'],
            'campaign_id' => $refs['campaign_id'],
            'is_active' => ['sometimes', 'boolean'],
        ], ['amount.gt' => 'Informe um valor maior que zero.']);

        foreach (['starts_month', 'ends_month'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->month($data[$field])->toDateString();
            }
        }
        if (! empty($data['ends_month']) && ! empty($data['starts_month']) && $data['ends_month'] < $data['starts_month']) {
            throw ValidationException::withMessages(['ends_month' => ['O fim deve ser depois do início.']]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEquipment(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $refs = $this->commonRefs($request);
        $data = $request->validate([
            'expense_category_id' => ['sometimes', ...$refs['expense_category_id']],
            'name' => [$req, 'string', 'max:160'],
            'purchase_value' => [$req, 'numeric', 'gt:0', 'max:9999999999'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'purchased_on' => ['nullable', 'date_format:Y-m-d'],
            'useful_life_months' => [$req, 'integer', 'min:1', 'max:600'],
            'depreciation_start_month' => [$req, 'date_format:Y-m'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['residual_value'], $data['purchase_value']) && (float) $data['residual_value'] >= (float) $data['purchase_value']) {
            throw ValidationException::withMessages(['residual_value' => ['O valor residual deve ser menor que o de compra.']]);
        }
        if (array_key_exists('residual_value', $data) && $data['residual_value'] === null) {
            $data['residual_value'] = 0;
        }
        if (isset($data['depreciation_start_month'])) {
            $data['depreciation_start_month'] = $this->month($data['depreciation_start_month'])->toDateString();
        }
        if (! $partial && empty($data['expense_category_id'])) {
            $data['expense_category_id'] = ExpenseCategory::query()
                ->where('kind', ExpenseCategory::KIND_DEPRECIATION)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->value('id') ?? throw ValidationException::withMessages([
                    'expense_category_id' => ['Crie uma categoria de depreciação.'],
                ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(ExpenseCategory $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'kind' => $c->kind,
            'is_system' => $c->is_system,
            'is_active' => $c->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Expense $e): array
    {
        return [
            'id' => $e->id,
            'expense_category_id' => $e->expense_category_id,
            'category_name' => $e->category?->name,
            'kind' => $e->category?->kind,
            'description' => $e->description,
            'amount' => $e->amount,
            'competence_month' => $e->competence_month->format('Y-m'),
            'due_on' => $e->due_on?->toDateString(),
            'paid_on' => $e->paid_on?->toDateString(),
            'supplier' => $e->supplier,
            'recurring_expense_id' => $e->recurring_expense_id,
            'equipment_asset_id' => $e->equipment_asset_id,
            'client_origin_id' => $e->client_origin_id,
            'client_origin_name' => $e->clientOrigin?->name,
            'campaign_id' => $e->campaign_id,
            'campaign_name' => $e->campaign?->name,
            'notes' => $e->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recurringPayload(RecurringExpense $r): array
    {
        return [
            'id' => $r->id,
            'expense_category_id' => $r->expense_category_id,
            'category_name' => $r->category?->name,
            'kind' => $r->category?->kind,
            'description' => $r->description,
            'amount' => $r->amount,
            'due_day' => $r->due_day,
            'starts_month' => $r->starts_month->format('Y-m'),
            'ends_month' => $r->ends_month?->format('Y-m'),
            'supplier' => $r->supplier,
            'client_origin_id' => $r->client_origin_id,
            'client_origin_name' => $r->clientOrigin?->name,
            'campaign_id' => $r->campaign_id,
            'campaign_name' => $r->campaign?->name,
            'is_active' => $r->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentPayload(EquipmentAsset $a): array
    {
        $now = CarbonImmutable::now();
        $elapsed = $a->monthsElapsed($now);
        $monthly = $a->monthlyDepreciation();
        $depreciable = max(0, (float) $a->purchase_value - (float) $a->residual_value);

        return [
            'id' => $a->id,
            'expense_category_id' => $a->expense_category_id,
            'category_name' => $a->category?->name,
            'name' => $a->name,
            'purchase_value' => $a->purchase_value,
            'residual_value' => $a->residual_value,
            'purchased_on' => $a->purchased_on?->toDateString(),
            'useful_life_months' => $a->useful_life_months,
            'depreciation_start_month' => $a->depreciation_start_month->format('Y-m'),
            'depreciation_end_month' => $a->lastDepreciationMonth()->format('Y-m'),
            'monthly_depreciation' => $this->money($monthly),
            'months_elapsed' => $elapsed,
            'book_value' => $this->money(max((float) $a->residual_value, (float) $a->purchase_value - min($depreciable, $monthly * $elapsed))),
            'is_active' => $a->is_active,
            'notes' => $a->notes,
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}

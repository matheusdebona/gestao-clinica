<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\StoreBudgetRequest;
use App\Http\Requests\Api\V1\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Models\Budget;
use App\Models\Sale;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    /**
     * Inbox of budgets in the current clinic.
     *
     * Query: `sale_id`, `client_id`, `status`. Superseded rows are hidden unless
     * `status` is set or `include_superseded=1`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Budget::query()
            ->with(['items', 'client', 'sale'])
            ->orderByDesc('id');

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->integer('sale_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        } elseif (! $request->boolean('include_superseded')) {
            $query->where('status', '!=', Budget::STATUS_SUPERSEDED);
        }

        return BudgetResource::collection($query->paginate(20));
    }

    public function indexForSale(Sale $sale): AnonymousResourceCollection
    {
        $budgets = Budget::query()
            ->where('sale_id', $sale->id)
            ->with(['items', 'client'])
            ->orderByDesc('version')
            ->paginate(20);

        return BudgetResource::collection($budgets);
    }

    public function store(StoreBudgetRequest $request, Sale $sale): JsonResponse
    {
        $budget = $this->budgets->createFromSale(
            $sale,
            $request->user()->id,
            $request->validated('notes'),
            $request->validated('valid_until'),
        );

        return (new BudgetResource($budget))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Budget $budget): BudgetResource
    {
        return new BudgetResource($budget->load(['items', 'client', 'sale', 'createdByUser']));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): BudgetResource
    {
        $budget = $this->budgets->updateDraft($budget, $request->validated());

        return new BudgetResource($budget);
    }

    public function send(Budget $budget): BudgetResource
    {
        return new BudgetResource($this->budgets->send($budget));
    }

    public function accept(Budget $budget): BudgetResource
    {
        return new BudgetResource($this->budgets->accept($budget));
    }

    public function reject(Budget $budget): BudgetResource
    {
        return new BudgetResource($this->budgets->reject($budget));
    }

    public function expire(Budget $budget): BudgetResource
    {
        return new BudgetResource($this->budgets->expire($budget));
    }
}

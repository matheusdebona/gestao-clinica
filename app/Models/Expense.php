<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Despesa por mês de competência. Lançamentos gerados por recorrência ou depreciação ficam
 * com recurring_expense_id / equipment_asset_id; excluir um deles não o recria (soft delete).
 */
class Expense extends Model
{
    use BelongsToClinic, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'expense_category_id',
        'description',
        'amount',
        'competence_month',
        'due_on',
        'paid_on',
        'supplier',
        'recurring_expense_id',
        'equipment_asset_id',
        'client_origin_id',
        'campaign_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'competence_month' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function clientOrigin(): BelongsTo
    {
        return $this->belongsTo(ClientOrigin::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

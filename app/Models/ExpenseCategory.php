<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToClinic;

    public const KIND_FIXED = 'fixed';

    public const KIND_VARIABLE = 'variable';

    public const KIND_MARKETING = 'marketing';

    public const KIND_PERSONNEL = 'personnel';

    public const KIND_TAXES = 'taxes';

    public const KIND_FINANCIAL = 'financial';

    public const KIND_DEPRECIATION = 'depreciation';

    public const KINDS = [
        self::KIND_FIXED,
        self::KIND_VARIABLE,
        self::KIND_MARKETING,
        self::KIND_PERSONNEL,
        self::KIND_TAXES,
        self::KIND_FINANCIAL,
        self::KIND_DEPRECIATION,
    ];

    protected $fillable = ['clinic_id', 'name', 'kind', 'is_system', 'is_active', 'sort_order'];

    protected $attributes = ['is_system' => false, 'is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}

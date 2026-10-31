<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Database\Factories\LeadStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    /** @use HasFactory<LeadStatusFactory> */
    use BelongsToClinic, HasFactory;

    public const SYSTEM_CONVERTED = 'converted';

    /**
     * Design-system tones (Badge variants). Never raw hex: see AGENTS.md.
     *
     * @var list<string>
     */
    public const COLORS = ['muted', 'purple', 'success', 'warning', 'danger'];

    protected $fillable = [
        'clinic_id',
        'name',
        'color',
        'sort_order',
        'is_active',
        'is_default',
        'system_key',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => 'muted',
        'sort_order' => 0,
        'is_active' => true,
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}

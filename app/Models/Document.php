<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const TYPE_BUDGET_PDF = 'budget_pdf';

    public const STATUS_ISSUED = 'issued';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_BUDGET_PDF,
    ];

    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use BelongsToClinic, HasFactory;

    protected $fillable = [
        'clinic_id',
        'client_id',
        'budget_id',
        'sale_id',
        'type',
        'status',
        'storage_path',
        'filename',
        'mime_type',
        'payload',
        'generated_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ISSUED,
        'mime_type' => 'application/pdf',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma chamada de ferramenta do conector MCP (quem, qual ferramenta, quando). */
class McpAuditLog extends Model
{
    use BelongsToClinic;

    public const UPDATED_AT = null;

    protected $fillable = [
        'clinic_id', 'user_id', 'oauth_client_id', 'tool', 'arguments', 'status', 'error', 'duration_ms', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

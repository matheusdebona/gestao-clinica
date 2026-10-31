<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_NOTE = 'note';

    public const TYPE_CONVERTED = 'converted';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_COMMENT = 'comment';

    public const TYPE_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'from_status_id',
        'to_status_id',
        'note',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'to_status_id');
    }
}

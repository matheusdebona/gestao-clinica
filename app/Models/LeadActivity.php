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

    public const TYPE_APPROACHED = 'approached';

    public const TYPE_REPLIED = 'replied';

    public const TYPE_BOOKED = 'booked';

    /** Types that count as the user working the lead ("tocou"). */
    public const TOUCH_TYPES = [
        self::TYPE_STATUS_CHANGED,
        self::TYPE_NOTE,
        self::TYPE_COMMENT,
        self::TYPE_WHATSAPP,
        self::TYPE_APPROACHED,
        self::TYPE_REPLIED,
        self::TYPE_BOOKED,
        self::TYPE_CONVERTED,
        self::TYPE_CANCELLED,
    ];

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

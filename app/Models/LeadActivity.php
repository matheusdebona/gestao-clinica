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
        'edited_at',
        'edited_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function isComment(): bool
    {
        return $this->type === self::TYPE_COMMENT;
    }

    /** Only the latest comment of the lead can be edited, by its author or a clinic admin. */
    public function canBeEditedBy(?User $user, ?int $latestCommentId = null): bool
    {
        if ($user === null || ! $this->isComment()) {
            return false;
        }
        $latestCommentId ??= self::latestCommentIdFor((int) $this->lead_id);
        if ((int) $latestCommentId !== (int) $this->id) {
            return false;
        }

        return (int) $this->user_id === (int) $user->id || $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function latestCommentIdFor(int $leadId): ?int
    {
        $id = self::query()
            ->where('lead_id', $leadId)
            ->where('type', self::TYPE_COMMENT)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Same ordering as latestCommentIdFor(), on an already loaded history. */
    public static function latestCommentIdIn(iterable $activities): ?int
    {
        $latest = collect($activities)
            ->filter(fn (LeadActivity $a) => $a->isComment())
            ->sort(fn (LeadActivity $a, LeadActivity $b) => [$b->created_at?->getTimestamp(), $b->id] <=> [$a->created_at?->getTimestamp(), $a->id])
            ->first();

        return $latest === null ? null : (int) $latest->id;
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
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

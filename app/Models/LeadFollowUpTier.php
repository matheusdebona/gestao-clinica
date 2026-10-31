<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/**
 * Follow-up alert period: an open lead with no activity for at least `days` days reaches this tier.
 */
class LeadFollowUpTier extends Model
{
    use BelongsToClinic;

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 365;

    protected $fillable = ['clinic_id', 'days', 'label', 'color'];

    protected function casts(): array
    {
        return ['days' => 'integer'];
    }

    public function displayLabel(): string
    {
        return $this->label !== null && trim($this->label) !== ''
            ? $this->label
            : "Parados há {$this->days}+ dias";
    }
}

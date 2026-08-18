<?php

namespace App\Models\Concerns;

use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToClinic
{
    public static function bootBelongsToClinic(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('clinic_id') !== null) {
                return;
            }

            $clinicId = CurrentClinic::id();

            if ($clinicId !== null) {
                $model->setAttribute('clinic_id', $clinicId);
            }
        });

        static::addGlobalScope('clinic', function (Builder $builder): void {
            $clinicId = CurrentClinic::id();

            if ($clinicId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.clinic_id',
                $clinicId
            );
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}

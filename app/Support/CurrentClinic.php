<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\User;

class CurrentClinic
{
    protected static ?int $id = null;

    public static function set(?Clinic $clinic): void
    {
        static::$id = $clinic?->id;
    }

    public static function setId(?int $id): void
    {
        static::$id = $id;
    }

    public static function id(): ?int
    {
        return static::$id;
    }

    public static function forget(): void
    {
        static::$id = null;
    }

    public static function fromUser(User $user): void
    {
        static::setId($user->clinic_id);
    }
}

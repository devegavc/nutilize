<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ApprovalClock
{
    public const TIMEZONE = 'Asia/Manila';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }
}

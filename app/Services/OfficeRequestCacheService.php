<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OfficeRequestCacheService
{
    private const CACHE_TTL = 5; // 5 minutes for queue data

    /**
     * Get office home data with caching per office
     */
    public static function getOfficeHomeData(int $officeId, bool $isPfAdmin = false): array
    {
        $cacheKey = "office.home.data.office.{$officeId}";

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($officeId, $isPfAdmin) {
            return [
                'totalRequests' => self::getTotalActionableRequests($officeId, $isPfAdmin),
                'pendingRequests' => self::getPendingRequests($officeId),
                'approvedRequests' => self::getApprovedRequests($officeId),
                'rejectedRequests' => self::getRejectedRequests($officeId),
            ];
        });
    }

    private static function getTotalActionableRequests(int $officeId, bool $isPfAdmin = false): int
    {
        if ($isPfAdmin) {
            return (int) Reservation::query()
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
                ->count();
        }

        return (int) ReservationApproval::query()
            ->where('office_id', $officeId)
            ->whereNull('approved_at')
            ->count();
    }

    private static function getPendingRequests(int $officeId): int
    {
        return (int) ReservationApproval::query()
            ->where('office_id', $officeId)
            ->whereNull('approved_at')
            ->count();
    }

    private static function getApprovedRequests(int $officeId): int
    {
        return (int) ReservationApproval::query()
            ->where('office_id', $officeId)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->count();
    }

    private static function getRejectedRequests(int $officeId): int
    {
        return (int) ReservationApproval::query()
            ->where('office_id', $officeId)
            ->where('status', 'rejected')
            ->whereNotNull('approved_at')
            ->count();
    }

    /**
     * Clear cache for an office
     */
    public static function clearCacheForOffice(int $officeId): void
    {
        Cache::forget("office.home.data.office.{$officeId}");
    }

    /**
     * Clear all office caches
     */
    public static function clearAllCaches(): void
    {
        Cache::flush(); // For office, clear all since we don't know all office IDs
    }
}

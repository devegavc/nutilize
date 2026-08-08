<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ItemOwnerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReservationApprovalNotifier
{
    /**
     * Notify admins in $officeId when that office should be alerted:
     * - it is the current actionable approver, or
     * - it is the Physical Facilities office (PF visibility across requests).
     * Skips users who already have an unread/read row for this reservation and type (idempotent).
     */
    public static function notifyOfficeIfRelevant(
        Reservation $reservation,
        int $officeId,
        ?int $actionableOfficeId,
        ?int $pfOfficeId,
    ): void {
        $office = Office::find($officeId);
        if (!$office) {
            return;
        }

        $officeId = (int) $officeId;
        $actionableOfficeId = !is_null($actionableOfficeId) ? (int) $actionableOfficeId : null;
        $pfOfficeId = !is_null($pfOfficeId) ? (int) $pfOfficeId : null;

        $shouldNotify = ($actionableOfficeId !== null && $officeId === $actionableOfficeId)
            || ($pfOfficeId !== null && $officeId === $pfOfficeId);

        if (!$shouldNotify) {
            return;
        }

        $isPfOffice = $pfOfficeId !== null && $officeId === $pfOfficeId;

        $adminUsers = User::query()
            ->where('office_id', $officeId)
            ->where(function ($query) use ($isPfOffice) {
                $query->whereRaw('LOWER(role) IN (?, ?)', ['admin', 'pc_admin']);
                if ($isPfOffice) {
                    $query->orWhereRaw('LOWER(role) = ?', ['pf_admin']);
                }
            })
            ->get();

        if ($adminUsers->isEmpty()) {
            return;
        }

        $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();
        if (
            !is_null($itemOwnerOfficeId)
            && $officeId === $itemOwnerOfficeId
            && ItemOwnerService::reservationRequiresItemOwnerApproval((int) $reservation->reservation_id)
        ) {
            $adminUsers = $adminUsers->filter(
                fn (User $admin) => ItemOwnerService::reservationIncludesOwnerItems(
                    (int) $reservation->reservation_id,
                    (int) $admin->user_id,
                ) && ItemOwnerService::itemOwnerHasPendingApproval(
                    $admin,
                    (int) $reservation->reservation_id,
                )
            )->values();
        }

        if ($adminUsers->isEmpty()) {
            return;
        }

        $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
        $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';

        $adminUserIds = $adminUsers->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        // Batch check: fetch all user_ids that already have an unread notification for this reservation
        $alreadyNotified = DB::table('notifications')
            ->whereIn('user_id', $adminUserIds)
            ->where('related_id', $reservation->reservation_id)
            ->where('type', 'reservation_approval_request')
            ->whereRaw('"read" = false')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $now = now();
        foreach ($adminUsers as $admin) {
            if (isset($alreadyNotified[(int) $admin->user_id])) {
                continue;
            }

            try {
                DB::table('notifications')->insert([
                    'user_id' => (int) $admin->user_id,
                    'type' => 'reservation_approval_request',
                    'title' => 'Reservation approval needed',
                    'message' => "Request '{$activityName}' by {$requesterName} is waiting for your approval.",
                    'related_id' => (int) $reservation->reservation_id,
                    'read' => DB::raw('false'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Cache::forget('approval_notification_sync.user.' . (int) $admin->user_id);
            } catch (\Throwable $throwable) {
                \Log::error('Failed to create notification', [
                    'error' => $throwable->getMessage(),
                    'admin_id' => $admin->user_id,
                    'reservation_id' => $reservation->reservation_id,
                ]);
                report($throwable);
            }
        }
    }

    /**
     * Notify the next office in the workflow after another office approved.
     * Replaces any unread approval alerts for this reservation so the message clearly reflects the handoff.
     */
    public static function notifyOfficeAfterPriorApproval(
        Reservation $reservation,
        int $targetOfficeId,
        int $fromOfficeId,
        ?int $pfOfficeId = null,
    ): void {
        $targetOffice = Office::find($targetOfficeId);
        $fromOffice = Office::find($fromOfficeId);

        if (!$targetOffice || !$fromOffice) {
            return;
        }

        $targetOfficeId = (int) $targetOfficeId;
        $pfOfficeId = !is_null($pfOfficeId) ? (int) $pfOfficeId : null;
        $isPfOffice = $pfOfficeId !== null && $targetOfficeId === $pfOfficeId;

        $adminUsers = User::query()
            ->where('office_id', $targetOfficeId)
            ->where(function ($query) use ($isPfOffice) {
                $query->whereRaw('LOWER(role) IN (?, ?)', ['admin', 'pc_admin']);
                if ($isPfOffice) {
                    $query->orWhereRaw('LOWER(role) = ?', ['pf_admin']);
                }
            })
            ->get();

        if ($adminUsers->isEmpty()) {
            return;
        }

        $itemOwnerOfficeId = ItemOwnerService::itemOwnerOfficeId();
        if (
            !is_null($itemOwnerOfficeId)
            && $targetOfficeId === $itemOwnerOfficeId
            && ItemOwnerService::reservationRequiresItemOwnerApproval((int) $reservation->reservation_id)
        ) {
            $adminUsers = $adminUsers->filter(
                fn (User $admin) => ItemOwnerService::reservationIncludesOwnerItems(
                    (int) $reservation->reservation_id,
                    (int) $admin->user_id,
                ) && ItemOwnerService::itemOwnerHasPendingApproval(
                    $admin,
                    (int) $reservation->reservation_id,
                )
            )->values();
        }

        if ($adminUsers->isEmpty()) {
            return;
        }

        $requesterName = $reservation->user->full_name ?? $reservation->user->username ?? 'Unknown';
        $activityName = trim((string) $reservation->activity_name) ?: 'Reservation request';
        $fromLabel = self::officeHandoffLabel($fromOffice);
        $yourOfficeLabel = self::officeHandoffLabel($targetOffice);

        $message = "Request '{$activityName}' by {$requesterName} was approved by {$fromLabel}. It is now waiting for approval at {$yourOfficeLabel}.";

        foreach ($adminUsers as $admin) {
            Notification::query()
                ->where('user_id', $admin->user_id)
                ->where('related_id', $reservation->reservation_id)
                ->whereIn('type', ['reservation_approval_request', 'reservation_approval_handoff'])
                ->whereRaw('"read" = false')
                ->delete();

            try {
                DB::table('notifications')->insert([
                    'user_id' => (int) $admin->user_id,
                    'type' => 'reservation_approval_handoff',
                    'title' => 'Request forwarded to your office',
                    'message' => $message,
                    'related_id' => (int) $reservation->reservation_id,
                    'read' => DB::raw('false'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Cache::forget('approval_notification_sync.user.' . (int) $admin->user_id);
            } catch (\Throwable $throwable) {
                \Log::error('Failed to create handoff notification', [
                    'error' => $throwable->getMessage(),
                    'admin_id' => $admin->user_id,
                    'reservation_id' => $reservation->reservation_id,
                ]);
                report($throwable);
            }
        }
    }

    private static function officeHandoffLabel(Office $office): string
    {
        $code = strtoupper(trim((string) ($office->short_code ?? '')));

        if ($code !== '') {
            return match ($code) {
                'PC' => 'Program Chair',
                'SDAO' => 'Student Development and Activities Office',
                'IO' => 'Inventory Office',
                'DO' => 'Discipline Office',
                'SEC' => 'Security',
                'PF' => 'Physical Facilities',
                'GENED' => 'General Education',
                default => $code,
            };
        }

        $name = trim((string) ($office->department_name ?? ''));

        return $name !== '' ? $name : 'Previous office';
    }
}

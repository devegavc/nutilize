<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ItemOwnerService
{
    private static ?int $itemOwnerOfficeIdCache = null;

    /** @var array<int, int|null> Per-request memo for ownerIdForUser(). */
    private static array $ownerIdByUserCache = [];

    public static function itemOwnerOfficeId(): ?int
    {
        if (self::$itemOwnerOfficeIdCache !== null) {
            return self::$itemOwnerOfficeIdCache;
        }

        $officeId = DB::table('offices')
            ->whereRaw('LOWER(TRIM(short_code)) = ?', ['io'])
            ->value('office_id');

        self::$itemOwnerOfficeIdCache = $officeId ? (int) $officeId : null;

        return self::$itemOwnerOfficeIdCache;
    }

    /**
     * Owner records that represent Physical Facilities stock (not personal item owners).
     *
     * @return array<int, int>
     */
    public static function physicalFacilitiesOwnerIds(): array
    {
        if (!Schema::hasTable('item_owners')) {
            return [];
        }

        return DB::table('item_owners')
            ->where(function ($query) {
                $query
                    ->whereRaw("LOWER(TRIM(COALESCE(department_affiliation, ''))) IN ('pfo', 'pf', 'physical facilities')")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(owner_name, ''))) LIKE '%physical facilities%'");
            })
            // Personal item-owner accounts are linked to a user_id; PF stock is not.
            ->where(function ($query) {
                $query->whereNull('user_id')->orWhere('user_id', 0);
            })
            ->pluck('owner_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function physicalFacilitiesOwnerId(): ?int
    {
        $ids = self::physicalFacilitiesOwnerIds();

        return $ids[0] ?? null;
    }

    public static function isItemOwnerUser(User $user): bool
    {
        $itemOwnerOfficeId = self::itemOwnerOfficeId();

        if (!$itemOwnerOfficeId || strtolower((string) $user->role) !== 'admin') {
            return false;
        }

        return (int) $user->office_id === $itemOwnerOfficeId;
    }

    public static function isUserLinkedOwner(object $ownerRow): bool
    {
        if (Schema::hasColumn('item_owners', 'user_id')) {
            $userId = (int) ($ownerRow->user_id ?? 0);

            if ($userId > 0) {
                return true;
            }
        }

        $affiliation = strtolower(trim((string) ($ownerRow->department_affiliation ?? '')));

        return $affiliation !== '' && str_starts_with($affiliation, 'user:');
    }

    public static function ownerIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !Schema::hasTable('item_owners')) {
            return null;
        }

        // Several call sites on the item-owner queue path ask for this within the same
        // request; each miss costs a round trip to Supabase.
        if (array_key_exists($userId, self::$ownerIdByUserCache)) {
            return self::$ownerIdByUserCache[$userId];
        }

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $ownerId = DB::table('item_owners')
                ->where('user_id', $userId)
                ->value('owner_id');

            if ($ownerId) {
                return self::$ownerIdByUserCache[$userId] = (int) $ownerId;
            }
        }

        $legacyKey = 'user:' . $userId;

        $ownerId = DB::table('item_owners')
            ->whereRaw('LOWER(department_affiliation) = ?', [strtolower($legacyKey)])
            ->value('owner_id');

        return self::$ownerIdByUserCache[$userId] = $ownerId ? (int) $ownerId : null;
    }

    public static function ensureForUser(User $user): int
    {
        $userId = (int) $user->user_id;
        $username = trim((string) $user->username);
        $fullName = trim((string) ($user->full_name ?? ''));

        if ($userId <= 0 || $username === '') {
            throw new \InvalidArgumentException('User details are missing for item ownership mapping.');
        }

        $ownerDisplayName = $fullName !== '' ? $fullName : $username;
        $existingOwnerId = self::ownerIdForUser($userId);

        if ($existingOwnerId) {
            $updatePayload = [
                'owner_name' => $ownerDisplayName,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('item_owners', 'user_id')) {
                $updatePayload['user_id'] = $userId;
                $updatePayload['department_affiliation'] = null;
            }

            DB::table('item_owners')
                ->where('owner_id', $existingOwnerId)
                ->update($updatePayload);

            self::$ownerIdByUserCache[$userId] = $existingOwnerId;

            return $existingOwnerId;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('item_owners', 'owner_id'), COALESCE(MAX(owner_id), 0) + 1, false) FROM item_owners");

        $insertPayload = [
            'owner_name' => $ownerDisplayName,
            'department_affiliation' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $insertPayload['user_id'] = $userId;
        } else {
            $insertPayload['department_affiliation'] = 'user:' . $userId;
        }

        return self::$ownerIdByUserCache[$userId] = (int) DB::table('item_owners')->insertGetId($insertPayload, 'owner_id');
    }

    public static function syncForUser(User $user): ?int
    {
        if (!self::isItemOwnerUser($user)) {
            return null;
        }

        return self::ensureForUser($user);
    }

    /**
     * Distinct item-owner records linked to a user account for items in this reservation.
     *
     * @return array<int, int>
     */
    public static function distinctUserLinkedOwnerIdsForReservation(int $reservationId): array
    {
        if ($reservationId <= 0 || !Schema::hasTable('item_owners')) {
            return [];
        }

        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->where('details.reservation_id', $reservationId);

        if (Schema::hasColumn('item_owners', 'user_id')) {
            return $query
                ->whereNotNull('owners.user_id')
                ->distinct()
                ->pluck('owners.owner_id')
                ->map(fn ($ownerId) => (int) $ownerId)
                ->values()
                ->all();
        }

        return $query
            ->whereRaw("LOWER(COALESCE(owners.department_affiliation, '')) LIKE 'user:%'")
            ->distinct()
            ->pluck('owners.owner_id')
            ->map(fn ($ownerId) => (int) $ownerId)
            ->values()
            ->all();
    }

    public static function reservationRequiresItemOwnerApproval(int $reservationId): bool
    {
        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->where('details.reservation_id', $reservationId);

        if (Schema::hasColumn('item_owners', 'user_id')) {
            return (clone $query)->whereNotNull('owners.user_id')->exists();
        }

        return $query
            ->whereRaw("LOWER(COALESCE(owners.department_affiliation, '')) LIKE 'user:%'")
            ->exists();
    }

    public static function reservationIncludesOwnerItems(int $reservationId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->where('details.reservation_id', $reservationId);

        if (Schema::hasColumn('item_owners', 'user_id')) {
            return (clone $query)->where('owners.user_id', $userId)->exists();
        }

        $ownerKey = strtolower('user:' . $userId);

        return $query
            ->whereRaw('LOWER(COALESCE(owners.department_affiliation, \'\')) = ?', [$ownerKey])
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    public static function reservationIdsIncludingOwnerItems(int $userId): array
    {
        if ($userId <= 0 || !Schema::hasTable('item_owners')) {
            return [];
        }

        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id');

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $query->where('owners.user_id', $userId);
        } else {
            $query->whereRaw('LOWER(COALESCE(owners.department_affiliation, \'\')) = ?', [strtolower('user:' . $userId)]);
        }

        return $query
            ->distinct()
            ->pluck('details.reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function openReservationIdsForItemOwner(int $userId, int $limit = 60): array
    {
        if ($userId <= 0) {
            return [];
        }

        $ioOfficeId = self::itemOwnerOfficeId();
        $ownerId = self::ownerIdForUser($userId);

        // Prefer pending IO approval rows (indexed) over scanning all reservation_details.
        if ($ioOfficeId && $ownerId) {
            $query = DB::table('reservation_approvals as ra')
                ->join('reservations as reservations', 'reservations.reservation_id', '=', 'ra.reservation_id')
                ->where('ra.office_id', $ioOfficeId)
                ->whereNull('ra.approved_at')
                ->where(function ($builder) use ($ownerId) {
                    $builder->where('ra.owner_id', $ownerId)->orWhereNull('ra.owner_id');
                });

            \App\Support\OpenReservationScope::apply($query, 'reservations.overall_status');

            return $query
                ->select('ra.reservation_id')
                ->selectRaw('MAX(reservations.created_at) as reservation_created_at')
                ->groupBy('ra.reservation_id')
                ->orderByDesc('reservation_created_at')
                ->limit(max(1, $limit))
                ->pluck('ra.reservation_id')
                ->map(fn ($reservationId) => (int) $reservationId)
                ->all();
        }

        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id');

        \App\Support\OpenReservationScope::apply($query, 'reservations.overall_status');

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $query->where('owners.user_id', $userId);
        } else {
            $query->whereRaw('LOWER(COALESCE(owners.department_affiliation, \'\')) = ?', [strtolower('user:' . $userId)]);
        }

        return $query
            ->select('details.reservation_id')
            ->selectRaw('MAX(reservations.created_at) as reservation_created_at')
            ->groupBy('details.reservation_id')
            ->orderByDesc('reservation_created_at')
            ->limit(max(1, $limit))
            ->pluck('details.reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();
    }

    public static function applyItemOwnerPendingApprovalScope($query, User $user): void
    {
        if (!self::isItemOwnerUser($user)) {
            return;
        }

        $ownerId = self::ownerIdForUser((int) $user->user_id);
        if (!$ownerId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($builder) use ($ownerId) {
            $builder->where('owner_id', $ownerId)
                ->orWhere(function ($legacy) {
                    $legacy->whereNull('owner_id')
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw('1'))
                                ->from('reservation_approvals as scoped_io_rows')
                                ->whereColumn('scoped_io_rows.reservation_id', 'reservation_approvals.reservation_id')
                                ->whereColumn('scoped_io_rows.office_id', 'reservation_approvals.office_id')
                                ->whereNotNull('scoped_io_rows.owner_id');
                        });
                });
        });
    }

    public static function reservationHasScopedIoApprovals(int $reservationId): bool
    {
        $ioOfficeId = self::itemOwnerOfficeId();
        if (!$ioOfficeId) {
            return false;
        }

        return DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->where('office_id', $ioOfficeId)
            ->whereNotNull('owner_id')
            ->exists();
    }

    /**
     * Item owners share the IO office queue, so only keep IO-step requests for their own items.
     *
     * @param  array<int, int>  $actionableOfficeIds
     * @param  array<int, int>  $actionableReservationIds
     * @return array<int, int>
     */
    public static function filterActionableReservationIdsForItemOwner(User $user, array $actionableOfficeIds, array $actionableReservationIds): array
    {
        if (!self::isItemOwnerUser($user)) {
            return array_values(array_unique(array_map('intval', $actionableReservationIds)));
        }

        $itemOwnerOfficeId = self::itemOwnerOfficeId();
        if (is_null($itemOwnerOfficeId)) {
            return [];
        }

        $userId = (int) $user->user_id;
        $candidateIds = [];

        foreach (array_values(array_unique(array_map('intval', $actionableReservationIds))) as $reservationId) {
            if ((int) ($actionableOfficeIds[$reservationId] ?? 0) === $itemOwnerOfficeId) {
                $candidateIds[] = $reservationId;
            }
        }

        if ($candidateIds === []) {
            return [];
        }

        $withOwnerItems = self::reservationIdsIncludingOwnerItemsAmong($candidateIds, $userId);
        if ($withOwnerItems === []) {
            return [];
        }

        $pendingIds = self::reservationIdsWithPendingItemOwnerApproval($user, $withOwnerItems);

        return array_values(array_intersect($withOwnerItems, $pendingIds));
    }

    /**
     * @param  array<int, int>  $reservationIds
     * @return array<int, int>
     */
    public static function reservationIdsIncludingOwnerItemsAmong(array $reservationIds, int $userId): array
    {
        if ($userId <= 0 || $reservationIds === []) {
            return [];
        }

        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));

        $query = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->join('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->whereIn('details.reservation_id', $reservationIds);

        if (Schema::hasColumn('item_owners', 'user_id')) {
            $query->where('owners.user_id', $userId);
        } else {
            $query->whereRaw('LOWER(COALESCE(owners.department_affiliation, \'\')) = ?', [strtolower('user:' . $userId)]);
        }

        return $query
            ->distinct()
            ->pluck('details.reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();
    }

    /**
     * @param  array<int, int>  $reservationIds
     * @return array<int, int>
     */
    public static function reservationIdsWithPendingItemOwnerApproval(User $user, array $reservationIds): array
    {
        if (!self::isItemOwnerUser($user) || $reservationIds === []) {
            return [];
        }

        $ioOfficeId = self::itemOwnerOfficeId();
        $ownerId = self::ownerIdForUser((int) $user->user_id);

        if (is_null($ioOfficeId) || is_null($ownerId)) {
            return [];
        }

        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));

        $query = DB::table('reservation_approvals')
            ->whereIn('reservation_id', $reservationIds)
            ->where('office_id', $ioOfficeId)
            ->whereNull('approved_at')
            ->whereRaw("LOWER(COALESCE(status, 'pending')) NOT IN ('approved', 'rejected')");

        if (Schema::hasColumn('reservation_approvals', 'owner_id')) {
            $query->where(function ($builder) use ($ownerId) {
                $builder->where('owner_id', $ownerId)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('owner_id')
                            ->whereNotExists(function ($sub) {
                                $sub->select(DB::raw('1'))
                                    ->from('reservation_approvals as scoped_io_rows')
                                    ->whereColumn('scoped_io_rows.reservation_id', 'reservation_approvals.reservation_id')
                                    ->whereColumn('scoped_io_rows.office_id', 'reservation_approvals.office_id')
                                    ->whereNotNull('scoped_io_rows.owner_id');
                            });
                    });
            });
        }

        return $query
            ->distinct()
            ->pluck('reservation_id')
            ->map(fn ($reservationId) => (int) $reservationId)
            ->all();
    }

    public static function itemOwnerHasPendingApproval(User $user, int $reservationId): bool
    {
        if (!self::isItemOwnerUser($user)) {
            return false;
        }

        $ioOfficeId = self::itemOwnerOfficeId();
        $ownerId = self::ownerIdForUser((int) $user->user_id);

        if (is_null($ioOfficeId) || is_null($ownerId)) {
            return false;
        }

        $query = DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->where('office_id', $ioOfficeId)
            ->whereNull('approved_at')
            ->whereRaw("LOWER(COALESCE(status, 'pending')) NOT IN ('approved', 'rejected')");

        if (Schema::hasColumn('reservation_approvals', 'owner_id')) {
            if (self::reservationHasScopedIoApprovals($reservationId)) {
                $query->where('owner_id', $ownerId);
            } else {
                $query->where(function ($builder) use ($ownerId) {
                    $builder
                        ->where('owner_id', $ownerId)
                        ->orWhereNull('owner_id');
                });
            }
        }

        return $query->exists();
    }

    public static function itemOwnerCanActOnReservation(User $user, int $reservationId): bool
    {
        if (!self::isItemOwnerUser($user)) {
            return true;
        }

        $itemOwnerOfficeId = self::itemOwnerOfficeId();
        if (is_null($itemOwnerOfficeId) || (int) $user->office_id !== $itemOwnerOfficeId) {
            return false;
        }

        return self::reservationIncludesOwnerItems($reservationId, (int) $user->user_id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationApproval;
use App\Services\ReservationApprovalNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficeRequestController extends Controller
{
    private ?array $officeIdsByShortCodeCache = null;
    private ?int $physicalFacilitiesOfficeIdCache = null;
    private ?array $officeIdByDepartmentNameCache = null;
    private array $ownerOfficeIdCache = [];

    /** @var array<int, true>|null Batch gym flags for getActionableOfficeIdsForReservations (avoid N queries per reservation). */
    private ?array $batchGymLookup = null;

    /** @var array<int, true>|null */
    private ?array $batchGymWithItemsLookup = null;

    public function index()
    {
        $user = Auth::user();

        if (!$user || !$user->isOfficeApprover()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        return view('office-home', $this->buildOfficeHomeData($user));
    }

    public function queueSnapshot()
    {
        $user = Auth::user();

        if (!$user || !$user->isOfficeApprover()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access.',
            ], 403);
        }

        $viewData = $this->buildOfficeHomeData($user);

        return response()->json([
            'success' => true,
            'summary' => [
                'actionable' => (int) ($viewData['totalRequests'] ?? 0),
                'pending' => (int) ($viewData['pendingRequests'] ?? 0),
                'approved' => (int) ($viewData['approvedRequests'] ?? 0),
                'rejected' => (int) ($viewData['rejectedRequests'] ?? 0),
            ],
            'rows_html' => view('partials.office-request-rows', ['requests' => $viewData['requests']])->render(),
            'pagination_html' => $viewData['requests']->links()->toHtml(),
        ]);
    }

    private function buildOfficeHomeData($user): array
    {
        $openReservationIds = Reservation::query()
            ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
            ->pluck('reservation_id')
            ->all();

        // Automatic workflow sync on every page load can time out on large datasets.
        $actionableReservationIds = [];

        if ($user->isPhysicalFacilitiesAdmin()) {
            $actionableReservationIds = $openReservationIds;
        } else {
            $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($openReservationIds);
            $this->ensureActionableApprovalRows($actionableOfficeIds, (int) $user->office_id);

            foreach ($actionableOfficeIds as $reservationId => $officeId) {
                if ($officeId === (int) $user->office_id) {
                    $actionableReservationIds[] = (int) $reservationId;
                }
            }
        }

        $requests = ReservationApproval::query()
            ->where('office_id', $user->office_id)
            ->whereNull('approved_at')
            ->whereIn('reservation_id', $actionableReservationIds)
            ->with(['reservation.user', 'reservation.details'])
            ->orderByDesc('created_at')
            ->paginate(10);

        $user->loadMissing('office');

        return [
            'requests' => $requests,
            'totalRequests' => count($actionableReservationIds),
            'pendingRequests' => $requests->total(),
            'approvedRequests' => ReservationApproval::query()
                ->where('office_id', $user->office_id)
                ->where('status', 'approved')
                ->whereNotNull('approved_at')
                ->count(),
            'rejectedRequests' => ReservationApproval::query()
                ->where('office_id', $user->office_id)
                ->where('status', 'rejected')
                ->whereNotNull('approved_at')
                ->count(),
            'authUser' => $user,
            'officeName' => $user->office?->department_name ?? null,
        ];
    }

    private function syncReservationApprovalWorkflow(?array $reservationIds = null): void
    {
        if (is_null($reservationIds)) {
            $reservationIds = Reservation::query()
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled', 'expired'])
                ->orderByDesc('created_at')
                ->limit(80)
                ->pluck('reservation_id')
                ->all();
        }

        foreach ($reservationIds as $reservationId) {
            $this->syncReservationApprovals((int) $reservationId);
        }
    }

    private function syncReservationApprovals(int $reservationId): void
    {
        $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);

        if (empty($workflowOfficeIds)) {
            return;
        }

        foreach ($workflowOfficeIds as $officeId) {
            $exists = DB::table('reservation_approvals')
                ->where('reservation_id', $reservationId)
                ->where('office_id', $officeId)
                ->exists();

            if (!$exists) {
                DB::table('reservation_approvals')->insert([
                    'reservation_id' => $reservationId,
                    'office_id' => $officeId,
                    'approved_by_user_id' => null,
                    'status' => 'pending',
                    'approved_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }

        DB::table('reservation_approvals')
            ->where('reservation_id', $reservationId)
            ->whereIn('office_id', $workflowOfficeIds)
            ->whereNull('status')
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    private function getCurrentActionableOfficeId(int $reservationId): ?int
    {
        return $this->getActionableOfficeIdsForReservations([$reservationId])[$reservationId] ?? null;
    }

    private function getActionableOfficeIdsForReservations(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }

        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));

        $this->warmBatchWorkflowLookups($reservationIds);

        try {
            $approvalsByReservation = ReservationApproval::query()
                ->whereIn('reservation_id', $reservationIds)
                ->get(['reservation_id', 'office_id', 'status', 'approved_at'])
                ->groupBy('reservation_id');

            $actionableOfficeIds = [];

            foreach ($reservationIds as $reservationId) {
                $reservationId = (int) $reservationId;
                $actionSequence = $this->resolveWorkflowOfficeIds($reservationId, false);

                if (empty($actionSequence)) {
                    continue;
                }

                $approvals = ($approvalsByReservation->get($reservationId) ?? collect())
                    ->keyBy(fn (ReservationApproval $row) => (int) $row->office_id);

                foreach ($actionSequence as $officeId) {
                    $officeId = (int) $officeId;
                    $approval = $approvals->get($officeId);
                    $status = strtolower((string) ($approval?->status ?? 'pending'));

                    if ($status === 'rejected' && !is_null($approval?->approved_at)) {
                        continue 2;
                    }

                    if ($status !== 'approved' || is_null($approval?->approved_at)) {
                        $actionableOfficeIds[$reservationId] = (int) $officeId;
                        continue 2;
                    }
                }
            }

            return $actionableOfficeIds;
        } finally {
            $this->batchGymLookup = null;
            $this->batchGymWithItemsLookup = null;
        }
    }

    /**
     * Preload gym flags and item-owner rows for many reservations in O(1) queries instead of per-id exists()/joins.
     */
    private function warmBatchWorkflowLookups(array $reservationIds): void
    {
        $this->batchGymLookup = [];
        $this->batchGymWithItemsLookup = [];

        if (!Schema::hasTable('reservation_details')) {
            return;
        }

        $gymIds = DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->distinct()
            ->pluck('details.reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($gymIds as $gid) {
            $this->batchGymLookup[$gid] = true;
        }

        if ($gymIds !== []) {
            $gymWithItemsIds = DB::table('reservation_details as details')
                ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
                ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
                ->whereIn('details.reservation_id', $gymIds)
                ->whereNotNull('items.item_id')
                ->distinct()
                ->pluck('details.reservation_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($gymWithItemsIds as $gid) {
                $this->batchGymWithItemsLookup[$gid] = true;
            }
        }

        $officeIdsByCode = $this->getOfficeIdsByShortCode();
        $pfOfficeId = $officeIdsByCode['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        $ownerRows = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->select(['details.reservation_id', 'owners.owner_name', 'owners.department_affiliation'])
            ->get();

        $grouped = [];

        foreach ($ownerRows as $row) {
            $rid = (int) $row->reservation_id;
            $grouped[$rid][] = $row;
        }

        foreach ($reservationIds as $rid) {
            if (array_key_exists($rid, $this->ownerOfficeIdCache)) {
                continue;
            }

            $rows = $grouped[$rid] ?? [];
            $this->ownerOfficeIdCache[$rid] = $this->computeOwnerOfficeIdFromItemOwnerRows($rows, $officeIdsByCode, $pfOfficeId);
        }
    }

    private function ensureActionableApprovalRows(array $actionableOfficeIds, int $officeId): void
    {
        if ($officeId <= 0 || empty($actionableOfficeIds)) {
            return;
        }

        $targetReservationIds = [];

        foreach ($actionableOfficeIds as $reservationId => $actionableOfficeId) {
            if ((int) $actionableOfficeId === $officeId) {
                $targetReservationIds[] = (int) $reservationId;
            }
        }

        if (empty($targetReservationIds)) {
            return;
        }

        $existingReservationIds = DB::table('reservation_approvals')
            ->whereIn('reservation_id', $targetReservationIds)
            ->where('office_id', $officeId)
            ->pluck('reservation_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $missingReservationIds = array_values(array_diff($targetReservationIds, $existingReservationIds));

        if (empty($missingReservationIds)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($missingReservationIds as $reservationId) {
            $rows[] = [
                'reservation_id' => $reservationId,
                'office_id' => $officeId,
                'approved_by_user_id' => null,
                'status' => 'pending',
                'approved_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }

        DB::table('reservation_approvals')->insert($rows);

        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        foreach ($missingReservationIds as $reservationId) {
            $reservation = Reservation::with('user')->find($reservationId);
            if (!$reservation) {
                continue;
            }

            $actionableOfficeId = $this->getCurrentActionableOfficeId((int) $reservationId);

            ReservationApprovalNotifier::notifyOfficeIfRelevant(
                $reservation,
                $officeId,
                $actionableOfficeId,
                $pfOfficeId,
            );

            if (!is_null($pfOfficeId)) {
                ReservationApprovalNotifier::notifyOfficeIfRelevant(
                    $reservation,
                    (int) $pfOfficeId,
                    $actionableOfficeId,
                    $pfOfficeId,
                );
            }
        }
    }

    private function resolveWorkflowOfficeIds(int $reservationId, bool $includePf): array
    {
        $ids = $this->getOfficeIdsByShortCode();
        $actionSequence = $this->getActionSequenceOfficeIds();

        if (empty($actionSequence)) {
            return [];
        }

        $pfOfficeId = $ids['PF'] ?? $this->getPhysicalFacilitiesOfficeId();
        $ownerOfficeId = $this->resolveOwnerOfficeId($reservationId, $ids, $pfOfficeId);
        $pcOfficeId = $ids['PC'] ?? null;
        $genEdOfficeId = $ids['GENED'] ?? null;
        $startOfficeId = $ownerOfficeId;

        if ($this->isGymRoomRequest($reservationId) && !is_null($genEdOfficeId)) {
            $gymOwnerOfficeId = (!is_null($ownerOfficeId) && (is_null($pfOfficeId) || (int) $ownerOfficeId !== (int) $pfOfficeId))
                ? (int) $ownerOfficeId
                : null;

            if ($this->isGymRoomRequestWithItems($reservationId) && !is_null($gymOwnerOfficeId)) {
                $actionSequence = array_values(array_filter([
                    $genEdOfficeId,
                    $gymOwnerOfficeId,
                    $pcOfficeId,
                    $ids['SDAO'] ?? null,
                    $ids['DO'] ?? null,
                    $ids['SEC'] ?? null,
                ]));
            } else {
                $actionSequence = array_values(array_filter([
                    $genEdOfficeId,
                    $pcOfficeId,
                    $ids['SDAO'] ?? null,
                    $ids['DO'] ?? null,
                    $ids['SEC'] ?? null,
                ]));
            }

            $startOfficeId = $genEdOfficeId;
        } else {
            if (is_null($startOfficeId) || (!is_null($pfOfficeId) && (int) $startOfficeId === (int) $pfOfficeId)) {
                $startOfficeId = $pcOfficeId;
            }
        }

        $startIndex = 0;
        if (!is_null($startOfficeId)) {
            $foundIndex = array_search($startOfficeId, $actionSequence, true);
            if ($foundIndex !== false) {
                $startIndex = $foundIndex;
            }
        }

        $workflowOfficeIds = array_slice($actionSequence, $startIndex);

        if ($includePf && !is_null($pfOfficeId) && !in_array($pfOfficeId, $workflowOfficeIds, true)) {
            $workflowOfficeIds[] = $pfOfficeId;
        }

        $seen = [];
        $deduped = [];
        foreach ($workflowOfficeIds as $officeId) {
            $officeId = (int) $officeId;
            if ($officeId <= 0 || isset($seen[$officeId])) {
                continue;
            }
            $seen[$officeId] = true;
            $deduped[] = $officeId;
        }

        return $deduped;
    }

    private function getOfficeIdsByShortCode(): array
    {
        if (!is_null($this->officeIdsByShortCodeCache)) {
            return $this->officeIdsByShortCodeCache;
        }

        $rows = DB::table('offices')
            ->select(['office_id', 'short_code'])
            ->whereNotNull('short_code')
            ->get();

        $ids = [];

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->short_code ?? '')));
            if ($code !== '') {
                $ids[$code] = (int) $row->office_id;
            }
        }

        $this->officeIdsByShortCodeCache = $ids;

        return $this->officeIdsByShortCodeCache;
    }

    /**
     * Return all office IDs per short_code (supports multiple IO offices).
     *
     * @return array<string, array<int, int>>
     */
    private function getOfficeIdsByShortCodeMulti(): array
    {
        $rows = DB::table('offices')
            ->select(['office_id', 'short_code', 'order_sequence'])
            ->whereNotNull('short_code')
            ->orderByRaw('COALESCE(order_sequence, 999999) ASC')
            ->orderBy('office_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->short_code ?? '')));
            if ($code === '') {
                continue;
            }
            $map[$code] ??= [];
            $map[$code][] = (int) $row->office_id;
        }

        return $map;
    }

    private function getActionSequenceOfficeIds(): array
    {
        $ids = $this->getOfficeIdsByShortCode();

        return array_values(array_filter([
            $ids['IO'] ?? null,
            $ids['PC'] ?? null,
            $ids['SDAO'] ?? null,
            $ids['DO'] ?? null,
            $ids['SEC'] ?? null,
        ]));
    }

    private function getPhysicalFacilitiesOfficeId(): ?int
    {
        if (!is_null($this->physicalFacilitiesOfficeIdCache)) {
            return $this->physicalFacilitiesOfficeIdCache;
        }

        $this->physicalFacilitiesOfficeIdCache = DB::table('offices')
            ->whereRaw('LOWER(department_name) = ?', ['physical facilities'])
            ->value('office_id');

        return $this->physicalFacilitiesOfficeIdCache;
    }

    private function getOfficeIdByDepartmentName(): array
    {
        if (!is_null($this->officeIdByDepartmentNameCache)) {
            return $this->officeIdByDepartmentNameCache;
        }

        $rows = DB::table('offices')
            ->select(['office_id', 'department_name'])
            ->whereNotNull('department_name')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $name = strtolower(trim((string) ($row->department_name ?? '')));
            if ($name !== '') {
                $map[$name] = (int) $row->office_id;
            }
        }

        $this->officeIdByDepartmentNameCache = $map;

        return $this->officeIdByDepartmentNameCache;
    }

    private function resolveOwnerOfficeId(int $reservationId, array $officeIdsByCode, ?int $pfOfficeId): ?int
    {
        if (array_key_exists($reservationId, $this->ownerOfficeIdCache)) {
            return $this->ownerOfficeIdCache[$reservationId];
        }

        $ownerRows = DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->where('details.reservation_id', $reservationId)
            ->select(['owners.owner_name', 'owners.department_affiliation'])
            ->get();

        return $this->ownerOfficeIdCache[$reservationId] = $this->computeOwnerOfficeIdFromItemOwnerRows(
            $ownerRows->all(),
            $officeIdsByCode,
            $pfOfficeId,
        );
    }

    /**
     * @param  array<int, object>  $ownerRows
     */
    private function computeOwnerOfficeIdFromItemOwnerRows(array $ownerRows, array $officeIdsByCode, ?int $pfOfficeId): ?int
    {
        if ($ownerRows === []) {
            return null;
        }

        $ioOfficeId = $officeIdsByCode['IO'] ?? null;
        $fallbackOfficeId = $ioOfficeId ?? $officeIdsByCode['PC'] ?? null;
        $hasPfOwner = false;
        $hasNonPfOwner = false;

        foreach ($ownerRows as $row) {
            $affiliation = strtolower(trim((string) ($row->department_affiliation ?? '')));
            $ownerName = strtolower(trim((string) ($row->owner_name ?? '')));

            if ($affiliation !== '' && str_starts_with($affiliation, 'user:')) {
                return $ioOfficeId ?? $fallbackOfficeId;
            }

            if ($ownerName === '' && $affiliation === '') {
                continue;
            }

            $department = $ownerName !== '' ? $ownerName : $affiliation;
            $matchedOfficeId = $this->getOfficeIdByDepartmentName()[$department] ?? null;

            if (!is_null($matchedOfficeId)) {
                $isPfMatched = !is_null($pfOfficeId) && (int) $matchedOfficeId === (int) $pfOfficeId;
                if ($isPfMatched) {
                    $hasPfOwner = true;
                } else {
                    $hasNonPfOwner = true;
                }
                continue;
            }

            $looksLikePf = !is_null($pfOfficeId)
                && (str_contains($ownerName, 'physical facilities') || str_contains($affiliation, 'physical facilities'));

            if ($looksLikePf) {
                $hasPfOwner = true;
            } else {
                $hasNonPfOwner = true;
            }
        }

        if ($hasNonPfOwner) {
            return $ioOfficeId ?? $fallbackOfficeId;
        }

        if ($hasPfOwner) {
            return $pfOfficeId ?? $fallbackOfficeId;
        }

        return $fallbackOfficeId;
    }

    private function isPhysicalFacilitiesOwnedReservation(int $reservationId, ?int $pfOfficeId = null): bool
    {
        $pfOfficeId ??= $this->getPhysicalFacilitiesOfficeId();

        if (is_null($pfOfficeId)) {
            return false;
        }

        return $this->resolveOwnerOfficeId($reservationId, $this->getOfficeIdsByShortCode(), $pfOfficeId) === $pfOfficeId;
    }

    private function getPhysicalFacilitiesAdminUserId(): ?int
    {
        $pfOfficeId = $this->getPhysicalFacilitiesOfficeId();

        if (is_null($pfOfficeId)) {
            return null;
        }

        return DB::table('users')
            ->where('office_id', $pfOfficeId)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->value('user_id');
    }

    private function upsertApprovalHistory(int $approvalId, int $reservationId, int $officeId, string $status, ?int $approvedByUserId, $approvedAt): void
    {
        if (!Schema::hasTable('reservation_approval_histories')) {
            return;
        }

        DB::table('reservation_approval_histories')->updateOrInsert(
            ['approval_id' => $approvalId],
            [
                'reservation_id' => $reservationId,
                'office_id' => $officeId,
                'approved_by_user_id' => $approvedByUserId,
                'status' => $status,
                'approved_at' => $approvedAt,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function isGymRoomRequest(int $reservationId): bool
    {
        $rid = (int) $reservationId;

        if ($this->batchGymLookup !== null) {
            return isset($this->batchGymLookup[$rid]);
        }

        if (!Schema::hasTable('reservation_details')) {
            return false;
        }

        return DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->where('details.reservation_id', $reservationId)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->exists();
    }

    private function isGymRoomRequestWithItems(int $reservationId): bool
    {
        $rid = (int) $reservationId;

        if ($this->batchGymWithItemsLookup !== null) {
            return isset($this->batchGymWithItemsLookup[$rid]);
        }

        if (!Schema::hasTable('reservation_details')) {
            return false;
        }

        $hasGymRoom = DB::table('reservation_details as details')
            ->join('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->where('details.reservation_id', $reservationId)
            ->whereRaw('LOWER(TRIM(rooms.room_number)) = ?', ['gym'])
            ->exists();

        if (!$hasGymRoom) {
            return false;
        }

        return DB::table('reservation_details as details')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->where('details.reservation_id', $reservationId)
            ->whereNotNull('items.item_id')
            ->exists();
    }
}

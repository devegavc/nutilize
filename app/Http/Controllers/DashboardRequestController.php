<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationApproval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardRequestController extends Controller
{
    private ?array $officeIdsByShortCodeCache = null;
    private ?int $physicalFacilitiesOfficeIdCache = null;
    private ?array $officeIdByDepartmentNameCache = null;
    private ?array $officeMetaByIdCache = null;
    private array $ownerOfficeIdCache = [];
    /** @var array<int, true>|null */
    private ?array $batchGymLookup = null;
    /** @var array<int, true>|null */
    private ?array $batchGymWithItemsLookup = null;

    public function index()
    {
        $user = Auth::user();

        if (!$user || !$user->isOfficeApprover()) {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        return view('dashboard-request', $this->buildRequestPageViewData($user));
    }

    public function requestList()
    {
        $user = Auth::user();

        if (!$user || !$user->isOfficeApprover()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access.',
            ], 403);
        }

        $viewData = $this->buildRequestPageViewData($user);

        return response()->json([
            'success' => true,
            'html' => view('partials.dashboard-request-list', $viewData)->render(),
        ]);
    }

    private function buildRequestPageViewData($user): array
    {
        // Full-table sync on every load is too slow; Approvals/Office still align rows for what they show.
        // Sync only the current page (max 20) so workflow tabs and steps match the same logic as other dashboards.

        $isPfAdmin = $user->isPhysicalFacilitiesAdmin();

        $reservations = Reservation::query()
            ->with(['user', 'approvals'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $reservationIds = $reservations->getCollection()->pluck('reservation_id')->map(fn($id) => (int) $id)->all();

        $this->syncReservationApprovalsForReservationIds($reservationIds);

        $reservations->getCollection()->load('approvals');

        $resourceMap = $this->buildResourceMap($reservationIds);
        $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($reservationIds);
        $pfOfficeId = $this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId();

        $preparedRequests = $reservations->getCollection()->map(function (Reservation $reservation) use ($resourceMap, $actionableOfficeIds, $isPfAdmin, $pfOfficeId) {
            $overallStatus = strtolower((string) $reservation->overall_status);
            $isFinalDecision = $overallStatus === 'rejected';
            $isWaitingReturn = $overallStatus === 'approved';
            $isClosed = in_array($overallStatus, ['returned', 'damaged'], true);
            $isPfActionable = !is_null($pfOfficeId)
                && (($actionableOfficeIds[(int) $reservation->reservation_id] ?? null) === (int) $pfOfficeId);
            $isFinal = $isPfActionable && !$isFinalDecision;
            $resources = $resourceMap[$reservation->reservation_id] ?? [];
            $workflow = $this->buildWorkflowForReservation($reservation);

            $tab = $isClosed
                ? 'closed'
                : ($isWaitingReturn
                ? 'return'
                : ($isFinalDecision ? 'rejected' : ($isFinal ? 'final' : 'pending')));

            return [
                'reservation' => $reservation,
                'tab' => $tab,
                'workflow_steps' => $workflow['steps'],
                'resources' => $resources,
                'decision_badge' => $tab === 'return'
                    ? 'Waiting Return'
                    : ($tab === 'closed' ? ucfirst($overallStatus) : ($tab === 'rejected' ? 'Rejected' : (!$isFinal ? 'Pending' : 'Final Review'))),
                'decision_status_class' => $tab === 'return'
                    ? 'is-approved'
                    : ($tab === 'closed' ? ($overallStatus === 'damaged' ? 'is-rejected' : 'is-approved') : ($tab === 'rejected' ? 'is-rejected' : '')),
            ];
        });

        $reservations->setCollection($preparedRequests);

        return [
            'finalRequests' => $preparedRequests->where('tab', 'final')->values(),
            'returnRequests' => $preparedRequests->where('tab', 'return')->values(),
            'rejectedRequests' => $preparedRequests->where('tab', 'rejected')->values(),
            'pendingRequests' => $preparedRequests->where('tab', 'pending')->values(),
            'requestPagination' => $reservations,
            'isPfAdmin' => $isPfAdmin,
        ];
    }

    private function buildResourceMap(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }

        $resourceRows = DB::table('reservation_details as details')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->select([
                'details.reservation_id',
                'details.quantity',
                'rooms.room_number',
                'items.item_name',
            ])
            ->get();

        $resourceMap = [];

        foreach ($resourceRows as $row) {
            $isRoom = !is_null($row->room_number);
            $name = $isRoom ? ('Room ' . $row->room_number) : ($row->item_name ?? 'Resource');
            $quantity = max(1, (int) $row->quantity);

            $resourceMap[$row->reservation_id][] = [
                'label' => $name,
                'icon' => $isRoom ? 'bi-house-door-fill' : 'bi-box-seam',
                'quantity' => $quantity,
            ];
        }

        return $resourceMap;
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

    private function getOfficeMetaById(): array
    {
        if (!is_null($this->officeMetaByIdCache)) {
            return $this->officeMetaByIdCache;
        }

        $rows = DB::table('offices')
            ->select(['office_id', 'short_code', 'department_name'])
            ->whereNotNull('short_code')
            ->get();

        $meta = [];
        foreach ($rows as $office) {
            $meta[(int) $office->office_id] = [
                'code' => strtoupper(trim((string) ($office->short_code ?? ''))),
                'name' => (string) $office->department_name,
            ];
        }

        $this->officeMetaByIdCache = $meta;

        return $this->officeMetaByIdCache;
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

    private function syncReservationApprovalWorkflow(?array $reservationIds = null): void
    {
        if (is_null($reservationIds)) {
            $reservationIds = Reservation::query()
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected', 'cancelled', 'canceled'])
                ->orderByDesc('created_at')
                ->limit(80)
                ->pluck('reservation_id')
                ->all();
        }

        foreach ($reservationIds as $reservationId) {
            $this->syncReservationApprovals((int) $reservationId);
        }
    }

    private function syncReservationApprovalsForReservationIds(array $reservationIds): void
    {
        if (empty($reservationIds)) {
            return;
        }

        $reservationIds = array_values(array_unique(array_map('intval', $reservationIds)));
        $this->warmBatchWorkflowLookups($reservationIds);

        $existingApprovals = ReservationApproval::query()
            ->whereIn('reservation_id', $reservationIds)
            ->get(['reservation_id', 'office_id', 'status', 'approved_at'])
            ->groupBy('reservation_id');

        $insertRows = [];
        $now = now();

        foreach ($reservationIds as $reservationId) {
            $workflowOfficeIds = $this->resolveWorkflowOfficeIds($reservationId, true);
            if (empty($workflowOfficeIds)) {
                continue;
            }

            $approvalsForReservation = $existingApprovals->get($reservationId, collect())->keyBy(fn (ReservationApproval $row) => (int) $row->office_id);

            foreach ($workflowOfficeIds as $officeId) {
                $officeId = (int) $officeId;
                if ($officeId <= 0 || $approvalsForReservation->has($officeId)) {
                    continue;
                }

                $insertRows[] = [
                    'reservation_id' => $reservationId,
                    'office_id' => $officeId,
                    'status' => 'pending',
                    'approved_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            }
        }

        if (!empty($insertRows)) {
            DB::table('reservation_approvals')->insert($insertRows);
        }

        DB::table('reservation_approvals')
            ->whereIn('reservation_id', $reservationIds)
            ->whereNull('status')
            ->update([
                'status' => 'pending',
                'updated_at' => $now,
            ]);
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

    private function buildWorkflowForReservation(Reservation $reservation): array
    {
        $officeMap = $this->getOfficeMetaById();

        $actionOfficeIds = $this->resolveWorkflowOfficeIds((int) $reservation->reservation_id, false);
        $steps = [];

        foreach ($actionOfficeIds as $officeId) {
            $approval = $reservation->approvals->firstWhere('office_id', $officeId);
            $status = strtolower((string) ($approval?->status ?? 'pending'));
            $officeCode = strtoupper((string) ($officeMap[$officeId]['code'] ?? 'OFF'));

            $dotClass = 'dot-pending';
            if ($status === 'approved' && !is_null($approval?->approved_at)) {
                $dotClass = 'dot-approved';
            } elseif ($status === 'rejected' && !is_null($approval?->approved_at)) {
                $dotClass = 'dot-rejected';
            }

            $officeIcon = match ($officeCode) {
                'PC' => 'bi-person-badge',
                'IO' => 'bi-box-seam',
                'PF' => 'bi-building-gear',
                'SDAO' => 'bi-people',
                'DO' => 'bi-clipboard-check',
                default => 'bi-building',
            };

            $stageLabel = match ($officeCode) {
                'PC' => 'Program Chair',
                'SDAO' => 'Student Development and Activities Office',
                'DO' => 'Discipline Office',
                'SEC' => 'Security',
                'PF' => 'Physical Facilities',
                default => $officeMap[$officeId]['name'] ?? 'Item Owner',
            };

            $steps[] = [
                'office_id' => $officeId,
                'office_code' => $officeCode,
                'office_name' => $officeMap[$officeId]['name'] ?? 'Office',
                'dot_class' => $dotClass,
                'icon_class' => $officeIcon,
                'stage_label' => $stageLabel,
            ];
        }

        return [
            'steps' => $steps,
        ];
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
                $actionSequence = $this->resolveWorkflowOfficeIds($reservationId, true);

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

        foreach ($gymIds as $gymId) {
            $this->batchGymLookup[$gymId] = true;
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

            foreach ($gymWithItemsIds as $gymId) {
                $this->batchGymWithItemsLookup[$gymId] = true;
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

        $groupedOwnerRows = [];
        foreach ($ownerRows as $row) {
            $groupedOwnerRows[(int) $row->reservation_id][] = $row;
        }

        foreach ($reservationIds as $reservationId) {
            if (array_key_exists($reservationId, $this->ownerOfficeIdCache)) {
                continue;
            }

            $rows = $groupedOwnerRows[$reservationId] ?? [];
            $this->ownerOfficeIdCache[$reservationId] = $this->computeOwnerOfficeIdFromItemOwnerRows(
                $rows,
                $officeIdsByCode,
                $pfOfficeId,
            );
        }
    }

    private function isGymRoomRequest(int $reservationId): bool
    {
        $reservationId = (int) $reservationId;
        if (!is_null($this->batchGymLookup)) {
            return isset($this->batchGymLookup[$reservationId]);
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
        $reservationId = (int) $reservationId;
        if (!is_null($this->batchGymWithItemsLookup)) {
            return isset($this->batchGymWithItemsLookup[$reservationId]);
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

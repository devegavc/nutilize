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

    public function index()
    {
        $user = Auth::user();

        if (!$user || strtolower((string) $user->role) !== 'admin') {
            return redirect('/dashboard/home')->with('error', 'Unauthorized access.');
        }

        return view('dashboard-request', $this->buildRequestPageViewData($user));
    }

    public function requestList()
    {
        $user = Auth::user();

        if (!$user || strtolower((string) $user->role) !== 'admin') {
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
        // Automatic workflow sync on every page load can time out on large datasets.
        // Keep page rendering fast; run sync via explicit actions/background jobs instead.

        $isPfAdmin = $user->isPhysicalFacilitiesAdmin();

        $reservations = Reservation::query()
            ->with(['user', 'approvals'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $reservationIds = $reservations->getCollection()->pluck('reservation_id')->map(fn($id) => (int) $id)->all();
        $resourceMap = $this->buildResourceMap($reservationIds);
        $actionableOfficeIds = $this->getActionableOfficeIdsForReservations($reservationIds);
        $pfOfficeId = $isPfAdmin
            ? ($this->getOfficeIdsByShortCode()['PF'] ?? $this->getPhysicalFacilitiesOfficeId())
            : null;

        $preparedRequests = $reservations->getCollection()->map(function (Reservation $reservation) use ($resourceMap, $actionableOfficeIds, $isPfAdmin, $pfOfficeId) {
            $overallStatus = strtolower((string) $reservation->overall_status);
            $isFinalDecision = $overallStatus === 'rejected';
            $isWaitingReturn = $overallStatus === 'approved';
            $isClosed = in_array($overallStatus, ['returned', 'damaged'], true);
            $isPfActionable = $isPfAdmin
                && !is_null($pfOfficeId)
                && (($actionableOfficeIds[(int) $reservation->reservation_id] ?? null) === (int) $pfOfficeId);
            $isFinal = $isPfActionable || $isFinalDecision;
            $resources = $resourceMap[$reservation->reservation_id] ?? [];
            $workflow = $this->buildWorkflowForReservation($reservation);

            $tab = $isClosed
                ? 'closed'
                : ($isWaitingReturn
                ? 'return'
                : ($isFinal ? 'final' : 'pending'));

            return [
                'reservation' => $reservation,
                'tab' => $tab,
                'workflow_steps' => $workflow['steps'],
                'resources' => $resources,
                'decision_badge' => $tab === 'return'
                    ? 'Waiting Return'
                    : ($tab === 'closed' ? ucfirst($overallStatus) : (!$isFinalDecision ? 'Pending' : 'Rejected')),
                'decision_status_class' => $tab === 'return'
                    ? 'is-approved'
                    : ($tab === 'closed' ? ($overallStatus === 'damaged' ? 'is-rejected' : 'is-approved') : (!$isFinalDecision ? '' : 'is-rejected')),
            ];
        });

        $reservations->setCollection($preparedRequests);

        return [
            'finalRequests' => $preparedRequests->where('tab', 'final')->values(),
            'returnRequests' => $preparedRequests->where('tab', 'return')->values(),
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
                ->whereNotIn(DB::raw("LOWER(COALESCE(overall_status, ''))"), ['approved', 'rejected'])
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

        if ($ownerRows->isEmpty()) {
            return $this->ownerOfficeIdCache[$reservationId] = null;
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
            return $this->ownerOfficeIdCache[$reservationId] = $ioOfficeId ?? $fallbackOfficeId;
        }

        if ($hasPfOwner) {
            return $this->ownerOfficeIdCache[$reservationId] = $pfOfficeId ?? $fallbackOfficeId;
        }

        return $this->ownerOfficeIdCache[$reservationId] = $fallbackOfficeId;
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
                'SAO' => 'bi-people',
                default => 'bi-building',
            };

            $stageLabel = match ($officeCode) {
                'PC' => 'Program Chair',
                'SAO' => 'SDAO',
                'DO' => 'DO',
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
        $ioOfficeId = $ids['IO'] ?? null;
        $ownerOfficeId = $this->resolveOwnerOfficeId($reservationId, $ids, $pfOfficeId);
        $pcOfficeId = $ids['PC'] ?? null;
        $startOfficeId = $ownerOfficeId;

        if (is_null($startOfficeId) || (!is_null($pfOfficeId) && $startOfficeId === $pfOfficeId)) {
            $startOfficeId = $pcOfficeId;
        } elseif (!is_null($ioOfficeId) && $startOfficeId !== $ioOfficeId) {
            $startOfficeId = $ioOfficeId;
        }

        $startIndex = 0;
        if (!is_null($startOfficeId)) {
            $foundIndex = array_search($startOfficeId, $actionSequence, true);
            if ($foundIndex !== false) {
                $startIndex = $foundIndex;
            }
        }

        $workflowOfficeIds = array_slice($actionSequence, $startIndex);

        if (!is_null($pfOfficeId) && !in_array($pfOfficeId, $workflowOfficeIds, true)) {
            $workflowOfficeIds[] = $pfOfficeId;
        }

        return array_values(array_unique($workflowOfficeIds));
    }

    private function getActionableOfficeIdsForReservations(array $reservationIds): array
    {
        if (empty($reservationIds)) {
            return [];
        }

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

            $approvals = ($approvalsByReservation->get($reservationId) ?? collect())->keyBy('office_id');

            foreach ($actionSequence as $officeId) {
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
    }
}

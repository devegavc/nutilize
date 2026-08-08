<?php

namespace App\Http\Controllers;

use App\Services\ItemOwnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficeArchiveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isOfficeApprover()) {
            abort(403);
        }

        $decision = strtolower((string) $request->query('decision', 'all'));
        if (!in_array($decision, ['all', 'approved', 'rejected'], true)) {
            $decision = 'all';
        }

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $isItemOwner = ItemOwnerService::isItemOwnerUser($user);
        $ownerReservationIds = $isItemOwner
            ? ItemOwnerService::reservationIdsIncludingOwnerItems((int) $user->user_id)
            : [];

        $historyQuery = DB::table('reservation_approval_histories as history')
            ->join('reservations', 'reservations.reservation_id', '=', 'history.reservation_id')
            ->leftJoin('users as requester', 'requester.user_id', '=', 'reservations.user_id')
            ->leftJoin('users as approver', 'approver.user_id', '=', 'history.approved_by_user_id')
            ->whereNotNull('history.approved_at')
            ->whereIn(DB::raw("LOWER(COALESCE(history.status, ''))"), ['approved', 'rejected']);

        if ($isItemOwner) {
            if ($ownerReservationIds === []) {
                $historyQuery->whereRaw('1 = 0');
            } else {
                $historyQuery->whereIn('history.reservation_id', $ownerReservationIds);
            }
        } else {
            $historyQuery->where('history.office_id', (int) $user->office_id);
        }

        if ($decision !== 'all') {
            $historyQuery->where(DB::raw("LOWER(COALESCE(history.status, ''))"), $decision);
        }

        if (!empty($fromDate)) {
            $historyQuery->whereDate('history.approved_at', '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $historyQuery->whereDate('history.approved_at', '<=', $toDate);
        }

        $historyRows = $historyQuery
            ->select([
                'history.approval_id',
                'history.reservation_id',
                'history.status',
                'history.approved_at',
                'reservations.activity_name',
                'requester.username as requester_username',
                'requester.full_name as requester_full_name',
                'approver.username as approver_username',
                'approver.full_name as approver_full_name',
            ])
            ->orderByDesc('history.approved_at')
            ->paginate(20)
            ->withQueryString();

        $resourceMap = $this->buildResourceMap(
            $historyRows->pluck('reservation_id')->all(),
            $isItemOwner ? (int) $user->user_id : null,
        );

        $records = $historyRows->getCollection()->map(function ($row) use ($resourceMap) {
            $status = strtolower((string) ($row->status ?? 'pending'));
            $requesterName = trim((string) ($row->requester_full_name ?? '')) ?: (string) ($row->requester_username ?? 'Unknown');
            $approverName = trim((string) ($row->approver_full_name ?? '')) ?: (string) ($row->approver_username ?? 'Office Admin');
            $resource = $resourceMap[(int) $row->reservation_id] ?? 'No resources listed';

            return [
                'request_id' => '#NU-' . str_pad((string) $row->reservation_id, 6, '0', STR_PAD_LEFT),
                'requested_by' => $requesterName,
                'resource' => $resource,
                'processed_at' => $row->approved_at ? date('M d, Y h:i A', strtotime((string) $row->approved_at)) : 'N/A',
                'processed_by' => $approverName,
                'reason' => $row->activity_name ?: 'No activity name',
                'decision' => $status === 'approved' ? 'Approved' : 'Rejected',
            ];
        });

        $historyRows->setCollection($records);

        $countQuery = DB::table('reservation_approval_histories')
            ->whereNotNull('approved_at')
            ->whereIn(DB::raw("LOWER(COALESCE(status, ''))"), ['approved', 'rejected']);

        if ($isItemOwner) {
            if ($ownerReservationIds === []) {
                $countQuery->whereRaw('1 = 0');
            } else {
                $countQuery->whereIn('reservation_id', $ownerReservationIds);
            }
        } else {
            $countQuery->where('office_id', (int) $user->office_id);
        }

        if ($decision !== 'all') {
            $countQuery->where(DB::raw("LOWER(COALESCE(status, ''))"), $decision);
        }

        if (!empty($fromDate)) {
            $countQuery->whereDate('approved_at', '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $countQuery->whereDate('approved_at', '<=', $toDate);
        }

        $allStatuses = $countQuery
            ->pluck('status')
            ->map(fn ($status) => strtolower((string) $status));

        $totalTransactions = $allStatuses->count();
        $approvedCount = $allStatuses->filter(fn ($status) => $status === 'approved')->count();
        $rejectedCount = $allStatuses->filter(fn ($status) => $status === 'rejected')->count();

        $todayQuery = DB::table('reservation_approval_histories')
            ->whereDate('approved_at', now()->toDateString())
            ->whereIn(DB::raw("LOWER(COALESCE(status, ''))"), ['approved', 'rejected']);

        if ($isItemOwner) {
            if ($ownerReservationIds === []) {
                $todayQuery->whereRaw('1 = 0');
            } else {
                $todayQuery->whereIn('reservation_id', $ownerReservationIds);
            }
        } else {
            $todayQuery->where('office_id', (int) $user->office_id);
        }

        if ($decision !== 'all') {
            $todayQuery->where(DB::raw("LOWER(COALESCE(status, ''))"), $decision);
        }

        if (!empty($fromDate)) {
            $todayQuery->whereDate('approved_at', '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $todayQuery->whereDate('approved_at', '<=', $toDate);
        }

        $todayCount = $todayQuery->count();

        return view('office-history', [
            'historyRows' => $historyRows,
            'totalTransactions' => $totalTransactions,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'todayCount' => $todayCount,
            'selectedDecision' => $decision,
            'selectedFromDate' => $fromDate,
            'selectedToDate' => $toDate,
        ]);
    }

    private function buildResourceMap(array $reservationIds, ?int $itemOwnerUserId = null): array
    {
        if (empty($reservationIds)) {
            return [];
        }

        $resourceRows = DB::table('reservation_details as details')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('item_owners as owners', 'owners.owner_id', '=', 'items.owner_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->select([
                'details.reservation_id',
                'rooms.room_number',
                'items.item_name',
            ]);

        if (!is_null($itemOwnerUserId) && $itemOwnerUserId > 0) {
            if (Schema::hasColumn('item_owners', 'user_id')) {
                $resourceRows->where('owners.user_id', $itemOwnerUserId);
            } else {
                $resourceRows->whereRaw(
                    'LOWER(COALESCE(owners.department_affiliation, \'\')) = ?',
                    [strtolower('user:' . $itemOwnerUserId)]
                );
            }
        }

        $resourceRows = $resourceRows->get();

        $resourceMap = [];

        foreach ($resourceRows as $row) {
            $label = !is_null($row->room_number)
                ? 'Room ' . (string) $row->room_number
                : ((string) ($row->item_name ?? 'Resource'));

            if ($label === 'Resource') {
                continue;
            }

            $resourceMap[(int) $row->reservation_id][] = $label;
        }

        return collect($resourceMap)
            ->map(fn ($labels) => implode(', ', array_unique($labels)))
            ->all();
    }
}

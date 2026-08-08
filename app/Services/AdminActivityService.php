<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminActivityService
{
    public static function log(int $userId, string $action, string $module, string $status = 'success'): void
    {
        if ($userId <= 0 || !Schema::hasTable('admin_activity_logs')) {
            return;
        }

        DB::table('admin_activity_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'status' => strtolower($status),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array{date: string, action: string, module: string, status: string, sort_at: int}>
     */
    public static function recentForUser(int $userId, int $limit = 25): array
    {
        if ($userId <= 0) {
            return [];
        }

        $entries = [];

        if (Schema::hasTable('admin_activity_logs')) {
            $rows = DB::table('admin_activity_logs')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['action', 'module', 'status', 'created_at']);

            foreach ($rows as $row) {
                $timestamp = strtotime((string) ($row->created_at ?? '')) ?: 0;
                $entries[] = [
                    'date' => $timestamp > 0 ? date('d/m/Y', $timestamp) : 'N/A',
                    'action' => (string) ($row->action ?? 'Activity'),
                    'module' => (string) ($row->module ?? 'System'),
                    'status' => self::formatStatus((string) ($row->status ?? 'success')),
                    'sort_at' => $timestamp,
                ];
            }
        }

        if (Schema::hasTable('reservation_approval_histories')) {
            $approvalRows = DB::table('reservation_approval_histories as history')
                ->leftJoin('offices', 'offices.office_id', '=', 'history.office_id')
                ->where('history.approved_by_user_id', $userId)
                ->whereNotNull('history.approved_at')
                ->whereIn(DB::raw("LOWER(COALESCE(history.status, ''))"), ['approved', 'rejected'])
                ->orderByDesc('history.approved_at')
                ->limit($limit)
                ->get([
                    'history.status',
                    'history.approved_at',
                    'offices.department_name as office_name',
                    'offices.short_code as office_code',
                ]);

            foreach ($approvalRows as $row) {
                $status = strtolower((string) ($row->status ?? ''));
                $timestamp = strtotime((string) ($row->approved_at ?? '')) ?: 0;
                $module = trim((string) ($row->office_name ?? ''));

                if ($module === '') {
                    $module = trim((string) ($row->office_code ?? '')) ?: 'Requests';
                }

                $entries[] = [
                    'date' => $timestamp > 0 ? date('d/m/Y', $timestamp) : 'N/A',
                    'action' => $status === 'rejected' ? 'Rejected request' : 'Approved request',
                    'module' => $module,
                    'status' => 'Success',
                    'sort_at' => $timestamp,
                ];
            }
        }

        usort($entries, fn (array $left, array $right) => ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0));

        return array_map(function (array $entry) {
            unset($entry['sort_at']);

            return $entry;
        }, array_slice($entries, 0, $limit));
    }

    private static function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'failed', 'error' => 'Failed',
            default => 'Success',
        };
    }
}

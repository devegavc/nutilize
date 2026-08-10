<?php

namespace Tests\Unit;

use App\Services\ReservationApprovalWorkflowService;
use PHPUnit\Framework\TestCase;

class ReservationApprovalWorkflowServiceTest extends TestCase
{
    public function test_it_treats_io_step_as_pending_until_every_owner_row_is_approved(): void
    {
        $ioOfficeId = 10;
        $rows = collect([
            (object) ['approval_id' => 1, 'office_id' => $ioOfficeId, 'owner_id' => 101, 'status' => 'approved', 'approved_at' => '2026-08-01'],
            (object) ['approval_id' => 2, 'office_id' => $ioOfficeId, 'owner_id' => 102, 'status' => 'pending', 'approved_at' => null],
        ]);

        $collapsed = ReservationApprovalWorkflowService::collapseByOfficeId($rows, $ioOfficeId);
        $effective = $collapsed->get($ioOfficeId);

        $this->assertNotNull($effective);
        $this->assertSame('pending', strtolower((string) $effective->status));
        $this->assertNull($effective->approved_at);
    }

    public function test_it_treats_io_step_as_complete_when_all_owner_rows_are_approved(): void
    {
        $ioOfficeId = 10;
        $rows = collect([
            (object) ['approval_id' => 1, 'office_id' => $ioOfficeId, 'owner_id' => 101, 'status' => 'approved', 'approved_at' => '2026-08-01'],
            (object) ['approval_id' => 2, 'office_id' => $ioOfficeId, 'owner_id' => 102, 'status' => 'approved', 'approved_at' => '2026-08-02'],
        ]);

        $collapsed = ReservationApprovalWorkflowService::collapseByOfficeId($rows, $ioOfficeId);
        $effective = $collapsed->get($ioOfficeId);

        $this->assertNotNull($effective);
        $this->assertSame('approved', strtolower((string) $effective->status));
        $this->assertNotNull($effective->approved_at);
    }

    public function test_owner_has_finalized_io_approval_when_owner_scoped_row_is_approved(): void
    {
        $ioOfficeId = 10;
        $rows = collect([
            (object) ['office_id' => $ioOfficeId, 'owner_id' => 101, 'status' => 'approved', 'approved_at' => '2026-08-01'],
        ]);

        $this->assertTrue(
            ReservationApprovalWorkflowService::ownerHasFinalizedIoApproval($rows, $ioOfficeId, 101)
        );
        $this->assertFalse(
            ReservationApprovalWorkflowService::ownerHasFinalizedIoApproval($rows, $ioOfficeId, 102)
        );
    }
}

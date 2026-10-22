<?php

namespace Tests\Unit;

use App\Support\RoleCatalog;
use PHPUnit\Framework\TestCase;

class RoleCatalogTest extends TestCase
{
    public function test_each_dean_covers_only_the_programs_in_that_school(): void
    {
        $this->assertSame('Dean_SACE', RoleCatalog::deanForChair('PC_InformationTechnology'));
        $this->assertSame('Dean_SABM', RoleCatalog::deanForChair('PC_Accountancy'));
        $this->assertSame('Dean_SAHS', RoleCatalog::deanForChair('PC_Nursing'));
        $this->assertNull(RoleCatalog::deanForProgramOffice(
            'BS Information Technology',
            'School of Accountancy, Business and Management'
        ));
        $this->assertSame('Dean_SACE', RoleCatalog::deanForProgramOffice(
            'BS Information Technology',
            'School of Architecture, Computing and Engineering'
        ));
    }

    public function test_either_chair_or_matching_dean_can_finish_an_ordinary_stage_once(): void
    {
        $chair = RoleCatalog::interpretProgramChairDecision(
            'PC_InformationTechnology',
            10,
            55,
            55,
            3,
            'Student',
            false,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );
        $dean = RoleCatalog::interpretProgramChairDecision(
            'Dean_SACE',
            20,
            null,
            55,
            3,
            'Student',
            false,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );
        $second = RoleCatalog::interpretProgramChairDecision(
            'Dean_SACE',
            20,
            null,
            55,
            3,
            'Student',
            false,
            true,
            'PC_InformationTechnology',
            'Dean_SACE'
        );
        $otherSchool = RoleCatalog::interpretProgramChairDecision(
            'Dean_SABM',
            21,
            null,
            55,
            3,
            'Student',
            false,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );

        $this->assertTrue($chair['allowed']);
        $this->assertFalse($chair['on_behalf']);
        $this->assertTrue($dean['allowed']);
        $this->assertTrue($dean['on_behalf']);
        $this->assertFalse($second['allowed']);
        $this->assertSame('already_decided', $second['code']);
        $this->assertFalse($otherSchool['allowed']);
    }

    public function test_a_program_chair_submission_requires_the_dean_and_blocks_self_approval(): void
    {
        $self = RoleCatalog::interpretProgramChairDecision(
            'PC_InformationTechnology',
            10,
            55,
            55,
            10,
            'PC_InformationTechnology',
            true,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );
        $otherChair = RoleCatalog::interpretProgramChairDecision(
            'PC_InformationTechnology',
            11,
            55,
            55,
            10,
            'PC_InformationTechnology',
            true,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );
        $dean = RoleCatalog::interpretProgramChairDecision(
            'Dean_SACE',
            20,
            null,
            55,
            10,
            'PC_InformationTechnology',
            true,
            false,
            'PC_InformationTechnology',
            'Dean_SACE'
        );

        $this->assertSame('self_approval', $self['code']);
        $this->assertFalse($otherChair['allowed']);
        $this->assertTrue($dean['allowed']);
        $this->assertFalse($dean['on_behalf']);
    }

    public function test_migration_maps_clear_accounts_and_flags_ambiguous_ones(): void
    {
        $this->assertSame(
            ['role' => 'Student', 'needs_review' => false],
            RoleCatalog::migrationFor('user', null, null)
        );
        $this->assertSame(
            ['role' => 'PC_InformationTechnology', 'needs_review' => false],
            RoleCatalog::migrationFor('pc_admin', 'BS Information Technology', 'PC')
        );
        $this->assertSame(
            ['role' => 'PFO', 'needs_review' => false],
            RoleCatalog::migrationFor('pf_admin', 'Physical Facilities', 'PF')
        );
        $this->assertSame(
            ['role' => 'Safety', 'needs_review' => false],
            RoleCatalog::migrationFor('admin', 'Student discipline office', 'DO')
        );
        $this->assertTrue(RoleCatalog::migrationFor('pc_admin', 'Program Chair', 'PC')['needs_review']);
        $this->assertSame('admin', RoleCatalog::migrationFor('admin', 'Item Owner', 'IO')['role']);
        $this->assertFalse(RoleCatalog::migrationFor('admin', 'Item Owner', 'IO')['needs_review']);
    }
}

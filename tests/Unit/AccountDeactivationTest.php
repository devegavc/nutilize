<?php

namespace Tests\Unit;

use App\Services\UserAccountStatusService;
use PHPUnit\Framework\TestCase;

class AccountDeactivationTest extends TestCase
{
    public function test_program_chairs_deans_and_office_accounts_can_be_deactivated_by_hand(): void
    {
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('PC_InformationTechnology'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Dean_SACE'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('PFO'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Gen_Ed'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('SDAO'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Safety'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Security'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Faculty'));
        $this->assertTrue(UserAccountStatusService::roleCanBeDeactivated('Student'));
    }

    public function test_succession_roles_are_not_on_the_fourteen_week_timer(): void
    {
        $this->assertFalse(UserAccountStatusService::roleUsesActivityWindow('PC_InformationTechnology'));
        $this->assertFalse(UserAccountStatusService::roleUsesActivityWindow('Dean_SABM'));
        $this->assertFalse(UserAccountStatusService::roleUsesActivityWindow('PFO'));
        $this->assertTrue(UserAccountStatusService::roleUsesActivityWindow('Faculty'));
        $this->assertTrue(UserAccountStatusService::roleUsesActivityWindow('student'));
        $this->assertFalse(UserAccountStatusService::roleCanBeDeactivated('item_owner'));
    }
}

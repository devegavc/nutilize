<?php

namespace Tests\Unit;

use App\Services\ProgramChairOfficeResolver;
use PHPUnit\Framework\TestCase;

class ProgramChairOfficeResolverTest extends TestCase
{
    public function test_it_inserts_the_resolved_program_chair_when_no_template_slot_exists(): void
    {
        $sequence = [10, 20, 30];

        $result = ProgramChairOfficeResolver::replaceTemplatePcInSequence($sequence, 999, 55);

        $this->assertSame([10, 55, 20, 30], $result);
    }

    public function test_it_replaces_the_template_program_chair_slot_when_present(): void
    {
        $sequence = [10, 999, 20, 30];

        $result = ProgramChairOfficeResolver::replaceTemplatePcInSequence($sequence, 999, 55);

        $this->assertSame([10, 55, 20, 30], $result);
    }

    public function test_it_keeps_sequence_unchanged_when_template_and_resolved_match(): void
    {
        $sequence = [10, 999, 20, 30];

        $result = ProgramChairOfficeResolver::replaceTemplatePcInSequence($sequence, 999, 999);

        $this->assertSame([10, 999, 20, 30], $result);
    }
}

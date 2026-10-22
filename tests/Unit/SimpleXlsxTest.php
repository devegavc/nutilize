<?php

namespace Tests\Unit;

use App\Support\EmailSpreadsheet;
use App\Support\SimpleXlsx;
use PHPUnit\Framework\TestCase;

class SimpleXlsxTest extends TestCase
{
    public function test_workbook_keeps_the_email_column_readable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'users');
        file_put_contents($path, SimpleXlsx::binary([
            ['Email', 'Name', 'Status'],
            ['ada@school.edu', 'Ada & Co', 'Active'],
            ['bea@school.edu', 'Bea', 'Inactive'],
        ]));

        $emails = EmailSpreadsheet::emailColumn($path, 'xlsx');
        unlink($path);

        $this->assertSame(['ada@school.edu', 'bea@school.edu'], $emails);
    }
}

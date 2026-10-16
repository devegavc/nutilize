<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE academic_terms ALTER COLUMN starts_on DROP NOT NULL');
            DB::statement('ALTER TABLE academic_terms ALTER COLUMN ends_on DROP NOT NULL');
        } else {
            Schema::table('academic_terms', function (Blueprint $table) {
                $table->date('starts_on')->nullable()->change();
                $table->date('ends_on')->nullable()->change();
            });
        }

        Schema::table('academic_terms', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_terms', 'date_note')) {
                $table->string('date_note', 255)->nullable();
            }
        });

        $now = now();
        $rows = [
            [
                'academic_year' => '2026-2027',
                'term_key' => 'term-1',
                'name' => '1st Term',
                'starts_on' => '2026-07-13',
                'ends_on' => '2026-11-02',
                'date_note' => null,
            ],
            [
                'academic_year' => '2026-2027',
                'term_key' => 'term-2',
                'name' => '2nd Term',
                'starts_on' => '2026-11-03',
                'ends_on' => null,
                'date_note' => 'The official end is February 2027. The exact day is not set.',
            ],
            [
                'academic_year' => '2026-2027',
                'term_key' => 'term-3',
                'name' => '3rd Term',
                'starts_on' => null,
                'ends_on' => null,
                'date_note' => 'Starts the day after 2nd Term ends, in February 2027, and runs through June 2027. Exact days are not set.',
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('academic_terms')
                ->where('academic_year', $row['academic_year'])
                ->where('term_key', $row['term_key'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('academic_terms')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('academic_terms')
            ->where('academic_year', '2026-2027')
            ->whereIn('term_key', ['term-1', 'term-2', 'term-3'])
            ->delete();

        Schema::table('academic_terms', function (Blueprint $table) {
            if (Schema::hasColumn('academic_terms', 'date_note')) {
                $table->dropColumn('date_note');
            }
        });
    }
};

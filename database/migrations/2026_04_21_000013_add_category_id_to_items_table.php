<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('category');
            }
        });

        if (Schema::hasTable('item_categories') && Schema::hasColumn('items', 'category')) {
            $defaultCategoryId = DB::table('item_categories')
                ->where('category_key', 'uncategorized')
                ->value('category_id');

            if (!$defaultCategoryId) {
                DB::table('item_categories')->insert([
                    'category_key' => 'uncategorized',
                    'display_name' => 'Uncategorized',
                    'is_active' => DB::raw('true'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $defaultCategoryId = DB::table('item_categories')
                    ->where('category_key', 'uncategorized')
                    ->value('category_id');
            }

            $items = DB::table('items')
                ->select(['item_id', 'category'])
                ->get();

            foreach ($items as $item) {
                $normalizedKey = $this->normalizeCategoryKey((string) ($item->category ?? ''));

                if ($normalizedKey === '' || $normalizedKey === 'multi_media' || str_contains($normalizedKey, 'multimedia') || str_contains($normalizedKey, 'audio_visual')) {
                    $normalizedKey = 'multimedia';
                } elseif (str_starts_with($normalizedKey, 'elect')) {
                    $normalizedKey = 'electronics';
                } elseif (str_contains($normalizedKey, 'util') || str_contains($normalizedKey, 'furnit') || str_contains($normalizedKey, 'chair') || str_contains($normalizedKey, 'table')) {
                    $normalizedKey = 'utility';
                }

                $categoryId = DB::table('item_categories')
                    ->where('category_key', $normalizedKey)
                    ->value('category_id');

                if (!$categoryId) {
                    $normalizedKey = 'uncategorized';
                    $categoryId = $defaultCategoryId;
                }

                DB::table('items')
                    ->where('item_id', (int) $item->item_id)
                    ->update([
                        'category_id' => $categoryId,
                        'updated_at' => now(),
                    ]);
            }
        }

        Schema::table('items', function (Blueprint $table) {
            $foreignKeys = collect(Schema::getForeignKeys('items'))
                ->pluck('columns')
                ->flatten()
                ->map(fn ($column) => strtolower((string) $column))
                ->all();

            if (!in_array('category_id', $foreignKeys, true)) {
                $table->foreign('category_id')->references('category_id')->on('item_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('items')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $foreignKeys = collect(Schema::getForeignKeys('items'))
                ->pluck('columns')
                ->flatten()
                ->map(fn ($column) => strtolower((string) $column))
                ->all();

            if (in_array('category_id', $foreignKeys, true)) {
                $table->dropForeign(['category_id']);
            }

            if (Schema::hasColumn('items', 'category_id')) {
                $table->dropColumn('category_id');
            }
        });
    }

    private function normalizeCategoryKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized;
    }
};

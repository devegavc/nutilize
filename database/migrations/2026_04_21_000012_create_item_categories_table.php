<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_key', 64)->unique();
            $table->string('display_name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaultCategories = [
            ['category_key' => 'multimedia', 'display_name' => 'Multimedia'],
            ['category_key' => 'electronics', 'display_name' => 'Electronics'],
            ['category_key' => 'utility', 'display_name' => 'Utility'],
        ];

        foreach ($defaultCategories as $category) {
            DB::table('item_categories')->updateOrInsert(
                ['category_key' => $category['category_key']],
                [
                    'display_name' => $category['display_name'],
                    'is_active' => DB::raw('true'),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (!Schema::hasTable('items') || !Schema::hasColumn('items', 'category')) {
            return;
        }

        $existingCategories = DB::table('items')
            ->whereNotNull('category')
            ->pluck('category')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        foreach ($existingCategories as $rawCategory) {
            $normalized = $this->normalizeCategoryKey($rawCategory);

            if ($normalized === '') {
                continue;
            }

            if ($normalized === 'multi_media' || str_contains($normalized, 'multimedia') || str_contains($normalized, 'audio_visual')) {
                $normalized = 'multimedia';
            } elseif (str_starts_with($normalized, 'elect')) {
                $normalized = 'electronics';
            } elseif (str_contains($normalized, 'util') || str_contains($normalized, 'furnit') || str_contains($normalized, 'chair') || str_contains($normalized, 'table')) {
                $normalized = 'utility';
            }

            DB::table('item_categories')->updateOrInsert(
                ['category_key' => $normalized],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $normalized)),
                    'is_active' => DB::raw('true'),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_categories');
    }

    private function normalizeCategoryKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized;
    }
};

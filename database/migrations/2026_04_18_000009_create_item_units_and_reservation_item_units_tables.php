<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_units', function (Blueprint $table) {
            $table->id('unit_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('unit_number');
            $table->string('unit_code', 64)->unique();
            $table->string('status', 32)->default('available');
            $table->string('condition_notes', 255)->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('cascade');
            $table->unique(['item_id', 'unit_number']);
        });

        Schema::create('reservation_item_units', function (Blueprint $table) {
            $table->id('reservation_item_unit_id');
            $table->unsignedBigInteger('reservation_items_id');
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();

            $table->foreign('reservation_items_id')->references('reservation_items_id')->on('reservation_items')->onDelete('cascade');
            $table->foreign('unit_id')->references('unit_id')->on('item_units')->onDelete('cascade');
            $table->unique(['reservation_items_id', 'unit_id']);
        });

        $items = DB::table('items')
            ->select(['item_id', 'quantity_total', 'quantity_in_use', 'maintenance_status'])
            ->orderBy('item_id')
            ->get();

        foreach ($items as $item) {
            $total = max(1, (int) ($item->quantity_total ?? 1));
            $inUseTarget = max(0, min($total, (int) ($item->quantity_in_use ?? 0)));
            $hasMaintenance = (bool) $item->maintenance_status;

            $specialStatus = $hasMaintenance ? 'maintenance' : null;
            $remainingInUse = $inUseTarget;

            for ($unitNumber = 1; $unitNumber <= $total; $unitNumber++) {
                $status = 'available';

                if ($specialStatus && $unitNumber === 1) {
                    $status = $specialStatus;
                } elseif ($remainingInUse > 0) {
                    $status = 'in_use';
                    $remainingInUse--;
                }

                DB::table('item_units')->insert([
                    'item_id' => (int) $item->item_id,
                    'unit_number' => $unitNumber,
                    'unit_code' => sprintf('ITM%04d-U%03d', (int) $item->item_id, $unitNumber),
                    'status' => $status,
                    'condition_notes' => null,
                    'last_maintenance_at' => $status === 'maintenance' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_item_units');
        Schema::dropIfExists('item_units');
    }
};
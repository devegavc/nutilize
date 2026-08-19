<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The requester app stamps item_units.status = in_use (and bumps quantity_in_use)
 * at submit time. That is a global lock, so a Sept 9 booking hides the item on
 * every other day. Occupancy is per calendar day via reservation rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('item_units')) {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION keep_item_units_borrowable()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.status IS NOT NULL AND LOWER(TRIM(NEW.status)) = 'in_use' THEN
        NEW.status := 'available';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_keep_item_units_borrowable ON item_units;
CREATE TRIGGER trg_keep_item_units_borrowable
    BEFORE INSERT OR UPDATE OF status ON item_units
    FOR EACH ROW
    EXECUTE PROCEDURE keep_item_units_borrowable();
SQL);

        if (Schema::hasTable('items')) {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION keep_item_quantity_unlocked()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    today_manila date := (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date;
    occupied_today integer := 0;
    has_serviceable boolean := true;
BEGIN
    SELECT COUNT(DISTINCT riu.unit_id)
      INTO occupied_today
      FROM reservation_item_units riu
      JOIN reservation_items ri ON ri.reservation_items_id = riu.reservation_items_id
      JOIN reservation_details rd ON rd.reservation_items_id = ri.reservation_items_id
      JOIN reservations r ON r.reservation_id = rd.reservation_id
     WHERE ri.item_id = NEW.item_id
       AND LOWER(TRIM(COALESCE(r.overall_status, ''))) = 'approved'
       AND DATE(COALESCE(r."Date_of_Activity", r."Start_of_activity")) <= today_manila
       AND DATE(COALESCE(r."End_of_Activity", r."Date_of_Activity", r."Start_of_activity")) >= today_manila;

    NEW.quantity_in_use := GREATEST(occupied_today, 0);

    SELECT EXISTS (
        SELECT 1
          FROM item_units u
         WHERE u.item_id = NEW.item_id
           AND LOWER(TRIM(COALESCE(u.status, ''))) NOT IN ('maintenance', 'damaged', 'retired')
    ) INTO has_serviceable;

    IF has_serviceable THEN
        NEW.availability_status := TRUE;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_keep_item_quantity_unlocked ON items;
CREATE TRIGGER trg_keep_item_quantity_unlocked
    BEFORE INSERT OR UPDATE OF quantity_in_use, availability_status ON items
    FOR EACH ROW
    EXECUTE PROCEDURE keep_item_quantity_unlocked();
SQL);
        }

        if (
            Schema::hasTable('reservation_item_units')
            && Schema::hasTable('reservation_items')
            && Schema::hasTable('reservation_details')
            && Schema::hasTable('reservations')
        ) {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_unit_double_book_same_day()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    new_start date;
    new_end date;
    conflict_id bigint;
BEGIN
    SELECT DATE(COALESCE(r."Date_of_Activity", r."Start_of_activity")),
           DATE(COALESCE(r."End_of_Activity", r."Date_of_Activity", r."Start_of_activity"))
      INTO new_start, new_end
      FROM reservation_items ri
      JOIN reservation_details rd ON rd.reservation_items_id = ri.reservation_items_id
      JOIN reservations r ON r.reservation_id = rd.reservation_id
     WHERE ri.reservation_items_id = NEW.reservation_items_id
     LIMIT 1;

    IF new_start IS NULL THEN
        RETURN NEW;
    END IF;

    IF new_end IS NULL THEN
        new_end := new_start;
    END IF;

    SELECT r.reservation_id
      INTO conflict_id
      FROM reservation_item_units riu
      JOIN reservation_items ri ON ri.reservation_items_id = riu.reservation_items_id
      JOIN reservation_details rd ON rd.reservation_items_id = ri.reservation_items_id
      JOIN reservations r ON r.reservation_id = rd.reservation_id
     WHERE riu.unit_id = NEW.unit_id
       AND riu.reservation_items_id IS DISTINCT FROM NEW.reservation_items_id
       AND LOWER(TRIM(COALESCE(r.overall_status, ''))) NOT IN (
            'rejected', 'cancelled', 'canceled', 'expired', 'returned', 'damaged'
       )
       AND DATE(COALESCE(r."Date_of_Activity", r."Start_of_activity")) <= new_end
       AND DATE(COALESCE(r."End_of_Activity", r."Date_of_Activity", r."Start_of_activity")) >= new_start
     LIMIT 1;

    IF conflict_id IS NOT NULL THEN
        RAISE EXCEPTION 'This unit is already reserved on an overlapping day (request #%)', conflict_id
            USING ERRCODE = '23505';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_prevent_unit_double_book_same_day ON reservation_item_units;
CREATE TRIGGER trg_prevent_unit_double_book_same_day
    BEFORE INSERT OR UPDATE OF unit_id, reservation_items_id ON reservation_item_units
    FOR EACH ROW
    EXECUTE PROCEDURE prevent_unit_double_book_same_day();
SQL);
        }

        DB::table('item_units')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'in_use'")
            ->update([
                'status' => 'available',
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('items')) {
            DB::table('items')
                ->where('quantity_in_use', '>', 0)
                ->update([
                    'quantity_in_use' => 0,
                    'availability_status' => DB::raw('TRUE'),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_unit_double_book_same_day ON reservation_item_units');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_unit_double_book_same_day()');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_keep_item_quantity_unlocked ON items');
        DB::unprepared('DROP FUNCTION IF EXISTS keep_item_quantity_unlocked()');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_keep_item_units_borrowable ON item_units');
        DB::unprepared('DROP FUNCTION IF EXISTS keep_item_units_borrowable()');
    }
};

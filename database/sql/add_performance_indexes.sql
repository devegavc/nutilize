-- NUTilize performance indexes for Supabase slow-query / Disk IO relief.
-- Run this in Supabase Dashboard -> SQL Editor (uses a direct DB session).

CREATE INDEX IF NOT EXISTS reservation_approvals_office_pending_idx
  ON reservation_approvals (office_id, reservation_id)
  WHERE approved_at IS NULL;

CREATE INDEX IF NOT EXISTS reservation_approvals_reservation_office_idx
  ON reservation_approvals (reservation_id, office_id);

CREATE INDEX IF NOT EXISTS reservation_approvals_office_owner_pending_idx
  ON reservation_approvals (office_id, owner_id, reservation_id)
  WHERE approved_at IS NULL;

CREATE INDEX IF NOT EXISTS reservation_approvals_office_status_approved_at_idx
  ON reservation_approvals (office_id, status, approved_at);

CREATE INDEX IF NOT EXISTS reservations_created_at_idx
  ON reservations (created_at DESC);

CREATE INDEX IF NOT EXISTS reservations_overall_status_lower_idx
  ON reservations ((LOWER(COALESCE(overall_status, ''))));

CREATE INDEX IF NOT EXISTS reservations_status_created_at_idx
  ON reservations (overall_status, created_at DESC);

CREATE INDEX IF NOT EXISTS reservation_details_reservation_id_idx
  ON reservation_details (reservation_id);

CREATE INDEX IF NOT EXISTS reservation_details_items_id_idx
  ON reservation_details (reservation_items_id)
  WHERE reservation_items_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS reservation_details_rooms_id_idx
  ON reservation_details (reservation_rooms_id)
  WHERE reservation_rooms_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS items_owner_id_idx
  ON items (owner_id);

CREATE INDEX IF NOT EXISTS item_owners_user_id_idx
  ON item_owners (user_id)
  WHERE user_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS notifications_user_created_at_idx
  ON notifications (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS notifications_user_unread_idx
  ON notifications (user_id)
  WHERE read = false;

CREATE INDEX IF NOT EXISTS notifications_user_related_type_idx
  ON notifications (user_id, related_id, type);

CREATE INDEX IF NOT EXISTS offices_short_code_lower_idx
  ON offices ((LOWER(TRIM(short_code))))
  WHERE short_code IS NOT NULL;

CREATE INDEX IF NOT EXISTS users_program_id_idx
  ON users (program_id)
  WHERE program_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS academic_programs_office_id_idx
  ON academic_programs (office_id)
  WHERE office_id IS NOT NULL;

ANALYZE reservation_approvals;
ANALYZE reservations;
ANALYZE reservation_details;
ANALYZE notifications;
ANALYZE items;
ANALYZE item_owners;
ANALYZE offices;
ANALYZE users;
ANALYZE academic_programs;

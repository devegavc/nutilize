# Network-Resilient Database Setup Guide

## Problem Solved
Your app no longer crashes when:
- On hotspot or restricted networks with DNS issues
- Switching between networks
- Database connection times out

## What Changed

### 1. Database Configuration (`.env`)
Now uses **SQLite by default** for development (zero network dependency) and offers Supabase connection pooler for production.

```env
# Development: uses local SQLite (instant, no network needed)
DB_CONNECTION=sqlite

# Production: uses Supabase connection pooler on port 6543
# This is more resilient than direct connection (port 5432)
DB_SUPABASE_HOST=db.uszlgigsuseomkwmqwan.supabase.co
DB_SUPABASE_PORT=6543
```

### 2. Connection Timeouts (`config/database.php`)
Added 5-10 second timeouts so the app fails fast instead of hanging indefinitely.

### 3. Error Handling Middleware
Created `app/Http/Middleware/HandleDatabaseErrors.php` which:
- Catches database connection errors
- Shows a user-friendly error page instead of Laravel's debug page
- Distinguishes network errors from other database issues

### 4. Error Views
- `resources/views/errors/database-offline.blade.php` - Network connection issues
- `resources/views/errors/database-error.blade.php` - Other database errors

---

## How to Use

### Option A: Development (Recommended - Already Set)
```
DB_CONNECTION=sqlite
```
✅ **Benefits:**
- No network needed
- Instant database startup
- Perfect for testing locally
- No DNS issues

### Option B: Switch to Supabase Connection Pooler
When you want to use remote database in development:

1. Update `.env`:
```env
DB_CONNECTION=pgsql_pooler
DB_SUPABASE_HOST=db.uszlgigsuseomkwmqwan.supabase.co
DB_SUPABASE_PORT=6543
```

2. Clear cache:
```bash
php artisan config:clear
php artisan cache:clear
```

3. Run migrations:
```bash
php artisan migrate
```

---

## Testing on Different Networks

### Local Testing (Current)
```bash
php artisan serve
```
Uses SQLite - will work everywhere.

### To Test Supabase Pooler Connection:
1. Change `DB_CONNECTION=pgsql_pooler` in `.env`
2. Run `php artisan config:clear`
3. Try on hotspot/different networks
4. If it fails, you get a friendly error page, not a crash

---

## Why This Works

| Scenario | Before | After |
|----------|--------|-------|
| On hotspot, DNS can't resolve | ❌ App crashes immediately | ✅ Loads with SQLite, or shows friendly error |
| Database unreachable | ❌ White screen with Laravel error | ✅ Shows helpful message |
| Network timeout | ❌ Hangs indefinitely | ✅ Fails after 5-10 seconds with message |
| Switching networks | ❌ Must restart app | ✅ Works on SQLite, pooler handles Postgres |

---

## Connection Pooler Details

**Why port 6543 (pooler) instead of 5432 (direct)?**

- **Port 5432** (Direct): One connection per request. On restricted networks, often blocked.
- **Port 6543** (PgBouncer pooler): Shares connections. Better for:
  - Limited connection slots
  - High latency networks
  - Networks with strict firewalling
  - Mobile hotspots

**Supabase pooler is pre-configured on your account** - just point to port 6543.

---

## Production Deployment

For production servers, use the Supabase pooler:

```env
APP_ENV=production
DB_CONNECTION=pgsql_pooler
DB_SUPABASE_HOST=db.uszlgigsuseomkwmqwan.supabase.co
DB_SUPABASE_PORT=6543
```

This ensures:
- Resilient database connections
- Fast failure on network issues
- Better performance under load

---

## Troubleshooting

### Still seeing "could not translate host name" error?
1. Check your network - can you ping `db.uszlgigsuseomkwmqwan.supabase.co`?
2. Your network may block port 5432 - use port 6543 (pooler) instead
3. Try on a different network to isolate the issue

### SQLite database empty?
Run migrations:
```bash
php artisan migrate
php artisan db:seed
```

### Want to switch back to Postgres?
```bash
# Update .env
DB_CONNECTION=pgsql_pooler

# Clear cache
php artisan config:clear

# Verify connection
php artisan tinker
>>> DB::connection()->getPdo()
```


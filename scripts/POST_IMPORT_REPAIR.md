Post-import repair instructions
==============================

Usage:

1. Copy `backup_hms_local.sql` (or your dump) to the server and place in the project root.
2. Make the script executable:

```bash
chmod +x scripts/post_import_repair.sh
```

3. Run the script from project root (it will read `.env` for DB credentials):

```bash
./scripts/post_import_repair.sh
```

What it does:
- Creates a timestamped mysqldump backup of the configured database.
- Normalizes `model_has_roles` / `model_has_permissions` entries referencing Admin to `App\\Models\\Admin`.
- Updates `roles` and `permissions` guard names to `admin` for Admin-like roles/permissions.
- Assigns the `Admin` role to `model_id=3` (if an Admin role exists).
- Resets Laravel/Spatie caches via `php artisan` (if `artisan` present in project root).
- Optionally sets a temporary password for `admin@gmail.com`.

Safety notes:
- The script makes a DB dump before making changes. Keep that dump safe.
- Review the diagnostic output printed by the script before confirming.
- If your production uses multiple app instances, run this on all instances (or restart workers/PHP-FPM after).

If you want me to adapt this script (dry-run-only, different model IDs, or different role names), tell me what to change.

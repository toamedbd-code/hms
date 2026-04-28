#!/usr/bin/env bash
set -euo pipefail

# Post-import repair script for HMS
# - Creates a DB dump (backup)
# - Normalizes `model_type` entries for Admin models
# - Ensures Admin-role/permission guard is `admin`
# - Assigns `Admin` role to `model_id=3` (if present)
# - Resets Spatie permission cache and Laravel caches
# - Optionally sets a temporary password for `admin@gmail.com`

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

ENV_FILE="$ROOT_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo ".env not found in project root ($ROOT_DIR). Run this script from project root." >&2
  exit 1
fi

get_env() {
  local key="$1"
  # support KEY=value or KEY="value"
  local line
  line=$(grep -E "^${key}=" "$ENV_FILE" || true)
  if [[ -z "$line" ]]; then
    echo ""
    return
  fi
  local val=${line#*=}
  # strip surrounding quotes
  val=${val%"}
  val=${val#"}
  val=${val%\'}
  val=${val#\'}
  echo "$val"
}

DB_CONNECTION=$(get_env DB_CONNECTION)
if [[ "$DB_CONNECTION" != "mysql" ]]; then
  echo "DB_CONNECTION=$DB_CONNECTION — this script supports MySQL only." >&2
  exit 1
fi

DB_HOST=$(get_env DB_HOST)
DB_PORT=$(get_env DB_PORT)
DB_DATABASE=$(get_env DB_DATABASE)
DB_USERNAME=$(get_env DB_USERNAME)
DB_PASSWORD=$(get_env DB_PASSWORD)

if [[ -z "$DB_DATABASE" ]]; then
  echo "DB_DATABASE is empty in .env — aborting." >&2
  exit 1
fi

TIMESTAMP=$(date +%F_%H%M%S)
BACKUP_FILE="$ROOT_DIR/backup_${DB_DATABASE}_${TIMESTAMP}.sql"

echo "== Post-import repair — project: $ROOT_DIR"
echo "DB: $DB_USERNAME@${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}/$DB_DATABASE"
echo "Backup will be written to: $BACKUP_FILE"

read -r -p "Proceed to create DB backup and run repairs? [y/N]: " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
  echo "Aborted by user."; exit 0
fi

export MYSQL_PWD="$DB_PASSWORD"
echo "Creating mysqldump backup..."
mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" --single-transaction --routines --triggers --events "$DB_DATABASE" > "$BACKUP_FILE"
echo "Backup saved: $BACKUP_FILE"

echo
echo "Current database diagnostics (distinct model_type, roles guard_name, Admin-like roles):"
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "SELECT DISTINCT model_type FROM model_has_roles;"
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "SELECT DISTINCT guard_name FROM roles;"
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "SELECT id,name,guard_name FROM roles WHERE name LIKE '%Admin%' OR name LIKE '%admin%' OR name LIKE '%Administrator%';"

read -r -p "Proceed with normalization updates (model_type, guards, assign Admin role to model_id=3)? [y/N]: " CONF2
if [[ ! "$CONF2" =~ ^[Yy]$ ]]; then
  echo "No changes applied. Exiting."; exit 0
fi

echo "Applying normalization SQL..."
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" <<'SQL'
-- Normalize model_type entries that reference Admin-like models
UPDATE model_has_roles
SET model_type = 'App\\Models\\Admin'
WHERE model_type NOT LIKE 'App\\Models\\Admin' AND (model_type LIKE '%Admin%' OR model_type LIKE '%App\\Admin%');

UPDATE model_has_permissions
SET model_type = 'App\\Models\\Admin'
WHERE model_type NOT LIKE 'App\\Models\\Admin' AND (model_type LIKE '%Admin%' OR model_type LIKE '%App\\Admin%');

-- Ensure Admin-like roles use the 'admin' guard
UPDATE roles
SET guard_name = 'admin'
WHERE guard_name != 'admin' AND (name LIKE '%Admin%' OR name LIKE '%admin%' OR name LIKE '%Administrator%');

-- Ensure permissions use admin guard as well (safe for apps that use a single guard for permissions)
UPDATE permissions
SET guard_name = 'admin'
WHERE guard_name != 'admin';
SQL

echo "Assigning Admin role to model_id=3 (if role exists)..."
ADMIN_ROLE_ID=$(mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "SELECT id FROM roles WHERE name='Admin' LIMIT 1;" || true)
if [[ -z "$ADMIN_ROLE_ID" ]]; then
  ADMIN_ROLE_ID=$(mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "SELECT id FROM roles WHERE name LIKE '%Admin%' LIMIT 1;" || true)
fi

if [[ -n "$ADMIN_ROLE_ID" ]]; then
  echo "Found Admin role id = $ADMIN_ROLE_ID — inserting mapping (if missing)..."
  mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -sN -e "INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id) VALUES (${ADMIN_ROLE_ID}, 'App\\Models\\Admin', 3);"
else
  echo "Admin role not found — skipping role assignment.";
fi

echo "Resetting caches and permission cache via artisan..."
if [[ -f artisan ]]; then
  php artisan permission:cache-reset || true
  php artisan cache:clear || true
  php artisan config:clear || true
  php artisan view:clear || true
  php artisan route:clear || true
else
  echo "artisan not found in project root — skip artisan cache reset." >&2
fi

read -r -p "Set temporary password for admin@gmail.com now? (recommended if passwords changed) [y/N]: " SETPASS
if [[ "$SETPASS" =~ ^[Yy]$ ]]; then
  read -r -s -p "Enter new temporary password: " NEWPASS
  echo
  if [[ -z "$NEWPASS" ]]; then
    echo "Empty password — skipping.";
  else
    NEW_HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$NEWPASS")
    mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -D "$DB_DATABASE" -e "UPDATE admins SET password='${NEW_HASH}' WHERE email='admin@gmail.com';"
    echo "Temporary password set for admin@gmail.com — please force-change on first login.";
  fi
fi

echo "Post-import repair complete. Please test login and permission-protected routes. Backup is at: $BACKUP_FILE"
echo "If you run into issues, restore the DB from the backup and contact the developer for manual assistance."

unset MYSQL_PWD

exit 0

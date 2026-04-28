## Automated Deploy (GitHub Actions)

This repository includes a GitHub Actions workflow to perform a safe deploy to production when you push to `main`.

What it does
- Pulls `origin/main` on the server
- Runs `composer install` (if composer exists on the server)
- Runs project scripts: `scripts/disable_subscription_enforce.php` and `scripts/activate_subscription_db.php` (if present)
- Clears Laravel caches
- Optionally restarts `php-fpm` service (if you configure the secret)

Required repository secrets (set in GitHub Settings → Secrets):
- `PROD_HOST` — server host (IP or domain)
- `PROD_USER` — SSH user
- `SSH_KEY` — private key (PEM) for `PROD_USER` (no passphrase or use deploy key)
- `PROD_PATH` — absolute path to project on server (e.g. `/var/www/phdc.toamed.com`)
- `PROD_PORT` — optional SSH port (default 22)
- `PHP_FPM_SERVICE` — optional systemd service name to restart (e.g. `php8.1-fpm`)

How to enable
1. Add the required secrets to your repository.
2. Ensure the `PROD_USER` can run the commands in the workflow (git pull, composer, php, and optionally sudo to restart php-fpm). If `sudo` is required for restart, the user must have passwordless sudo for that command.
3. Push to `main` — the workflow will run automatically.

Security notes
- The workflow uses SSH keys via GitHub Secrets — make sure the private key is added only to GitHub Secrets and the public key is present in `~/.ssh/authorized_keys` for `PROD_USER` on the server.
- Review and remove temporary scripts from `scripts/` after the migration is complete, or restrict their file permissions.

Manual run (if you prefer to run commands yourself):
```bash
cd /path/to/project
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader
php scripts/disable_subscription_enforce.php
php scripts/activate_subscription_db.php
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
sudo systemctl restart php8.1-fpm  # optional
```

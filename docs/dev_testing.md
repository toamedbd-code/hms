Developer testing — run unit tests locally

This page explains how to run the project's unit tests on a developer machine (Windows / PowerShell).

1) Quick helper (recommended)

- Run the provided PowerShell helper which will auto-detect pdo_sqlite and fall back to MySQL if needed:

.\\scripts\\run-tests.ps1

You can pass DB parameters when needed:

.\\scripts\\run-tests.ps1 -DBUser root -DBPass '' -DBHost 127.0.0.1 -DBName hms_test

2) Enable pdo_sqlite (optional, faster)

On Windows (Laragon example):

- Open the CLI php.ini shown by `php --ini` (for example: C:\laragon\bin\php\php-8.2.0-...\php.ini).
- Uncomment (remove the leading `;`) these lines or add them if missing:

extension=pdo_sqlite
extension=sqlite3

- Restart Laragon / your web server / ensure CLI PHP is restarted.
- Verify with:

php -r "echo extension_loaded('pdo_sqlite') ? 'OK' : 'MISSING';"

3) Manual MySQL flow (if not enabling sqlite):

# create test DB (adjust credentials as necessary)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS hms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# run tests using the test DB
$env:DB_CONNECTION = 'mysql'; $env:DB_DATABASE = 'hms_test'; $env:DB_USERNAME = 'root'; $env:DB_PASSWORD = ''; $env:DB_HOST = '127.0.0.1'; php vendor\\bin\\phpunit --testsuite Unit

Notes

- The helper tries to be safe: if pdo_sqlite is present it uses an in-memory DB so tests are fast and isolated.
- If MySQL is used the helper will attempt to create the `hms_test` database automatically (requires `mysql` CLI in PATH).

4) Continuous Integration (GitHub Actions)

- CI workflow is provided at `.github/workflows/ci.yml`. It runs on `push` and `pull_request` to `main`, sets up PHP 8.2, starts a MySQL service, runs migrations and the **Unit** test suite.
- Use the composer scripts added to `composer.json`:

```
composer test        # runs phpunit locally
composer test:ci     # runs migrations and phpunit (used by CI)
```

This lets contributors run `composer test` locally, and ensures PRs trigger the same checks on CI.

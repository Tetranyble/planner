# Unipotence Composer package-discovery recovery (Increment 71 hotfix)

If `composer install` fails during `@php artisan package:discover` with an invalid Unipotence configuration, check overrides and stale config cache. The default Unipotence limits are valid.

````dotenv
UNIPOTENCE_DB_CONNECTION=mysql
UNIPOTENCE_MAX_KEY_LENGTH=255
UNIPOTENCE_MAX_RESPONSE_BYTES=1048576
UNIPOTENCE_RETENTION_DAYS=30
UNIPOTENCE_INDETERMINATE_RETENTION_DAYS=90
UNIPOTENCE_LEASE_SECONDS=300
UNIPOTENCE_MAX_REQUEST_SECONDS=120
````

After updating `.env` (or `.env.testing` for tests):

```sh
php artisan config:clear
composer dump-autoload
```

If your checkout is still on an older version of the provider and even `config:clear` fails, delete only `bootstrap/cache/config.php` and rerun the command; this does not touch MySQL.

**Fail-closed boundary:** Only `package:discover`, `config:clear` and `optimize:clear` skip boot-time Unipotence config validation. Normal HTTP requests, tests, workers and `config:cache` still validate, with error messages naming invalid variables. Skipping package discovery validation does not make invalid runtime configuration acceptable.

Use a dedicated local MySQL **test** database. Never run migrations or `RefreshDatabase` against production data.

# Planner

Planner is a Laravel application for multi-brand marketing planning, content review, social-account connections, and governed publishing workflows.

## Requirements

- PHP 8.4 and Composer
- Node.js and npm
- MySQL 8+ with a local development database

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Set the local MySQL credentials in `.env`, create the configured database, then run:

```bash
php artisan migrate
npm run dev
```

For the combined Laravel, queue, log, and Vite development processes, use:

```bash
composer run dev
```

## Tests

Tests use a disposable local MySQL database. Create a local `.env.testing` from `.env.testing.example`, set `APP_ENV=testing`, and use a dedicated database whose name ends in `_test` or `_testing`.

```bash
vendor/bin/phpunit
npm run test:controllers
```

The test safety guard refuses to run migrations unless the test environment is local MySQL, `APP_ENV=testing`, `DB_URL` is empty, and the database name is clearly dedicated to testing.

## Safety defaults

Provider publishing, autonomous agent actions, private creative previews, and remote revocation are disabled by default. Do not enable them until the relevant provider, storage, and MySQL verification requirements have been completed.

## Repository hygiene

Local credentials, test environment files, dependencies, IDE files, generated caches, and private engineering handoffs are excluded through `.gitignore` and must not be committed.

#!/usr/bin/env bash
# Local disposable MySQL test runner; refuses production DBs before tests migrate.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ! -f .env.testing ]]; then
  echo 'Missing .env.testing; copy .env.testing.example, set local disposable DB credentials, generate APP_KEY.' >&2
  exit 1
fi
if [[ ! -f vendor/autoload.php || ! -f vendor/bin/phpunit ]]; then
  echo 'Install Composer dependencies first: composer install' >&2
  exit 1
fi
if [[ -f bootstrap/cache/config.php ]]; then
  echo 'Cached Laravel configuration found; remove with php artisan config:clear BEFORE running tests.' >&2
  exit 1
fi
export APP_ENV=testing
export DB_CONNECTION=mysql
export DB_URL=
php scripts/assert_safe_test_database.php
suite="${1:-all}"
if [[ $# -gt 0 ]]; then shift; fi
if [[ "$suite" == 'all' ]]; then
  suites=(Unit Feature Integration)
elif [[ "$suite" == 'Unit' || "$suite" == 'Feature' || "$suite" == 'Integration' ]]; then
  suites=("$suite")
else
  echo 'Usage: scripts/run_mysql_tests.sh [all|Unit|Feature|Integration] [phpunit flags]' >&2
  exit 2
fi
run_phpunit() {
  php -d "memory_limit=${PHP_TEST_MEMORY_LIMIT:-512M}" vendor/bin/phpunit \
    --no-coverage --colors=never "$@"
}
for name in "${suites[@]}"; do
  echo "=== MySQL $name tests (memory limit ${PHP_TEST_MEMORY_LIMIT:-512M}) ==="
  if [[ "${PLANNER_TEST_SPLIT_BY_DIRECTORY:-0}" == '1' ]]; then
    # Optional low-memory mode. Each feature area gets a fresh PHP process,
    # trading additional migration time for smaller peak memory usage.
    while IFS= read -r dir; do
      echo "  >> $dir"
      run_phpunit "$dir" "$@"
    done < <(find "tests/$name" -mindepth 1 -maxdepth 1 -type d | sort)
    mapfile -t files < <(find "tests/$name" -mindepth 1 -maxdepth 1 -name '*Test.php' -type f | sort)
    if [[ "${#files[@]}" -gt 0 ]]; then
      # Short-lived PHP processes cap retained test-model metadata and ORM
      # application state. Avoid per-test process isolation (very slow on MySQL).
      batch_size="${PLANNER_TEST_FILE_BATCH_SIZE:-8}"
      if ! [[ "$batch_size" =~ ^[1-9][0-9]*$ ]]; then
        echo 'PLANNER_TEST_FILE_BATCH_SIZE must be a positive integer.' >&2
        exit 2
      fi
      batch=()
      for file in "${files[@]}"; do
        batch+=("$file")
        if [[ "${#batch[@]}" -ge "$batch_size" ]]; then
          run_phpunit "${batch[@]}" "$@"
          batch=()
        fi
      done
      if [[ "${#batch[@]}" -gt 0 ]]; then
        run_phpunit "${batch[@]}" "$@"
      fi
    fi
  else
    run_phpunit --testsuite="$name" "$@"
  fi
done

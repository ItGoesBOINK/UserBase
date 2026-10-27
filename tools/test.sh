#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <filename.php>" >&2
  exit 1
fi

test_file="$1"

if [[ ! -f "tests/${test_file}.php" ]]; then
  echo "Error: Test file not found: $test_file" >&2
  exit 1
fi

docker compose run --rm --no-deps \
  -v "$PWD/tests:/var/www/html/tests:ro" \
  userbase \
  php "/var/www/html/tests/${test_file}.php"
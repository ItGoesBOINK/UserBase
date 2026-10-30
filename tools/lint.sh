#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <filename.php>" >&2
  exit 1
fi

file="$1"

if [[ ! -f "${file}.php" ]]; then
  echo "Error: File not found: ${file}" >&2
  exit 1
fi

docker compose run --rm --no-deps userbase \
  php -l "/var/www/html/${file}.php"
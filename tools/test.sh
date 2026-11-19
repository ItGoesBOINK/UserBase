#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

source "$SCRIPT_DIR/lib/console.sh"


headerBar="================================"
footerBar="--------------------------------"
rebuild=false
logs=false
clear=false
flushEventsBefore=false
flushEventsAfter=false

header() {
  printf '%s %s %s\n\n' "$headerBar" "$1" "$headerBar"
}
footer() {
  printf '%s------%s\n' "$footerBar" "$footerBar"
}



if [[ "${1:-}" == "-a" ]]; then
  rebuild=true
  clear=true
  logs=true
  flushEventsBefore=true
  flushEventsAfter=true
  shift
fi

if [[ "${1:-}" == "-c" ]]; then
  clear=true
  shift
fi

if [[ "${1:-}" == "-r" ]]; then
  rebuild=true
  shift
fi

if [[ "${1:-}" == "-l" ]]; then
  logs=true
  shift
fi

if [[ "${1:-}" == "-feb" ]]; then
  flushEventsBefore=true
  shift
fi

if [[ "${1:-}" == "-fea" ]]; then
  flushEventsAfter=true
  shift
fi

if [[ "${1:-}" == "-fe" ]]; then
  flushEventsBefore=true
  flushEventsAfter=true
  shift
fi

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 -a | -c | -r | -l <filename.php>" >&2
  echo "-a   All Options"
  echo "-c   Clear First"
  echo "-r   Rebuild"
  echo "-l   Show Logs After Test"
  echo "-feb Flush Rate-Limit Events Table before Testing"
  echo "-fea Flush Rate-Limit Events Table after Testing"
  echo "-fe  Flush Rate-Limit Events Table before and after Testing"
  exit 1
fi

test_file="$1"

cd "$PROJECT_ROOT"

if [[ ! -f "tests/${test_file}.php" ]]; then
  echo "Error: Test file not found: $test_file" >&2
  exit 1
fi

if [[ "$clear" == true ]]; then
  clear
fi

if [[ "$rebuild" == true ]]; then

  header "BUILD"
  docker compose build userbase
  docker compose up -d --no-deps userbase
  docker compose ps userbase
  footer
  if [[ "$clear" == true ]]; then
    clear
  fi
fi

if [[ "$flushEventsBefore" == true ]]; then
  source "$SCRIPT_DIR/clear-rate-limits.sh"
fi

header "TEST"

set +e
docker compose run --rm --no-deps \
  -v "$PWD/tests:/var/www/html/tests:ro" \
  userbase \
  php "/var/www/html/tests/${test_file}.php"

test_status=$?

set -e

footer

if [[ "$flushEventsAfter" == true ]]; then
  source "$SCRIPT_DIR/clear-rate-limits.sh"
fi

if [[ "$logs" == true ]]; then
  header "LOGS"
  LOGS=$(docker compose logs --tail=100 userbase)
  echo "$LOGS" | sed 's/, /,\
  - /g'
fi
footer
printf "\n"
exit "$test_status"


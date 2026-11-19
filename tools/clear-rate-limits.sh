#!/usr/bin/env bash

# ----------------------------------------------------------
# Initialization
# ----------------------------------------------------------

# Resolve project root relative to this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT" || exit 1

# ----------------------------------------------------------
# Confirmation
# ----------------------------------------------------------

# echo
# echo "WARNING: This will delete ALL rate-limit events!"
# echo

# read -r -p "Type CLEAR to continue: " CONFIRMATION

# if [[ "$CONFIRMATION" != "CLEAR" ]]; then
#  echo "Operation Cancelled."
#  exit 0
# fi


# ----------------------------------------------------------
# Execute
# ----------------------------------------------------------

docker compose exec -T userbase php bin/clear-rate-limits.php --confirm

# exit $?
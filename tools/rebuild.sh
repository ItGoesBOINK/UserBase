#1/use/bin/env bash

# Stop on errors, undefined variables, and failed pipeline commands
set -euo pipefail

# Move to the project root, regardless of where this script is launched
cd "$(dirname "$0")/.."

# Optional database refresh
REFRESH_DB=false

if [[ "${1:-}" == "-r" ]]; then
  REFRESH_DB=true
elif [[ $# -gt 0 ]]; then
  echo "Usage: $0 [-r]"
  exit 1
fi

# Rebuild the application image using the latest local source and configuration
docker compose build userbase

# Stop containers without removing persistent volumes
docker compose down

# Intentionally remove and re-create the database volume when requested
if [[ "$REFRESH_DB" == true ]]; then
  echo "WARNING: Removing Docker volumes and refreshing the database!"
  docker compose down -v
fi

# Recreate and start the application container in the background
docker compose up -d # --no-deps userbase

# Show the current container status
docker compose ps userbase

echo "UserBase container rebuilt and restarted."
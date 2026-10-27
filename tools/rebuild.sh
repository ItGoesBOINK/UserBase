#1/use/bin/env bash

# Stop on errors, undefined variables, and failed pipeline commands
set -euo pipefail

# Move to the project root, regardless of where this script is launched
cd "$(dirname "$0")/.."

# Rebuld the application image using the latest local source and configuration
docker compose build userbase

# Recreate and start the application container in the background
docker compose up -d --no-deps userbase

# Show the current container status
docker compose ps userbase

echo "UserBase container rebuilt and restarted."
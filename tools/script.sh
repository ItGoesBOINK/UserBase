#!/usr/bin/env bash

set -uo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

source "$SCRIPT_DIR/lib/console.sh"

printf "🟢 %s\n" "Green"
printf "🔴🔴🔴🔴🔴🔴\n"
printf '🍺 Homebrew\n'
printf '\U0001F37A Homebrew\n'
printf '\xF0\x9F\x8D\xBA Homebrew\n'

icon_red_x printf " Oh No!\n"
icon_green_check printf " Neat!\n"

exit 0
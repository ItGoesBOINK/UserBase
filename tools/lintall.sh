#!/usr/bin/env bash

set -uo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

source "$SCRIPT_DIR/lib/console.sh"

PASSED=0
FAILED=0

#echo
#echo "=================================================="
#echo " PHP Syntax Check"
#echo "=================================================="
#echo
console_section "PHP Syntax Check"

while IFS= read -r -d '' FILE; do
  RELATIVE_PATH="${FILE#"$PROJECT_ROOT"}"

  if OUTPUT=$(php -l "$FILE" 2>&1); then
    #echo "[🟢 PASS] $RELATIVE_PATH"
    console_pass "$RELATIVE_PATH"
    ((PASSED += 1))
  else
    #echo "[🔴 FAIL] $RELATIVE_PATH"
    console_fail "$RELATIVE_PATH"
    echo "$OUTPUT" | sed 's/^/       /'

    # Extract the reported error line number
    ERROR_LINE=$(echo "$OUTPUT" | sed -nE 's/.*on line ([0-9]+).*/\1/p' | tail -n 1)

    if [[ -n "$ERROR_LINE" ]]; then
      START_LINE=$((ERROR_LINE > 2 ? ERROR_LINE -2 : 1))
      END_LINE=$((ERROR_LINE + 2))

      echo
      echo "       Source context (line $ERROR_LINE):"

      nl -ba "$FILE" | sed -n "${START_LINE},${END_LINE}" |
        sed 's/^/       /'
    fi

    ((FAILED += 1))
  fi
done < <(
  find "$PROJECT_ROOT" \
    -type d \( -name vendor -o -name .git -o -name node_modules \) -prune \
    -o -type f -name '*.php' -print0 | sort -z
)

echo
echo "=================================================="
Echo " Results "
echo "=================================================="
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo "Total:  $((PASSED + FAILED))"
echo

if (( FAILED > 0 )); then
  echo "🔴 PHP Syntax check failed."
  exit 1
fi

echo "🟢 All PHP files passed syntax checking."
exit 0

#!/usr/bin/env bash

I_DOT='●'
I_DBL_RING='◎'
I_RING_DOT_LG='◉'
I_RING_DOT_SM='⦿'
I_OFF='⦾'
I_ON='⦿'
I_BOX='☐'
I_BOX_X='☒'
I_BOX_CHK='☑︎'
I_STAR='★'
I_CHK='✓'
I_CHK_BOLD='✔︎'
I_X='✕'
I_X_BOLD='✖︎'
I_TRI_FILLED='▶︎'
I_TRI_EMPTY='▷'
I_HAPPY='☻'
I_SAD='☹︎'

# --------------------------------------------------
# Terminal Colors
# --------------------------------------------------

if [[ -t 1 && -z "${NO_COLOR:-}" ]]; then
  C_RESET=$'\033[0m'

  C_BLACK=$'\033[0;30m'
  C_RED=$'\033[0;31m'
  C_GREEN=$'\033[0;32m'
  C_YELLOW=$'\033[0;33m'
  C_BLUE=$'\033[0;34m'
  C_MAGENTA=$'\033[0;35m'
  C_CYAN=$'\033[0;36m'
  C_WHITE=$'\033[0;37m'

  C_BOLD=$'\033[1m'
  C_DIM=$'\033[2m'
  C_UNDERLINE=$'\033[4m'
else
  C_RESET=''

  C_BLACK=''
  C_RED=''
  C_GREEN=''
  C_YELLOW=''
  C_BLUE=''
  C_MAGENTA=''
  C_CYAN=''
  C_WHITE=''

  C_BOLD=''
  C_DIM=''
  C_UNDERLINE=''
fi


# --------------------------------------------------
# Formatting Helpers
# --------------------------------------------------

console_color() {
  local COLOR_CODE="$1"
  local MESSAGE="$2"

  printf '%s%s%s\n' "$COLOR_CODE" "$MESSAGE" "$C_RESET"
}

console_inline() {
  printf '%b' "$@"
  printf '%s\n' "$C_RESET"
 }

console_bold() {
  printf '%s%s%s\n' "$C_BOLD" "$1" "$C_RESET"
}

console_dim() {
  printf '%s%s%s\n' "$C_DIM" "$1" "$C_RESET"
}

console_underline() {
  printf '%s%s%s\n' "$C_UNDERLINE" "$1" "$C_RESET"
}

console_section() {
  printf '\n'
  console_line
  console_bold "$1"
  console_line
  printf '\n'
}

console_label() {
  local LABEL_COLOR="$1"
  local LABEL="$2"
  local DESC="$3"

  console_inline "$LABEL_COLOR" "$C_DIM" "[" "$C_RESET" "$LABEL_COLOR" "$LABEL" "$LABEL_COLOR" "$C_DIM" "] " "$C_RESET" "$DESC"
}

icon_red_x() {
  printf '%s%s%s ' "$C_RED" "$I_X_BOLD" "$C_RESET"
}

icon_green_check() {
  printf '%s%s%s ' "$C_GREEN" "$I_CHK_BOLD" "$C_RESET"
}


# --------------------------------------------------
# Status Helpers
# --------------------------------------------------

console_pass() {
  icon_green_check
  console_label "$C_GREEN" "PASS" "$1"
}
console_fail() {
  icon_red_x
  console_label "$C_RED" "FAIL" "$1"
}
console_warn() {
  console_label "$C_YELLOW" "WARN" "$1"
}
console_info() {
  console_label "$C_CYAN" "INFO" "$1"
}
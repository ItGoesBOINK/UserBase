#!/usr/bin/env bash

# ------------------------------------------------------
# Configuration
# ------------------------------------------------------

set -uo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

source "$SCRIPT_DIR/lib/console.sh"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env file not found: $ENV_FILE" >&2
  exit 1
fi

API_BASE_URL=""
APP_URL=""

while IFS='=' read -r key value; do
  if [[ "$key" == "APP_URL" ]]; then
    APP_URL="$value"
    break
  fi
done < "$ENV_FILE"

if [[ -z "$APP_URL" ]]; then
  echo "ERROR: APP_URL is missing from .env" >&2
  exit 1
fi

# Remove trailing slash if present
API_BASE_URL="${APP_URL%/}/api"

# ------------------------------------------------------
# Defaults
# ------------------------------------------------------

API_PATH=""
AUTH_TYPE=""
USERNAME=""
PASSWORD=""
EMAIL=""


# ------------------------------------------------------
# Argument Parsing
# ------------------------------------------------------

if [[ $# -lt 1 ]]; then
  echo "Usage: api.sh <path> [-a (b)earer|(c)ookie] [-u username] [-p password]"
  exit 1
fi

API_PATH="$1"
shift

while [[ "$API_PATH" == /* ]]; do
  API_PATH="${API_PATH#/}"
done

while [[ $# -gt 0 ]]; do

  case "$1" in

    -a|--auth)
      if [[ $# -lt 2 ]]; then
        echo "ERROR: -a requires an authentication type" >&2
        exit 1
      fi

      AUTH_TYPE="$2"
      shift 2
      ;;

    -u|--username)
      if [[ $# -lt 2 ]]; then
        echo "ERROR: -u requires a username" >&2
        exit 1
      fi

      USERNAME="$2"
      shift 2
      ;;

    -p|--password)
      if [[ $# -lt 2 ]]; then
        echo "ERROR: -p requires a password" >&2
        exit 1
      fi

      PASSWORD="$2"
      shift 2
      ;;

    -e|--email)
      [[ $# -lt 2 ]] && { echo "ERROR: -p requires a password" >&2; exit 1; }
      EMAIL="$2"
      shift 2
      ;;

    *)
      echo "ERROR: Unknown argument: $1" >&2
      exit 1
      ;;

  esac
done


# ------------------------------------------------------
# Validation
# ------------------------------------------------------

case "$AUTH_TYPE" in
  "")
    if [[ -n "$USERNAME" || -n "$PASSWORD" ]]; then
      echo "ERROR: Credentials require an authentication type (-a)" >&2
      exit 1
    fi
    ;;

  c|cookie)
    AUTH_TYPE="cookie"
    ;;

  b|bearer)
    AUTH_TYPE="bearer"
    ;;

  *)
    echo "ERROR: Authentication type must be c/cookie or b/bearer" >&2
    exit 1
    ;;
esac

if [[ -n "$AUTH_TYPE" ]]; then

  if [[ -z "$USERNAME" ]]; then
    echo "ERROR: Username is required for authentication" >%2
    exit 1
  fi

fi

# ------------------------------------------------------
# Request Preview (temporary)
# ------------------------------------------------------

# echo "API URL: $APP_URL/$API_PATH"
# echo "Authentication: ${AUTH_TYPE:-none}"
# echo "Username: ${USERNAME:-none}"
# echo "Password: $([[ -n "$PASSWORD" ]] && echo '[provided]' || echo '[none]')"

# ------------------------------------------------------
# Execute
# ------------------------------------------------------

ENDPOINT_URL="${API_BASE_URL}/${API_PATH}"

# Registration Request
if [[ "$API_PATH" == "register" ]]; then

  if [[ -z "$USERNAME" || -z "$PASSWORD" || -z "$EMAIL" ]]; then
    echo "ERROR: Registration requires a username, password, and email" >&2
    exit 1
  fi

  if ! command -v jq >/dev/null 2>&1; then
    echo "ERROR: jq is required for registration" >&2
    exit 1
  fi

  REGISTER_BODY=$(jq -n \
    --arg username "$USERNAME" \
    --arg password "$PASSWORD" \
    --arg email "$EMAIL" \
    '{
      username: $username,
      password: $password,
      email: $email
    }')

  curl -sS -i -H "Content-Type: application-json" \
    -d "$REGISTER_BODY" "$ENDPOINT_URL"

  exit $?

fi

# Anonymous Request
if [[ -z "$AUTH_TYPE" ]]; then
  curl -sS -i "$ENDPOINT_URL"
  exit $?
fi

# Authentication requires jq for JSON handling
if ! command -v jq >/dev/null 2>&1; then
  echo "ERROR: jq is required for authenticated requests." >&2
  exit 1
fi

# Build login request body
LOGIN_BODY=$(jq -n \
  --arg username "$USERNAME" \
  --arg password "$PASSWORD" \
  --arg transport "$AUTH_TYPE" \
  '{
    username: $username,
    password: $password,
    session_transport: $transport
  }')

# Temporary files for this invocation
COOKIE_JAR=$(mktemp)
LOGIN_RESPONSE=$(mktemp)

trap 'rm -f "$COOKIE_JAR" "$LOGIN_RESPONSE"' EXIT

# Authenticate
LOGIN_STATUS=$(curl -sS \
  -o "$LOGIN_RESPONSE" \
  -w "%{http_code}" \
  -c "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -d "$LOGIN_BODY" \
  "$API_BASE_URL/login")

# Stop if Login failed
if [[ ! "$LOGIN_STATUS" =~ ^2[0-9][0-9]$ ]]; then
  echo "ERROR: Login failed (HTTP $LOGIN_STATUS)" >&2
  cat "$LOGIN_RESPONSE" >&2
  exit 1
fi

# Cookie Authentication
if [[ "$AUTH_TYPE" == "cookie" ]]; then
  curl -sS -i -b "$COOKIE_JAR" "$ENDPOINT_URL"

# Bearer Authentication
elif [[ "$AUTH_TYPE" == "bearer" ]]; then
  SESSION_TOKEN=$(jq -r \
    '.data.session_token // .session_token // empty' \
    "$LOGIN_RESPONSE")

  if [[ -z "$SESSION_TOKEN" ]]; then
    echo "ERROR: Login response did not contain a session token" >&2
    cat "$LOGIN_RESPONSE" >&2
    exit 1
  fi

  curl -sS -i -H "Authorization: Bearer $SESSION_TOKEN" "$ENDPOINT_URL"

fi

exit 0
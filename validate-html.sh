#!/bin/bash

# HTML Validator local (vnu.jar) — ltw07g01
# Usage: ./validate-html.sh [PORT]

PORT="${1:-9000}"
BASE="http://localhost:$PORT"
VNU=$(find /home -name "vnu.jar" 2>/dev/null | head -1)

if [ -z "$VNU" ]; then
  echo "ERRO: vnu.jar não encontrado. Instala com: pip install html5validator"
  exit 1
fi

echo "  Validador: $VNU"

COOKIE_USER=$(mktemp)
COOKIE_ADMIN=$(mktemp)
COOKIE_TRAINER=$(mktemp)
TMPHTML=$(mktemp --suffix=.html)
cleanup() { rm -f "$COOKIE_USER" "$COOKIE_ADMIN" "$COOKIE_TRAINER" "$TMPHTML"; }
trap cleanup EXIT

declare -A PAGES=(
  ["actions/login.php"]="pub"
  ["actions/register.php"]="pub"
  ["pages/index.php"]="pub"
  ["pages/about.php"]="pub"
  ["pages/trainers.php"]="pub"
  ["pages/locations.php"]="pub"
  ["pages/schedule.php"]="pub"
  ["pages/membership.php"]="pub"
  ["pages/equipment.php"]="user"
  ["pages/dashboard.php"]="user"
  ["pages/profile.php"]="user"
  ["pages/trainer-profile.php?id=4"]="user"
  ["pages/confirm-purchase.php?plan=gym-1"]="user"
  ["actions/edit-profile.php"]="user"
  ["actions/edit-admin-profile.php"]="admin"
  ["pages/admin-profile.php"]="admin"
  ["pages/admin-members.php"]="admin"
  ["actions/edit-trainer-profile.php"]="trainer"
)

PAGE_ORDER=(
  "actions/login.php"
  "actions/register.php"
  "pages/index.php"
  "pages/about.php"
  "pages/trainers.php"
  "pages/locations.php"
  "pages/schedule.php"
  "pages/membership.php"
  "pages/equipment.php"
  "pages/dashboard.php"
  "pages/profile.php"
  "pages/trainer-profile.php?id=4"
  "pages/confirm-purchase.php?plan=gym-1"
  "actions/edit-profile.php"
  "actions/edit-admin-profile.php"
  "pages/admin-profile.php"
  "pages/admin-members.php"
  "actions/edit-trainer-profile.php"
)

ERRORS_TOTAL=0
WARNINGS_TOTAL=0
PROCESSED=0
SKIPPED=0

echo "=================================================="
echo "  HTML Validator — vnu (local)"
echo "  Server: $BASE"
echo "  Total de páginas: ${#PAGE_ORDER[@]}"
echo "=================================================="
echo ""

echo ">> A fazer login como user (ana@cubogym.com)..."
curl -s -c "$COOKIE_USER" -b "$COOKIE_USER" \
  -X POST "$BASE/actions/login.php" \
  --data-urlencode "email=ana@cubogym.com" \
  --data-urlencode "password=password123" \
  -L -o /dev/null
echo "   OK"

echo ">> A fazer login como trainer (maria.fernandes@cubogym.com)..."
curl -s -c "$COOKIE_TRAINER" -b "$COOKIE_TRAINER" \
  -X POST "$BASE/actions/login.php" \
  --data-urlencode "email=maria@cubogym.com" \
  --data-urlencode "password=password123" \
  -L -o /dev/null
echo "   OK"

echo ">> A fazer login como admin (admin@cubogym.com)..."
curl -s -c "$COOKIE_ADMIN" -b "$COOKIE_ADMIN" \
  -X POST "$BASE/actions/login.php" \
  --data-urlencode "email=admin@cubogym.com" \
  --data-urlencode "password=password123" \
  -L -o /dev/null
echo "   OK"
echo ""

for PAGE in "${PAGE_ORDER[@]}"; do
  COOKIE_TYPE="${PAGES[$PAGE]}"
  URL="$BASE/$PAGE"

  case "$COOKIE_TYPE" in
    admin)   COOKIE_FILE="$COOKIE_ADMIN";   TAG="[ADMIN]"   ;;
    trainer) COOKIE_FILE="$COOKIE_TRAINER"; TAG="[TRAINER]" ;;
    user)    COOKIE_FILE="$COOKIE_USER";    TAG="[USER]"    ;;
    *)       COOKIE_FILE="";               TAG="[PUB]"     ;;
  esac

  echo ">> $TAG $PAGE"

  if [ -n "$COOKIE_FILE" ]; then
    curl -s --max-time 10 -L -c "$COOKIE_FILE" -b "$COOKIE_FILE" "$URL" > "$TMPHTML"
  else
    curl -s --max-time 10 -L "$URL" > "$TMPHTML"
  fi

  if [ ! -s "$TMPHTML" ]; then
    echo "   [SKIP] Servidor não respondeu"
    SKIPPED=$((SKIPPED + 1))
    echo ""
    continue
  fi

  if ! grep -qi "<!DOCTYPE" "$TMPHTML"; then
    echo "   [SKIP] Sem DOCTYPE — redirect de autenticação?"
    SKIPPED=$((SKIPPED + 1))
    echo ""
    continue
  fi

  # Validate with local vnu.jar
  RESULT=$(java -jar "$VNU" --format json "$TMPHTML" 2>&1)

  PARSED=$(echo "$RESULT" | python3 -c "
import sys, json
data = json.load(sys.stdin)
msgs = data.get('messages', [])
errors = [m for m in msgs if m.get('type') == 'error']
warnings = [m for m in msgs if m.get('type') == 'info' and m.get('subType') == 'warning']
print(f'ERRORS:{len(errors)}')
print(f'WARNINGS:{len(warnings)}')
for m in errors:
    line = m.get('lastLine', '?')
    col = m.get('lastColumn', '?')
    msg = m.get('message', '')
    print(f'  [ERRO] linha {line}, col {col}: {msg}')
for m in warnings:
    line = m.get('lastLine', '?')
    col = m.get('lastColumn', '?')
    msg = m.get('message', '')
    print(f'  [WARN] linha {line}, col {col}: {msg}')
" 2>/dev/null)

  if [ -z "$PARSED" ]; then
    echo "   [SKIP] Falha ao processar resultado do vnu"
    SKIPPED=$((SKIPPED + 1))
    echo ""
    continue
  fi

  PAGE_ERRORS=$(echo "$PARSED" | grep "^ERRORS:" | cut -d: -f2)
  PAGE_WARNINGS=$(echo "$PARSED" | grep "^WARNINGS:" | cut -d: -f2)
  PAGE_ERRORS=${PAGE_ERRORS:-0}
  PAGE_WARNINGS=${PAGE_WARNINGS:-0}

  ERRORS_TOTAL=$((ERRORS_TOTAL + PAGE_ERRORS))
  WARNINGS_TOTAL=$((WARNINGS_TOTAL + PAGE_WARNINGS))
  PROCESSED=$((PROCESSED + 1))

  if [ "$PAGE_ERRORS" -eq 0 ] && [ "$PAGE_WARNINGS" -eq 0 ]; then
    echo "   [OK] Sem erros nem avisos"
  else
    echo "   Erros: $PAGE_ERRORS  |  Avisos: $PAGE_WARNINGS"
    echo "$PARSED" | grep -E "^\s+\[(ERRO|WARN)\]"
  fi

  echo ""
done

echo "=================================================="
echo "  Validadas:  $PROCESSED / ${#PAGE_ORDER[@]} páginas"
if [ "$SKIPPED" -gt 0 ]; then
  echo "  Saltadas:   $SKIPPED  (ver [SKIP] acima)"
fi
echo "  TOTAL: $ERRORS_TOTAL erros | $WARNINGS_TOTAL avisos"
echo "=================================================="

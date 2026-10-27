#!/usr/bin/env bash
# WebDevHub DASHBOARD SMOKE TEST — end-to-end flow:
# dashboard khulta hai -> project banta hai (dashboard form se) ->
# project list mein ata hai -> start -> site 200 -> one-click admin
# (magic link se wp-admin 200) -> stop -> export zip -> delete -> list khaali.
# Report: tests/reports/dashboard-smoke-<stamp>.md
set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
TEST_ROOT="${WDH_TEST_DIR:-$HOME/workspace/webdevhub-test-v2}"
export WDH_SITES_DIR="$TEST_ROOT/dash-sites-$STAMP"
export WDH_DASH_PORT=17777
DASH="http://127.0.0.1:$WDH_DASH_PORT"
REPORT_DIR="$REPO/tests/reports"
mkdir -p "$REPORT_DIR" "$WDH_SITES_DIR"
REPORT="$REPORT_DIR/dashboard-smoke-$STAMP.md"
PASS=0; FAIL=0
echo "# WebDevHub Dashboard Smoke Test — $STAMP" > "$REPORT"; echo "" >> "$REPORT"

ok()   { echo "- [x] PASS — $1" | tee -a "$REPORT"; PASS=$((PASS+1)); }
bad()  { echo "- [ ] FAIL — $1" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (mila: $2, chahiye tha: $3)"; fi; }

PHP_BIN="${WDH_PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)";
  elif [ -x "$HOME/workspace/webdevhub-test/php-common/php" ]; then PHP_BIN="$HOME/workspace/webdevhub-test/php-common/php"; fi
fi
export WDH_PHP="$PHP_BIN"

COOKIES="$TEST_ROOT/dash-cookies-$STAMP.txt"
rm -f "$COOKIES"

# 1. Dashboard start + page
WDH_SITES_DIR="$WDH_SITES_DIR" bash "$REPO/panel/start-dashboard.sh" start >> "$REPORT" 2>&1
CODE="$(curl -s -o "$TEST_ROOT/dash-home.html" -w '%{http_code}' --max-time 30 -c "$COOKIES" "$DASH/")"
check "Dashboard page HTTP 200" "$CODE" "200"
grep -q "WebDev" "$TEST_ROOT/dash-home.html" && ok "Dashboard par WebDev Hub title" || bad "Dashboard par WebDev Hub title"
grep -q "Naya Project Banayein" "$TEST_ROOT/dash-home.html" && ok "Create form mojood" || bad "Create form mojood"

# CSRF token page se lo
CSRF="$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TEST_ROOT/dash-home.html" | head -1 | sed 's/.*value="//;s/"//')"
[ -n "$CSRF" ] && ok "CSRF token mila" || bad "CSRF token mila"

# 2. Dashboard se project banao (business blueprint)
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -c "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF" --data-urlencode "action=create" \
  --data-urlencode "name=demobiz" --data-urlencode "blueprint=business" \
  --data-urlencode "admin_user=admin" --data-urlencode "admin_email=admin@example.local" \
  --data-urlencode "admin_pass=DashTest!234"
ok "Create request bhej di"

# 3. Project banne ka intezar (install engine peeche chalti hai)
DONE=0
for i in $(seq 1 36); do
  if [ -f "$WDH_SITES_DIR/demobiz/.webdevhub.json" ]; then DONE=1; break; fi
  sleep 10
done
check "Project dashboard se ban gaya (.webdevhub.json)" "$DONE" "1"

curl -s --max-time 30 -b "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-list.html"
grep -q "demobiz" "$TEST_ROOT/dash-list.html" && ok "Project dashboard list mein hai" || bad "Project dashboard list mein hai"

PORT="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/demobiz/.webdevhub.json'))['port'])" 2>/dev/null)"
SITE="http://127.0.0.1:$PORT"

# 4. Start (dashboard action)
CSRF2="$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TEST_ROOT/dash-list.html" | head -1 | sed 's/.*value="//;s/"//')"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=start" --data-urlencode "site=demobiz"
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "$SITE/")"
check "Site start hokar front page HTTP 200" "$CODE" "200"

# 5. One-click admin (magic link)
ADMIN_URL="$(grep -o "href=\"[^\"]*wdh_login=[^\"]*\"" "$TEST_ROOT/dash-list.html" | head -1 | sed 's/href="//;s/"//' | sed 's/&amp;/\&/g')"
if [ -n "$ADMIN_URL" ]; then
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 -c "$TEST_ROOT/wp-cookies.txt" -L "$ADMIN_URL")"
  check "One-click admin: magic link ke baad wp-admin HTTP 200" "$CODE" "200"
else
  bad "One-click admin: magic link dashboard mein nahi mila"
fi

# 6. Export zip
curl -s -o "$TEST_ROOT/demobiz-export.zip" --max-time 120 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=export" --data-urlencode "site=demobiz"
if [ -s "$TEST_ROOT/demobiz-export.zip" ] && python3 -c "import zipfile;zipfile.ZipFile('$TEST_ROOT/demobiz-export.zip')" 2>/dev/null; then
  ok "Export zip download hua aur valid hai"
else
  bad "Export zip download hua aur valid hai"
fi

# 7. Stop + Delete
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=stop" --data-urlencode "site=demobiz"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" "$DASH/" > /dev/null
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=delete" --data-urlencode "site=demobiz"
[ ! -d "$WDH_SITES_DIR/demobiz" ] && ok "Delete: project folder gaya" || bad "Delete: project folder gaya"
curl -s --max-time 30 -b "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-final.html"
# Note: flash message mein project ka naam ata hai — is liye check naam
# se nahi, site ke URL (card) se hota hai.
grep -q "127.0.0.1:$PORT" "$TEST_ROOT/dash-final.html" && bad "Delete ke baad list khaali" || ok "Delete ke baad list khaali"

bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
{ echo ""; echo "## Natija: PASS=$PASS FAIL=$FAIL"; } >> "$REPORT"
echo ""; echo "DASHBOARD SMOKE KHATAM — PASS=$PASS FAIL=$FAIL"; echo "Report: $REPORT"
[ "$FAIL" -eq 0 ]

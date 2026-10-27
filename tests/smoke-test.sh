#!/usr/bin/env bash
# WebDevHub SMOKE TEST — fresh install par lazmi checks.
# Do sites banti hain (business + store), server chalta hai, HTTP + WP
# state dono verify hote hain, report tests/reports/ mein banti hai.
# Istemal: bash tests/smoke-test.sh
set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
TEST_ROOT="${WDH_TEST_DIR:-$HOME/workspace/webdevhub-test-v2}"
export WDH_SITES_DIR="$TEST_ROOT/sites-$STAMP"
export WDH_ADMIN_USER="admin"
export WDH_ADMIN_EMAIL="admin@example.local"
export WDH_ADMIN_PASS="SmokeTest!234"

REPORT_DIR="$REPO/tests/reports"
mkdir -p "$REPORT_DIR" "$WDH_SITES_DIR"
REPORT="$REPORT_DIR/smoke-$STAMP.md"
PASS=0; FAIL=0
{
  echo "# WebDevHub Smoke Test Report — $STAMP"
  echo ""
} > "$REPORT"

check() { # check <label> <command...>
  local label="$1"; shift
  if "$@" >/dev/null 2>&1; then
    echo "- [x] PASS — $label" | tee -a "$REPORT"; PASS=$((PASS+1))
  else
    echo "- [ ] FAIL — $label" | tee -a "$REPORT"; FAIL=$((FAIL+1))
  fi
}
http_ok() { # pehli request slow ho sakti hai — 60s timeout, 2 koshishein
  local try
  for try in 1 2; do
    [ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "$1")" = "200" ] && return 0
    sleep 2
  done
  return 1
}

PHP_BIN="${WDH_PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)";
  elif [ -x "$HOME/workspace/webdevhub-test/php-common/php" ]; then PHP_BIN="$HOME/workspace/webdevhub-test/php-common/php"; fi
fi
export WDH_PHP="$PHP_BIN"
SETUP="$REPO/installer-linux/setup-site.php"

echo "== Bundle fetch ==" | tee -a "$REPORT"
bash "$REPO/bundle/fetch-bundle.sh" >> "$REPORT" 2>&1
check "Bundle fetch (WP core + plugins)" test -f "$REPO/bundle/wordpress/dist/wordpress-6.8.3.zip"

echo "== Business site ==" | tee -a "$REPORT"
bash "$REPO/installer-linux/install.sh" --site biz --blueprint business --port 18081 >> "$REPORT" 2>&1
check "Business install script exit 0" test -f "$WDH_SITES_DIR/biz/.webdevhub.json"
bash "$REPO/installer-linux/webdevhub.sh" start biz >> "$REPORT" 2>&1
sleep 1
check "Business front page HTTP 200" http_ok "http://127.0.0.1:18081/"
check "Business /about/ HTTP 200" http_ok "http://127.0.0.1:18081/about/"
check "Business /services/ HTTP 200" http_ok "http://127.0.0.1:18081/services/"
check "Business /contact/ HTTP 200" http_ok "http://127.0.0.1:18081/contact/"
check "Business login page HTTP 200" http_ok "http://127.0.0.1:18081/wp-login.php"
curl -s --max-time 60 "http://127.0.0.1:18081/" -o "$TEST_ROOT/biz-home.html" || \
  curl -s --max-time 60 "http://127.0.0.1:18081/" -o "$TEST_ROOT/biz-home.html"
grep -q "Khush Aamdeed" "$TEST_ROOT/biz-home.html" \
  && { echo "- [x] PASS — front page par blueprint content" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — front page par blueprint content" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }

VERIFY="$("$PHP_BIN" "$SETUP" --path="$WDH_SITES_DIR/biz" --url="http://127.0.0.1:18081" --verify)"
echo "$VERIFY" >> "$REPORT"
echo "$VERIFY" | grep -q '"timezone": "Asia/Karachi"' \
  && { echo "- [x] PASS — timezone Asia/Karachi" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — timezone Asia/Karachi" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
echo "$VERIFY" | grep -q '"language_option": "ur"' \
  && { echo "- [x] PASS — zubaan Urdu (WPLANG=ur)" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — zubaan Urdu (WPLANG=ur)" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
echo "$VERIFY" | grep -q '"permalink": "/%postname%/"' \
  && { echo "- [x] PASS — permalink /%postname%/" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — permalink /%postname%/" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
bash "$REPO/installer-linux/webdevhub.sh" stop biz >> "$REPORT" 2>&1 || true

echo "== Store site ==" | tee -a "$REPORT"
bash "$REPO/installer-linux/install.sh" --site shop --blueprint store --port 18082 >> "$REPORT" 2>&1
check "Store install script exit 0" test -f "$WDH_SITES_DIR/shop/.webdevhub.json"
bash "$REPO/installer-linux/webdevhub.sh" start shop >> "$REPORT" 2>&1
sleep 1
check "Store front page HTTP 200" http_ok "http://127.0.0.1:18082/"
check "Store /shop/ HTTP 200" http_ok "http://127.0.0.1:18082/shop/"
check "Store /cart/ HTTP 200" http_ok "http://127.0.0.1:18082/cart/"
VERIFY2="$("$PHP_BIN" "$SETUP" --path="$WDH_SITES_DIR/shop" --url="http://127.0.0.1:18082" --verify)"
echo "$VERIFY2" >> "$REPORT"
echo "$VERIFY2" | grep -q '"woo_currency": "PKR"' \
  && { echo "- [x] PASS — Woo currency PKR" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — Woo currency PKR" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
echo "$VERIFY2" | grep -Eq '"woo_products": [1-9]' \
  && { echo "- [x] PASS — sample products mojood" | tee -a "$REPORT"; PASS=$((PASS+1)); } \
  || { echo "- [ ] FAIL — sample products mojood" | tee -a "$REPORT"; FAIL=$((FAIL+1)); }
bash "$REPO/installer-linux/webdevhub.sh" stop shop >> "$REPORT" 2>&1 || true

{
  echo ""
  echo "## Natija: PASS=$PASS FAIL=$FAIL"
} >> "$REPORT"
echo ""
echo "SMOKE TEST KHATAM — PASS=$PASS FAIL=$FAIL"
echo "Report: $REPORT"
[ "$FAIL" -eq 0 ]

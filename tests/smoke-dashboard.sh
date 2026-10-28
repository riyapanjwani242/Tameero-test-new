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
export WDH_NO_WIZARD=1 # main flow wizard-free; wizard section 8 mein alag azmaya jata hai
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
# Pehle koi purana dashboard (pid file wala) band karo — warna start
# script "pehle se chal raha hai" keh kar kisi aur sites dir wala
# dashboard rehne deta hai (ek dafa yahi hua tha).
bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
sleep 1
WDH_SITES_DIR="$WDH_SITES_DIR" bash "$REPO/panel/start-dashboard.sh" start >> "$REPORT" 2>&1
CODE="000"
for i in $(seq 1 6); do
  CODE="$(curl -s -o "$TEST_ROOT/dash-home.html" -w '%{http_code}' --max-time 30 -c "$COOKIES" "$DASH/")"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
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
# Pehla WP load (thanda cache, SQLite) kabhi kabhi pehle curl se
# reh jata hai — 200 ka poll karo (product theek hai, test sakht tha).
CODE="000"
for i in $(seq 1 24); do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$SITE/" 2>/dev/null)"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
check "Site start hokar front page HTTP 200" "$CODE" "200"

# 5. One-click admin (magic link)
ADMIN_URL="$(grep -o "href=\"[^\"]*wdh_login=[^\"]*\"" "$TEST_ROOT/dash-list.html" | head -1 | sed 's/href="//;s/"//' | sed 's/&amp;/\&/g')"
if [ -n "$ADMIN_URL" ]; then
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 -c "$TEST_ROOT/wp-cookies.txt" -L "$ADMIN_URL")"
  check "One-click admin: magic link ke baad wp-admin HTTP 200" "$CODE" "200"
else
  bad "One-click admin: magic link dashboard mein nahi mila"
fi

# 5b. v2.1 — naye UI hisse (search, toolbar, photo button)
grep -q 'id="q"' "$TEST_ROOT/dash-list.html" && ok "v2.1: Search box mojood" || bad "v2.1: Search box mojood"
grep -q "Sab Shuru Karein" "$TEST_ROOT/dash-list.html" && ok "v2.1: Sab Start/Stop toolbar mojood" || bad "v2.1: Sab Start/Stop toolbar mojood"
grep -q "Photo Taaza" "$TEST_ROOT/dash-list.html" && ok "v2.1: Photo Taaza button mojood" || bad "v2.1: Photo Taaza button mojood"
grep -q "Aakhri dafa chala" "$TEST_ROOT/dash-list.html" && ok "v2.1: Card par last-run ki line hai" || bad "v2.1: Card par last-run ki line hai"

# 5c. v2.1 — Health check page
CODE="$(curl -s -o "$TEST_ROOT/dash-health.html" -w '%{http_code}' --max-time 30 -b "$COOKIES" "$DASH/?view=health")"
check "v2.1: Health page HTTP 200" "$CODE" "200"
grep -q "PHP version" "$TEST_ROOT/dash-health.html" && ok "v2.1: Health page par PHP maloomat" || bad "v2.1: Health page par PHP maloomat"

# 5d. v2.1 — Size meta (dashboard GET ne sizes worker chalaya tha)
SZ=0
for i in $(seq 1 12); do
  if grep -q '"size_bytes"' "$WDH_SITES_DIR/demobiz/.webdevhub.json" 2>/dev/null; then SZ=1; break; fi
  sleep 5
done
check "v2.1: Site ka size meta mein aa gaya" "$SZ" "1"

# 5e. v2.1 — Thumbnail (start par worker banta hai; is env mein GD
# placeholder, Windows par Edge se asal screenshot)
TH=0
for i in $(seq 1 12); do
  if ls "$WDH_SITES_DIR/demobiz"/.webdevhub-thumb.* >/dev/null 2>&1; then TH=1; break; fi
  sleep 5
done
check "v2.1: Thumbnail file ban gayi" "$TH" "1"
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 -b "$COOKIES" "$DASH/?thumb=demobiz")"
check "v2.1: Thumbnail dashboard se serve hoti hai" "$CODE" "200"

# 5f. v2.1 — Password reset (naya password chale, purana na chale)
curl -s -o /dev/null --max-time 120 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=resetpass" --data-urlencode "site=demobiz" \
  --data-urlencode "new_pass=NewPass!456"
rm -f "$TEST_ROOT/wp-login-new.txt" "$TEST_ROOT/wp-login-old.txt"
curl -s -c "$TEST_ROOT/wp-login-new.txt" --max-time 60 -o /dev/null \
  --data-urlencode "log=admin" --data-urlencode "pwd=NewPass!456" --data-urlencode "wp-submit=Log In" \
  "$SITE/wp-login.php"
grep -q "wordpress_logged_in" "$TEST_ROOT/wp-login-new.txt" && ok "v2.1: Reset ke baad naya password chalta hai" || bad "v2.1: Reset ke baad naya password chalta hai"
curl -s -c "$TEST_ROOT/wp-login-old.txt" --max-time 60 -o /dev/null \
  --data-urlencode "log=admin" --data-urlencode "pwd=DashTest!234" --data-urlencode "wp-submit=Log In" \
  "$SITE/wp-login.php"
grep -q "wordpress_logged_in" "$TEST_ROOT/wp-login-old.txt" && bad "v2.1: Purana password band ho gaya" || ok "v2.1: Purana password band ho gaya"

# 5g. v2.1 — Sab band / sab shuru
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=stop_all"
sleep 2
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$SITE/" 2>/dev/null)"
[ "$CODE" != "200" ] && ok "v2.1: stop_all se site band" || bad "v2.1: stop_all se site band"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=start_all"
UP=0
for i in $(seq 1 24); do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$SITE/" 2>/dev/null)"
  if [ "$CODE" = "200" ]; then UP=1; break; fi
  sleep 5
done
check "v2.1: start_all se site wapas 200" "$UP" "1"

# 6. Export zip
curl -s -o "$TEST_ROOT/demobiz-export.zip" --max-time 120 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=export" --data-urlencode "site=demobiz"
if [ -s "$TEST_ROOT/demobiz-export.zip" ] && python3 -c "import zipfile;zipfile.ZipFile('$TEST_ROOT/demobiz-export.zip')" 2>/dev/null; then
  ok "Export zip download hua aur valid hai"
else
  bad "Export zip download hua aur valid hai"
fi

# 6b. v2.2 Build 2 — Copy engine: backup, clone, template, import, demo
# Backup (manual button ka POST)
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=backup" --data-urlencode "site=demobiz"
BK=0
for i in $(seq 1 36); do
  if ls "$WDH_SITES_DIR/.exports/"backup-demobiz-*.zip >/dev/null 2>&1; then BK=1; break; fi
  sleep 5
done
check "v2.2: Backup zip ban gaya" "$BK" "1"

# Clone — demobiz ki copy, nayi port par, purana password chale
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=clone" --data-urlencode "site=demobiz" \
  --data-urlencode "new_name=democlone"
CL=0
for i in $(seq 1 36); do
  if [ -f "$WDH_SITES_DIR/democlone/.webdevhub.json" ]; then CL=1; break; fi
  sleep 10
done
check "v2.2: Clone ban gaya (.webdevhub.json)" "$CL" "1"
CNAME="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/democlone/.webdevhub.json'))['site'])" 2>/dev/null)"
check "v2.2: Clone ki meta pehchan apni hai (purani site ki nahi)" "$CNAME" "democlone"
CPORT="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/democlone/.webdevhub.json'))['port'])" 2>/dev/null)"
[ "$CPORT" != "$PORT" ] && ok "v2.2: Clone ki port asal site se alag hai" || bad "v2.2: Clone ki port asal site se alag hai"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=start" --data-urlencode "site=democlone"
CODE="000"
for i in $(seq 1 24); do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://127.0.0.1:$CPORT/" 2>/dev/null)"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
check "v2.2: Clone front page HTTP 200 (nayi port par)" "$CODE" "200"
CLONE_HTML="$(curl -s --max-time 30 "http://127.0.0.1:$CPORT/")"
echo "$CLONE_HTML" | grep -q "127.0.0.1:$PORT" && bad "v2.2: Clone ke links nayi URL par hain" || ok "v2.2: Clone ke links nayi URL par hain"
rm -f "$TEST_ROOT/clone-login.txt"
curl -s -c "$TEST_ROOT/clone-login.txt" --max-time 60 -o /dev/null \
  --data-urlencode "log=admin" --data-urlencode "pwd=NewPass!456" --data-urlencode "wp-submit=Log In" \
  "http://127.0.0.1:$CPORT/wp-login.php"
grep -q "wordpress_logged_in" "$TEST_ROOT/clone-login.txt" && ok "v2.2: Clone par asal site ka (reset wala) password chalta hai" || bad "v2.2: Clone par asal site ka (reset wala) password chalta hai"

# Template — demobiz se template, phir us se nayi site
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=totemplate" --data-urlencode "site=demobiz" \
  --data-urlencode "tpl_name=demotpl"
TPL=0
for i in $(seq 1 36); do
  if [ -f "$REPO/templates/demotpl/.webdevhub-template.json" ]; then TPL=1; break; fi
  sleep 10
done
check "v2.2: Template ban gayi" "$TPL" "1"
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=create" --data-urlencode "name=demofromtpl" \
  --data-urlencode "blueprint=tpl:demotpl" --data-urlencode "admin_user=admin" \
  --data-urlencode "admin_email=admin@example.local"
TF=0
for i in $(seq 1 36); do
  if [ -f "$WDH_SITES_DIR/demofromtpl/.webdevhub.json" ]; then TF=1; break; fi
  sleep 10
done
check "v2.2: Template se site ban gayi" "$TF" "1"
TPORT="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/demofromtpl/.webdevhub.json'))['port'])" 2>/dev/null)"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=start" --data-urlencode "site=demofromtpl"
CODE="000"
for i in $(seq 1 24); do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://127.0.0.1:$TPORT/" 2>/dev/null)"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
check "v2.2: Template-site front page HTTP 200" "$CODE" "200"
# Template safai (repo ganda na ho)
rm -rf "$REPO/templates/demotpl" "$REPO/templates/demotpl.job.log"

# Import — step 6 ki export zip ko naye naam se wapas lao.
# Test VM mein kabhi kabhi chand minute ka "write storm" ata hai
# (saari writes EPERM) — job log mein us ke nishan hon to ek dafa
# waqfe ke baad dobara koshish (asal user bhi yahi karega: product
# kharab site activate nahi karta, saaf fail deta hai).
IM=0
for attempt in 1 2; do
  curl -s -o /dev/null --max-time 600 -b "$COOKIES" -X POST "$DASH/" \
    -F "csrf=$CSRF2" -F "action=import" -F "name=demoimport" \
    -F "zipfile=@$TEST_ROOT/demobiz-export.zip;type=application/zip"
  for i in $(seq 1 36); do
    if [ -f "$WDH_SITES_DIR/demoimport/.webdevhub.json" ]; then IM=1; break; fi
    sleep 10
  done
  if [ "$IM" = "1" ]; then break; fi
  if [ "$attempt" = "1" ] && grep -qE "Operation not permitted|extract nahi ho sakin" "$WDH_SITES_DIR/demoimport.job.log" 2>/dev/null; then
    echo "Import write-storm mein phansa — 60s waqfe ke baad dobara koshish..."
    rm -rf "$WDH_SITES_DIR/demoimport" "$WDH_SITES_DIR/.import-demoimport"
    sleep 60
  else
    break
  fi
done
check "v2.2: Export zip import ho gayi" "$IM" "1"
IPORT="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/demoimport/.webdevhub.json'))['port'])" 2>/dev/null)"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=start" --data-urlencode "site=demoimport"
CODE="000"
for i in $(seq 1 24); do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://127.0.0.1:$IPORT/" 2>/dev/null)"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
check "v2.2: Imported site front page HTTP 200" "$CODE" "200"

# Demo content — blank site demo checkbox ke sath (posts to har WP par hote hain)
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=create" --data-urlencode "name=demodemo" \
  --data-urlencode "blueprint=blank" --data-urlencode "admin_user=admin" \
  --data-urlencode "admin_email=admin@example.local" --data-urlencode "admin_pass=DemoTest!234" \
  --data-urlencode "demo_content=1"
DM=0
for i in $(seq 1 36); do
  if [ -f "$WDH_SITES_DIR/demodemo/.webdevhub.json" ]; then DM=1; break; fi
  sleep 10
done
check "v2.2: Demo-content site ban gayi" "$DM" "1"
DCNT="$("$PHP_BIN" -r '$p=new PDO("sqlite:'"$WDH_SITES_DIR"'/demodemo/wp-content/database/.ht.sqlite");echo $p->query("SELECT COUNT(*) FROM wp_posts WHERE post_status='"'"'publish'"'"' AND post_type='"'"'post'"'"' AND post_title LIKE '"'"'%Khush Aamdeed%'"'"'")->fetchColumn();' 2>/dev/null)"
check "v2.2: Urdu demo post database mein hai" "$DCNT" "1"

# 6c. v2.3 Build 3 — blueprints, updates, plugins, db tool, mail catcher
# Plugins view
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=plugins&site=demobiz" -o "$TEST_ROOT/plug.html"
grep -q "Elementor" "$TEST_ROOT/plug.html" && ok "v2.3: Plugins view mein plugins ki list hai" || bad "v2.3: Plugins view mein plugins ki list hai"
# Plugin toggle: code-snippets chalu karo (business mein pehle band hota hai)
curl -s -o /dev/null --max-time 150 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=plugin_toggle" --data-urlencode "site=demobiz" \
  --data-urlencode "plugin=code-snippets/code-snippets.php" --data-urlencode "mode=on"
PACT="$("$PHP_BIN" -r '$p=new PDO("sqlite:'"$WDH_SITES_DIR"'/demobiz/wp-content/database/.ht.sqlite");$v=$p->query("SELECT option_value FROM wp_options WHERE option_name='"'"'active_plugins'"'"'")->fetchColumn();echo strpos($v,"code-snippets/code-snippets.php")!==false?1:0;' 2>/dev/null)"
check "v2.3: Plugin toggle se code-snippets chalu ho gaya" "$PACT" "1"
# DB view + query + edit
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=db&site=demobiz" -o "$TEST_ROOT/dbv.html"
grep -q "wp_options" "$TEST_ROOT/dbv.html" && ok "v2.3: DB view mein tables list hai" || bad "v2.3: DB view mein tables list hai"
curl -s --max-time 30 -b "$COOKIES" -L "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=db_query" --data-urlencode "site=demobiz" \
  --data-urlencode "table=wp_options" --data-urlencode "sql=SELECT option_name FROM wp_options WHERE option_name='blogname'" \
  -o "$TEST_ROOT/dbq.html"
grep -q "Sawal chal gaya" "$TEST_ROOT/dbq.html" && ok "v2.3: DB SELECT sawal chalta hai" || bad "v2.3: DB SELECT sawal chalta hai"
RID="$("$PHP_BIN" -r '$p=new PDO("sqlite:'"$WDH_SITES_DIR"'/demobiz/wp-content/database/.ht.sqlite");echo $p->query("SELECT rowid FROM wp_options WHERE option_name='"'"'users_can_register'"'"'")->fetchColumn();' 2>/dev/null)"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=db_edit" --data-urlencode "site=demobiz" \
  --data-urlencode "table=wp_options" --data-urlencode "col=option_value" --data-urlencode "rowid=$RID" --data-urlencode "value=1"
UCR="$("$PHP_BIN" -r '$p=new PDO("sqlite:'"$WDH_SITES_DIR"'/demobiz/wp-content/database/.ht.sqlite");echo $p->query("SELECT option_value FROM wp_options WHERE option_name='"'"'users_can_register'"'"'")->fetchColumn();' 2>/dev/null)"
check "v2.3: DB edit se value badal gayi" "$UCR" "1"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=db_edit" --data-urlencode "site=demobiz" \
  --data-urlencode "table=wp_options" --data-urlencode "col=option_value" --data-urlencode "rowid=$RID" --data-urlencode "value=0"
# Mail catcher: create ke waqt WP ki welcome mail pakri gayi hogi
MCNT="$(ls "$WDH_SITES_DIR/demobiz/wp-content/webdevhub-mail/"*.json 2>/dev/null | wc -l)"
[ "$MCNT" -ge 1 ] && ok "v2.3: Mail catcher ne mail pakri (welcome waghera)" || bad "v2.3: Mail catcher ne mail pakri (welcome waghera)"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=mail&site=demobiz" -o "$TEST_ROOT/mailv.html"
grep -q "Mail: demobiz" "$TEST_ROOT/mailv.html" && ok "v2.3: Mail view khulta hai" || bad "v2.3: Mail view khulta hai"
# Update khabar: dashboard loads par stale check spawn hota hai — meta poll
UPD=0
for i in $(seq 1 24); do
  if [ -f "$WDH_SITES_DIR/demobiz/.webdevhub.json" ] && grep -q '"upd_at"' "$WDH_SITES_DIR/demobiz/.webdevhub.json"; then UPD=1; break; fi
  sleep 5
done
check "v2.3: Update check meta mein likha gaya" "$UPD" "1"
# Restaurant blueprint (Build 3 ke 5 naye blueprints mein se ek asal test)
curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF2" --data-urlencode "action=create" --data-urlencode "name=demorest" \
  --data-urlencode "blueprint=restaurant" --data-urlencode "admin_user=admin" \
  --data-urlencode "admin_email=admin@example.local" --data-urlencode "admin_pass=DemoTest!234"
RM=0
for i in $(seq 1 36); do
  if [ -f "$WDH_SITES_DIR/demorest/.webdevhub.json" ]; then RM=1; break; fi
  sleep 10
done
check "v2.3: Restaurant blueprint site ban gayi" "$RM" "1"
RMENU="$("$PHP_BIN" -r '$p=new PDO("sqlite:'"$WDH_SITES_DIR"'/demorest/wp-content/database/.ht.sqlite");echo $p->query("SELECT COUNT(*) FROM wp_posts WHERE post_type='"'"'page'"'"' AND post_name='"'"'menu'"'"' AND post_status='"'"'publish'"'"'")->fetchColumn();' 2>/dev/null)"
check "v2.3: Restaurant ka Menu page database mein hai" "$RMENU" "1"

# 6e. v2.4 Build 4 — XAMPP scan, PHP switcher UI, HTTPS, preview (stub), deploy, update check
# Dashboard ko test env ke sath restart karo (preview stub + fake update server ke URL)
mkdir -p "$TEST_ROOT/fakeupd" "$TEST_ROOT/stubbin"
echo "9.9.9" > "$TEST_ROOT/fakeupd/VERSION"
( cd "$TEST_ROOT/fakeupd" && nohup python3 -m http.server 18899 >/dev/null 2>&1 & )
cat > "$TEST_ROOT/stubbin/faketunnel.sh" <<'STUBEOF'
#!/bin/bash
echo "INF Your quick Tunnel has been created" >&2
echo "INF |  https://smoke-preview.trycloudflare.com  |" >&2
sleep 600
STUBEOF
chmod +x "$TEST_ROOT/stubbin/faketunnel.sh"
bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
sleep 1
WDH_SITES_DIR="$WDH_SITES_DIR" WDH_TUNNEL_CMD="$TEST_ROOT/stubbin/faketunnel.sh {URL}" \
  WDH_UPDATE_VERSION_URL="http://127.0.0.1:18899/VERSION" WDH_UPDATE_ZIP_URL="http://127.0.0.1:18899/pkg.zip" \
  bash "$REPO/panel/start-dashboard.sh" start >> "$REPORT" 2>&1
CODE="000"
for i in $(seq 1 6); do
  CODE="$(curl -s -o "$TEST_ROOT/dash-home2.html" -w '%{http_code}' --max-time 30 -b "$COOKIES" -c "$COOKIES" "$DASH/")"
  if [ "$CODE" = "200" ]; then break; fi
  sleep 5
done
check "v2.4: Dashboard restart ke baad HTTP 200" "$CODE" "200"
CSRF3="$(grep -o 'name="csrf" value="[a-f0-9]*"' "$TEST_ROOT/dash-home2.html" | head -1 | sed 's/.*value="//;s/"//')"
[ -n "$CSRF3" ] || CSRF3="$CSRF2"
# Health: XAMPP + PHP versions + WebDevHub version
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=health" -o "$TEST_ROOT/health.html"
grep -q "XAMPP scan" "$TEST_ROOT/health.html" && ok "v2.4: Health page par XAMPP scan hai" || bad "v2.4: Health page par XAMPP scan hai"
grep -q "PHP versions" "$TEST_ROOT/health.html" && ok "v2.4: Health page par PHP versions hain" || bad "v2.4: Health page par PHP versions hain"
grep -q "WebDevHub version" "$TEST_ROOT/health.html" && ok "v2.4: Health page par WebDevHub version hai" || bad "v2.4: Health page par WebDevHub version hai"
# Card buttons
grep -q "Deploy Package" "$TEST_ROOT/dash-home2.html" && ok "v2.4: Card par Deploy Package button hai" || bad "v2.4: Card par Deploy Package button hai"
grep -q "Preview Link" "$TEST_ROOT/dash-home2.html" && ok "v2.4: Card par Preview Link button hai" || bad "v2.4: Card par Preview Link button hai"
grep -q "PHP version" "$TEST_ROOT/dash-home2.html" && ok "v2.4: Card par PHP version section hai" || bad "v2.4: Card par PHP version section hai"
# Update check (fake server — sirf check, apply NAHI: asal repo par apply kabhi smoke mein nahi)
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=update_check"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=health" -o "$TEST_ROOT/health2.html"
grep -q "9.9.9" "$TEST_ROOT/health2.html" && ok "v2.4: Update check ne naya version (9.9.9) pakra" || bad "v2.4: Update check ne naya version (9.9.9) pakra"
FPID="$(ss -tlnp 2>/dev/null | grep 18899 | grep -oP 'pid=\K[0-9]+' | head -1)"; [ -n "$FPID" ] && kill "$FPID" 2>/dev/null
# demobiz chal raha ho (HTTPS/preview ke liye)
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=start" --data-urlencode "site=demobiz"
sleep 3
# HTTPS on
curl -s -o /dev/null --max-time 150 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=https_toggle" --data-urlencode "site=demobiz"
HPORT="$(python3 -c "import json;print(json.load(open('$WDH_SITES_DIR/demobiz/.webdevhub.json')).get('https_port',''))" 2>/dev/null)"
HCODE="000"
if [ -n "$HPORT" ]; then
  for i in $(seq 1 12); do
    HCODE="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 20 "https://127.0.0.1:$HPORT/" 2>/dev/null)"
    if [ "$HCODE" = "200" ]; then break; fi
    sleep 5
  done
fi
check "v2.4: HTTPS par site HTTP 200" "$HCODE" "200"
# CA verify ke sath bhi
CACODE="$(curl -s --cacert "$REPO/panel/.ca/ca.crt" -o /dev/null -w '%{http_code}' --max-time 30 "https://127.0.0.1:$HPORT/" 2>/dev/null)"
check "v2.4: HTTPS CA verify ke sath 200" "$CACODE" "200"
# HTTPS off
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=https_toggle" --data-urlencode "site=demobiz"
# Preview (stub provider)
curl -s -o /dev/null --max-time 240 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=preview_toggle" --data-urlencode "site=demobiz"
grep -q "smoke-preview" "$WDH_SITES_DIR/demobiz/.webdevhub.json" && ok "v2.4: Preview link meta mein aya (stub)" || bad "v2.4: Preview link meta mein aya (stub)"
curl -s -o /dev/null --max-time 90 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=preview_toggle" --data-urlencode "site=demobiz"
grep -q "preview_url" "$WDH_SITES_DIR/demobiz/.webdevhub.json" && bad "v2.4: Preview band par meta saaf" || ok "v2.4: Preview band par meta saaf"
# PHP switcher (sirf jab runtime mein 8.3 ho — CI checkout mein nahi hota)
if [ -x "$REPO/runtime/php-8.3/php" ]; then
  curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
    --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_php" --data-urlencode "site=demobiz" --data-urlencode "php_line=8.3"
  grep -q '"php_line":"8.3"' "$WDH_SITES_DIR/demobiz/.webdevhub.json" && ok "v2.4: PHP switcher ne meta mein 8.3 likha" || bad "v2.4: PHP switcher ne meta mein 8.3 likha"
  curl -s -o /dev/null --max-time 60 -b "$COOKIES" -X POST "$DASH/" \
    --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_php" --data-urlencode "site=demobiz" --data-urlencode "php_line="
  grep -q '"php_line"' "$WDH_SITES_DIR/demobiz/.webdevhub.json" && bad "v2.4: PHP default par wapas (key saaf)" || ok "v2.4: PHP default par wapas (key saaf)"
fi
# Deploy package (aakhir mein — ye site band kar deta hai)
# Is VM ke write-storms deploy ki copy ko bhi saaf nakam kar sakte
# hain (product designed tor par clean-fail karta hai) — is liye
# import step ki tarah ek dafa 60s ruk kar dobara koshish hoti hai.
DEPLOY_OK=0
for attempt in 1 2; do
  curl -s --max-time 900 -b "$COOKIES" -X POST "$DASH/" \
    --data-urlencode "csrf=$CSRF3" --data-urlencode "action=deploy" --data-urlencode "site=demobiz" \
    -o "$TEST_ROOT/deploy.zip"
  if [ -s "$TEST_ROOT/deploy.zip" ] && unzip -l "$TEST_ROOT/deploy.zip" 2>/dev/null | grep -q "database.sql" && unzip -l "$TEST_ROOT/deploy.zip" | grep -q "DEPLOY-CHECKLIST.txt"; then
    DEPLOY_OK=1; break
  fi
  if [ "$attempt" = "1" ]; then echo "Deploy pehli koshish nakam — 60s ruk kar dobara (write-storm retry)" >> "$REPORT"; sleep 60; fi
done
if [ "$DEPLOY_OK" = "1" ]; then
  ok "v2.4: Deploy package bana (database.sql + checklist)"
else
  bad "v2.4: Deploy package bana (database.sql + checklist)"
fi
rm -f "$TEST_ROOT/deploy.zip"

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

echo "" >> "$REPORT"; echo "## 8. v2.5 — Zuban, Lite mode, Wizard" >> "$REPORT"
# 8a. Language toggle (dashboard abhi chal raha hai)
curl -s --max-time 30 -b "$COOKIES" -c "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-lang.html"
CSRF3="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/dash-lang.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_lang" --data-urlencode "lang=en"
curl -s --max-time 30 -b "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-en.html"
grep -q "Create New Project" "$TEST_ROOT/dash-en.html" && ok "v2.5: English mode — dashboard English mein" || bad "v2.5: English mode — dashboard English mein"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=health" -o "$TEST_ROOT/dash-health-en.html"
grep -q "Disk free / total" "$TEST_ROOT/dash-health-en.html" && ok "v2.5: English mode — health page bhi English" || bad "v2.5: English mode — health page bhi English"
CSRF3="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/dash-en.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_lang" --data-urlencode "lang=ur"
curl -s --max-time 30 -b "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-ur.html"
grep -q "Naya Project Banayein" "$TEST_ROOT/dash-ur.html" && ok "v2.5: Urdu wapas aa gayi" || bad "v2.5: Urdu wapas aa gayi"
# 8a2. Lite toggle settings mein save hota hai
CSRF3="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/dash-ur.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_lite" --data-urlencode "lite_on=1"
grep -q '"lite":true' "$REPO/panel/.settings.json" && ok "v2.5: Lite mode ON save hua" || bad "v2.5: Lite mode ON save hua"
curl -s --max-time 30 -b "$COOKIES" "$DASH/" -o "$TEST_ROOT/dash-lite.html"
CSRF3="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/dash-lite.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$CSRF3" --data-urlencode "action=set_lite" --data-urlencode "lite_on="
grep -q '"lite":false' "$REPO/panel/.settings.json" && ok "v2.5: Lite mode OFF bhi ho jata hai" || bad "v2.5: Lite mode OFF bhi ho jata hai"

# 8b. Wizard — bilkul fresh haalat (settings alag, sites khaali, env OFF)
bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
sleep 1
SETTINGS_F="$REPO/panel/.settings.json"
[ -f "$SETTINGS_F" ] && mv "$SETTINGS_F" "$SETTINGS_F.smokebak"
WSITES="$TEST_ROOT/wiz-sites-$STAMP"; mkdir -p "$WSITES"
env -u WDH_NO_WIZARD WDH_SITES_DIR="$WSITES" bash "$REPO/panel/start-dashboard.sh" start >> "$REPORT" 2>&1
sleep 2
WCODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 -c "$COOKIES" "$DASH/")"
check "v2.5 Wizard: pehli dafa dashboard wizard par bhejta hai (302)" "$WCODE" "302"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=wizard" -o "$TEST_ROOT/wiz1.html"
grep -q "Khush Aamdeed" "$TEST_ROOT/wiz1.html" && ok "v2.5 Wizard: step 1 khulta hai" || bad "v2.5 Wizard: step 1 khulta hai"
WCSRF="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/wiz1.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$WCSRF" --data-urlencode "action=wizard_step1" --data-urlencode "lang=en" --data-urlencode "lite_on=1"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=wizard&step=2" -o "$TEST_ROOT/wiz2.html"
grep -q "Create Your First Project" "$TEST_ROOT/wiz2.html" && ok "v2.5 Wizard: step 2 chuni hui zuban (English) mein" || bad "v2.5 Wizard: step 2 chuni hui zuban (English) mein"
grep -q '"lite":true' "$SETTINGS_F" && ok "v2.5 Wizard: lite choice save hui" || bad "v2.5 Wizard: lite choice save hui"
WCSRF="$(grep -o 'name="csrf" value="[^"]*"' "$TEST_ROOT/wiz2.html" | head -1 | sed 's/.*value="//; s/"//')"
curl -s -o /dev/null --max-time 30 -b "$COOKIES" -X POST "$DASH/" \
  --data-urlencode "csrf=$WCSRF" --data-urlencode "action=wizard_create" \
  --data-urlencode "name=wizzsite" --data-urlencode "blueprint=business" --data-urlencode "admin_pass=WizPass!234"
for i in $(seq 1 40); do [ -f "$WSITES/wizzsite/.webdevhub.json" ] && break; sleep 5; done
[ -f "$WSITES/wizzsite/.webdevhub.json" ] && ok "v2.5 Wizard: pehla project asal mein bana" || bad "v2.5 Wizard: pehla project asal mein bana"
grep -q '"wizard_done":true' "$SETTINGS_F" && ok "v2.5 Wizard: wizard_done nishan lag gaya" || bad "v2.5 Wizard: wizard_done nishan lag gaya"
curl -s --max-time 30 -b "$COOKIES" "$DASH/?view=wizard&step=3" -o "$TEST_ROOT/wiz3.html"
grep -q "Congratulations" "$TEST_ROOT/wiz3.html" && ok "v2.5 Wizard: step 3 mubarak wala page" || bad "v2.5 Wizard: step 3 mubarak wala page"
WCODE2="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 -b "$COOKIES" "$DASH/")"
check "v2.5 Wizard: us ke baad dashboard seedha khulta hai (200)" "$WCODE2" "200"
WDH_SITES_DIR="$WSITES" bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
rm -f "$SETTINGS_F"; [ -f "$SETTINGS_F.smokebak" ] && mv "$SETTINGS_F.smokebak" "$SETTINGS_F"
rm -rf "$WSITES"

bash "$REPO/panel/start-dashboard.sh" stop >> "$REPORT" 2>&1 || true
{ echo ""; echo "## Natija: PASS=$PASS FAIL=$FAIL"; } >> "$REPORT"
echo ""; echo "DASHBOARD SMOKE KHATAM — PASS=$PASS FAIL=$FAIL"; echo "Report: $REPORT"
[ "$FAIL" -eq 0 ]

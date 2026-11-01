#!/usr/bin/env bash
# WebDevHub Linux installer — REFERENCE build
# Ek site banata hai: WP core + SQLite + plugins + pre-config + blueprint.
# Usage:
#   install.sh --site mysite [--blueprint business|store|blank] [--port 8080]
# Admin: WDH_ADMIN_USER / WDH_ADMIN_EMAIL env, password ya to WDH_ADMIN_PASS
# env se (sirf CI/tests ke liye) ya terminal par khud type karna hoga.
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
BUNDLE="$REPO/bundle"
SITE="mysite"; BLUEPRINT="business"; PORT="8080"
while [ $# -gt 0 ]; do
  case "$1" in
    --site) SITE="$2"; shift 2;;
    --blueprint) BLUEPRINT="$2"; shift 2;;
    --port) PORT="$2"; shift 2;;
    *) echo "Unknown arg: $1"; exit 1;;
  esac
done

# PHP dhoondo
PHP_BIN="${WDH_PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)";
  elif [ -x "$HOME/workspace/webdevhub-test/php-common/php" ]; then PHP_BIN="$HOME/workspace/webdevhub-test/php-common/php";
  else echo "PHP nahi mila — WDH_PHP env se path do."; exit 1; fi
fi
echo "PHP: $PHP_BIN ($($PHP_BIN -r 'echo PHP_VERSION;'))"

SITES_DIR="${WDH_SITES_DIR:-$REPO/sites}"
SITE_DIR="$SITES_DIR/$SITE"
URL="http://127.0.0.1:$PORT"

if [ -e "$SITE_DIR" ]; then echo "Site pehle se mojood hai: $SITE_DIR"; exit 1; fi

# Bundle tayyar hai? WP zip ka intekhaab (v2.6.3): pehle manifest
# ke pinned version wali exact file; warna sab se unchi version.
# Alphabetical pehli file KABHI nahi (6.8.3 wala bug).
pick_wp_zip() {
  local pin=""
  pin="$(sed -n '/"wordpress"/,/}/p' "$BUNDLE/manifest.json" 2>/dev/null | grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | sed 's/.*"\([^"]*\)"$/\1/')"
  if [ -n "$pin" ] && [ -f "$BUNDLE/wordpress/dist/wordpress-$pin.zip" ]; then
    echo "$BUNDLE/wordpress/dist/wordpress-$pin.zip"; return 0
  fi
  ls "$BUNDLE"/wordpress/dist/wordpress-*.zip 2>/dev/null | sort -V | tail -1
}
WP_ZIP="$(pick_wp_zip || true)"
if [ -z "$WP_ZIP" ]; then
  echo "Bundle pehle fetch kar raha hun..."
  bash "$BUNDLE/fetch-bundle.sh"
  WP_ZIP="$(pick_wp_zip || true)"
fi
if [ -z "$WP_ZIP" ]; then echo "WordPress zip bundle mein nahi mila"; exit 1; fi

mkdir -p "$SITE_DIR"
TMP="$(mktemp -d)"
unzip -q "$WP_ZIP" -d "$TMP"
cp -a "$TMP/wordpress/." "$SITE_DIR/"
rm -rf "$TMP"

# Plugins extract
mkdir -p "$SITE_DIR/wp-content/plugins"
for z in "$BUNDLE"/plugins/dist/*.zip; do
  [ -e "$z" ] || continue
  unzip -q -o "$z" -d "$SITE_DIR/wp-content/plugins/"
done

# SQLite drop-in
SQLITE_DIR="$SITE_DIR/wp-content/plugins/sqlite-database-integration"
if [ -f "$SQLITE_DIR/db.copy" ]; then cp "$SQLITE_DIR/db.copy" "$SITE_DIR/wp-content/db.php"; fi
mkdir -p "$SITE_DIR/wp-content/database"

# Must-use plugin
mkdir -p "$SITE_DIR/wp-content/mu-plugins"
cp "$BUNDLE/mu-plugins/webdevhub-core.php" "$SITE_DIR/wp-content/mu-plugins/"

# Urdu language pack (bundle se — WPLANG=ur ke liye lazmi)
if [ -f "$BUNDLE/languages/ur-core.zip" ]; then
  mkdir -p "$SITE_DIR/wp-content/languages"
  unzip -q -o "$BUNDLE/languages/ur-core.zip" -d "$SITE_DIR/wp-content/languages/"
fi

# wp-config (fresh random salts)
SALTS="$($PHP_BIN -r '$c="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|";foreach(["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT"] as $k){$s="";for($i=0;$i<64;$i++){$s.=$c[random_int(0,strlen($c)-1)];}echo "define( \x27$k\x27, \x27".addslashes($s)."\x27 );\n";}')"
DB_NAME="$(echo "$SITE" | tr -cd 'a-zA-Z0-9_')"
sed -e "s|%%SITE_DIR%%|$SITE_DIR|g" -e "s|%%DB_NAME%%|$DB_NAME|g" \
  "$BUNDLE/config/wp-config-template.php" > "$SITE_DIR/wp-config.php"
python3 - "$SITE_DIR/wp-config.php" "$SALTS" << 'PYEOF'
import sys
path, salts = sys.argv[1], sys.argv[2]
txt = open(path).read().replace("%%SALTS%%", salts)
open(path, "w").write(txt)
PYEOF

# Admin credentials
ADMIN_USER="${WDH_ADMIN_USER:-admin}"
ADMIN_EMAIL="${WDH_ADMIN_EMAIL:-admin@example.local}"
ADMIN_PASS="${WDH_ADMIN_PASS:-}"
if [ -z "$ADMIN_PASS" ]; then
  if [ -t 0 ]; then
    read -r -s -p "Admin password khud set karein: " ADMIN_PASS; echo
  else
    ADMIN_PASS="$($PHP_BIN -r 'echo bin2hex(random_bytes(9));')"
    echo "$ADMIN_PASS" > "$SITE_DIR/.webdevhub-admin.txt"
    chmod 600 "$SITE_DIR/.webdevhub-admin.txt"
    echo "Note: non-interactive mode — test password .webdevhub-admin.txt mein hai (600)."
  fi
fi

HUB_SECRET="$($PHP_BIN -r 'echo bin2hex(random_bytes(24));')"
SETUP="$REPO/installer-linux/setup-site.php"
"$PHP_BIN" "$SETUP" --path="$SITE_DIR" --url="$URL" --install \
  --title="WebDevHub — $SITE" --admin-user="$ADMIN_USER" \
  --admin-email="$ADMIN_EMAIL" --admin-pass="$ADMIN_PASS" \
  --hub-secret="$HUB_SECRET"

# Plugins activate (har plugin alag process mein)
if [ "$BLUEPRINT" != "blank" ]; then
  PLUGINS=$(python3 - "$BUNDLE/blueprints/$BLUEPRINT/blueprint.json" << 'PYEOF'
import json, sys
bp = json.load(open(sys.argv[1]))
print(" ".join(bp.get("required_plugins", [])))
PYEOF
)
  for slug in $PLUGINS; do
    "$PHP_BIN" "$SETUP" --path="$SITE_DIR" --url="$URL" --activate="$slug" || true
  done
  "$PHP_BIN" "$SETUP" --path="$SITE_DIR" --url="$URL" --apply-blueprint --blueprint="$BLUEPRINT"
fi

cat > "$SITE_DIR/.webdevhub.json" << EOF
{"site":"$SITE","blueprint":"$BLUEPRINT","port":$PORT,"url":"$URL","created":"$(date -Iseconds)","secret":"$HUB_SECRET"}
EOF
chmod 600 "$SITE_DIR/.webdevhub.json"

echo ""
echo "Site tayyar hai: $URL"
echo "Admin: $URL/wp-admin/  (user: $ADMIN_USER)"
echo "Start karne ke liye: bash installer-linux/webdevhub.sh start $SITE"

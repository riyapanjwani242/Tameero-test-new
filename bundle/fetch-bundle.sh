#!/usr/bin/env bash
# WebDevHub — bundle fetch script
# Pinned WordPress core + saare free/GPL plugins wordpress.org se lata hai.
# Dobara chalana safe hai: jo file mojood hai wo skip ho jati hai.
set -euo pipefail

BUNDLE_DIR="$(cd "$(dirname "$0")" && pwd)"
WP_DIST="$BUNDLE_DIR/wordpress/dist"
PLUGIN_DIST="$BUNDLE_DIR/plugins/dist"
mkdir -p "$WP_DIST" "$PLUGIN_DIST"

WP_VERSION="$(python3 -c "import json;print(json.load(open('$BUNDLE_DIR/manifest.json'))['wordpress']['version'])")"
if [ ! -f "$WP_DIST/wordpress-$WP_VERSION.zip" ]; then
  echo "WordPress $WP_VERSION download ho raha hai..."
  curl -fL -o "$WP_DIST/wordpress-$WP_VERSION.zip" \
    "https://wordpress.org/wordpress-$WP_VERSION.zip"
fi

# Urdu core language pack — WP translations API ne WP 7.1.4 ke liye ye
# package diya (5.4.25). Bundle mein is liye hai taake WPLANG=ur bina
# runtime download ke save ho jaye (WP ka sanitize sirf installed
# language allow karta hai).
mkdir -p "$BUNDLE_DIR/languages"
if [ ! -f "$BUNDLE_DIR/languages/ur-core.zip" ]; then
  echo "Urdu language pack download ho raha hai..."
  curl -fL -o "$BUNDLE_DIR/languages/ur-core.zip" \
    "https://downloads.wordpress.org/translation/core/5.4.25/ur.zip"
fi

# SQLite integration plugin — version bundle/manifest.json mein PIN
# hai (v2.6.3). 'Latest' wali baghair-version URL istemal nahi hoti:
# har build mein wahi pinned version ana chahiye.
SQLITE_URL="$(python3 -c "import json;print(json.load(open('$BUNDLE_DIR/manifest.json'))['sqlite_plugin']['url'])")"
SQLITE_VER="$(python3 -c "import json;print(json.load(open('$BUNDLE_DIR/manifest.json'))['sqlite_plugin']['version'])")"
if [ ! -f "$PLUGIN_DIST/sqlite-database-integration.zip" ]; then
  echo "SQLite plugin $SQLITE_VER download ho raha hai..."
  curl -fL -o "$PLUGIN_DIST/sqlite-database-integration.zip" "$SQLITE_URL"
fi

python3 - "$BUNDLE_DIR/plugins/manifest.json" "$PLUGIN_DIST" << 'PYEOF'
import json, os, sys, urllib.request
manifest = json.load(open(sys.argv[1]))
dist = sys.argv[2]
for p in manifest["plugins"]:
    url = p.get("url", "")
    if not url.startswith("https://downloads.wordpress.org"):
        continue
    name = os.path.basename(url)
    path = os.path.join(dist, name)
    if os.path.exists(path):
        print("skip (mojood):", name)
        continue
    print("download:", name)
    urllib.request.urlretrieve(url, path)
print("Plugins tayyar hain:", dist)
PYEOF

echo "Bundle fetch mukammal: $WP_DIST , $PLUGIN_DIST"

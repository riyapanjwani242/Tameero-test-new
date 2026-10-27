#!/usr/bin/env bash
# WebDevHub site manager (Linux reference): start | stop | list | url
# Server sirf 127.0.0.1 par bind hota hai — LAN par khula nahi.
# Router (router.php) pretty permalinks ke liye lazmi hai, aur
# PHP_CLI_SERVER_WORKERS se server multi-thread chalta hai.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"
SITES_DIR="${WDH_SITES_DIR:-$REPO/sites}"
ROUTER="$REPO/installer-linux/router.php"

PHP_BIN="${WDH_PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)";
  elif [ -x "$HOME/workspace/webdevhub-test/php-common/php" ]; then PHP_BIN="$HOME/workspace/webdevhub-test/php-common/php"; fi
fi

cmd="${1:-list}"; site="${2:-}"
case "$cmd" in
  start)
    DIR="$SITES_DIR/$site"
    PORT=$(python3 -c "import json;print(json.load(open('$DIR/.webdevhub.json'))['port'])")
    if [ -f "$DIR/.server.pid" ] && kill -0 "$(cat "$DIR/.server.pid")" 2>/dev/null; then
      echo "Pehle se chal raha hai: http://127.0.0.1:$PORT"; exit 0
    fi
    # php seedha background mein — $! hi asal server pid hai (koi subshell nahi)
    PHP_CLI_SERVER_WORKERS=6 nohup "$PHP_BIN" -S "127.0.0.1:$PORT" -t "$DIR" "$ROUTER" \
      > "$DIR/.server.log" 2>&1 &
    echo $! > "$DIR/.server.pid"
    # Warm-up: pehli request slow hoti hai (plugins ki pehli dafa init) —
    # generous timeout ke sath front page prime karo taake baqi istemal tez ho.
    for i in $(seq 1 10); do
      if curl -s -o /dev/null --max-time 60 "http://127.0.0.1:$PORT/"; then break; fi
      sleep 1
    done
    echo "Chal gaya: http://127.0.0.1:$PORT  (admin: /wp-admin/)"
    ;;
  stop)
    DIR="$SITES_DIR/$site"
    if [ -f "$DIR/.server.pid" ]; then
      PID="$(cat "$DIR/.server.pid")"
      kill "$PID" 2>/dev/null || true
      for i in $(seq 1 10); do kill -0 "$PID" 2>/dev/null || break; sleep 0.5; done
      kill -9 "$PID" 2>/dev/null || true
      rm -f "$DIR/.server.pid"
      echo "Band ho gaya: $site"
    fi
    ;;
  list)
    for d in "$SITES_DIR"/*/; do
      [ -f "$d.webdevhub.json" ] || continue
      echo "$(basename "$d"): $(python3 -c "import json;d=json.load(open('$d.webdevhub.json'));print(d['url'], '| blueprint:', d['blueprint'])")"
    done
    ;;
  *) echo "Usage: webdevhub.sh start|stop|list [site]"; exit 1;;
esac

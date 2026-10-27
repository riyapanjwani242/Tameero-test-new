# Dashboard — Product ka DIL

Owner vision (2026-10-27): dashboard hi asal product hai — saare
WordPress projects ek jagah, XAMPP/htdocs ke baghair.
Owner ki pasand: **web dashboard pehle** (desktop app baad mein ho sakta hai).

## Kya hai is folder mein

- `public/index.php` — dashboard UI + tamam actions (create/start/stop/
  delete/export/logs, one-click admin)
- `lib/hub.php` — core logic (sites registry, process management,
  dashboard → installer engine ka bridge)
- `server.php` — PHP built-in server ke liye router
- `start-dashboard.sh` — dashboard chalana/band karna (port 7777,
  env `WDH_DASH_PORT` se badal sakta hai)
- `launch-hub.sh` — silent launcher (Linux): dashboard chalao + browser kholo
- Windows: `installer-windows/launch_hub.vbs` (usi pattern par)

## Chalana

```bash
bash panel/launch-hub.sh        # ya:
bash panel/start-dashboard.sh start
# Browser: http://127.0.0.1:7777
```

Dashboard sirf 127.0.0.1 par bind hota hai. Naya project dashboard ke
form se banta hai — peeche wahi installer engine chalti hai jo smoke
test mein 17/17 pass hai. Admin password form mein user khud type
karta hai; dashboard kahin store/log nahi karta.

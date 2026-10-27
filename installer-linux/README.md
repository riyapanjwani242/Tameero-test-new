# Linux Installer — Reference Build

Linux version pehle banta hai aur yahin test hota hai; Windows packaging
usi shared bundle se banti hai.

```bash
# 1. Bundle lao (WP core + plugins, pinned)
bash ../bundle/fetch-bundle.sh

# 2. Site banao (password terminal par khud type hoga)
bash install.sh --site mysite --blueprint business --port 8080

# 3. Chalao / band karo / list dekho
bash webdevhub.sh start mysite
bash webdevhub.sh list
bash webdevhub.sh stop mysite
```

Env overrides: `WDH_SITES_DIR` (sites kahan banein), `WDH_PHP` (php binary),
`WDH_ADMIN_USER`, `WDH_ADMIN_EMAIL`, `WDH_ADMIN_PASS` (sirf CI/tests ke liye —
asal istemal mein password hamesha terminal prompt se).

Design notes:
- DB: SQLite (sqlite-database-integration) — MySQL server ki zaroorat nahi.
- Server: PHP built-in server, sirf `127.0.0.1` par bind.
- Plugin activation har plugin ke liye alag process mein hoti hai taake
  ek plugin ki kharabi poori install na giraye (setup-site.php dekho).

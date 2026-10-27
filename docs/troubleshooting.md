# Troubleshooting

- **PHP nahi mila (Linux):** PHP 8.2+ chahiye. Ya `WDH_PHP=/path/php` env do.
- **Port band hai / site nahi khul rahi:** doosra port chun kar dobara
  install karo, ya purani site `webdevhub.sh stop <site>` se band karo.
  Site ki log file: `<site>/.server.log`
- **Plugin WARN aya install mein:** ek plugin activate nahi hua — baqi
  setup chalti rehti hai (design hi aisa hai). Kaunsa plugin, install
  output mein `WARN activate` line mein likha hota hai.
- **Windows SmartScreen warning:** v2 abhi unsigned hai (certificate
  pending) — "More info → Run anyway". Tafseel: `docs/code-signing.md`
- **Database error:** SQLite file `<site>/wp-content/database/` mein hoti
  hai. Folder writable hona chahiye. Fresh start chahiye to site folder
  delete karke dobara install karo (pehle backup!).
- **Windows par poora test kab hoga:** GitHub Actions CI har push par
  compile + install smoke karta hai; site-level test release se pehle
  manual hota hai.

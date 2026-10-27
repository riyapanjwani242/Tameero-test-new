# Changelog

## 2.0.0-dev (in progress, 2026-10-27)
- Shared bundle system: EK bundle dono platforms ke liye, pinned versions.
- Plugins pack (sirf free/GPL): Rank Math, Wordfence, UpdraftPlus, Elementor,
  GenerateBlocks, Fluent Forms, FluentSMTP, W3 Total Cache, WooCommerce,
  Migrate Guru, All-in-One WP Migration, ASE, Query Monitor, Code Snippets.
- Urdu pre-config: language `ur`, timezone Asia/Karachi, `/%postname%/` permalinks.
- 2 blueprints: Business Site, Online Store (WooCommerce + PKR).
- Linux installer = reference build + programmatic WP install + smoke test.
- Windows: improved Inno Setup script + GitHub Actions CI (compile + smoke).
- Code-signing tayyari: metadata + signing-ready layout (certificate = owner faisla).
- Documentation set docs/ mein.

## 1.0.0 (dost ki build, gulambarkhan1)
- Inno Setup 6.7 installer "WebDev Hub Studio", stock WordPress + Akismet.
- Koi signature, panel, blueprint ya pre-config nahi thi.

## Smoke test — 2026-10-27 (Linux reference, fresh installs)
- Run 4: **PASS 17 / FAIL 0** — report: tests/reports/smoke-20261027-2311.md
- Fixes jo is run se pehle lage (sab evidence-based):
  - WooCommerce 10.4.0 pin (11.x ko WP 7.0+ chahiye tha; bundle WP 6.8.3 hai)
  - Urdu language pack bundle mein shamil — baghair is ke WP ka
    sanitize_option WPLANG=ur save hi nahi karta (core source se verified)
  - setup-site.php: WP_INSTALLING sirf install/activate modes mein —
    apply-blueprint ko plugins live loaded chahiye (WC_PLUGIN_BASENAME fatal)
  - Server: router.php (pretty permalinks) + PHP_CLI_SERVER_WORKERS +
    warm-up + sahi pid tracking (band karne par server asal mein band hota hai)

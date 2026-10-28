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
- Dashboard (panel/): project cards, create/start/stop/export/delete,
  one-click admin (signed magic link), background create.
- Windows packaging pass (2026-10-28):
  - PHP 8.4.27 (NTS VS17 x64) runtime installer ke andar bundle —
    fetch-runtime.ps1 build waqt download + verify karta hai.
  - VC++ redist (x64) silent auto-install, sirf jab system par na ho.
  - Cross-platform PHP worker (panel/bin/wdh.php): create/start/stop
    ka ek hi code path Linux + Windows par; dashboard Linux par bash
    ki bajaye yehi worker istemal karta hai.
  - launch_hub.vbs v2: chalta hua dashboard dhoondh kar kholta hai,
    warna naye free port (7777-7780) par start karta hai.
  - Naya stop_hub.vbs + Start/Stop desktop shortcuts ka pair.
  - Inno: uninstall ab user ke projects (sites/) delete NAHI karta.
  - Windows CI ab asal install smoke chalata hai (tests/smoke-windows.ps1):
    silent install → dashboard → site create → front page → stop →
    delete → uninstall.
- Bug fix (2026-10-28): dashboard start/stop/delete hang. Asal wajah:
  shell_exec ke capture pipe ke siray lambe-ji site server inherit kar
  leta tha (pipe EOF kabhi nahi aata tha → request latakti thi → usi
  session ki baqi requests session lock par ruk jati thi). Fix: worker
  detached (koi capture pipe nahi), site server proc_open se file
  descriptors par (pipe hai hi nahi), actions se pehle session lock
  release. Regression reports: dashboard-smoke 0452/0503 (11/12, sirf
  delete fail) → fix ke baad dashboard smoke 12/12 + bundle smoke
  17/17 (2026-10-28).

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

## [Unreleased] — Dashboard (product ka dil)

- **Web dashboard** (`panel/`): saare projects ek jagah — card par
  status (Chal raha / Band / Ban raha), site ka address, Open Site,
  one-click WP Admin, Start/Stop, Export (zip), Delete, error log.
- Naya project form: naam, blueprint (Business / Online Store), admin
  user/email/password — background mein banta hai, dashboard free
  rehta hai.
- **One-click admin login**: har site ka hub-secret; dashboard se
  "Admin Kholain" = 120-second signed link se seedha wp-admin, dobara
  password nahi.
- Launchers: `launch_hub.vbs` (Windows, khamosh) + `launch-hub.sh` /
  desktop shortcut (Linux); Inno script install ke baad dashboard
  khud kholta hai.
- Export ki marammat (smoke mein 3 dafa pakra gaya): export ab system
  `zip` CLI se banta hai (ek site ~24k files / 400MB+ hoti hai,
  ZipArchive close() us par fail ho jata tha) aur zip sites dir ke
  sath disk par banta hai, /tmp mein nahi. ZipArchive fallback rakha
  hai jahan CLI na ho (Windows — note darj hai).
- Tests: `tests/smoke-dashboard.sh` **12/12 PASS** (create →
  start → front page → one-click admin → export → delete) aur purana
  bundle smoke dobara **17/17 PASS**.
- `docs/class-notes-alignment.md`: class ke universal installer ke
  6 fixes ke muqable hamari haalat (2 item Windows packaging pass ke
  hain: stop-shortcut pair, port auto-detect).

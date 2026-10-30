# TAMEERO — v2.6

**Your Website. One Click. Done.**

One-click local WordPress development environment.
(Pehle "WebDev Hub Studio" — v2.6.0 se naya naam TAMEERO hai.)
Joint project: **Sahil (Abdul Salam)** + **gulambarkhan1**.

v2 ka maqsad: v1 ka khaali stock WordPress tajurba khatam karke ek tayyar,
Urdu pre-configured, blueprint-based local environment dena — Windows aur
Linux dono par, lekin **EK hi shared bundle** se.

## Structure

```
bundle/              Shared WordPress package (dono platforms ke liye EK)
  manifest.json      Bundle ki versions (WP, PHP, plugins)
  plugins/           Plugins manifest + fetch kiye gaye plugin zips (dist/, git-ignored)
  config/            wp-config template + default settings (Urdu, Asia/Karachi)
  mu-plugins/        TAMEERO must-use plugin (pehle run par settings apply)
  blueprints/        business/ + store/ — ek click tayyar sites
  fetch-bundle.sh    Pinned WP core + saare free/GPL plugins wordpress.org se lata hai
installer-linux/     Linux installer — REFERENCE build (pehle yahan banta + test hota hai)
installer-windows/   Inno Setup script (Windows packaging, GitHub Actions CI par build/test)
panel/               Control panel — baad ka phase (abhi khaali rakha hai)
docs/                Saari documentation
.github/workflows/   Linux smoke test + Windows Inno build/test CI
```

## Quick start (Linux, reference)

```bash
bash bundle/fetch-bundle.sh
bash installer-linux/install.sh --site mysite --blueprint business
bash installer-linux/webdevhub.sh start mysite
```

## Usool (owner ke qayem karda)

- Testing lazmi: fresh-install smoke test pass hue baghair koi version ready nahi.
- Sirf free/GPL plugins bundle hote hain. Premium paid files kabhi nahi.
- Admin password kabhi default nahi — user pehle run par khud banata hai.
- Local server sirf 127.0.0.1 par bind hota hai.
- Windows build/test GitHub Actions ke Windows runners par hoti hai.

Version: dekho `VERSION` file. Changelog: `CHANGELOG.md`.

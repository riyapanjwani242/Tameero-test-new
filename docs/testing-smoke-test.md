# Testing — Smoke Test Protocol

Usool: **test report ke baghair koi version ready nahi.**

```bash
bash tests/smoke-test.sh
```

Ye script:
1. Bundle fetch verify karta hai
2. Fresh **business** site banata hai (port 18081) — front page, blueprint
   pages (about/services/contact), login page — sab HTTP 200 check
3. WP state verify: timezone Asia/Karachi, permalink `/%postname%/`
4. Fresh **store** site banata hai (port 18082) — shop/cart HTTP 200,
   Woo currency PKR, sample products mojood
5. Report `tests/reports/smoke-<date>.md` mein likhta hai (PASS/FAIL count)

Windows: GitHub Actions `windows-build.yml` Inno compile + silent-install
smoke karta hai. Jo cheez wahan test na ho sake (masalan poori site
chalana) wo release se pehle manual nazar mein ati hai — aur report mein
saaf "unverified" likha jata hai, "complete" kabhi nahi.

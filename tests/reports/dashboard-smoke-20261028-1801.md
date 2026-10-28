# WebDevHub Dashboard Smoke Test — 20261028-1801

Dashboard tayyar hai: http://127.0.0.1:17777
- [x] PASS — Dashboard page HTTP 200
- [x] PASS — Dashboard par WebDev Hub title
- [x] PASS — Create form mojood
- [x] PASS — CSRF token mila
- [x] PASS — Create request bhej di
- [x] PASS — Project dashboard se ban gaya (.webdevhub.json)
- [x] PASS — Project dashboard list mein hai
- [x] PASS — Site start hokar front page HTTP 200
- [x] PASS — One-click admin: magic link ke baad wp-admin HTTP 200
- [x] PASS — v2.1: Search box mojood
- [x] PASS — v2.1: Sab Start/Stop toolbar mojood
- [x] PASS — v2.1: Photo Taaza button mojood
- [x] PASS — v2.1: Card par last-run ki line hai
- [x] PASS — v2.1: Health page HTTP 200
- [x] PASS — v2.1: Health page par PHP maloomat
- [x] PASS — v2.1: Site ka size meta mein aa gaya
- [x] PASS — v2.1: Thumbnail file ban gayi
- [x] PASS — v2.1: Thumbnail dashboard se serve hoti hai
- [x] PASS — v2.1: Reset ke baad naya password chalta hai
- [x] PASS — v2.1: Purana password band ho gaya
- [x] PASS — v2.1: stop_all se site band
- [x] PASS — v2.1: start_all se site wapas 200
- [x] PASS — Export zip download hua aur valid hai
- [x] PASS — v2.2: Backup zip ban gaya
- [x] PASS — v2.2: Clone ban gaya (.webdevhub.json)
- [x] PASS — v2.2: Clone ki meta pehchan apni hai (purani site ki nahi)
- [x] PASS — v2.2: Clone ki port asal site se alag hai
- [x] PASS — v2.2: Clone front page HTTP 200 (nayi port par)
- [x] PASS — v2.2: Clone ke links nayi URL par hain
- [x] PASS — v2.2: Clone par asal site ka (reset wala) password chalta hai
- [x] PASS — v2.2: Template ban gayi
- [x] PASS — v2.2: Template se site ban gayi
- [x] PASS — v2.2: Template-site front page HTTP 200
- [x] PASS — v2.2: Export zip import ho gayi
- [x] PASS — v2.2: Imported site front page HTTP 200
- [x] PASS — v2.2: Demo-content site ban gayi
- [x] PASS — v2.2: Urdu demo post database mein hai
- [x] PASS — v2.3: Plugins view mein plugins ki list hai
- [x] PASS — v2.3: Plugin toggle se code-snippets chalu ho gaya
- [x] PASS — v2.3: DB view mein tables list hai
- [x] PASS — v2.3: DB SELECT sawal chalta hai
- [x] PASS — v2.3: DB edit se value badal gayi
- [x] PASS — v2.3: Mail catcher ne mail pakri (welcome waghera)
- [x] PASS — v2.3: Mail view khulta hai
- [x] PASS — v2.3: Update check meta mein likha gaya
- [x] PASS — v2.3: Restaurant blueprint site ban gayi
- [x] PASS — v2.3: Restaurant ka Menu page database mein hai
Dashboard band ho gaya.
Dashboard tayyar hai: http://127.0.0.1:17777
- [x] PASS — v2.4: Dashboard restart ke baad HTTP 200
- [x] PASS — v2.4: Health page par XAMPP scan hai
- [x] PASS — v2.4: Health page par PHP versions hain
- [x] PASS — v2.4: Health page par WebDevHub version hai
- [x] PASS — v2.4: Card par Deploy Package button hai
- [x] PASS — v2.4: Card par Preview Link button hai
- [x] PASS — v2.4: Card par PHP version section hai
- [x] PASS — v2.4: Update check ne naya version (9.9.9) pakra
- [x] PASS — v2.4: HTTPS par site HTTP 200
- [x] PASS — v2.4: HTTPS CA verify ke sath 200
- [x] PASS — v2.4: Preview link meta mein aya (stub)
- [x] PASS — v2.4: Preview band par meta saaf
- [x] PASS — v2.4: PHP switcher ne meta mein 8.3 likha
- [x] PASS — v2.4: PHP default par wapas (key saaf)
Deploy pehli koshish nakam — 60s ruk kar dobara (write-storm retry)
- [ ] FAIL — v2.4: Deploy package bana (database.sql + checklist)
- [x] PASS — Delete: project folder gaya
- [x] PASS — Delete ke baad list khaali

## 8. v2.5 — Zuban, Lite mode, Wizard
- [x] PASS — v2.5: English mode — dashboard English mein
- [x] PASS — v2.5: English mode — health page bhi English
- [x] PASS — v2.5: Urdu wapas aa gayi
- [x] PASS — v2.5: Lite mode ON save hua
- [x] PASS — v2.5: Lite mode OFF bhi ho jata hai
Dashboard band ho gaya.
Dashboard tayyar hai: http://127.0.0.1:17777
- [x] PASS — v2.5 Wizard: pehli dafa dashboard wizard par bhejta hai (302)
- [x] PASS — v2.5 Wizard: step 1 khulta hai
- [x] PASS — v2.5 Wizard: step 2 chuni hui zuban (English) mein
- [x] PASS — v2.5 Wizard: lite choice save hui
- [x] PASS — v2.5 Wizard: pehla project asal mein bana
- [x] PASS — v2.5 Wizard: wizard_done nishan lag gaya
- [x] PASS — v2.5 Wizard: step 3 mubarak wala page
- [x] PASS — v2.5 Wizard: us ke baad dashboard seedha khulta hai (200)
Dashboard band ho gaya.

## Natija: PASS=76 FAIL=1

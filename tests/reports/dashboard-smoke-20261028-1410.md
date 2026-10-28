# WebDevHub Dashboard Smoke Test — 20261028-1410

Dashboard band ho gaya.
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
- [ ] FAIL — v2.2: Demo-content site ban gayi (mila: 0, chahiye tha: 1)
- [ ] FAIL — v2.2: Urdu demo post database mein hai (mila: 
Fatal error: Uncaught PDOException: SQLSTATE[HY000] [14] unable to open database file in Command line code:1
Stack trace:
#0 Command line code(1): PDO->__construct('sqlite:/home/ha...')
#1 {main}
  thrown in Command line code on line 1, chahiye tha: 1)
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
- [x] PASS — Delete: project folder gaya
- [x] PASS — Delete ke baad list khaali
Dashboard band ho gaya.

## Natija: PASS=47 FAIL=2

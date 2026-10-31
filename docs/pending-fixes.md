# Pending Fixes — agli version ke liye

Chhoti verified fixes jo alag build ke laiq nahi; agli version mein
baqi kaam ke sath jayengi. Har item ke sath saboot/makaan darj hai.

## 1. ✅ BAND (v2.6.2, 2026-10-31) — Dashboard sub-views ke H1 mein purana naam "WebDev Hub"
- **Saboot:** Owner ke PC ka screenshot — Plugins page ki heading
  "WebDev Hub — Plugins" (baqi branding TAMEERO hai).
- **Asal daira (code check 2026-10-31):** sirf Plugins nahi — chaar
  sub-view H1 reh gaye thay, `panel/public/index.php`:
  - line 541: Plugins view H1
  - line 633: Database view H1
  - line 740: Mail view H1
  - line 829: Wizard view H1
- **Wajah:** Rename (v2.6.0) mein saare `<title>` aur main/health H1
  badle thay; ye chaar view-H1 usi pattern (`WebDev <span>Hub</span>`)
  par thay lekin sweep se reh gaye. Live verification mein sirf
  home + health dekha tha, sub-views nahi — agli dafa rename jaisi
  tabdeeli par har view ka render check lazmi.
- **Fix:** chaaron H1 → "TAMEERO — …" (wizard wala sirf "TAMEERO").

## 2. Deploy: fix-url Wordfence ki BLOB column par crash (2026-10-30)
- **Saboot:** Standalone reproduce — `installer-linux/fix-url.php:123`
  PDOException: "cannot store TEXT value in BLOB column
  wp_wfconfig.val". Dashboard smoke ka deploy check isi par FAIL
  hota hai (pehle flake samjha gaya tha; asal mein deterministic
  hai jab site par Wordfence active + data ho).
- **Daira:** v2.5.1 shipped code mein bhi mojood hai; site deploy
  package Wordfence-active sites par nahi banta.
- **Fix ki simt (agli version mein diagnose ke sath):** fix-url ke
  replace loop mein BLOB/binary columns ko skip ya alag handle
  karna (wp_wfconfig jaisi tables URL nahi rakhtin).

## 3. ✅ BAND (v2.6.2, 2026-10-31) — Site creation "Creating..." par hamesha ke liye atak jana
- **Saboot:** Owner ke PC par (v2.6.1) nayi Store site ki creation
  30+ minute "Creating..." par atki rahi; Task Manager: php
  processes 0% CPU / 0 disk (hang hai, slow nahi). Dashboard zinda,
  doosri site Running.
- **Code se sabit kamzoriyan:**
  1. `wdh_run_setup()` / `wdh_run_tool()` (panel/bin/wdh.php)
     bachon ko `exec()` output-capture se chalate hain — pipe ke
     EOF ka intezar; koi grandchild process pipe pakre rakhe to
     parent hamesha ke liye ruk jata hai (0% CPU/disk wali halat).
     Yehi bug class `start` flow mein pehle pakri gayi thi, wahan
     file-redirect se fix hui — create path par ab bhi baqi hai.
  2. "Creating" state sirf files se banta hai (folder hai + meta
     `.webdevhub.json` nahi + `.job.log` hai) — koi staleness
     check (mtime/PID) aur koi startup reconciliation NAHI hai.
     Worker mar bhi jaye to card hamesha "Creating" rahega,
     restart se bhi nahi jata.
  3. Creating card par koi button (Delete/Cancel) hi nahi —
     owner ke liye dashboard ke andar recovery ka rasta hi nahi.
- **Fix (agli version mein LAZMI):**
  1. Har setup/tool step par timeout (masalan 120s) — timeout par
     step fail, create error state mein jaye.
  2. `exec()` capture ki jagah child output seedha file mein
     (start wale fix ki tarz) — pipe-wait hang mumkin hi na ho.
  3. Creating-state ki reconciliation: job log purana + worker
     dead ho to card "Failed" state mein aye, Delete button ke sath.
  4. Creating card par Cancel/Delete button har halat mein.
- **Manual recovery (tab tak):** dashboard band karke
  `sites/<site>` folder + `sites/<site>.job.log` dono delete —
  card ghayab, site dobara ban sakti hai.
- **Creation Visibility (owner faisla 2026-10-31 — isi fix package
  ka hissa):**
  1. **Live Progress card:** creating card sirf "Creating..." na
     kahe — job log se step-by-step haalat dikhaye (masalan
     "Step 4/8 — Plugins activate ho rahe hain"), progress bar ke
     sath, mukammal steps par ✓. Stuck step screen par saaf nazar
     aye. (Is ke liye worker har step ke SHURU mein bhi log mein
     step marker likhe ga — abhi output step khatam hone ke baad
     ati hai.)
  2. **Live Preview:** creation ke doran site ka thumbnail/
     screenshot har step ke baad khud refresh ho — site banti hui
     nazar aye. Dashboard ki maujooda photo machinery (thumbnail
     system) par hi bane ga, nayi machinery nahi.

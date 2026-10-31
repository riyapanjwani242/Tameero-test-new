# Proposal — Creation-Hang Fix Package (agli version ka core)

**Status:** PROPOSAL — owner ki approval ke baghair build nahi hoga.
**Date:** 2026-10-31 · **Mutaliq:** pending-fixes.md item #3

## 1. Sabit-shuda root cause (code se, 2026-10-31)

Owner ke PC par nayi Store site ki creation 30+ minute "Creating..."
par atki rahi (0% CPU/disk; restart se bhi card saaf nahi hua).
Code ki tehqeeq se teen sabit kamzoriyan:

1. **Pipe-wait hang:** creation ke saare steps (`--install`,
   `--activate` ×N, `--apply-blueprint`, `demo-content`)
   `panel/bin/wdh.php` ke `wdh_run_setup()` / `wdh_run_tool()` se
   chalte hain, jo PHP `exec()` ke **output-capture** par hain —
   parent child ki pipe ke EOF ka intezar karta hai; koi grandchild
   process pipe pakre rakhe to parent hamesha ke liye ruk jata hai
   (0% CPU/disk). Yehi bug class `start` flow mein pehle pakri gayi
   thi aur wahan **file-redirect** se fix hui — create path par wo
   fix kabhi nahi laga.
2. **State sirf files se banti hai, koi reconciliation nahi:**
   "Creating" card tab banta hai jab site folder ho + `.webdevhub.json`
   meta na ho + `.job.log` ho (`hub_sites()`) — koi umar/liveness
   check nahi. Worker mar jaye ya phans jaye, card hamesha ke liye
   "Creating" rehta hai, restart be-kaar.
3. **Recovery ka rasta hi nahi:** creating card par koi button
   (Delete/Cancel/Log) mojood hi nahi.

Worker browser request se NAHI chalta (detached `.cmd`/`wscript`
process) — tab band/navigate karna wajah nahi ho sakta, ye bhi code
se sabit hai.

## 2. Fix package — chaar hisse

### (a) Root fix: file-redirect + step timeout (wdh.php)
- `wdh_run_setup()` / `wdh_run_tool()` ka `exec()` capture khatam:
  child ki output seedha ek step file mein redirect hogi (spawn ke
  waqt hi, `hub_spawn_worker` wale sabit tareeqe se) — pipe EOF ka
  intezar hi nahi hoga, hang ka rasta band.
- Parent step file ko poll karke output job log mein live likhta
  rahe ga (diagnosis ki salahiyat wohi rahe gi jo aaj hai).
- **Per-step timeout: 180 second.** Timeout par child tree kill,
  step FAILED, create saaf error ke sath rukay ga. Poori creation
  par ek **15-minute watchdog** backstop ke tor par.
- Worker har step ke **SHURU** par job log mein step marker likhe
  ga (`STEP k/8 — <naam>`), khatam par `OK` — taake log se hamesha
  pata ho kaun sa step chal raha tha (aaj output step ke baad ati
  hai, atka step khamosh rehta hai).

### (b) ERROR state (hub.php + dashboard)
- Worker kisi bhi create failure par (fail, timeout, exception)
  site folder mein ek **failure marker** likhe ga: fail wala step
  + wajah + waqt.
- `hub_sites()` ki reconciliation: meta-less site agar (i) failure
  marker rakhti hai, ya (ii) us ka job log **30 minute se purana**
  hai (power-cut jaisi surat jahan marker likhne wala hi mar gaya
  ho) → state **"Failed"** hogi, "Creating" nahi.
- Failed card par: rukne wala step, error ki aakhri lines, aur
  neeche wala recovery button set.

### (c) Dashboard-se-hi recovery
- Creating AUR Failed dono cards par buttons: **Delete** (aadhi
  site + job log saaf — meta-less sites par delete path verify/
  extend hoga, port/process ki koi baqiyat na rahe) aur **Log**
  (job log ki aakhri lines dashboard par hi nazar ayen — owner ko
  file dhoondhni na pare).
- Is se wo surat khatam: recovery ke liye Explorer mein ja kar
  folder haath se delete karna.

### (d) Visibility (owner ke manzoor items — isi package mein)
- **Live Progress card:** creating card job log ke STEP markers
  se haalat dikhaye ga — "Step 4/8 — Plugins activate ho rahe
  hain", progress bar, mukammal steps par ✓. Maujooda 6-second
  auto-refresh wohi rahe ga.
- **Live Preview:** WP install mukammal hone ke baad wale steps
  par, maujooda thumbnail machinery se site ki photo refresh
  hogi — site banti hui nazar ayegi. (Imaandarana hadd: photo
  tabhi mumkin hai jab site serve ho sakay; us se pehle ke steps
  par progress bar hi preview hai — card waisa hi dikhaye ga.)

## 3. Is package mein NAHI hai
- MCP ka koi kaam (alag phase, alag faisla).
- Deploy/fix-url Wordfence bug (pending #2 — alag item; owner
  chahe to scope mein jor sakta hai).
- Dukaan/theme ka koi kaam.

## 4. Self-testing — pehle IS machine par (owner ki standing hidayat)

**Standing rule (owner, 2026-10-31):** "Sara kaam karke apne pas
run karke, test karke, check karke do." Har fix pehle isi Linux
machine par run + test + verify hoga, saboot ke sath. **Untested
kaam owner tak nahi jayega.** Owner ke PC par sirf Windows-specific
aakhri tasdeeq rehti hai (installer, SmartScreen, Windows process
bartao — jo Linux par ho hi nahi sakti; Windows CI smoke us ka
hissa hai).

### 4.1 Aam creation flow — yahan, throwaway sites par
- Throwaway **Store** sites (demo ON aur OFF) + Blog site yahan
  bana kar poora creation flow end-to-end: saare steps, meta,
  Woo active, pages/products, coming-soon OFF — wahi checks jo
  smoke suites karte hain, haath se bhi verify.
- Clone + import ke raste bhi dobara (ye bhi unhi run helpers se
  guzarte hain).
- Purane suites: smoke-test + smoke-dashboard saare green.

### 4.2 Hang-simulation test (alag, LAZMI — is fix ki jaan)
Maqsad: sabit karna ke owner ko 30-minute wala tajurba dobara
nahi hoga.
- Ek step ko **jaan boojh kar** roka jayega (test-only stall:
  step child ek aisi haalat mein jata hai jo kabhi khatam nahi
  hoti — pipe-wait wali asal surat ki naqal).
- **Matuqa (expected) tartib, ghari ke sath:** step timeout
  (180s; test mein chhota karke) → child tree kill → job log mein
  FAILED line + failure marker → dashboard card **Failed** state
  mein (step ka naam + wajah ke sath) → card ke **Delete** button
  se aadhi site dashboard se hi saaf → **usi naam se dobara
  create kamyab**.
- In mein se ek bhi hissa fail ho to fix nakam — release nahi
  hogi.

### 4.3 Kill simulation (power-cut ki naqal)
- Creation ke darmiyan worker process ko zabardasti mara jayega
  (koi marker likhne ka mauqa nahi) — staleness rule (test mein
  waqt chhota karke) card ko Failed mein badle, aur Delete se
  recovery ho.

### 4.4 Saboot jo report hoga
- Test ginati (PASS/FAIL har suite ki), hang/kill simulations ke
  logs ke ahem hisse, aur dashboard ke **screenshots**: creating
  card (progress ke sath), Failed card, Delete ke baad saaf
  dashboard — fresh browser profile se (standing verification
  discipline).

### 4.5 Owner ke PC par sirf ye jayega
- Installer in-place upgrade + SmartScreen ka bartao + Windows
  par process tree ka asal bartao (timeout/kill Windows par bhi
  waisa hi chale — CI smoke isay pehle pakre ga).
- Aur aakhir mein wohi adhoora born-complete trial: nayi Store
  site, Live Progress ke sath.

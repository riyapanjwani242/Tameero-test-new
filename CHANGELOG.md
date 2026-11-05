# Changelog

## 2.8.11 (Curl client probe - run #33 ke NO-NEW-TMP mein client vs server ka farq, 2026-11-05)

- **Run #33 ka verdict: NO-NEW-TMP - body server tmp write tak nahi pahunchi.** Watcher ne 900s mein koi tmp file nahi dekhi (181 NEW lines sirf apni file ke - FullName self-exclusion fail hua tha, cosmetic bug). Defender RTP band tha, is liye scan theory kamzor hai. Bacha hua sawal: IWR streaming stall ya server read-path?
- **Sirf smoke changes, product code untouched.** (1) 6m big POST: IWR timeout par turant throw nahi - curl.exe probe sequential chalega (replacement nahi; IWR gate ki asal test hai). Wahi URL/fields/XHR header/900s timeout, sirf client badla hai: Expect band (wire behavior IWR jaisi), session cookie $sess se PHPSESSID (na mile to warning + baghair cookie - fast csrf failure timeout se alag nazar aayega), body temp file mein + exit code se faisla (0 = pass, 28 = timeout, aur = probe setup). Curl na mile to probe skip + purana throw (probe kabhi gate ko nahi torega). Curl pass ho to import aage waisa hi chalega (poll meta, start, baqi checks). (2) Watcher fix: apni files ko leaf NAME se exclude (TAMEERO-* - FullName path-form par fail hua tha, naam ka muqabla path ki shakal se azaad hai; is se curl body/err files bhi curve se bahar rahengi) + $base pehli sight par update (NEW sirf aik dafa, phir GROW - run #33 wali NEW spam khatam). (3) Tiny probe IWR hi rahega; 900s timeout, flags, ports, Defender read-only reads unchanged. Curl ka .Source use hota hai (PowerShell ka curl alias Invoke-WebRequest hai - us se bachne ke liye).
- **Caveat (shart ke tor par):** curl-pass par gate "GREEN WITH FINDING" hoga - report mein IWR-client failure aur browser-path caveat (field mein 451MB browser import bhi atka tha) numaya hoga; ship par owner ki aagahi lazmi hai. Curl-green ka matlab server+handler+worker verified hai, browser-upload path nahi.
- **Decision tree (agle gate ke liye):** IWR pass = curl kabhi nahi chalega, gate normal; IWR timeout + curl pass + tmp growth = CLIENT-SIDE verdict (IWR streaming stall), GREEN WITH FINDING; IWR timeout + curl timeout + phir bhi no-tmp = SERVER-SIDE verdict (koi client 420MB nahi pahuncha saka - agli tehqeeq php -S read-path, owner faisla); IWR timeout + curl fast-fail = probe-setup masla (product verdict nahi). Launch, handler, move/spawn - sab unchanged.

## 2.8.10 (Upload watcher - run #32 ke H2 stall mein susti vs stall ka farq, 2026-11-05)

- **Run #32 ka verdict: H2 stall, lekin susti vs stall ka farq baqi tha.** Probe PASS, big POST 900s timeout, 420MB size, ciimport ZERO. Timeout dump mein tmp file nahi mili thi - lekin us ki wajah ab saaf hai: PHP ki tmp file ka naam `/tmp/php<random>` hota hai (koi `.tmp` extension nahi), is liye v2.8.9 ka `php*.tmp` pattern kabhi match hi nahi kar sakta tha (Linux tajurbe se sabit: tmp=/tmp/php9duv6v1h1m6heHLabDc). Pattern ki ghalti thi, saboot ki kami nahi.
- **Sirf smoke changes, product code untouched.** (1) Tmp-watcher (Start-Job) big POST se foran pehle: TEMP ka baseline snapshot, har 5s poll, nayi/badli files timestamp ke sath `$env:TEMP\TAMEERO-upload-watch.log` mein. Growth = body aa rahi hai (susti); freeze = filter stall ki nishani; kuch nahi = bytes tmp write tak nahi pahunche. Watcher kabhi gate ko nahi torega: Start-Job fail ho to POST baghair watcher chalega. POST ke natije ke baad job roko + watch log job output mein print karo; finally mein defensive cleanup. (2) Watch-log collection: finally copies (TEMP + workspace) + inventory + workflow diagnostics (non-dot naam upload-watch.log). (3) Timeout-catch dump mein Defender read-only reads (Get-MpComputerStatus / Get-MpPreference, SilentlyContinue) - koi Set-* cmdlet, koi exclusion, koi disable, kisi machine par koi setting change nahi. Purana `php*.tmp` pattern khatam. Watch log TEMP mein rehta hai, is liye .iss mein entry nahi (UninstallDelete sirf panel files ke liye hai).
- **Decision tree (agle gate ke liye):** steady growth + POST pass = susti confirm, gate green; growth phir minutes tak freeze = filter stall signature (Defender reads + owner faisla: exclusion experiment, controlled machine par); koi nayi tmp kabhi nahi = client/server transfer stall (agli version: curl.exe probe variant, owner-approved); growth + 900s timeout = 900s se zyada susti (owner faisla: timeout vs chunked upload); probe fail = endpoint-level branch. Launch, handler, move/spawn, flags, probe, 900s timeout - sab unchanged.

## 2.8.9 (Body-phase probe - run #31 ne hang ko handler se pehle pin kiya, 2026-11-05)

- **Run #31 ka verdict: hang handler se pehle hai.** import-trace mein IMPORT-* markers ZERO (handler kabhi chala hi nahi), companion 16 accepted / 15 closed (ghair-band sirf import POST, 18:53:12 Accepted), section 3 PASS. Matlab rukawat TCP accept aur handler entry ke darmiyan hai - request-body transfer/parsing phase. Linux tajurba: 300MB multipart loopback par ~3s mein, script ENTRY sirf body mukammal hone ke baad - to CI ka 300s stall PHP ki fitrat nahi, environmental hai. Limits-exceeded bhi kharij (wo timeout nahi, response deta).
- **Sirf smoke changes, product code untouched.** (1) 6f mein export zip ka size job log mein (CI-EXPORT-SIZE). (2) 6m mein tiny-import probe big POST se pehle (chand KB ki zip, name ciprobe, TimeoutSec 60, redirect JSON ki umeed) - probe pass + bari POST hang = size/body phase; probe bhi hang = endpoint/connection level. Tiny zip ka worker fail be-zarar hai (ciimport ko haath nahi lagta). (3) Big POST timeout 300 se 900s (test-only) - field case mein 451MB import 15+ min leti thi, H1 (susti) ko poora hone ka mauqa. (4) Timeout par evidence dump (companion log tail + TEMP php tmp listing + export size) phir rethrow - failure mode wahi, saboot ke sath.
- **Decision tree (agle gate ke liye):** probe hang = endpoint level; probe pass + big POST 900s mein pass = H1 slowness confirm, gate green; probe pass + big POST 900s par bhi timeout + size bara = H2 stall (agli version: parallel tmp-watcher + Defender/filter tehqeeq); probe pass + big POST jaldi pass = #30/#31 transient the; size chhota + phir bhi timeout = size theory khatam, foran H2 tehqeeq. Reviewers ki spawn recipes conditional hi hain (sirf tab jab kisi agle trace mein SPAWN marker aakhri ho). Launch layer, handler, move/spawn, StartServer flags - sab unchanged.

## 2.8.8 (Import trace - run #30 ke 300s timeout ki layer pin karne ke liye, 2026-11-04)

- **Run #30 ka verdict: launch fix kaam kar gaya, naya fail 6m import par hai.** Companion WAIT OK 0.5s, state durust, PHP started line, section 3 + early probe PASS. Import POST 7827 par 300s timeout par ruki; companion log ki aakhri line sirf "Accepted" (handler ne kabhi jawab nahi diya). Kharij: session-lock (main 72/72 closed + POST dispatch line 249 par session close) aur worker ki susti (ciimport.job.log ghair-maujood = worker kabhi start hi nahi hua). Baqi segment: handler ke andar move_uploaded_file / shell_exec spawn.
- **Is version mein BEHAVIOR CHANGE KOI NAHI - sirf file-based trace.** Move vs spawn pin nahi hua aur import-via-companion sirf aik dafa chala hai (n=1), is liye kisi par bhi fix andaza hota. Trace: hub_import_trace helper (har line ke baad file band, warning suppress taake JSON na kharab ho) + import handler mein IMPORT-START/VALIDATED/BEFORE-MOVE/AFTER-MOVE/BEFORE-SPAWN/AFTER-SPAWN/BEFORE-HUBBACK markers + Windows spawn branch mein SPAWN-ENTER (poori command line)/CMD-WRITTEN/SHELLEXEC-RETURNED markers. Trace file: panel/.import-trace.log - smoke finally + workflow diagnostics (non-dot naam import-trace.log) + .iss UninstallDelete mein shamil.
- **Decision tree (agle gate ke liye):** aakhri marker BEFORE-MOVE = move stall; AFTER-MOVE baghair SPAWN-ENTER = spawn entry gap; CMD-WRITTEN = shell_exec/wscript stall (v2.8.9 mein pipe-proof detached spawn); AFTER-SPAWN = hub_back/session stall; trace mukammal + response = 6m pass (phir worker execution layer); 6m baghair behavior change ke pass = #30 aik dafa ka runner condition tha. Launch layer, timeouts, ports, spawn mechanism - sab unchanged.

## 2.8.7 (Companion launch fix - separate log file, 2026-11-04)

- **Run #29 ke verdict ka fix (launcher layer, product code
  mein koi tabdeeli nahi):** sathi server 7827 kabhi utha hi
  nahi tha - na listener, na process, na log mein Listening
  line, 5/5 runs deterministic. Wajah code se tay: StartServer
  main aur sathi DONON ka output aik hi `.dashboard.log` par
  `>>` redirect karta tha; main ka cmd woh file khol kar
  rakhta hai (php -S zinda rehta hai), is liye sathi ka cmd
  doosri dafa kholne par Windows sharing violation se php
  chalne se pehle ruk jata tha aur error hidden console mein
  zaya hota tha. Ab StartServer ko `logFile` parameter mila:
  main `.dashboard.log` par hi likhta hai, sathi apne
  `.dashboard-companion.log` par; sath hi har StartServer ki
  detail trace line (port + cmd + log). Agar ye hypothesis
  ghalat hui to bhi nuqsan nahi - sathi ka apna log us ki
  apni error ke sath maujood hoga, jo agle fail ki layer
  seedha batayega. Is ke sath: smoke evidence mein companion
  log tail 30, finally preserve + inventory mein companion
  log, workflow diagnostics mein companion log (non-dot naam
  `dashboard-companion.log`), aur `.iss` UninstallDelete mein
  companion log entry. Ports, wait times, probe logic, state
  format, stop_hub - sab waisa hi. v2.8.6 ka tag audit record
  reh gaya (us par koi release nahi bani); ship hone wali
  version 2.8.7 hai.

## 2.8.6 (CI evidence + diagnostics naming fix, 2026-11-04)

- **Run #28 ki diagnosis ka jawab, evidence round (product code
  aur launcher mein koi tabdeeli nahi):** v2.8.5 ne hang fix
  kar ke layer saaf kar di thi (launcher ne 30s wait mukammal
  kiya, honest port-only state likhi), lekin companion 7827
  ke process ki asal halat UNKNOWN reh gayi - StartServer
  fire-and-forget hai (na PID, na return code) aur fail-path
  par koi process snapshot nahi liya jata tha. Ab smoke mein
  naya `Dump-CompanionEvidence`: dono companion-fail throws
  par, throw se PEHLE (processes zinda hon), try/catch mein,
  job log mein `COMPANION-EVIDENCE |` prefix se - netstat
  :7827 listener/PID/state, php processes (Id + StartTime),
  `.dashboard.log` ki aakhri 30 lines, aur
  `.run\dashboard-7827.cmd` ka content. Agle gate ka evidence
  faisla kare ga: na php/listener to StartServer fix, php
  zinda + listener + jawab nahi to hang fix, ghair-mutaliqa
  PID to collision, "Failed to listen" to bind fix.
- **Diagnostics naam fix:** run #28 mein files workspace
  fallback mein mehfooz thin phir bhi upload ne "No files
  found" kaha - dot-prefixed naam upload-artifact ke directory
  glob mein nahi aate. Ab workflow diagnostics files ko
  non-dot naam se copy karta hai (dashboard-state.json,
  dashboard.log, launcher-trace.log); smoke finally inventory
  ab dot-naam check karta hai (v2.8.5 ki jhooti MISSING
  line ka fix). Inventory print + upload warn barqarar.
  Saaf rahe: v2.8.6 companion ka claimed fix NAHI hai - ye
  evidence round hai. v2.8.5 ka tag audit record reh gaya (us
  par koi release nahi bani); ship hone wali version 2.8.6 hai.

## 2.8.5 (Launcher + CI diagnostics fix, 2026-11-04)

- **Run #27 ki diagnosis ka fix, product code mein koi tabdeeli
  nahi:** v2.8.4 ka tag run #27 section 3 par is liye gira ke
  launcher sathi (7827) ke wait mein atka raha aur state likhi
  hi nahi - trace sirf wait ke natijay par likhta tha, har probe
  attempt nahi, aur iteration-count wala wait slow-probe par
  54-126s khinch sakta tha jabke smoke poll fixed 60s hai.
  Ab: (1) launch_hub.vbs har probe attempt ki wajah-code ke
  sath log karta hai (OK / HTTP-status / NO-TAMEERO /
  ERR-number) aur wait wall-clock se bandha hai (30s + aik
  probe ki tail se zyada nahi chalega); wait ke natijay mein
  attempts, aakhri wajah aur elapsed bhi darj hote hain;
  (2) smoke poll 60 se 90 tries (launcher worst ~44s ke liye
  2x margin), launcher process ka zinda/mara dump mein, aur
  finally mein preserved files ki inventory + workspace
  fallback copy (TEMP ke ilawa); (3) workflow diagnostics
  step copies ke baad poori inventory job log mein print karta
  hai, workspace fallback bhi uthata hai, aur upload
  if-no-files-found ignore se warn par hai taake ghaib
  artifact khamoshi se na guzre. Slow-probe ka asal trigger
  abhi UNKNOWN hai, lekin fix us nuqsan ko bandhta hai -
  agla gate ya to PASS hoga ya diagnosable fail dega, andha
  fail nahi. v2.8.4 ka tag audit record reh gaya (us par koi
  release nahi bani); ship hone wali version 2.8.5 hai.

## 2.8.4 (Launcher + CI diagnostics fix, 2026-11-04)

- **Run #26 ki diagnosis ka fix, product code mein koi tabdeeli
  nahi:** v2.8.3 ka tag run #26 section 3 par is liye gira ke
  state file mein sirf port tha ya state bani hi nahi - ek hi
  FAIL line se ye farq nahi hota tha, aur section 3 outer try
  se bahar tha is liye fail par koi diagnostics artifact bhi
  nahi bana. Ab: (1) launch_hub.vbs har faisla waqt ke sath
  `.launcher-trace.log` mein likhta hai (start, har probe ka
  natija, state ke arguments, start-failed wajah); (2) smoke
  ka outer try section 3 samet poore script ko cover karta hai,
  finally mein state + log + trace TEMP mein mehfooz hote hain
  phir Stop-Dashboard chalta hai; (3) section 3 ke teen alag
  checks - state nahi mili / port nahi hai / upload_port nahi
  hai - taake FAIL line hi layer bata de, aur fail par trace
  ki aakhri lines job log mein bhi print hoti hain; (4) sathi
  probe 10s se 30s (cold runner par slow start ko munsifana
  moqa; normal case mein probe pehle jawab par ruk jata hai);
  (5) workflow diagnostics mein trace file bhi copy hoti hai
  aur uninstall par trace saaf hota hai. v2.8.3 ka tag audit
  record reh gaya (us par koi release nahi bani); ship hone
  wali version 2.8.4 hai.

## 2.8.3 (Launcher + CI fix, 2026-11-04)

- **Sathi (companion) server ki shuruaat ab verify hoti hai:**
  v2.8.2 ka tag run #25 section 6m par is liye gira ke sathi
  server (port 7827) par pehli hit "connection refused" mili —
  us waqt 7827 par koi server sun nahi raha tha. Launcher sathi
  ko bina probe chalata tha aur smoke usay 6m tak kabhi probe
  nahi karta tha (section 3 sirf state file + main dashboard
  check karta tha), is liye masla 7 minute baad 6m par khula.
  Ab: (1) launch_hub.vbs sathi par bhi WaitDashboard probe
  karta hai — na uthay to state mein upload_port ka dawa nahi
  likha jata (dashboard phir bhi chalta hai, import main
  server se hoti hai); (2) smoke section 3 mein 7827 ka early
  retry-loop probe; (3) 6m ki pehli hit par retry loop (single
  attempt ki jagah); (4) fail diagnostics mein state file +
  dashboard log — smoke ke finally block mein Stop-Dashboard
  se PEHLE TEMP mein mehfooz hote hain, kyunke stop_hub state
  file delete kar deta hai (run #25 ka artifact isi liye sirf
  ek job log tha). Product logic mein koi
  tabdeeli nahi; v2.8.2 ka tag audit record reh gaya (us par
  koi release nahi bani); ship hone wali version 2.8.3 hai.

## 2.8.2 (CI fix, 2026-11-04)

- **Sirf CI test ka fix, product code mein koi tabdeeli nahi:**
  v2.8.1 ka tag run #24 "Install smoke" ki aakhri nayi check par
  gira: "MCP bina token ke 401 deta hai". Diagnosis (code + local
  reproduction dono se): check GET request bhej raha tha, aur MCP
  server design ke mutabiq ghair-POST par auth se pehle 405 deta
  hai. Asal auth gate bilkul theek hai — local saboot: GET = 405,
  POST bina token = 401, POST ghalat token = 401. Check ab POST
  bhejta hai. v2.8.1 ka tag audit record reh gaya (us par koi
  release nahi bani); ship hone wali version 2.8.2 hai.

## 2.8.1 (CI fix, 2026-11-04)

- **Sirf CI test ka fix, product code mein koi tabdeeli nahi:**
  v2.8.0 ka tag run #23 "Install smoke" par is liye gira ke
  6e check Home page par "English" / "Lite mode" dhoond raha tha,
  jab ke Mockup A restyle mein Zuban + Lite toggle Settings page
  par chale gaye hain (Home par ye lafz hain hi nahi). Check ab
  Settings page par hota hai. v2.8.0 ka tag audit record reh gaya
  (us par koi release nahi bani); ship hone wali version 2.8.1 hai.

## 2.8.0 (Dhansoo Dashboard, 2026-11-04)

- **Naya dashboard (Mockup A "Command Center"):** dark navy + gold,
  sidebar, top summary strip, bade cards, dayein AI chat rail
  hamesha nazar aati hai; single window, main area andar scroll
  hota hai. Saare pages (Home / New Project / AI Agent /
  Settings / Health) isi shell mein — koi feature gum nahi hua.
- **Dashboard AI chat panel:** dashboard ka backend owner ke AI
  gateway (default 9Router, localhost:20128) se baat karta hai;
  model jo tool mange woh MCP layer se chalta hai — parhne wale
  foran, likhne wale approval queue mein (chat mein Approve /
  Reject card, destructive par type-confirm). Token browser tak
  kabhi nahi jata; gateway band ho to chat "AI band" batati hai,
  dashboard ka baqi kaam chalta rehta hai.
- **Dukaan v1 (create-time CHOICE):** apni Urdu RTL store theme
  (Purple GPL fork, maroon + gold) sirf Online Store banate
  waqt chuni ja sakti hai — Twenty Twenty-Five default rehta
  hai. Urdu script demo pack (posts, pages, products) Dukaan
  sites ke liye; baqi sites ka Roman Urdu pack waisa ka waisa.
- **Field fix — "Turn MCP On" ab asal mein server chalata hai:**
  v2.7.1 mein toggle sirf flag tha (field bug #4) — ab On par
  bundled PHP se `mcp.php serve` spawn hota hai (PID file),
  Off par wahi process band hoti hai, aur Agent page par status
  asal process ki haalat dikhata hai (PC restart ke baad khud
  dobara start bhi ho jata hai).
- **Field fix — Import hang ka jarr se hal:** dashboard ki GET
  requests session lock foran chhor deti hain, aur Windows
  launcher ek sathi upload server (port+50) bhi chalata hai —
  bari import ke dauraan dashboard responsive rehta hai, aur
  import par progress nazar aati hai.
- **CI gate mein naye test:** launcher (VBS) se start + sathi
  server, Dukaan choice se store, MCP toggle on/off (port 8971
  + 401), sathi server se import, aur uninstall par v2.8 ki
  nayi runtime state (pid/log/chat) ki safai.
- **Code review ke 3 chhote fix:** asset path par `..` ki rok
  (defense-in-depth), gateway-alive probe par error JSON ka
  khayal, aur khaali AI jawab par fallback paigham.

## 2.7.1 (MCP + Uninstall Fix, 2026-11-02)

- **v2.7.0 ki MCP wali saari cheezein** (36 tools, approval
  queue, Agent Connection) — v2.7.0 khud kabhi release nahi
  hua tha (Windows test ke aakhri check par ruk gaya tha);
  v2.7.1 us ki jagah hai.
- **Uninstall ki rukawat theek:** dashboard khulte hi MCP ka
  `.mcp-actions` folder sirf parhne par bhi ban jata tha —
  uninstall ke baad poora panel folder reh jata tha. Ab
  folder sirf tab banta hai jab asal mein koi action save
  ho, aur uninstall par MCP ki runtime state (actions +
  audit log) baqi dashboard state ki tarah saaf ho jati
  hai. Aap ke projects (sites) uninstall par hamesha ki
  tarah mehfooz rehte hain.
- **CI mein naya negative test (6j):** MCP read ke baad
  folder na banna + MCP state ke sath uninstall saaf
  guzarna — yehi fail dobara nahi hoga.

## 2.7.0 (MCP — AI Agent Connection, 2026-11-02)

- **NAYA: MCP server — AI agent ab TAMEERO se baat kar sakta hai.**
  Open MCP standard par bana server (Muse, Cursor, aur har
  MCP client ke liye) — kul **36 tools**: sites ki list/haalat/
  health/logs, products/pages/posts/plugins/themes ki tafseel,
  aur manzoori ke baad site banana, clone, backup, import, deploy,
  password reset, PHP switch, product/page/post banana-badalna,
  plugin on/off, theme badalna.
- **Har tabdeeli owner ki manzoori se:** koi bhi write kaam
  seedha nahi chalta — pehle dashboard ke **Agent Activity**
  panel mein pending ata hai; owner Manzoor/Radd karta hai.
  Action 10 minute mein expire ho jata hai.
- **DESTRUCTIVE kaam par type-confirm:** site delete, product
  delete aur TAMEERO update par owner ko site ka exact naam
  (update ke liye "TAMEERO") type karke confirm karna padta
  hai — ghalat naam par faisla darj hi nahi hota.
- **Agent Connection settings (dashboard):** MCP on/off,
  token banana (sirf ek dafa dikhta hai) aur khatam karna,
  client config ka ready snippet. Token baghair koi connection
  nahi — ghalat token 401, MCP band ho to 403.
- **Transports:** HTTP (127.0.0.1:8971; --bind se apne private
  Tailscale network par bhi) aur stdio (local clients ke liye).
  Har call ka audit log banta hai (passwords kabhi darj nahi).
- **Linux spawn bug theek (product-wide):** dash shell 2-digit
  fd numbers ko redirection mein nahi manta — jis process ke
  paas 11+ fds hon (jaisay MCP server, ya masroof dashboard),
  us ke spawn kiye gaye workers khamoshi se gayab ho jate thay.
  Ab spawn bash se jata hai; bash na ho to mehfooz fallback.


## 2.6.3 (Core Selection Fix, 2026-11-01)

- **Upgraded PC par Store creation ka asal bug theek:** purani
  WordPress zip (masalan 6.8.3) installed bundle mein pari honay
  par creation ghalat (sab se purani) core utha leti thi aur
  WooCommerce activate fail ho jata tha. Ab WP zip ka intekhaab
  **manifest ke pinned version** se hota hai (warna sab se unchi
  version wali zip) — alphabetical pehli file kabhi nahi.
- **Plugin extraction manifest-exact:** site mein sirf pinned
  plugin zips jati hain — dist mein pari koi purani ya ajnabee
  zip site mein nahi ghusti.
- **Upgrade safai:** installer ab upgrade par bundle ke dist
  folders se superseded zips khud saaf karta hai. Sirf dist
  zips — **sites, backups aur settings bilkul mehfooz**.
- **SQLite plugin pin (3.1.2):** pehle baghair-version wali
  "latest" URL istemal hoti thi — har build mein khamoshi se
  version badal sakta tha. Ab manifest hi wahid source hai
  (WordPress version bhi wahin se parha jata hai).
- **Build-time compatibility guard:** har Windows build par
  asal payload files se WordPress ka version aur har plugin ki
  "Requires at least" shart parh kar jora verify hota hai —
  incompatible jora ho to build wahin ruk jati hai, installer
  banta hi nahi.
- **CI kasotiyan:** upgrade-path test (purani zip + mojooda
  site → in-place upgrade → dobara born-complete) aur
  incompatible-jore ka failure test shamil.

## 2.6.2 (Creation Fix + Visibility, 2026-10-31)

- **Creation hang ka asal fix:** site banane ke saare steps ab
  file-redirect par chalte hain (exec() ke output-capture pipe
  wala purana tareeqa khatam — usi pipe-wait se creation owner
  ke PC par 30+ minute atki thi). Har step ki hadd **180 second**
  hai: step latke to khud ruk kar saaf fail hota hai, aur poori
  creation par 15-minute ka watchdog hai.
- **Failed state:** creation fail/ruk jaye to dashboard card
  hamesha "Creating..." nahi rehta — **"Fail ho gayi"** card
  ata hai jis step par ruki + wajah ke sath, aur wahi se
  **Delete** (recovery) aur **Log Dekho** buttons milte hain.
  Worker achanak mar jaye (power cut waghera) to bhi 30 minute
  baad card khud Failed ho jata hai.
- **Live Progress:** banti hui site ke card par step-by-step
  haalat ("Step 3/6 — Plugins activate ho rahe hain"), progress
  bar aur mukammal steps par ✓.
- **Live Preview:** creation ke doran site ki photo do martaba
  khud taaza hoti hai — site banti hui nazar ati hai.
- **Windows CI FULL E2E:** ab har release se pehle asal Windows
  par poora flow verify hota hai — installer install, dashboard,
  Store site born-complete check (Woo active, pages, 8 products,
  store Live) aur ek negative hang test (atka step → timeout →
  Failed state → dashboard se recovery). Fail ho to release
  nahi banti.
- Dashboard ke chaar sub-pages (Plugins, Database, Mail, Wizard)
  ki headings mein reh gaya purana "WebDev Hub" naam TAMEERO
  se badla.

## 2.6.1 (Pehli TAMEERO Release, 2026-10-31)

- **TAMEERO ki pehli shipped release** — rename (2.6.0 wala code)
  is version mein pehli dafa installer ke sath ja raha hai.
- **CI fix:** Windows build ki compile-check purani naam wali
  installer file (`WebDevHub_Setup_v*.exe`) dhoondhti thi, is liye
  v2.6.0 ka build kamyab compile ke bawajood fail ho gaya tha aur
  koi Release nahi bani. Check ab naam-azad hai (`*_Setup_v*.exe`),
  aur CI artifact ka naam bhi TAMEERO-Windows-Installer hai.
- v2.6.0 ka tag repo mein record ke tor par mojood rahe ga
  (failed CI, no release) — owner ka faisla.

## 2.6.0 (Rename — TAMEERO, 2026-10-30)

- **Product ka naya naam: TAMEERO** (tagline: "Your Website. One Click.
  Done.") — pehle "WebDev Hub Studio". Naam takkar-check ke baad final
  hua: exact naam par koi company/software/product nahi mila. Installer,
  wizard, shortcuts, dashboard, site titles, mu-plugin — har
  user-facing jagah naya naam.
- Andar ke naam (functions, option keys, file names) aur install
  folder/AppId barqarar hain, taake mojooda projects mehfooz rahein.

## 2.5.0-dev (Build 5 — "Sab Ke Liye Asaan", 2026-10-28)

Roadmap ke aakhri 3 features — pehli-dafa ka tajurba aur har
tarah ke PC ke liye:

- **First-run wizard (23):** bilkul pehli dafa (koi project nahi)
  dashboard ki jagah 3-step wizard khulta hai: (1) zuban +
  lite mode ka intekhab, (2) pehla project (naam, blueprint,
  admin password), (3) mubarak + dashboard ka rasta. "Wizard
  chhor dein" se kabhi bhi bahar. Settings `panel/.settings.json`
  mein rehti hain; automation ke liye official escape hatch
  `WDH_NO_WIZARD=1` hai.
- **Urdu/English switch (24):** dashboard ke main page aur
  health page ki mukammal do zubanein — header ke button se
  ek click mein tabdeeli, setting mehfooz rehti hai. Default
  Roman Urdu hi hai (product ki pehchan). Tool views
  (plugins/database/mail) aur flash paighamat abhi Roman Urdu
  mein hain — ye is build ke scope se bahar rakha gaya hai.
- **Lite mode (25):** kamzor/purane PCs ke liye halka mode —
  site server 1 worker par (aam tor par 6), card photo ke liye
  browser screenshot skip (halka placeholder), background size
  ginna band, creating refresh 6s ki jagah 30s. Header se
  on/off, wizard mein bhi poochha jata hai.

## 2.4.0-dev (Build 4 — "Bahir Ki Duniya", 2026-10-28)
Roadmap ka chautha batch — site ko bahir ki duniya se jorna:
- **XAMPP Scan (17):** Health page ab XAMPP ki haalat batata hai —
  install hai ya nahi, Apache/MySQL chal raha hai ya nahi.
  WebDevHub XAMPP ko haath nahi lagata (alag ports + SQLite);
  ye sirf maloomat hai (class checklist item #2 mukammal).
- **PHP Version Switcher (18):** bundle/config/php-versions.json
  mein pinned downloadable lines (8.3.26, 8.2.29 — Windows:
  windows.php.net, Linux: static-php-cli). Health page se ek
  click par download + install (runtime/php-<line>/), har project
  ke card se apna PHP chuna ja sakta hai (meta php_line; site ka
  server usi binary se uthta hai). Download ke tar extraction
  mein --no-same-owner lazmi nikla (sandbox root par chown fail
  se tar ruk jata tha) — error ab asal wajah ke sath fail hota hai.
- **Local HTTPS (19):** PHP ke openssl se local CA + har site ka
  cert (SAN localhost/<slug>.local/127.0.0.1); ek chhota TLS
  proxy (tools/https-proxy.php) site ke aage lagta hai jo
  X-Forwarded-Proto + X-WDH-HTTPS-Port headers jorta hai aur
  Location redirects https par badalta hai; mu-plugin (2.4.0)
  in headers par siteurl/home https origin par kar deta hai —
  is liye WP http par redirect nahi karta aur saare URL https
  bante hain. Dashboard se CA certificate download ho sakta hai
  (browser warning khatam karne ke liye trust store mein lagana
  hota hai). Site band karne par HTTPS/preview khud band ho jate
  hain. Tasdeeq: CA verify ke sath https front + menu page 200.
- **Live Preview Link (15):** card se ek click — cloudflared
  quick tunnel (pehli dafa ~40MB tool khud download hota hai,
  tools-bin/ mein rehta hai); na mile to ssh (localhost.run)
  fallback. Link meta mein rehta hai, band karna bhi card se.
  Log parser ka jhoota match (api.* host) pakra aur theek kiya:
  asal address "Your quick Tunnel" wali line ke baad se pakra
  jata hai. Is test sandbox ka egress TLS-intercept karta hai,
  is liye asal tunnel yahan nahi ban saka — download, spawn,
  parse, state aur nakami par mukammal safai sab verified hai;
  aam internet (user PC) par tunnel banta hai.
- **Deploy Package (16):** card ka button live hosting ke liye
  tayyar zip deta hai: site files (local junk saaf karke) +
  database.sql (tools/deploy-dump.php: SQLite se MySQL dump —
  CREATE conversion AUTO_INCREMENT/ENGINE ke sath; SQLite
  driver ki internal _wp_sqlite_* tables kharij) +
  wp-config-live.php template + DEPLOY-CHECKLIST.txt. URLs
  fix-url se https://APKI-DOMAIN.com placeholder par badal
  jati hain (guid column jaan boojh kar nahi badalta — WP ka
  usool hai). Tasdeeq: mailtest ka 128MB package, dump mein
  44 asal tables/522 rows, restaurant ka content mojood.
- **Auto-Update (22):** VERSION file (2.4.0-dev) + health page
  par check/apply. Apply pehle backup (sites/.update/backup-*)
  banata hai, phir sirf code dirs (panel, bundle, installer-*)
  badalta hai — sites/templates/runtime/CA mehfooz. Source
  bundle/config/update.json mein hai (public GitHub repo);
  repo abhi private hai, is liye asal check public hone ke
  baad kaam karega — poora mechanism local fake server se
  E2E verified hai (9.9.9 check + apply + backup sab pass).
- **Meta safai ka bug fix:** hub_save_meta ka merge-on-write
  unset() ko be-asar kar deta tha (hatayi gayi key purani copy
  se wapas a jati thi) — ab '_remove' list se keys asal mein
  hat ti hain (preview_url/php_line ki safai mein pakra gaya).
- Tests: dashboard smoke mein naya 6e section (health hisse,
  card buttons, update check fake server se, HTTPS on/CA-
  verify/off, preview stub se on/off + meta safai, PHP switcher
  meta (runtime ho to), deploy package download); Windows smoke
  mein install checks (https-proxy, deploy-dump, VERSION,
  php-versions config) + health/card presence checks shamil.
  Installer version 2.4.0.

## 2.3.0-dev (Build 3 — "Andar Ke Tools", 2026-10-28)
Roadmap ka teesra batch — dashboard ke andar ke roz-marra tools:
- **5 naye blueprints** (feature 12): Blog, Portfolio, Restaurant,
  School, Agency — har ek tayyar pages + menu + Roman Urdu sample
  content ke sath (Restaurant mein sample menu/prices bhi). Create
  form ki blueprint list ab bundle se khud banti hai.
- **Update ki khabar** (feature 10): card par amber line jab bundle
  mein WordPress ya kisi plugin ka naya version ho. Check background
  worker mein, 24 ghante mein ek dafa, bundle se muqabla karke
  (internet ki zaroorat nahi).
- **Plugin manager** (feature 9): har site ke plugins ki list —
  naam, version, bundle mein naya ho to nishan, aur ek click
  chalu/band (tools/plugin-toggle.php, WP bootstrap ke zariye).
- **Database tool** (feature 8): tables browse (50 rows/page),
  kisi cell ki value badlo, aur SELECT-only sawal wali khirki —
  ghalat/na-mehfooz sawal saaf inkaar ke sath rukta hai. Serialized
  values par khaas warning.
- **Mail catcher** (feature 6): site ki saari outgoing mail
  mu-plugin (v2.3.0) pakar kar site ke andar mehfooz karti hai —
  koi mail machine se bahar NAHI jati. Dashboard mein mail list +
  parhna (HTML mail sandboxed frame mein) + delete/clear. Purani
  sites clone/import par naya mu-plugin khud refresh ho jata hai.
- Teen asal fixes (teeno smoke/diagnosis mein pakre gaye):
  - **FD inheritance — ASAL fix:** Build 2 wala php://fd wala
    tareeqa is PHP build mein FD band hi nahi karta (tajurbe se
    sabit). Ab worker spawn wali shell line FDs band karti hai
    (bachay ke paas sirf 0,1,2 rehte hain — sabit-shuda). Dashboard
    band hone ke baad us ki port phansne wala masla jad se khatam.
  - **Plugin-toggle variable takrao:** tool ke $plugin/$path/$mode
    WP ke globals se takra kar khamoshi se ghalat plugin toggle
    karte thay — ab $wdh* naam.
  - **Copy/extract ki sakhti:** test mahol mein bojh tale writes
    kabhi kabhi khamoshi se nakam hoti dekhi gayin (0-byte files,
    adhoora extract, koi warning nahi). Ab: copy har file ka size
    mila kar kharab files ki repair + ginti deta hai; extract ke
    baad har zip entry verify hoti hai, adhoori files infiradi
    repair hoti hain, na theek hon to saaf fail — kharab site
    kabhi activate nahi hoti; copy se pehle database ki sehat
    (size) bhi check hoti hai.
- Windows CI Run #7 mein pakra gaya delete masla: 42/43 checks
  pass, sirf aakhri site delete fail — Windows par background
  worker ke open handle ki wajah se kuch files delete nahi hoti
  theen aur folder reh jata tha (hub_rrmdir single-pass tha,
  nakami khamosh). Ab rrmdir bounded retries (5 pass) karta hai
  aur hub_delete asal natija wapas karta hai — folder reh jaye
  to dashboard jhoota "delete ho gaya" nahi kehta.
- Tests: dashboard smoke 39 -> 49 checks. Is test VM par kabhi
  kabhi chand-minute ke "write storm" (saari writes EPERM) ate
  hain — aakhri run 47/49, sirf demo-content wale 2 checks storm
  mein phanse; wahi 2 checks fori baad haath se dobara chala kar
  PASS verified. Bundle smoke 17/17. Windows CI smoke mein
  plugin-toggle + mail-catcher + naye blueprints ke checks shamil.

## 2.2.0-dev (Build 2 — "Copy Engine", 2026-10-28)
Roadmap ka doosra batch — ek hi copy engine par panch features:
- **Site clone** (feature 2): card se ek click mein poori copy,
  nayi port/URL par. Chalti site copy ke dauraan mehfooz tareeqe se
  band->copy->shuru hoti hai; admin purani site wala hi chalta hai.
- **Apne templates** (feature 11): kisi site ko template bana kar
  mehfooz karo, phir create form se usi template se kitni bhi
  sites. Templates {app}/templates mein rehti hain.
- **Zip import** (feature 13): WebDevHub export (ya SQLite wali koi
  bhi WP site) ki zip dashboard se upload karke site ke tor par
  wapas. MySQL wali zip par saaf inkaar + wajah.
- **Urdu demo content pack** (feature 14): create par checkbox —
  5 Urdu posts, 2 pages, store par 5 mazeed products (sab
  'webdevhub-demo' nishan ke sath).
- **Auto-backup** (feature 20): dashboard khulne par jis site ka
  backup 7 din se purana ho us ka background mein ban jata hai;
  har site ke sirf aakhri 2 backups rehte hain; card par "Aakhri
  backup" + manual Backup button bhi.
- Copy engine ka dil: tools/fix-url.php — SQLite par
  serialized-aware search-replace + wp-config path fix; WP
  bootstrap ke baghair, side-effects se paak.
- Dashboard server ab 1 GB tak upload qabool karta hai (import ke
  liye — dono launchers mein ini flags).
- Do asal bug fixes (dono smoke mein pakre gaye):
  - Meta race: copy ke darmiyan nayi dir mein purani site ki
    .webdevhub.json maujood rehti thi; dashboard ke background
    workers usay asal site samajh kar purani pehchan wapas likh
    dete thay. Ab pehchan copy NAHI hoti aur hub_sites() naam/meta
    ke mismatch wali entry ko site hi nahi manta.
  - FD inheritance: site servers dashboard ka listen socket
    inherit kar lete thay — dashboard band hone ke baad bhi us ki
    port phasi rehti thi (dobara bind fail). Ab worker spawn se
    pehle apne ghair-zaroori FDs band karta hai (Build 1 ke hang
    wale bug ke khaandan ka doosra asar).
  - fix-url ek martaba retry hota hai — bojh tale PHP process ke
    kabhi-kabhar crash (segfault) par poora clone rukta nahi.
- Tests: dashboard smoke 25 -> 39 checks; Windows CI smoke mein
  clone ka asal test + naye tools ki install check.

## 2.1.0-dev (Build 1 — "Roz ka kaam", 2026-10-28)
Owner ki manzoor-shuda 25-feature roadmap (docs/feature-roadmap.md)
ka pehla batch:
- Har project card par homepage ki **thumbnail photo** (start par
  khud banti hai; "Photo Taaza" button se dobara; browser screenshot
  na mile to GD placeholder — JPEG jahan mumkin, warna PNG).
- Card par **size + aakhri dafa kab chala** (sizes background worker
  mein ginte hain taake dashboard kabhi slow na ho).
- Toolbar: **sab projects ek sath start/stop** + **project search**.
- Dashboard se **admin password reset** (password sirf ek-dafa wali
  env file se jata hai, argv/log mein kabhi nahi; naya chalta hai,
  purana band ho jata hai — smoke-verified).
- **Health check page** (/?view=health): PHP version + extensions,
  disk, bundle, projects ki haalat ek jagah.
- Fix (smoke mein pakri race): hub_save_meta ab merge-on-write hai —
  ek sath chalne wale workers (start/sizes) ek doosre ki meta
  tabdeeli nahi mitate.
- Tests: dashboard smoke 12 -> 25 checks; Windows CI smoke mein bhi
  v2.1 checks (health, UI markers, thumbnail, password reset).

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
- Bug fix (2026-10-28, CI Windows Run #3): uninstall ke baad panel reh
  jata tha — panel ke runtime-generated files (.run\*.cmd waghera)
  Inno manifest mein nahi thay. [UninstallDelete] ab sirf unhi ko
  saaf karta hai; sites/ hamesha mehfooz. Is se pehle ke saare Windows
  smoke checks (PHP runtime, extensions, dashboard, create, front
  page, one-click admin, stop, delete) usi run mein PASS thay.
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

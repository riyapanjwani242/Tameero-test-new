# WebDevHub — Decision Log

Format: muse-decision-log skill. Append-only — sudhaar nayi entry se hoga, purani entry dobara nahi likhi jayegi.
Query par entry quote hogi, behes dobara nahi hogi.

---

## 2026-11-03 — Owner ko bheji jane wali PowerShell scripts ASCII-only
- **Faisla:** Owner ki machine par chalne wali har .ps1 script ASCII-only hogi (ya UTF-8 BOM ke sath). Release gate mein byte-scan lazmi check hai.
- **Wajah:** Field failure — dukaan-polish.ps1 mein do em dash (—, UTF-8 without BOM) ko Windows PowerShell 5.1 ne ANSI parha; ParserError "string missing terminator", script chali hi nahi (store mehfooz raha). Janu ne ASCII fix karke -2 version bheja.
- **Faisla kis ka:** Owner (janu ke zariye)
- **Amal:** tests/smoke-windows.ps1 + installer-windows/fetch-runtime.ps1 ASCII saaf (line 147 ka ● regex mein ● shakl mein); release-gate skill mein check shamil.
- **Revisit:** No revisit set

## 2026-10-27 — Dual version, ek shared bundle; Linux reference build
- **Faisla:** Windows aur Linux versions ek hi shared bundle (~90% shared code) se banein ge; pehle Linux reference build, phir usi tree se Windows packaging.
- **Wajah:** Do alag codebases ki maintenance dogni hoti aur fixes ek side reh jate; shared bundle mein har fix dono ko milta hai.
- **Faisla kis ka:** Owner
- **Alternatives:** Alag-alag native builds (rejected — maintenance cost)
- **Revisit:** No revisit set

## 2026-10-28 — 25 features ka roadmap mukammal approve, 5 audited builds mein
- **Faisla:** Feature roadmap ke saare 25 features approve hain aur 5 builds (v2.1–v2.5) mein ek waqt mein ek build, testing ke sath, banaye jayein ge.
- **Wajah:** Owner ne poori list ek sath approve ki; audited-build discipline (proposal → pick → ek build → test) se har version verified raha.
- **Faisla kis ka:** Owner
- **Alternatives:** Ek hi bari release (rejected — risk zyada)
- **Revisit:** No revisit set

## 2026-10-28 — Repo PRIVATE rahega; auto-update sirf public hone par
- **Faisla:** GitHub repo private rahega; auto-update mechanism asal source se tabhi active hoga jab repo public ho (mechanism pehle bana kar local fake server se prove kar diya gaya).
- **Wajah:** Project abhi joint development phase mein hai; public karna owner ka alag faisla hoga.
- **Faisla kis ka:** Owner
- **Alternatives:** Abhi public karna (rejected)
- **Revisit:** Jab owner public karne ka soche

## 2026-10-30 — Themes ka route: sirf WordPress.org free GPL base; Purple = Dukaan ka base
- **Faisla:** Apni themes sirf WordPress.org ke free GPL themes ko fork karke banein gi; Dukaan ka base Purple (WooCommerce) hai. Scratch se theme banana band; Nexora Shop sirf level benchmark hai, clone kabhi nahi; nulled route hamesha ke liye band.
- **Wajah:** ThemeForest/marketplace licenses resale products ki ijazat nahi dete; GPL base par apni theme bechna qanuni aur mehfooz hai, aur waqt bhi bachta hai.
- **Faisla kis ka:** Owner
- **Alternatives:** Scratch classic theme (shuru karke band ki gayi), Blocksy (Pro gate ki wajah se rejected), Nexora clone (paywall + trademark — rejected)
- **Revisit:** No revisit set

## 2026-10-30 — Pehle bundle update (WP 7.1.4 + Woo 11.1.2), phir Dukaan v1
- **Faisla:** Dukaan v1 se pehle bundle ko WordPress 7.1.4 + WooCommerce 11.1.2 + Urdu pack 5.4.25 par update kiya gaya, taake theme apni asal supported zameen par bane.
- **Wajah:** Purani stack (WP 6.8.3/Woo 10.4) par bani theme nayi versions par toot sakti thi; pehle zameen pakki, phir theme.
- **Faisla kis ka:** Owner (janu ke zariye)
- **Alternatives:** Pehle v1, update baad mein (rejected)
- **Revisit:** No revisit set

## 2026-10-30 — Agent connection ka interface: MCP server (final)
- **Faisla:** WebDevHub se agent ka connection MCP server se hoga — local/private network par hi; har live/destructive action se pehle owner ki approval. Build Marhala 3 (v2.6 theme integration) ke baad.
- **Wajah:** Browser-clicks nazuk aur CLI-as-interface ghair-munasib qarar paye; MCP programmatic, mehfooz aur doosre agents ke liye bhi khula rasta hai. Bunyad WP-CLI + hub ke programmatic hisse rahen ge.
- **Faisla kis ka:** Owner
- **Alternatives:** Browser automation (rejected), CLI as interface (rejected)
- **Revisit:** No revisit set

## 2026-10-30 — Hosted service sab se aakhri marhala
- **Faisla:** Hosted (SaaS) version sab se aakhir mein bane ga — themes + MCP mukammal hon aur paying customers sabit hon, tab.
- **Wajah:** Hosted ke sath server cost, 24/7 uptime, backups aur security ki zimmedari ati hai; pehle product aur customers prove hon.
- **Faisla kis ka:** Owner
- **Alternatives:** Hosted pehle (rejected)
- **Revisit:** Jab paying customers ka saboot mile

## 2026-10-30 — Demo store mein asal Handy Crafts products; decimals/reviews = site settings
- **Faisla:** Dukaan demo store mein nakli products ki jagah Handy Crafts ke 9 asal products hain. Price decimals 0 aur reviews off WooCommerce ki site settings se hain — theme mein hardcode nahi.
- **Wajah:** Class demo asal dukaan jaisa lagna chahiye; settings ko theme mein bandhna har site ki azadi khatam karta, is liye wo site-level faisla raha.
- **Faisla kis ka:** Owner (polish passes ke zariye)
- **Alternatives:** Theme mein decimals/reviews hardcode (rejected)
- **Revisit:** No revisit set

## 2026-10-30 — Card title: 3 lines tak dikhe, price neeche bandhi hui
- **Faisla:** Product card ka title block 2 lines par katne ki jagah ab 3 lines tak poora naam dikhata hai (kam az kam 2 lines ki jagah reserved); price card ke aakhir mein bandhi (margin-top auto) rehti hai taake alignment na bigre. Ye Polish Pass 3 ke "fixed 2-line block" wale faisle ki jagah leta hai.
- **Wajah:** Owner ne Cushion Covers ka poora naam "(Set of 5)" card par dekhna lazmi qarar diya; price ki bottom-anchor alignment ki wajah se lambay title ke bawajood prices ek seedh mein rehte hain (screenshot-verified).
- **Faisla kis ka:** Owner
- **Alternatives:** Font size chhota karna (rejected — nazuk hal, har naam par kaam nahi karta)
- **Revisit:** No revisit set

## 2026-10-30 — Product ka naam: TAMEERO
- **Faisla:** Product ka naam "WebDev Hub Studio" se badal kar **TAMEERO** rakha gaya hai (tagline: "Your Website. One Click. Done."). Rename v2.6.0 mein ship hoga — installer, wizard, shortcuts, dashboard, site titles, mu-plugin; Dukaan theme ka naam nahi badlega.
- **Wajah:** Class ki doosri version se alag, apni pehchan chahiye thi. Owner ka usool: pehle takkar-check, phir final naam. TAMEERO sab se saaf nikla — exact naam par koi company, software ya product nahi mila.
- **Faisla kis ka:** Owner
- **Alternatives:** Zavero (pehle chuna gaya, lekin exact naam par ek chhoti health-gadget dukaan mili — owner ne reject kiya), Vexaro (asal software company), Calviro (furniture line + gadget brand), Kavero (do website agencies + Shopify app + trademark), KAROBIX (Pakistan mein live POS software + Indian software company), purana naam barqarar (rejected — alag pehchan nahi banti)
- **Revisit:** No revisit set

## 2026-10-31 — v2.6.0 re-tag NAHI, naya version v2.6.1
- **Faisla:** v2.6.0 ka CI fail (workflow ki compile-check purana installer naam dhoondhti thi) theek karke tag dobara lagane ki bajaye naya version **v2.6.1** banega. v2.6.0 ka tag repo mein waisa hi rahe ga — failed CI, koi Release nahi, bas record.
- **Wajah:** Tag move/delete karna saaf record ko bigarta hai; naya patch version seedha aur imaandarana hal hai.
- **Faisla kis ka:** Owner
- **Alternatives:** v2.6.0 tag delete karke fix par dobara lagana (rejected)
- **Revisit:** No revisit set

## 2026-10-31 — MCP agent-connection OPEN STANDARD par bane ga
- **Faisla:** MCP server khule MCP standard ke mutabiq bane ga — kisi ek AI se lock NAHI hoga. Muse, ChatGPT, Gemini, Cursor — jo bhi AI client MCP support kare, wo jud sakega. Pehla test Muse (janu) se hoga, lekin design/architecture Muse-specific nahi hoga.
- **Wajah:** Owner nahi chahta ke product ek hi AI ke ecosystem mein band ho jaye; open standard se har MCP client ke liye rasta khula rehta hai aur mustaqbil mehfooz hota hai. Baqi MCP shartein barqarar: local/private network only, har live action se pehle owner approval.
- **Faisla kis ka:** Owner
- **Alternatives:** Muse-specific connection (rejected — lock-in)
- **Revisit:** No revisit set

## 2026-10-31 — Phase tartib tabdeel: MCP pehle, Dukaan v1 baad mein
- **Faisla:** Phase tartib badal gayi hai — ab (1) MCP agent-connection phase PEHLE hoga, (2) phir Dukaan v1 + theme integration, (3) phir plugins phase, (4) hosted service sab se aakhir (unchanged).
- **Wajah:** Owner ne tartib dobara soch kar MCP ko pehle rakha — agent-connection product ki agli pehchan hai aur wo themes se pehle chahiye.
- **Faisla kis ka:** Owner
- **Alternatives:** Purani tartib (Dukaan v1 → theme integration → MCP) barqarar (rejected — owner ne tartib badli)
- **Revisit:** No revisit set

## 2026-10-31 — Site Visibility features manzoor (owner ke apne khayal)
- **Faisla:** Do visibility features manzoor hain: (1) creation ka **Live Progress** (job log se step-by-step haalat + progress bar, stuck step ki pehchan screen par) aur creation ke doran **live preview photo** ka refresh — ye dono creation-hang fix ke sath agli version mein ayengi; (2) MCP scope mein **"Agent Activity — Live" panel** (audit log ki live dashboard surat, pending actions par approve/reject usi panel se).
- **Wajah:** Owner ka apna khayal hai — kaam hota hua nazar aye to atki hui haalat foran pakri jati hai aur bharosa banta hai. Creation-hang wale waqiye ne is ki zaroorat sabit bhi kar di.
- **Faisla kis ka:** Owner
- **Alternatives:** Sirf text "Creating..." wali purani haalat (rejected)
- **Revisit:** No revisit set

## 2026-10-31 — TAMEERO v2.6.1 release live (pehli TAMEERO release)
- **Faisla/Natija:** v2.6.1 shipped hai: commit `d8836c2`, tag `v2.6.1`, CI dono green (Windows Build #17, Linux Smoke #14), auto-Release live — installer `TAMEERO_Setup_v2.6.1.exe`, 162 MB, SHA256 `4f375e278ecdbdf98b0174deb2a4c85e49e435489f34a6423444c7513c47653e`, download verified. Owner ke PC par install + running verified; dono sites in-place upgrade mein mehfooz rahi.
- **Faisla kis ka:** Owner (release) / system (auto-Release)
- **Alternatives:** —
- **Revisit:** No revisit set

## 2026-10-31 — Har release se pehle Windows CI FULL end-to-end test lazmi
- **Faisla:** Har version ki release se pehle GitHub ki Windows machine (windows-build workflow) par poora asal flow chalega: us build ka installer asal Windows par install → dashboard launch + site list verify → Store site create (WooCommerce + PKR, Urdu demo ON) → born-complete verify (creation mukammal, Woo active, shop pages, 8 demo products, store Live) → negative hang-simulation (step rok kar timeout + ERROR state + dashboard recovery ka saboot). Fail ho to release aage NAHI barhe gi.
- **Wajah:** Owner ne Antigravity par shift ki bajaye ye rasta chuna — har version asal Windows par verify ho, taake owner ke PC par ja kar surprise na mile (creation-hang wala waqiya is ki sab se bari misaal hai).
- **Faisla kis ka:** Owner
- **Alternatives:** Antigravity par shift (rejected — owner ne CI wala rasta chuna)
- **Revisit:** No revisit set

## 2026-10-31 — DONO GO: v2.6.2 build + MCP phase (4 jawab samet)
- **Faisla:** (1) **v2.6.2 GO** — scope wohi jo docs/v2.6.2-scope.md mein hai: creation-fix package + 4 headings fix; Windows CI FULL E2E test is release ki shart hai. (2) **MCP phase GO** — 4 sawalon ke jawab manu ki sifarishat ke mutabiq: test route **B (Tailscale)**; approval flow manzoor (pending queue + 10-min expiry + destructive par site-naam type-confirm); content tools isi phase mein (build step 5 ki tartib par); phase 1 mein sirf token kafi, IP allowlist nahi.
- **Tartib:** pehle v2.6.2 mukammal (build → Linux self-test: creation E2E + hang-sim + kill-sim → CI dono green incl. Windows FULL E2E → auto-Release), phir MCP build proposal ki tartib se (core → READ → approval queue → WRITE lifecycle → WRITE content → destructive → packaging). Tailscale joint test owner ke PC on hone par hoga — wo hisa blocker hai, baqi kaam us par nahi rukega.
- **Faisla kis ka:** Owner
- **Alternatives:** —
- **Revisit:** No revisit set

## 2026-10-31 — TAMEERO v2.6.2 release live (creation fix + visibility)
- **Faisla/Natija:** v2.6.2 shipped hai: commit `de9f101`, tag `v2.6.2`, CI dono green — Windows Build #18 (Install smoke 5m06s; andar nayi FULL E2E ke 16/16 born-complete checks + 7/7 negative hang checks PASS, kul ~80 PASS lines, log se verify) aur Linux Smoke #16. Auto-Release live — installer `TAMEERO_Setup_v2.6.2.exe`, 162 MB, SHA256 `06cf2cd08670182c7b96244cdd5b2fe27de867dd5f860cc544197ef1e1df7fda`, download verified. Linux self-test: smoke 17/17, dashboard smoke 76/77 (deploy check load ke doran fail; quiet window mein CLI + dashboard dono se PASS verify — koi regression nahi).
- **Faisla kis ka:** Owner (GO) / system (auto-Release)
- **Alternatives:** —
- **Revisit:** No revisit set

## 2026-10-31 — Correction: owner ki atki creation asal mein hang NAHI thi (field evidence)
- **Correction:** 2026-10-31 ki pehli diagnosis (creation pipe-wait par hang) owner ke card ki asal wajah NAHI thi. Owner ke PC ke job log (v2.6.2 ke neeche nayi creation par bhi) se sabit: creation STEP 3 par **WooCommerce activation par fail** hui thi — "Current WordPress version (6.8.3) does not meet minimum requirements for WooCommerce. The plugin requires WordPress 7.0." — aur process ##WDH_END## ke sath khatam ho chuka tha. Pipe-wait ek asal lekin alag latent risk tha jo v2.6.2 mein fix hua; purane dashboard ki khami ye thi ke wo ye failure dikhata hi nahi tha (card hamesha "Creating...").
- **Asal mechanism (code + local repro se sabit):** create ke waqt WP zip `glob(dist/wordpress-*.zip)[0]` se chuna jata hai (wdh.php) — alphabetical pehli = sab se purani. Pre-v2.5.1 installers (workflow mein WP 6.8.3 hardcoded tha) PC par `wordpress-6.8.3.zip` chhor gaye; in-place upgrades naya 7.1.4 zip jodte hain lekin purana delete nahi karte — is liye upgraded PC par har nayi creation 6.8.3 hi nikaalti rahi, jabke plugins manifest se naye (Woo 11.1.2) ate rahe. Fresh install par masla nahi (ek hi zip hota hai) — CI isi liye hamesha green raha; upgrade-path CI mein test hi nahi hota tha.
- **Fix:** pending — proposal owner ke samne jayegi (manzori ke baghair koi build nahi).
- **Release-gate tajweez (2026-10-31, owner ke sawal par):** v2.6.2 ke Linux self-tests repo tree ke bundle par hue thay; Windows CI E2E packaged installer par hua LEKIN fresh install par, aur installer ke andar ke versions kabhi extract karke nahi gine gaye; upgrade-path (purani dist leftover ke sath) kabhi test nahi hua — masla wahi se nikla. Agli har release ke liye gate tajweez: (a) built installer ke packaged payload ki versions gin kar report (WP $wp_version + Woo version), (b) creation test sirf packaged payload se (clean dir, repo tree baghair), (c) upgrade-path simulation. Owner ki manzori ke baad usool bane ga.
- **v2.6.3 build (2026-11-01, owner GO ke baad):** Phase A chaaro items implement hue — hub_wp_zip() helper (manifest pin warna highest version) teeno jagah, manifest-exact plugin extraction, .iss [InstallDelete] sirf superseded dist zips, bundle/manifest.json wahid source (SQLite 3.1.2 pin samet), tools/compat-check.php build-time guard (asal payload se versions; Woo ki header wali 7.0 shart bhi parhta hai). CI mein upgrade-path E2E (6h) + incompatible-pair failure test (6i) shamil. Antigravity route owner ne band ki — Windows verification sirf CI se.
- **v2.6.3 RELEASE RECORD (2026-11-01):** commits f2bf6e3 + 76c456b (doosra sirf CI test fix — 6g stall-test ki step-timeout hadd 20s se 60s; run #19 mein blueprint step ka thanda WP+Woo bootstrap 20s se nikal gaya tha aur test ghalat step par fail hua, cihang job log saboot; product code theek tha). Tag v2.6.3 kabhi publish na hone ki wajah se naye commit par منتقل hua — version ek hi raha. Windows Build #20 SUCCESS (15m 11s): guard COMPAT PASS, born-complete, 6g NEG 7/7, 6h upgrade-path 9/9, 6i incompatible-pair 6/6, WINDOWS SMOKE SAB PASS. Linux Smoke #17-#20 sab green. Release LIVE (Latest): TAMEERO_Setup_v2.6.3.exe, 162 MB, SHA256 d4e1436a…98af7 — poora download karke hash match bhi verify hua. Release gate (muse_release_gate) ke 7 sections pehli dafa poori tarah laagu hue.
- **dash fd spawn bug (2026-11-02, MCP Step 4 ki testing mein pakra gaya):** hub_spawn_worker ki FD-safai wali shell loop dash mein 'exec 10>&-' par fatal ho jati thi ("exec: 10: not found" — dash 2-digit fd redirection nahi manta, aur exec special builtin honay se shell hi mar jata tha). Natija: jis bulane wali process ke paas 11+ fds hon, us ke workers khamoshi se kabhi start hi nahi hote thay (koi error, koi log nahi). Fix: Linux spawn bash ke zariye; bash na ho to sirf 1-digit fds band karne wala mehfooz fallback. Saboot: proc_open-child context mein pehle spawn gayab, fix ke baad worker + job log + site creation mukammal.
- **MCP v2.7.0 BUILD RECORD (2026-11-02):** steps 1–7 mukammal — core (token auth, stdio+HTTP), READ tools 13, approval queue + Agent Activity panel, WRITE lifecycle 8, WRITE content 8, destructive 3 + type-confirm, packaging + Agent Connection settings UI. Kul 36 tools. Linux rig par har tool ka end-to-end test (approve → pump → asal natija verify): backup fresh-zip check, password reset PW-OK (wp_check_password), product/page/post CRUD asal liston se verify, theme/plugin off-on-wapas, site_delete/product_delete asal ghayab, update_apply bogus-source par saaf error + tree mehfooz, HTTP gate 200/401/403 + server zinda. Fixes is build mein: dash fd spawn bug, pump FIFO tartib, partial-edit empty-content, backup freshness check, action result JSON decode, token-once session reopen, MCP 403 double-close server crash. Suites: smoke-test 17/17, dashboard 76/77 (baseline).
- **Mustaqbil ki tajweez 1 — Dashboard AI Chat Panel (owner approved 2026-11-02, RECORD ONLY — abhi kaam NAHI):** MCP ship honay ke BAAD wali phase (v2.8-type) mein dashboard ke andar AI chat window: chat + images/data drag-drop attach; peeche wahi MCP backend (v2.7.0 wala) — chat sirf naya UI chehra hoga, doosra engine nahi; approval queue chat par bhi waisa hi lazim. **v2.7.0 mein shamil NAHI.** Tafseel: ~/workspace/your_files/tameero/TAMEERO-Future-Proposals.md
- **Mustaqbil ki tajweez 2 — Mobile ki tartib (owner approved 2026-11-02, RECORD ONLY):** Full Android server version **MUKAMMAL RADD** (Android background apps sulata hai; battery/storage/performance/customer-setup mozoon nahi). Manzoor tartib: (1) pehle phone ke browser mein poora dashboard (v2.7.0 ke LAN/Tailscale raste se, bina app); (2) baad mein halki companion Android app sirf remote control ke liye (Agent Activity, approvals, orders/status) — server hamesha PC par. Dono qadam bikri ke saboot ke baad wale phases mein. Tafseel: upar wali file.
- **Requirement — NAYA DASHBOARD (owner, 2026-11-02, RECORD ONLY — abhi kaam NAHI):** post-MCP bari version ke liye dashboard bilkul naya design hoga: (1) ek hi window/screen, **scroll bilkul nahi** — saare features/cards/buttons ek nazar mein (app-shell tarz: side navigation + compact panels; tafseel ke liye panel/modal, lambi page nahi); (2) look impressive lazmi — owner ka usool **"jo dikhega, wo bikega"** (yehi chehra demo/screenshots/marketing mein bikta hai); (3) AI chat panel (pehle se record-shuda Tajweez 1) **isi naye dashboard ke andar** design hoga — dono ek hi version ki headline cheezein. **v2.7.0 ka scope bilkul NAHI badal raha.** Bari ane par process: pehle 2–3 design mockups owner ko → owner ka pick → phir build; khud se design final NAHI hoga. Tafseel: ~/workspace/your_files/tameero/TAMEERO-Future-Proposals.md (Requirement 3).
- **Naye Dashboard ka BLUEPRINT record (owner structure + janu ka feature-audit, 2026-11-02, RECORD ONLY):** App Shell (Top Bar: New button, Settings button, Agent status, Search + Sidebar) — main window par scroll nahi. Pages: (1) HOME — ready projects ke cards (thumbnail/status/Open Site/Open Admin/Start-Stop), creating project ki Live Progress wahin, Failed card + View Log, Start All/Stop All; (2) NEW — ek taraf options (naam, blueprint cards: Store/Business/Blog/Portfolio/Restaurant/School/Agency/Blank, PHP version, demo content, Template/Zip import), dusri taraf **AI Chat window + attach button** (chat ki instruction se AI options bhare ya project banaye; images/data pehle attach), Create + Live Progress isi page par; (3) PROJECT — ek project ke saare buttons ek screen (Plugins, Database, Mail, Backup/Restore, Export, Deploy Package, Preview Link, PHP switch, Clone, Password reset, Log, Delete); (4) AI AGENT — Connection/token, Approval Queue, Agent Activity; (5) SETTINGS — language, Lite mode, defaults, auto-backup, updates, MCP access, sab ek page; (6) HEALTH — disk/ports/PHP/XAMPP scan ek nazar. Feature→jagah audit blueprint mein mukammal (koi feature gum nahi). Poora blueprint: ~/workspace/your_files/tameero/New-Dashboard-Blueprint.md. Tartib locked: post-MCP version ki headline; v2.7.0 scope NAHI badla; build se pehle 2–3 mockups owner ko.

## 2026-11-02 — v2.7.1 RELEASED: MCP ship + uninstall fix (v2.7.0 kabhi release nahi hua)

- v2.7.0 (commit 2132faf) CI Windows Build #21 mein aakhri uninstall check par fail tha: hub_mcp_actions_dir() har call par mkdir karta tha — dashboard khulte hi {app}\panel\.mcp-actions ban jata, uninstall ke baad panel folder zinda reh jata.
- v2.7.1 commit `81615df`, tag v2.7.1. Fix: directory ab sirf write (action save) par banta hai; .iss [UninstallDelete] mein .mcp-actions + .mcp-audit.log; smoke-windows.ps1 naya section 6j (read par folder na bane + MCP state ke sath uninstall saaf).
- CI: Windows Build #22 SUCCESS — "WINDOWS SMOKE SAB PASS" (6j 2/2, uninstall check PASS); Linux Smoke #24 PASS=17 FAIL=0.
- Release LIVE (Latest): TAMEERO_Setup_v2.7.1.exe, 169,978,543 bytes, SHA256 d23f625a28b9ad5294e00d0b80dbc7fe9467209d1d03c12f28992a6c3b8abd74 (poori download, hash khud verify).
- MCP: 36 tools, token auth (hash store), approval queue + 10-min expiry + type-confirm, Agent Activity + Connection card. Tailscale joint test ab bhi baqi hai (owner PC on hone par).
- Local dashboard suite note: is VM par heavy-op checks (import/create/deploy) timing windows + EPERM write-storms se fail hote rahe — job logs sabit karte hain ke workers asal mein mukammal hote hain. Release ka governing saboot Windows CI hai (fresh runner, full E2E incl. upgrade 6h).

## 2026-11-04 - v2.8.4: run #26 diagnosis ka diagnostics-layer fix (PASS par)

- Run #26 (v2.8.3) section 3 par gira: ek hi FAIL line se ye farq nahi hota tha ke state bani hi nahi ya sirf port tha (sathi probe fail). Artifact bhi nahi bana kyunke section 3 outer try se bahar tha - throw par finally (TEMP preserve) chala hi nahi.
- Fix (product code mein koi tabdeeli nahi): launcher trace file (har faisla + waqt), smoke outer try poore script par, section 3 ke teen alag layer checks + fail par trace dump job log mein, sathi probe 10s se 30s, workflow diagnostics mein trace copy, uninstall par trace saaf. Agle fail ki layer FAIL line + trace se foran nazar aayegi.

## 2026-11-04 - v2.8.5: run #27 diagnosis ka probe + collection fix (PASS par)

- Run #27 (v2.8.4) mein launcher sathi wait mein atka: trace mein STARTSERVER companion ke baad koi WAIT/SAVESTATE line nahi thi. Wajah: Trace sirf wait ke natijay par likhta tha, har probe attempt nahi - aur iteration-count wala wait slow-probe par 54-126s khinch sakta tha (400ms timeouts + 500ms sleep), jabke smoke poll fixed 60s hai. Is race se "state nahi mili" aya.
- Diagnostics artifact #27 mein bhi ghaib raha jabke try/finally pairing durust hai (try 67 - finally 539) aur paths identical hain - exact break runtime par hai aur khamosh copies ki wajah se job log mein saboot nahi bachta tha.
- Fix: probe wajah-code + attempt log (pehli 3, phir har 10th) + wall-clock wait (~32s max) + natijay mein attempts/last/elapsed; smoke poll 90 + launcher process dump + finally inventory + workspace fallback copy; workflow diagnostics inventory print + workspace copy + upload warn. Slow-probe ka asal trigger UNKNOWN rehta hai, lekin fix us nuqsan ko bandhta hai.

## 2026-11-04 - v2.8.6: run #28 ke baad evidence round (PASS par)

- Run #28 (v2.8.5) ne v2.8.5 ka kaam sabit kiya: hang khatam, 30s wait mukammal (elapsed 30.5s), honest port-only state, split check ne companion layer pakri. Baqi masla: companion 7827 ne aik bhi probe ka jawab nahi diya (mixed 12029 cannot-connect + 12002 timeout, 30.5s mein 15 attempts) - lekin process ki asal halat UNKNOWN, kyunki StartServer fire-and-forget hai (shell.Run, na PID na return code), trace intent + natija likhta hai process state nahi, aur smoke ka fail-path koi process snapshot nahi leta. Tehqeeq ka rasta: stop_hub.vbs ka KillPort netstat-PID technique pehle se rakhta hai, sirf band karne ke liye.
- Diagnostics: files workspace fallback mein mehfooz thin (inventory ne dikhaya) phir bhi upload "No files found" - dot-prefixed naam upload-artifact ke directory glob mein nahi aate (testable hypothesis: non-dot rename agle artifact se confirm hoga). v2.8.5 ki DIAG-PRESERVE MISSING jhooti alarm thi (inventory baghair-dot naam dhoondta tha, copies dot wali thin).
- Fix (smoke + workflow ONLY, launcher/product untouched, wait 30s hi, koi restart/retry nahi): Dump-CompanionEvidence dono companion-fail throws par throw se pehle (netstat :7827, php Id + StartTime, dashboard.log tail 30, companion .cmd content); finally inventory dot-naam; workflow non-dot copies + inventory + warn. Agle evidence se faisla: na php/listener to StartServer fix, php zinda + listener + jawab nahi to hang fix, ghair PID to collision, "Failed to listen" to bind fix. v2.8.6 claimed companion fix NAHI, evidence round hai.

## 2026-11-04 - v2.8.7: run #29 verdict ka StartServer fix (PASS par)

- Run #29 ka COMPANION-EVIDENCE faisla-kun tha: 7827 par koi listener nahi, php sirf main wala, log mein 7827 ki koi Listening/Failed line nahi - companion kabhi utha hi nahi (na hang, na collision, na bind-conflict). Kharabi ki jagah: StartServer main aur sathi DONON ka output aik hi `.dashboard.log` par `>>` karta tha; main ka cmd file khol kar rakhta hai (php -S zinda), sathi ka cmd doosri dafa kholne par sharing violation se php se pehle ruk jata hai, error hidden console mein zaya. Kharaj: quoting/CWD (main usi construction se chalta hai, CI path mein spaces nahi), port reserved (tab "Failed to listen" log hota), AV delay (30s mein aik Listening line to aati), .cmd write fail (StartServer mein error-handling nahi - error hota to launcher WAIT/SAVESTATE se pehle mar jata, woh lines mojood hain).
- Fix (sirf launch layer): StartServer ko logFile parameter - main `.dashboard.log`, sathi `.dashboard-companion.log`; har StartServer ki detail trace line (port + cmd + log); smoke evidence/finally/inventory + workflow diagnostics + .iss UninstallDelete mein companion log shamil. Hypothesis mazboot hai lekin direct observed nahi - fix hi us ka test hai: pass hua to confirm, fail hua to sathi ka apna log agla saboot dega. Ports, waits, probes, state format, stop_hub - sab waisa hi; product code untouched.

## 2026-11-04 - v2.8.8: run #30 ke import timeout par trace-only version (PASS par)

- Run #30 ne v2.8.7 ka launch fix confirm kiya (companion WAIT OK 0.5s, state durust, PHP started, section 3 + early probe PASS) aur naya fail 6m par dikhaya: import POST 7827 par 300s timeout, companion log ki aakhri line sirf "Accepted" (koi Closing nahi). Kharij, saboot se: session-lock (main log 72 accepted / 72 closed, koi stuck request nahi; POST dispatch line 249 par session close karta hai) aur worker ki susti (ciimport.job.log artifact mein nahi, jabke cismoke.job.log hai - worker kabhi start hi nahi hua; us ka log cmd redirect se shuru mein hi banta hai). Baqi segment: handler ke andar move_uploaded_file / shell_exec spawn.
- Move vs spawn pin nahi hua - aur import-via-companion sirf aik dafa chala hai (n=1; pehle runs 6m tak pahunche hi nahi), is liye "import spawn hamesha phansta hai" bhi saboot se zyada hoga. Spawn ka pipe-wait mechanism akela mujrim nahi ho sakta: agar shell_exec worker ke khatam hone tak rukta to worker chal raha hota aur us ka log mojood hota. Is liye v2.8.8 mein behavior fix nahi, sirf file-based trace: IMPORT-*/SPAWN-* markers, har line ke baad file band, warning suppress (JSON na kharab ho), trace file panel/.import-trace.log, collection smoke finally + workflow diagnostics (non-dot) + .iss UninstallDelete mein. Agle gate ki aakhri marker step pin kare gi, phir v2.8.9 mein targeted fix - decision tree proposal mein mukammal hai. Launch layer, timeouts, ports, spawn mechanism unchanged; product code ka behavior waisa hi.

## 2026-11-05 - v2.8.9: run #31 ke body-phase verdict par smoke-only version (PASS par)

- Run #31 ne v2.8.8 ke trace ka faisla diya: IMPORT-* markers ZERO (handler kabhi chala hi nahi, IMPORT-START bhi nahi), companion 16 accepted / 15 closed (ghair-band sirf import POST), section 3 PASS. Worker ka "STARTED line" wala quote ghalat tha (wo line smoke script mein hai hi nahi) - asal fail run #30 jaisa 300s POST timeout hai. Hang TCP accept aur handler entry ke darmiyan hai: request-body transfer/parsing phase. Move-vs-spawn tree is run par lagu nahi hota.
- Linux tajurba (proposal se pehle chalaya gaya): 300MB multipart loopback par ~3s, script ENTRY sirf body mukammal hone ke baad; post_max_size exceed par bhi script chalta hai (warning + khaali files). Is liye CI stall PHP-intrinsic nahi, environmental hai; limits-exceeded kharij (response deta, timeout nahi). CI limits StartServer -d flags se: 1024M/1024M/1024M; upload_tmp_dir unset (system TEMP). Export zip hub_export ke mutabiq ~24,000 files / 400MB+ (stored, -0) hota hai, lekin size smoke mein kabhi log nahi hua tha.
- Fix sirf smoke mein (product code untouched): 6f size log (CI-EXPORT-SIZE) + 6m tiny-import probe (ciprobe, 60s, redirect JSON) + big POST timeout 300 se 900s (test-only; field case 451MB par 15+ min) + timeout par evidence dump phir rethrow (failure mode wahi). Launch, handler, move/spawn, flags unchanged. Reviewers ki spawn recipes conditional rehti hain (agle trace mein SPAWN aakhri ho tab hi). Decision tree proposal mein mukammal hai: probe hang = endpoint level; probe pass + 900s pass = H1 slowness; probe pass + 900s timeout + bara size = H2 stall (tmp-watcher + Defender tehqeeq); size chhota + timeout = size theory khatam.

## 2026-11-05 - v2.8.10: run #32 ke H2 stall par watcher version (PASS par)

- Run #32 ka verdict H2 stall tha, lekin susti vs stall ka farq baqi tha kyunki timeout dump mein tmp file nahi mili thi. Us ki asal wajah ab sabit hai: PHP ki tmp file ka naam php<random> hota hai, koi .tmp extension nahi (Linux tajurba: tmp=/tmp/php9duv6v1h1m6heHLabDc) - is liye v2.8.9 ka php*.tmp pattern kabhi match hi nahi kar sakta tha. Ye pattern ki ghalti thi, aur v2.8.10 is ko pattern-agnostic baseline-diff watcher se durust karta hai (naam par nahi, baseline ke baad ke farq par). Slow-growth ka Linux observation mukammal nahi hua tha (test harness mein backgrounding ki kharabi) - watcher ka growth premise PHP tmp lifecycle behavior par hai, tajurbati tasdeeq gate run hi hogi.
- Sirf smoke changes (product untouched): Start-Job watcher big POST se pehle (baseline snapshot, 5s poll, timestamped NEW/GROW lines, TEMP watch log); POST ke natije ke baad job roko + log print karo (success aur catch dono paths); finally mein defensive cleanup + watch-log copies + inventory; workflow diagnostics mein direct + fallback copy (non-dot upload-watch.log) + DIAG-SOURCES watch flag. Watcher kabhi gate ko nahi torega: Start-Job fail ho to POST baghair watcher chalega (saboot v2.8.9 level par). Timeout dump mein Defender read-only reads (status/preference, SilentlyContinue) - koi Set-* cmdlet, koi exclusion, koi disable, kisi machine par koi setting change nahi. Owner ke teen sawal (freeze par exclusion experiment kis controlled machine par; slow growth par timeout vs chunked upload; no-tmp par curl.exe probe variant) proposal mein khule chhore hain, faisle nahi. Watch log TEMP mein hai is liye .iss mein entry nahi. Decision tree proposal mein mukammal hai.

## 2026-11-05 - v2.8.11: run #33 ke NO-NEW-TMP par curl client probe (PASS par)

- Run #33 ka verdict NO-NEW-TMP tha: watcher ne 900s mein koi tmp file nahi dekhi, companion par abort ke waqt "Unexpected EOF" (server body ka intezar kar raha tha), Defender RTP band (scan theory kamzor). Bacha sawal client vs server hai, is liye v2.8.11 mein IWR big POST gate ki asal test hi rahegi aur us ke timeout par curl.exe probe sequential chalega (replacement nahi - IWR baseline 4 runs se maujood hai, aur gate ki semantics badalna manzoor nahi). Probe design: wahi URL/fields/XHR header/900s, Expect band (wire behavior IWR jaisi), PHPSESSID cookie $sess se (na mile to warning + baghair cookie), body temp file + exit code se faisla, curl na mile to skip + purana throw. Curl pass ho to import aage waisa hi chalega; curl-green = GREEN WITH FINDING (IWR-client failure + browser-path caveat report mein numaya, ship par owner aagahi lazmi - field 451MB browser hang alag sawal hai).
- Watcher fix: FullName self-exclusion run #33 mein fail hua tha (path-form ka farq, wajah reproduce nahi ho saki) - is liye leaf NAME se exclude (TAMEERO-*; is se curl body/err files bhi curve se bahar) + $base pehli sight par update (NEW aik dafa, phir GROW). Sirf smoke changes; tiny probe IWR hi, 900s, flags, ports, Defender read-only reads unchanged. Curl .Source se chalega (PowerShell curl alias se bachne ke liye). Decision tree proposal mein mukammal hai.

## 2026-11-05 - v2.8.12: run #34 ke SERVER-SIDE par threshold probes (PASS par)

- Run #34 ka verdict SERVER-SIDE tha (IWR + curl dono timeout, 0 bytes received, koi tmp nahi) - client theory khatam. Teen sawalon mein se (a) bheje gaye bytes aur (b) size threshold is round mein hain; (c) php -S vs environment jaan boojh kar agli version/owner-faisle par rakha gaya hai (step-by-step usool). Padded-zip mechanics Linux par verify hain (25MB, 1.15s, size_upload poora, tmp bani) - is liye threshold probes (25/100/200MB, curl, --max-time 120, -w uploaded+time, early exit par bracket) evidence-only hain, gate ka faisla big POST par hi rahega. Watcher teeno windows ko aik log mein cover karega (probes se pehle start). Big curl par -w se sawal (a) ka jawab milega. Stall parhna: sirf exit code nahi - rate (uploaded/time) + watcher GROW pattern lazmi (120s cap par slow path bhi exit 28 de sakta hai). Sirf smoke changes; tiny probe IWR hi, 900s, flags, ports, Defender read-only reads unchanged. Decision tree proposal mein mukammal hai.

## 2026-11-05 - v2.8.13: run #35 GREEN WITH FINDING ke baad .iss version-inventory fix (owner GO, Option 2)

- Run #35 (tag v2.8.12, commit f58a907) Windows gate pehli dafa poora pass hua - lekin GREEN WITH FINDING: bari 420MB import IWR client se fail, curl probe se mukammal (841.1s, size_upload 440,738,536 bytes). Curl-green server + handler + worker verify karta hai, browser-upload path NAHI (field 451MB browser hang alag sawal hai) - caveat M6 report mein numaya rahega. Threshold probes ne koi size bracket nahi diya, sirf duration/slowness pattern.
- Gate ke baad ki apni verification mein naya nuqta pakra gaya: installer-windows/webdevhub.iss ka MyAppVersion v2.8.0 se kabhi bump hi nahi hua tha (2.7.1 par reh gaya), is liye workflow ne v2.8.12 ki Release TAMEERO_Setup_v2.7.1.exe naam ke asset se banayi - aik hi naam, do mukhtalif contents, record mein mojood asal v2.7.1 file se takrao. Owner ka faisla: misnamed installer ship NAHI hoga; v2.8.12 ka release record HOLD rahega, tag/Release audit record ke tor par waisi hi rahegi.
- Fix v2.8.13 mein: .iss MyAppVersion 2.8.13 (define se bannay wale OutputBaseFilename/AppVersion/VersionInfoVersion khud update; AppId/install folder unchanged). Sath mustaqil safeguard: windows-build.yml ke Compatibility guard step mein .iss <-> VERSION match check (fail-closed) taake aayenda koi version bump .iss ko peeche na chhor sake - mismatch par build compile se pehle hi FAIL, Release bane gi hi nahi. Usool record: har release version par .iss bump lazmi hai, aur ye ab insaan ki yaad par nahi, gate par hai.

## 2026-11-05 - v2.8.14: run #36 ke receive-path stall par chunked import (owner GO: complete karna hai)

- Run #36 (tag v2.8.13) FAIL: IWR timeout + curl exit 28; bracket (25MB, 100MB] + send-side progressive stall + php -S read-path stall. VERSION-GUARD PASS aur sahi naam ka installer bana - v2.8.13 ka fix verify ho gaya. Cross-run nuqta: wahi 100MB run #35 mein 50.68s mein pass, run #36 mein 120s cap par fail - fixed threshold nahi, throughput variance + read-path.
- Owner ka faisla: TAMEERO COMPLETE karna hai - park/delete reject. Round-3 mashwara (ChatGPT + Gemini) ittifaq: layer = Windows par php -S ki request-body receive path; #1 diagnostic Python raw-TCP receiver control; ChatGPT ki #1 product sifarish chunked uploads; Gemini ki #1 file-path handoff hamare browser UI par nahi beth ti (browser asal path nahi deta).
- Fix v2.8.14 mein: (1) evidence-only A/B control smoke mein (alternating x2, recv+hash+discard, PHP CPU/mem polling, gate decide nahi karega) - ye aakhri diagnostic hai; (2) product fix sirf chunked uploads (8 MiB, raw octet-stream, UUID, .parts, per-chunk SHA-256, sequential assembly, worker unchanged). CORS/preflight is ki lazmi shart hai (maujooda companion XHR mein CORS tha hi nahi - browser mein preflight fail par fallback form.submit() chalta tha, is liye companion XHR asal mein kabhi chala hi nahi). Legacy multipart import hata di gayi hai; threshold probes retire (A/B un ka sawal jawab dega). Agar chunked bhi gate fail ho to agla qadam dedicated receiver hoga - owner ko bata kar, alag version mein.

## 2026-11-05 - v2.8.14 RELEASED: chunked import (run #36 stall ka product fix; diagnosis chapter band)

- v2.8.14 commit `83ba103`, tag v2.8.14. Fix: chunked uploads (8 MiB raw octet-stream chunks, sequential, UUID upload_id, per-chunk SHA-256, resume, sequential assembly + size/hash verify + atomic rename, worker unchanged) + Python A/B raw-receiver control (smoke-only) + CORS/preflight (sirf chunk requests, sirf 127.0.0.1/localhost). Legacy multipart import hata di gayi hai; threshold probes retire.
- CI: Windows Build run #37 (42015554341) SUCCESS - "WINDOWS SMOKE SAB PASS" (build 31m 4s, Install smoke 29m 20s, koi FAIL line nahi). VERSION-GUARD PASS (2.8.14 == 2.8.14). A/B: Python dono attempts poora 420.3 MB <1s mein (SHA-256 c94867d6...), PHP dono attempts 300s cap par exit 28 (~216-218 MB) - PHP-specific diagnosis CONFIRMED, diagnosis ka chapter band. Chunked import: 54 CHUNK-UPLOAD lines (53 ciimport + 1 ciprobe), finalize SHA-256 match PASS. 6k Dukaan exactly 8 demo products; 6l MCP no-token 401, toggle ON par 8971 listening, OFF par band; 6m import front 200 / stop / delete PASS.
- Linux: smoke-test PASS=17 FAIL=0; dashboard suite 76/77 (sirf pehle se mojood Deploy-package flake).
- Release LIVE (Latest): TAMEERO_Setup_v2.8.14.exe, 177,621,838 bytes, SHA256 3299a1c675e0a4582e66bda8891b7cc7a33b2129133bae2f0875da056ba1c81a (poori download, hash khud verify). v2.8.12 ka misnamed Release (TAMEERO_Setup_v2.7.1.exe naam se) HOLD par hi rahega, record mein nahi.
- Note: run page par 1 warning annotation hai - Node.js 20 deprecated (actions Node 24 par force hote hain) - build job ki warning hai, smoke step ki nahi, ship ko nahi rokti; agle workflow touch par actions update karne hain.
- Pending-fixes #4 (MCP spawn) BAND - v2.8.14 mein fix + CI mein verify. Baqi khula: #2 deploy fix-url Wordfence BLOB crash; Tailscale joint test; code-signing; repo rename; Qasmi sync.

## 2026-11-05 - v2.8.15: AI chat gateway key (field diagnosis par owner-approved fix)

- Field (v2.8.14, owner PC): AI chat "hy" par "AI gateway se ajeeb jawab aya. (Missing API key)". Diagnosis (code + local stub test se sabit): chat.php gateway ko Authorization header bhejta hi nahi tha, key wali koi setting thi hi nahi; Model khaali par model field omitted hota hai; error text 9Router ke jawab ka error.message hai jo panel relay karta hai (stub par exact string reproduce). Owner ka Model-wala config test FAIL raha - wajah pakki missing key.
- Owner PASS (2026-11-05 ~20:55 PKT) sirf is scope par: (1) Settings mein "Gateway Key" password field (chat_gateway_key; key page par wapas print nahi hoti, khaali field = purani key mehfooz); (2) hub_chat_gateway_round() mein key set ho to Authorization: Bearer header, khaali ho to header nahi (purana bartao); (3) gateway error par hint line. Smoke mein stub-gateway header-capture check (6n) lazmi.
- Key ki hifazat: owner apni 9Router key khud dalega; key kabhi chat/log/record mein nahi ayegi.

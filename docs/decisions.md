# WebDevHub — Decision Log

Format: muse-decision-log skill. Append-only — sudhaar nayi entry se hoga, purani entry dobara nahi likhi jayegi.
Query par entry quote hogi, behes dobara nahi hogi.

---

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

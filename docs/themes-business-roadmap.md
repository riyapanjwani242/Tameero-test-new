# Themes/Plugins Business — Master Brief (owner, 2026-10-30)

Bari tasveer: StyloThemes jaisa apna WordPress themes/plugins business —
Urdu RTL niche, apni site se direct sale, bundles, free install/support.
WebDevHub (tool) + apni themes library + apni plugins aik doosre ko
bechte hain. Earnings/partnership model gulambarkhan1 ke sath tay hai.

## Aaj ke faisle
1. Scratch theme plan KHATAM — GPL base fork karo, apni tabdeeli/features
   charhao, apne naam se product. (Pages pehle se maloom, code tested.)
2. Nexora Shop ($59) sirf LEVEL ka benchmark hai — clone nahi hoga
   (files paywall; nulled rasta hamesha band; naam un ka trademark).
   Us ka dhacha seekhna hai (hero + category carousel + deals row +
   promo banners + kai homepage layouts), copy nahi.
3. Base sirf WordPress.org ki free GPL themes. ThemeForest / Woo
   marketplace / Etsy licenses = sirf istemal, resale product mana.
4. Purple (Automattic, free FSE, koi Pro gate nahi) FINAL base —
   Blocksy is liye reh gaya ke us ki achi Woo cheezein Pro mein hain.

## Roadmap (har marhala owner ki manzoori ke baad hi)
- **Marhala 1 — HO GAYA (2026-10-30):** Purple-fork "Dukaan" test build
  v0.1.0-test `themes/dukaan-fse/` — rebrand, maroon/gold, Nastaliq RTL,
  announcement strip, homepage pattern, WhatsApp dynamic block
  (config: Settings → Dukaan), apni stack par test site + screenshots
  `themes/dukaan-fse/test-shots/`. Bari finding: Purple asal mein
  WP 7.1 + Woo 11.1 mangta hai, bundle WP 6.8.3 + Woo 10.4 par chal
  to gaya lekin supported range se bahar — bundle decision owner ka.
  Owner ke faisle ka intezar.
- **Marhala 2:** Dukaan v1 mukammal theme (mazeed layouts/patterns,
  header/footer options, shop/cart/checkout polish, settings +
  Roman Urdu guide) — tafseel alag brief mein ayegi.
- **Marhala 3:** WebDevHub integration (v2.6) — theme bundle/themes
  mein, panel mein site banate waqt theme choose option, mojooda
  sites par theme lagana (ZIP manual rasta bhi rahega). Audited
  build: proposal → owner pick → EK build → test.
- **Marhala 4:** Plugins (tartib owner baad mein chune ga):
  (1) WhatsApp Order plugin (doosri themes ke liye),
  (2) Advance Payment Verification (screenshot upload + admin approve),
  (3) Low Stock Alert + Smart Order Manager,
  (4) Urdu PDF Invoice,
  (5) AI Product Description,
  (6) Profit & Loss Dashboard.
- **Marhala 5 (door):** apni themes ki dukaan wali site (storefront).

## Mustaqbil ki direction — Agent-connection (owner faisla 2026-10-30)
Marhala 3 ke BAAD: WebDevHub ke liye chhota agent-interface —
**soorat MCP server hi FINAL hai** (owner faisla: browser-clicks aur
sirf-CLI wale tareeqe interface ke tor par mustarad; buniyad WP-CLI +
hub ke programmatic hisse hi rahenge, lekin pehchan MCP ki hogi) —
tools: site banao, sites list, theme
lagao/badlo, plugin on/off, products daalo, backup, export/deploy.
Buniyad: WP-CLI + hamare headless site-creation hisse (setup script /
wdh.php CLI) — dashboard clicks wala tareeqa nahi.
Security hadd: sirf local machine / private network; internet par
kabhi khula nahi; har destructive/live action se pehle owner ki
manzoori lazmi.
**Design asool aaj se laagu:** ahem functionality sirf dashboard
buttons ke peeche band na ho — peeche programmatic/CLI rasta saaf
rakho taake agent-interface usi par ban sake. (Maujooda architecture
pehle se aisi hi hai: wdh.php CLI engine hai, panel us ka shell.)
Ye abhi ka kaam NAHI hai — sirf direction mehfooz hai.

## Sab se aakhri manzil — HOSTED SERVICE version (v3 ki simt)
Owner ki tay shuda soch: mustaqbil mein WebDevHub ki hosted soorat
hogi — software hamare apne server par, customers (mobile/iPhone/PC)
login karke browser se sites banayenge/chalayenge. Software customer
ke paas jayega hi nahi (piracy/license khatam, updates aik jagah se,
mobile+Apple masla hal).
Tartib: Marhala 1 → 2 → 3 → MCP agent-interface → service de kar
paying customers ka saboot → PHIR hosted build. Abhi kaam NAHI.
**Design door-andeshi (Marhala 3 + MCP se laagu):** kal yehi system
kai users ko aik sath serve kare ga — user/site ka data saaf alag
rakhne wali soch; koi aisi bandish na lagana jo sirf single-user/local
farz karti ho.
**Zimmedari ka wazan:** hosted ke sath server kharcha, 24/7 uptime,
backups, security hamari zimmedari — owner ne aankhein khol kar
faisla liya hai; ye wazan har design faisle mein yaad rahe.

## Usool (har marhale par)
- Proposal pehle, build baad mein; aik waqt mein aik build; "ho gaya"
  test + saboot (screenshots/logs) ke baghair nahi.
- GPL tameez: base ka credit README/style.css mein; kisi ka
  trademarked naam istemal nahi.
- bundle/, panel/, installer ko owner ki wazeh manzoori ke baghair
  nahi chherna — Marhala 3 se pehle bilkul nahi.
- Reports isi (WebDevHub) chat mein; owner faisle main chat se deta
  hai, janu pohchata hai. Scope se bahir ki cheez ho to khud farz
  karne ki bajaye report mein poochna.

# MCP Proposal — TAMEERO Agent Connection (v1)

**Status:** PROPOSAL — owner ki approval ke baghair build nahi hoga.
**Date:** 2026-10-31 · **Phase:** nayi tartib ka pehla phase (MCP pehle, Dukaan v1 baad mein)

## 1. Maqsad + usool

- TAMEERO ke andar ek **MCP server** bane ga jo **khule MCP standard** ke
  mutabiq hoga — kisi ek AI se lock NAHI. Muse, ChatGPT, Gemini, Cursor —
  jo client MCP support kare, jud sakega. **Pehla test Muse (janu) se.**
- MCP ek **interface** hai, nayi business logic nahi: andar wahi machinery
  chalegi jo aaj dashboard chalata hai (`panel/bin/wdh.php` worker +
  `panel/lib/hub.php` + `installer-linux/` tools). Jo kaam dashboard se
  hota hai, wahi agent se hoga — ek hi sach, do darwaze.
- Shartein (owner ke pehle faisle): **sirf local/private network**,
  **har live/write action se pehle owner approval**.

## 2. Architecture

- **Zubaan/runtime:** PHP — bundled runtime pehle se har install mein hai;
  user ke PC par koi naya dependency (Node/Python) nahi lagega.
- **Process:** dashboard ki tarah ek managed process (`panel/bin/mcp.php`),
  dashboard se start/stop/status; apni alag port (default **8971**,
  dashboard 8080 se takrao nahi).
- **Protocol:** MCP JSON-RPC — `initialize`, `tools/list`, `tools/call`,
  `ping`. Spec ke standard error codes; har tool ka naam + description +
  input schema (JSON Schema) `tools/list` se milega, taake koi bhi MCP
  client khud samajh jaye.
- **Transports (dono):**
  1. **Streamable HTTP** — default bind sirf `127.0.0.1` par. LAN ya
     Tailscale interface par bind sirf dashboard setting se, owner ki
     marzi se.
  2. **stdio adapter** — jo AI client owner ke apne PC par chale (Cursor /
     Muse waghera), wo process ke tor par server chala sakega;
     network bilkul nahi khulta.
- **Andar ki wiring:** har tool call worker ko shell se chalata hai
  (wahi tareeqa jo dashboard use karta hai — alag process, log file ke
  sath). WordPress-level kaam (products/pages/posts) ke liye ek naya
  site tool `tools/wc-tool.php` bane ga — `setup-site.php` /
  `plugin-toggle.php` ki tarz par: site bootstrap karke WooCommerce/WP
  API se kaam, JSON output.

## 3. Tools ki poori list

### READ — token se seedha, approval nahi (kuch badaltay nahi)
1. `sites_list` — saari sites: naam, blueprint, port, chal rahi hai ya band, size, aakhri run
2. `site_status` — ek site ki tafseel (meta, URL, admin user, WP/Woo versions)
3. `site_health` — health report: PHP versions, XAMPP scan, bundle version, site verify (options/pages/plugins/woo state)
4. `site_logs` — site server log + aakhri create/job log ki lines (error diagnosis ke liye)
5. `products_list` — site ke products (naam, qeemat, sale price, stock, SKU, status)
6. `product_get` — ek product ki mukammal tafseel (description, categories, images)
7. `pages_list` — site ke pages (title, slug, status)
8. `posts_list` — blog posts (title, status, date)
9. `plugins_list` — plugins + active/inactive halat
10. `themes_list` — installed themes + active theme
11. `backups_list` — site ke backups/exports ki list (naam, size, date)
12. `updates_check` — TAMEERO update available hai ya nahi (sirf check)
13. `server_info` — MCP server + TAMEERO version, uptime, bound address

### WRITE — har ek par owner approval lazmi (neeche wala flow)
14. `site_create` — nayi site (naam, blueprint, demo content on/off)
15. `site_start` — site chalana
16. `site_stop` — site band karna
17. `site_clone` — site ki copy naye naam se
18. `site_template_create` — maujooda site ko template banana / template se site banana
19. `product_add` — product banana: naam, qeemat, sale price, category, description, stock, SKU, aur **images PC ke folder se (paths)**
20. `product_edit` — product ki koi bhi field badalna (qeemat, stock, naam, description, status)
21. `page_create` / `page_edit` — page banana ya badalna (title, content, status)
22. `post_create` / `post_edit` — post banana ya badalna
23. `plugin_set` — plugin activate/deactivate
24. `theme_activate` — theme badalna
25. `site_backup` — abhi backup/export zip banana
26. `site_import` — zip se site wapas lana
27. `admin_password_reset` — site ke admin ka password reset
28. `deploy_package` — live hosting ke liye deploy package (SQL + checklist)
29. `site_php_switch` — site ki PHP version line badalna

### DESTRUCTIVE — approval + dashboard par doosri tasdeeq (type-confirm)
30. `site_delete` — site delete (data jata hai — sab se sakht gate)
31. `product_delete` — product delete
32. `tameero_update_apply` — TAMEERO ka update lagana

### Approval machinery (system tools)
33. `action_status` — apni pending/approved/rejected action ki halat + natija (agent is se poll karega)
34. `action_cancel` — apni pending request wapas lena

**Phase 1 mein NAHI (jaan boojh kar):** public preview link (cloudflared
tunnel) MCP se kholna — internet-facing hai; pehle local system prove ho,
phir alag faisla. Local HTTPS start/stop bhi phase 1 se bahir hai.

## 4. Security model

- **Network:** server default sirf `127.0.0.1` sunta hai. LAN/Tailscale
  bind ek dashboard setting hai (default OFF) — public internet par
  kabhi nahi.
- **Authentication:** har HTTP request par **Bearer token** lazmi. Token
  dashboard → Settings → Agent Connection se generate hota hai (ek dafa
  dikhta hai, settings file mein hash ki surat mein rehta hai), wahan se
  **revoke/regenerate** bhi hota hai. Token ghalat/missing = 401, koi
  tool nahi chalta.
- **Approval gate (exact flow):**
  1. Agent write tool call karta hai → server foran kuch NAHI chalata;
     ek **pending action** banta hai aur jawab milta hai:
     `{status: "pending_approval", action_id, expires_in: 600}`.
  2. Dashboard par foran ek **approval card** a jata hai (aur dashboard
     khula ho to banner): kya action hai, kis site par, tafseeli
     parameters, **kis client ne manga** (client ka naam initialize se)
     + **[Approve] [Reject]** buttons.
  3. Owner Approve kare to worker chalta hai; natija action mein save
     hota hai. Agent `action_status` poll karke natija le leta hai.
  4. Reject ho ya **10 minute** guzar jayein to action expire/reject —
     kuch nahi chalta.
  5. Destructive tools (30–32) par dashboard Approve ke sath site ka
     naam type karna parega (galti se tap na ho).
- **Audit:** har tool call (read bhi) ek audit log mein jata hai —
  waqt, client, tool, parameters ka khulasa, faisla (auto/approved/
  rejected), natija. Dashboard mein "Agent activity" view se owner
  sab dekh sakega. Log rotate hoga (size limit).
- **Rate/safety:** ek waqt mein ek hi write action per site (dashboard
  ke maujooda worker locks ke sath); ghalat schema par tool call reject,
  koi side effect nahi.

### Agent Activity — Live panel (owner faisla 2026-10-31)

Upar wale audit log + approval queue ki **live dashboard surat** — ye
usi approval flow (faisla #2) ka chehra hai, alag system nahi:

- Dashboard mein ek **"Agent Activity — Live"** panel: har entry mein
  **konsa client** (Claude/ChatGPT/Cursor…), **konsa tool/action**,
  **kis site par**, waqt, aur **maujooda halat** (pending / approved /
  rejected / running / done / failed).
- **Pending actions par Approve/Reject wahi panel se** — owner ko
  kahin aur nahi jana; destructive actions par wahi type-confirm
  gate lage ga jo approval card par hai.
- Panel khula ho to khud refresh hota rahe (dashboard ke maujooda
  auto-refresh ki tarz par) — naya action ate hi foran nazar aye.
- Neeche wahi audit history bhi — aaj ke saare agent kaam ek nazar
  mein, taake owner ko hamesha pata ho agent ne kya kiya, kab kiya,
  aur kis ki ijazat se.

## 5. Pehla test — topology ka sawal (owner faisla, neeche dekho)

Janu (Muse) is waqt owner ke PC par nahi, ek alag machine par hai. Is
liye pehle test ke do raste hain — server dono support karega, lekin
pehla test kis se ho ye owner chune ga:
- **Rasta A — Local client:** owner ke PC par hi koi MCP client
  (Cursor/Muse) → stdio ya localhost HTTP. Bilkul local, sab
  se mehfooz, lekin janu seedha test nahi kar sakta (owner ke screenshots
  se hoga).
- **Rasta B — Tailscale:** owner ka PC aur janu ki machine ek private
  Tailscale network par; MCP server Tailscale interface par bind hota
  hai. Janu seedha end-to-end test kar sakta hai; network phir bhi
  private hai, public internet par kuch nahi khulta. (Tailscale free
  hai; dono taraf install lagega.)

## 6. Build ki tartib (approval ke baad, alag brief ayegi)

1. **MCP core:** JSON-RPC server (HTTP + stdio), token auth, tools/list
   machinery, audit log — koi tool abhi nahi, sirf `server_info`.
2. **READ tools** (1–13) + `wc-tool.php` ka read hissa.
3. **Approval queue:** dashboard card + action store + `action_status`.
4. **WRITE tools — site lifecycle** (14–18, 25–29) worker ke upar.
5. **WRITE tools — content** (19–24) `wc-tool.php` ke write hisse se.
6. **Destructive tools** (30–32) + type-confirm gate.
7. **Packaging:** installer mein server shamil, dashboard mein
   "Agent Connection" settings (token, bind, on/off), docs.

## 7. Testing plan

- **Owner ki standing hidayat (2026-10-31) lagu hai:** "Sara kaam
  karke apne pas run karke, test karke, check karke do" — MCP build
  ka har hissa pehle **isi machine (Linux) par** asal MCP client se
  run + test + verify hoga, saboot (test ginati, logs, screenshots)
  ke sath; **untested kaam owner tak nahi jayega.** Owner ke PC par
  sirf Windows-specific aakhri tasdeeq (installer mein server ka
  bartao, Windows process/token handling) aur topology trial rahe
  ga — asal flow pehle yahan sabit hoga.
- **Step-by-step, Linux pehle (hamari server par):** har step ke baad
  throwaway sites par test — Muse client (janu khud, ek asal MCP
  client config se) se end-to-end: list → create (approval flow ke
  sath) → product add (tasveer ke sath) → start/stop → backup → delete.
- **Negative tests:** baghair token call (401), reject wala flow,
  expire wala flow, ghalat schema, doosri site par galti se action —
  sab ka saboot.
- **Windows:** CI mein MCP server ka smoke (server start, tools/list,
  ek read tool, ek approved write tool) — Linux + Windows dono green
  ke baghair release nahi.
- **Owner PC trial:** pehle READ-only mode mein owner ki asal sites
  (sahil-bedding, sahil-bedding-store) par, phir write tools ek site
  par, owner ki nazar ke samne.

## 8. Owner ko pehle se decide karna hai

1. **Pehla test kis raste se ho** — A (PC par local client) ya B
   (Tailscale se janu seedha)? (Sifarish: B — janu har cheez khud
   verify kar sakega; A mein har test owner ke waqt par hoga.)
2. **Approval ka yahi flow theek hai?** Dashboard queue + 10-minute
   expire + destructive par type-confirm. (Sifarish: haan — owner
   pehle se dashboard-first kaam karta hai.)
3. **Content write tools (products/pages) isi phase mein hon** ya
   pehle sirf site lifecycle prove karke baad mein? (Sifarish: isi
   phase mein, lekin build tartib mein step 5 par — lifecycle pehle
   prove ho.)
4. **Token ki hifazat:** token dashboard se ek dafa dikhe aur copy ho —
   kafi hai, ya token ke sath IP allowlist bhi chahiye? (Sifarish:
   phase 1 mein token kafi hai; allowlist baad mein bhi lag sakta hai.)

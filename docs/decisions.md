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

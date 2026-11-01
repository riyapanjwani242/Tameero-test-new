# Antigravity Windows Check — TAMEERO payload + upgrade-path verification

**Ye doc kiske liye hai:** Owner ye checklist apne PC par Antigravity ko dega.
Antigravity ka kaam sirf **parhna, test karna aur report karna** hai — code
badalna, sites delete karna ya settings change karna is ka kaam NAHI hai.

**Pas-e-manzar (30 second):** TAMEERO ek local WordPress environment hai
(ek click mein WordPress sites banti hain). Ek bug mila tha: upgraded PC par
purani WordPress zip (6.8.3) installed folder mein reh gayi thi, aur program
usi purani zip se site banata raha — jabke naya WooCommerce use qabool nahi
karta tha. Nayi build (v2.6.3) is ka fix layegi. Ye checklist us fix ki
Windows-side tasdeeq ke liye hai: **packaged payload asal mein kya hai, aur
upgrade ke baad asal mein kya hota hai.**

Install folder (default): `%LOCALAPPDATA%\Programs\WebDevHub`
Dashboard: browser mein `http://127.0.0.1:7777`

---

## Hisse A — Installed payload ke versions parhna

1. **Bundle mein WP ki kaun kaun si zips hain:**
   Folder kholo: `%LOCALAPPDATA%\Programs\WebDevHub\bundle\wordpress\dist\`
   Wahan pari **har** `wordpress-*.zip` file ka naam report mein likho.
   (Ek se zyada zip = purani leftover mojood hai — ye khud ek finding hai.)
2. **Kisi bani hui site ka asal WP version:**
   File kholo: `%LOCALAPPDATA%\Programs\WebDevHub\sites\<site-naam>\wp-includes\version.php`
   Us mein `$wp_version = '...';` wali line lafz-ba-lafz copy karo.
3. **WooCommerce ka version + us ki shart:**
   - File: `sites\<site-naam>\wp-content\plugins\woocommerce\woocommerce.php`
     ke shuru mein `Version:` wali line.
   - File: `sites\<site-naam>\wp-content\plugins\woocommerce\readme.txt`
     ke shuru mein `Requires at least:` wali line.
   Dono lafz-ba-lafz copy karo. (Qaida: WP version is "Requires at least"
   se bara ya barabar hona chahiye — warna jora incompatible hai.)
4. **PHP runtime version:** install folder ke andar `php.exe` dhoondho
   (aksar `runtime\` ke andar hota hai) aur us ka version report karo
   (`php.exe -v` ki pehli line).

## Hisse B — Upgrade-path scenario (sab se ahem test)

Order bilkul yehi rakhna hai — beech mein uninstall NAHI karna:

1. **Pehle maujooda shipped release (v2.6.2)** install karo (agar pehle se
   installed hai to ye step ho chuka samjho). Hisse A wala dist-zips wala
   check abhi kar ke note karo.
2. Dashboard se ek **Store** site banao, naam `agtest-pehla` rakho.
   Demo content ON. Ban jaye to note karo; fail ho to card ki photo +
   View Log ki aakhri lines report mein dalo.
3. Ab **nayi build (v2.6.3 ka installer jo owner dega)** usi ke upar
   install karo — in-place upgrade, wahi folder, uninstall baghair.
4. Hisse A wala dist-zips check **dobara** karo: ab kaun si zips hain?
   (v2.6.3 ke baad dist mein sirf mojooda version ki zip rehni chahiye.)
5. Dashboard kholo — purani sites (jin mein `agtest-pehla` aur owner ki
   asal sites bhi hain) waisi hi nazar ani chahiyein, koi site ghayab
   ya tooti hui nahi honi chahiye. `agtest-pehla` khol kar dekho ke
   site chalti hai.
6. Ab ek **nayi Store** site banao: naam `agtest-doosra`, demo content ON.
   Is par Hisse C ke saare asserts lagao.

## Hisse C — Born-complete asserts (agtest-doosra par)

Har assert ka natija PASS ya FAIL likho, saboot (screenshot ya file ki
line) ke sath:

- [ ] Creation mukammal hui — dashboard par normal card aya (Failed ya
      hamesha wala "Creating..." NAHI)
- [ ] Site ke andar ka WP version (Hisse A-2 wala tareeqa) = sab se nayi
      zip wala version hai
- [ ] WooCommerce ACTIVE hai (site ke wp-admin → Plugins mein
      WooCommerce "Active" nazar aye)
- [ ] Ye pages mojood hain: Shop, Cart, Checkout, My Account
- [ ] Demo products theek **8** hain
- [ ] Currency PKR hai
- [ ] Store LIVE hai — WooCommerce ki "Coming Soon" mode OFF hai
      (wp-admin → WooCommerce → Settings → Site visibility)
- [ ] `/shop/` page khulta hai aur products nazar ate hain
- [ ] Kisi ek product ka page khulta hai

## Hisse D — Report format

Report in teen hison mein ho, har daawe ke aage us ka darja:

- **VERIFIED** — jo apni ankhon se dekha/parha; saboot sath (screenshot,
  file path + copy ki hui line).
- **INFERENCE** — jo dekhe hue se andaza lagaya; wajah likho.
- **UNKNOWN** — jo check nahi ho saka; wajah likho.

Har assert (Hisse C) ki ek alag line: `PASS/FAIL — <assert> — <saboot>`.
Versions hamesha **lafz-ba-lafz** likho (masalan `$wp_version = '7.1.4';`),
andaze se number mat banao. Jo step na ho sake wahan ruk kar FAIL/UNKNOWN
likho — khamoshi se skip mat karo.

## Hifazati hudood

- Owner ki asal sites (`sahil-bedding` waghera) ko delete/edit NAHI karna.
  Sirf `agtest-` se shuru hone wali test sites banana/delete karna hai.
- Koi setting, account ya payment wali cheez change NAHI karni.
- Code files edit NAHI karni — ye sirf verification hai. Masla mile to
  report mein likho, theek karne ki koshish mat karo.

# WebDevHub — Feature Roadmap (owner approved 2026-10-28)

Owner ne 25 tajaweez ki mukammal list manzoor ki hai. Kaam hamesha
ki tarah batches mein hoga: EK build, phir smoke test, phir agla
batch. Tartib dependencies aur rozana fayde par hai.

## Build 1 (v2.1) — Roz ka kaam ✅ HO GAYA (2026-10-28)
1. Site card par homepage ki thumbnail photo
3. Sab sites ek sath Start / Stop
4. Har card par site size (MB) + aakhri dafa kab chali
5. Projects ki search
7. Dashboard se admin password reset
21. Health check page (disk, ports, PHP, status ek jagah)
Verification: Linux bundle smoke 17/17 + dashboard smoke 25/25
(dono fresh); Windows CI smoke mein bhi v2.1 checks shamil.

## Build 2 (v2.2) — Copy engine ✅ HO GAYA (2026-10-28)
2. Site clone (ek click mein copy)
11. Apni template banana (site → template → nayi sites)
13. Zip se site import (backup / client ki site)
14. Urdu demo content pack
20. Khudkar hafta-war backup (purani copies ki safai ke sath)
Verification: Linux bundle smoke 17/17 + dashboard smoke 39/39
(dono fresh); Windows CI smoke mein clone ka asal test shamil.
Sath mein 2 asal fixes: meta race (pehchan copy nahi hoti) aur
site servers ka dashboard socket inherit karna band.

## Build 3 (v2.3) — Andar ke tools — DONE 2026-10-28
6. Mail catcher (local emails dashboard mein parhna)
8. Built-in database tool (tables + URL search-replace)
9. Plugin manager dashboard se (on/off, zip install)
10. Card par update ki khabar (core/plugin updates pending)
12. Mazeed blueprints: Blog, Portfolio, Restaurant, School, Agency
Verification: bundle smoke 17/17; dashboard smoke 47/49 (sirf
demo-content pair test-VM ke write-storm mein phansa, fori baad
haath se dobara chala kar PASS verified). Sath mein asal FD fix
(spawn shell safai), extract verify+repair, copy size-verify.

## Build 4 (v2.4) — Sharing aur system pro
15. Live preview link (client ko aarzi public link)
16. Live hosting par bhejne ka package + checklist
17. XAMPP scan (class checklist ka aakhri hissa)
18. Har site ke liye alag PHP version
19. Local HTTPS
22. WebDevHub auto-update

## Build 5 (v2.5) — Naye users
23. Pehli dafa 3-step Urdu wizard
24. Dashboard Urdu / English switch
25. Halka mode kamzor PCs ke liye

## Note
- Har build ke baad: Linux bundle + dashboard smoke, phir Windows CI
  install smoke. Teeno green hon tab "ready".
- Thumbnail ki technique: Windows par Edge headless (har Windows PC
  par hota hai), Linux par waisa hi maujooda browser — worker action
  se li jayegi, CI mein verify hogi.

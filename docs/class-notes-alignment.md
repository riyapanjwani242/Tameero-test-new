# Class Notes Alignment — Universal Installer ke 6 Fixes

Source: owner ki Zoom class (teacher Hania Khan; Siddeeque Qasmi ka
universal WebDevHub installer, 2026-10-27 raat screen par parhe gaye
fixes). Ye hamari checklist hai — har fix ke samne hamari haalat:

| # | Class wala fix | Hamari v2/dashboard mein haalat |
|---|---|---|
| 1 | Dynamic paths — hardcoding khatam | ✅ Dashboard/installer saare paths apni location se nikalte hain (repo/app dir relative). Sites dir + PHP env se badal sakte hain. |
| 2 | Mojooda XAMPP ka tamam-drives scan | ⚠️ Windows packaging ka item hai — hamara design XAMPP istemal hi nahi karta (PHP built-in server + SQLite), is liye takrao mumkin nahi. Windows installer mein phir bhi ek note/check shamil hoga ke agar XAMPP chal raha ho to ports takrayen nahi (hamare ports alag range se ate hain). |
| 3 | launch_hub.vbs active port khud dhoondh kar browser kholta hai | ✅ Packaging pass (2026-10-28): launch_hub.vbs v2 — pehle se chalta dashboard pehchanta hai, warna 7777..7780 mein pehla jawab-dene wala port dhoondh kar wahi browser kholta hai; port state file mein rehta hai. |
| 4 | VC++ runtime (vc_redist.x64) silent auto-install | ✅ Packaging pass (2026-10-28): Inno redist ko sirf tab khamoshi se chalata hai jab registry mein VC++ 2015-2022 x64 mojood na ho (NeedsVCRedist check). PHP 8.4.27 VS17 runtime bundle ke sath ata hai. |
| 5 | Zero hardcoding / runtime path detection | ✅ (1) hi dekho — PHP binary, sites dir, ports sab runtime par detect/hesab hote hain. |
| 6 | Process isolation — student ke XAMPP ko nahi chherta | ✅ Har site apna alag PHP server process (apna port, apna pid) — band karne par sirf wahi process band hota hai. XAMPP ya kisi aur server ko haath nahi lagta. |

Install flow (class): EXE → Yes → Next → Install → dashboard khud
khulta hai + 2 desktop shortcuts (Start / Stop). Class mein edit hone
wali files: `start_silent.vbs`, `stop_silent.vbs`, `launch_hub.vbs`,
`setup_post_install.bat`, `WebDevHub.iss` — compile do dafa "finished"
aya. Dashboard ka address pattern wahan: `localhost:8080/hub` ya port
80 par `localhost/hub` (jo port active ho, launcher wahi kholta hai).
VC++ wali tafseel: `MSVCP140.dll` / `VCRUNTIME140.dll` missing hon to
Apache/PHP crash hota hai — is liye redist peeche khamoshi se lagta hai.

Hamara Inno script: post-install par dashboard khud khulta hai
(launch_hub.vbs) aur desktop par **Start + Stop dono shortcuts**
bante hain (packaging pass 2026-10-28 — stop_hub.vbs state file ke
port se dashboard ka process band karta hai).

Note: class wala ZIP (120.6 MB) hamare pas NAHI aya (Zoom download
admin-restricted tha) — owner hasil karke de to us ki jaanch alag se
hogi. Teacher ki screen par EXE 135 MB dikha tha.

Sync/merge ka faisla owner ka hai — ye doc sirf alignment ka record hai.

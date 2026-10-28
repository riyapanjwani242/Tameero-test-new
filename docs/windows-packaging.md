# Windows Packaging — PHP Runtime Bundle

Packaging pass (2026-10-28) mein Windows package asal install ke
qabil hua. Muqaddima: Windows user ke PC par PHP nahi hota — is liye
PHP khud package ke andar jata hai.

## Kya shamil hai

| Cheez | Jagah | Note |
|---|---|---|
| PHP **8.4.27** NTS VS17 x64 | `runtime\php\` → `{app}\runtime\php` | windows.php.net official zip; Linux test env bhi 8.4 hai |
| Hamari `php.ini` | `installer-windows/php.ini` | extensions: curl, exif, fileinfo, gd, gettext, intl, mbstring, mysqli, openssl, pdo_mysql, pdo_sqlite, sodium, sqlite3, zip |
| VC++ redist x64 | `runtime\vc_redist.x64.exe` | Inno sirf tab chalata hai jab registry mein pehle se mojood na ho (`NeedsVCRedist`) — is ke baghair php.exe crash hota hai (class note #4) |

## Runtime kaise tayyar hota hai

Binaries repo mein commit NAHI hote (`.gitignore` mein `runtime/`) —
packaging se pehle ye script chalti hai:

```
pwsh installer-windows/fetch-runtime.ps1
```

Script PHP zip + redist download karke extract karti hai, hamari
php.ini lagati hai, aur aakhir mein `php -v` + extensions verify
karti hai. CI (`windows-build.yml`) mein ye step khud chalta hai.

## Cross-platform worker

Site banana/chalana/band karna ab bash scripts nahi karte — ek hi
PHP worker hai: `panel/bin/wdh.php` (create / start / stop / list).
Dashboard bhi usi ko bulata hai. Linux smoke tests usi code path ko
test karte hain jo Windows par chalta hai; sirf process spawn/kill
ki OS wali shakhein alag hain aur wo Windows CI mein test hoti hain.

## Launchers (class checklist)

- `launch_hub.vbs` — pehle se chal raha dashboard pehchanta hai;
  warna pehla jawab-dene wala port 7777..7780 mein dhoondh kar
  server hidden chalata hai, port `panel\.dashboard-state.json`
  mein likhta hai, browser kholta hai.
- `stop_hub.vbs` — state file ke port par listener ki pid nikaal
  kar sirf usi ko band karta hai (process isolation). Desktop par
  Start aur Stop dono shortcuts bante hain.

## Imtihan

`tests/smoke-windows.ps1` CI mein asal imtihan hai: silent install →
bundled php chale → dashboard 200 → dashboard se project bane →
site front page 200 → one-click admin → stop → delete → uninstall.

## Maloom had

- Windows par PHP built-in server single-threaded chalta hai
  (`PHP_CLI_SERVER_WORKERS` Windows par lagu nahi hota). Local dev
  ke liye kaam chalta hai; site ke pages thore sabr se khulte hain.
- SHA256 pinning PHP zip ke liye abhi nahi — download HTTPS se
  official host se hota hai aur `php -v` verify hota hai. Baad mein
  hash add ho sakta hai jab windows.php.net is repo ke build
  environment se seedha khule.

## Sabaq (2026-10-28): pipes, session lock aur FD inheritance

Dashboard ka start/stop/delete hang hua to layer-wise diagnose se ye nikla:

1. `shell_exec` apne capture pipe ke **dono siray** child ko de deta hai.
   Lambe-ji grandchild (site ka `php -S`) wo pipe inherit kar leta hai —
   pipe kabhi band nahi hoti, `shell_exec` kabhi wapas nahi aata,
   dashboard ki request hamesha ke liye latak jati hai.
2. Wo latki request PHP **session lock** pakre rehti hai — usi session
   ki har agli request (stop, delete, list) bhi ruk jati hai.
3. Spawned servers parent ke **saare FDs** inherit karte hain, dashboard
   ka listen socket bhi — dashboard band honay ke baad bhi us ka port
   orphan site servers ke paas reh jata hai.

Usool: jo process kabhi band nahi hota, use **kabhi capture pipe ke
neeche spawn na karo**. Is project mein: actions detached worker se,
site server `proc_open` se file descriptors par, aur lambi actions se
pehle `session_write_close()`.

# WebDevHub - Windows install smoke (CI, asal imtihan)
# Silent install -> bundled PHP chale -> dashboard HTTP 200 ->
# dashboard se project bane -> site start -> front page 200 ->
# one-click admin -> stop -> delete -> uninstall.
# Fail kahin bhi ho to script wahi ruk kar error deta hai (throw).

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

function Check($cond, $name) {
    if ($cond) { Write-Host "PASS - $name" }
    else { throw "FAIL - $name" }
}

# Dashboard band karo: direct php -S process ho to woh, aur
# launcher (launch_hub.vbs) wale server hon to stop_hub.vbs se
# (state file se port + sathi port + MCP pid - sab).
function Stop-Dashboard {
    if ($dash -and !$dash.HasExited) { Stop-Process -Id $dash.Id -Force -ErrorAction SilentlyContinue }
    $sp = Start-Process wscript.exe -ArgumentList "$app\stop_hub.vbs" -WindowStyle Hidden -PassThru -ErrorAction SilentlyContinue
    if ($sp) { Wait-Process -Id $sp.Id -Timeout 60 -ErrorAction SilentlyContinue }
}

# v2.8.4: launcher trace ki aakhri lines job log mein chhapo taake
# artifact na bhi bane to fail ki wajah job log mein hi nazar aaye.
function Dump-LauncherTrace {
    $trace = "$app\panel\.launcher-trace.log"
    Write-Host "TRACE state-file: $(Test-Path "$app\panel\.dashboard-state.json") dashboard-log: $(Test-Path "$app\panel\.dashboard.log") trace: $(Test-Path $trace)"
    if ($launcherProc) { Write-Host "TRACE launcher-process-running: $($launcherProc.HasExited -eq $false)" } else { Write-Host "TRACE launcher-process: unknown (no handle)" }
    if (Test-Path $trace) {
        try {
            $tlines = @(Get-Content $trace)
            if ($tlines.Count -eq 0) { Write-Host "TRACE | trace khaali hai" }
            if ($tlines.Count -gt 200) { $tlines = $tlines | Select-Object -Last 40 }
            $tlines | ForEach-Object { Write-Host "TRACE | $_" }
        } catch {
            Write-Host "TRACE | trace parhi nahi gayi"
        }
    } else {
        Write-Host "TRACE | trace file nahi mili"
    }
}

# v2.8.6: companion-fail ke waqt process/port ka snapshot job
# log mein - throw se PEHLE chalta hai taake processes abhi
# zinda hon (finally ka Stop-Dashboard us ke baad chalta hai).
# Har step try/catch mein: evidence khud smoke ko kabhi na
# giraye. Run #28 ka sabak: trace + state se layer to mili,
# lekin companion process ki asal halat (start hua? mar gaya?
# hang?) ka koi saboot nahi tha - ye function woh gap bharta
# hai. Launcher untouched: ye sirf test-side evidence hai.
function Dump-CompanionEvidence {
    try {
        $ns = netstat -ano -p TCP 2>$null | Select-String ":7827"
        if ($ns) { $ns | ForEach-Object { Write-Host "COMPANION-EVIDENCE | netstat: $($_.Line.Trim())" } }
        else { Write-Host "COMPANION-EVIDENCE | netstat: 7827 par koi line nahi" }
    } catch {
        Write-Host "COMPANION-EVIDENCE | netstat nahi chala"
    }
    try {
        $phps = Get-Process php -ErrorAction SilentlyContinue
        if ($phps) { $phps | ForEach-Object { Write-Host "COMPANION-EVIDENCE | php process: Id=$($_.Id) StartTime=$($_.StartTime)" } }
        else { Write-Host "COMPANION-EVIDENCE | php process: koi nahi" }
    } catch {
        Write-Host "COMPANION-EVIDENCE | php process list nahi mili"
    }
    try {
        $dlog = "$app\panel\.dashboard.log"
        if (Test-Path $dlog) {
            Get-Content $dlog -Tail 30 | ForEach-Object { Write-Host "COMPANION-EVIDENCE | dashboard.log: $_" }
        } else {
            Write-Host "COMPANION-EVIDENCE | dashboard.log nahi mili"
        }
    } catch {
        Write-Host "COMPANION-EVIDENCE | dashboard.log parhi nahi gayi"
    }
    try {
        $clog = "$app\panel\.dashboard-companion.log"
        if (Test-Path $clog) {
            Get-Content $clog -Tail 30 | ForEach-Object { Write-Host "COMPANION-EVIDENCE | companion.log: $_" }
        } else {
            Write-Host "COMPANION-EVIDENCE | companion.log nahi mili"
        }
    } catch {
        Write-Host "COMPANION-EVIDENCE | companion.log parhi nahi gayi"
    }
    try {
        $cmdFile = "$app\panel\.run\dashboard-7827.cmd"
        if (Test-Path $cmdFile) {
            Get-Content $cmdFile | ForEach-Object { Write-Host "COMPANION-EVIDENCE | companion.cmd: $_" }
        } else {
            Write-Host "COMPANION-EVIDENCE | companion.cmd nahi mili"
        }
    } catch {
        Write-Host "COMPANION-EVIDENCE | companion.cmd parhi nahi gayi"
    }
}

$exe = Get-ChildItem "build/windows/TAMEERO_Setup_v*.exe" | Select-Object -First 1
if (!$exe) { throw "Installer exe nahi mili (compile step fail?)" }
$app = "$env:LOCALAPPDATA\Programs\WebDevHub"

# --- 1. Silent install ---
Start-Process $exe.FullName -ArgumentList "/VERYSILENT","/SUPPRESSMSGBOXES","/NORESTART" -Wait
Check (Test-Path "$app\bundle\manifest.json") "Bundle install hua"
Check (Test-Path "$app\panel\lib\hub.php") "Dashboard install hua"
Check (Test-Path "$app\tools\setup-site.php") "Tools install hue"
Check (Test-Path "$app\tools\reset-admin.php") "Reset tool install hua (v2.1)"
Check (Test-Path "$app\tools\fix-url.php") "Fix-url tool install hua (v2.2)"
Check (Test-Path "$app\tools\demo-content.php") "Demo content tool install hua (v2.2)"
Check (Test-Path "$app\tools\plugin-toggle.php") "Plugin toggle tool install hua (v2.3)"
Check (Test-Path "$app\bundle\blueprints\restaurant\blueprint.json") "Restaurant blueprint install hua (v2.3)"
Check (Test-Path "$app\bundle\blueprints\blog\blueprint.json") "Blog blueprint install hua (v2.3)"
Check (Test-Path "$app\tools\https-proxy.php") "HTTPS proxy tool install hua (v2.4)"
Check (Test-Path "$app\tools\deploy-dump.php") "Deploy dump tool install hua (v2.4)"
Check (Test-Path "$app\VERSION") "VERSION file install hua (v2.4)"
Check (Test-Path "$app\bundle\config\php-versions.json") "PHP versions config install hua (v2.4)"
Check (Test-Path "$app\stop_hub.vbs") "Stop launcher install hua"

# --- 2. Bundled PHP asal mein chale (VC++ redist ka asal test) ---
$php = "$app\runtime\php\php.exe"
Check (Test-Path $php) "Bundled php.exe mojood hai"
$ver = & $php -v
Write-Host $ver[0]
$mods = & $php -m
foreach ($need in @("curl","gd","intl","mbstring","openssl","pdo_sqlite","sqlite3","zip","fileinfo","exif")) {
    Check ($mods -contains $need) "Extension: $need"
}

try {
# --- 3. Dashboard start (v2.8: ASAL launcher se) ---
# launch_hub.vbs hi owner wala rasta hai - woh dashboard ke sath
# sathi upload server (port+50) bhi chalata hai aur state file
# likhta hai. CI mein browser khulna theek hai (runner par rehta
# hai, script ko nahi rokta). Pehle wala direct php -S is liye
# hata diya: woh sathi server ko kabhi azmata hi nahi tha.
# v2.8.4: ye try poore script ko cover karta hai (pehle section 3
# is se bahar tha, is liye run #26 mein section 3 par fail hote hi
# finally chala hi nahi aur koi diagnostics artifact nahi bana).
$env:WDH_NO_WIZARD = "1" # wizard ka pehla-run redirect smoke mein nahi (Linux par alag azmaya jata hai)
$launcherProc = Start-Process wscript.exe -ArgumentList "$app\launch_hub.vbs" -WindowStyle Hidden -PassThru
$stateFile = "$app\panel\.dashboard-state.json"
$sawState = $false
$st = $null
foreach ($i in 1..90) {
    if (Test-Path $stateFile) {
        try {
            $st = Get-Content $stateFile -Raw | ConvertFrom-Json
            $sawState = $true
            if ($st.port -and $st.upload_port) { break }
        } catch { }
    }
    Start-Sleep -Seconds 1
}
# v2.8.4: ek hi FAIL line ki jagah teen alag checks - har FAIL
# apni layer ka naam batata hai (run #26 ka sabak: ek line se
# ye farq nahi hota tha ke state bani hi nahi ya sirf port tha).
if (!$sawState) { Dump-LauncherTrace; throw "FAIL - v2.8: State file nahi mili (launcher ne state likhi hi nahi)" }
if (!$st.port) { Dump-LauncherTrace; throw "FAIL - v2.8: State file mein dashboard port nahi hai" }
if (!$st.upload_port) { Dump-LauncherTrace; Dump-CompanionEvidence; throw "FAIL - v2.8: State mein upload_port nahi hai (sathi server nahi utha)" }
$dashPort = $st.port
$upPort = $st.upload_port
Write-Host "PASS - v2.8: Launcher ne state file likhi (port + upload_port)"
Check ($dashPort -eq 7777) "v2.8: Launcher port 7777 hai (6g ke 7778 se takrao nahi)"
Check ($upPort -eq 7827) "v2.8: Sathi upload port 7827 hai"
$upOk = $false
foreach ($i in 1..30) {
    try {
        $upR = Invoke-WebRequest "http://127.0.0.1:$upPort/" -UseBasicParsing -TimeoutSec 10
        if ($upR.StatusCode -eq 200 -and $upR.Content -match "TAMEERO") { $upOk = $true; break }
    } catch { Start-Sleep -Seconds 1 }
}
if ($upOk) { Write-Host "PASS - v2.8: Sathi server shuru mein jawab de raha hai (7827)" } else { Dump-LauncherTrace; Dump-CompanionEvidence; throw "FAIL - v2.8: Sathi server shuru mein jawab de raha hai (7827)" }
    $ok = $false
    foreach ($i in 1..30) {
        try {
            $r = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -UseBasicParsing -TimeoutSec 10 -SessionVariable sess
            if ($r.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 1 }
    }
    Check $ok "Dashboard HTTP 200"
    Check ($r.Content -match "TAMEERO") "Dashboard title"
    $csrf = [regex]::Match($r.Content, 'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
    Check ($csrf.Length -gt 10) "CSRF token mila"

    # --- 4. Dashboard se project banao ---
    $body = @{
        csrf = $csrf; action = "create"; name = "cismoke"; blueprint = "business"
        admin_user = "admin"; admin_email = "admin@example.local"; admin_pass = "cismoke-pass-123"
    }
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body $body -WebSession $sess -UseBasicParsing -TimeoutSec 30 | Out-Null
    $metaFile = "$app\sites\cismoke\.webdevhub.json"
    $ok = $false
    foreach ($i in 1..96) {  # 8 minute tak (har 5 sec)
        if (Test-Path $metaFile) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "Project dashboard se ban gaya (.webdevhub.json)"
    $meta = Get-Content $metaFile | ConvertFrom-Json
    $siteUrl = $meta.url
    Write-Host "Site URL: $siteUrl"

    # --- 5. Site start + front page ---
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "cismoke" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $ok = $false
    foreach ($i in 1..24) {
        try {
            $fr = Invoke-WebRequest "$siteUrl/" -UseBasicParsing -TimeoutSec 30
            if ($fr.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "Site front page HTTP 200"

    # --- 6. One-click admin (magic link) ---
    $list = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -WebSession $sess -UseBasicParsing -TimeoutSec 30
    $m = [regex]::Match($list.Content, 'href="(http://127\.0\.0\.1:\d+/\?wdh_login=[^"]+)"')
    Check $m.Success "Magic admin link dashboard par hai"
    $magic = $m.Groups[1].Value -replace "&amp;", "&"
    $adm = Invoke-WebRequest $magic -UseBasicParsing -TimeoutSec 60 -MaximumRedirection 5 -SessionVariable wpsess
    Check ($adm.StatusCode -eq 200) "One-click admin ke baad wp-admin HTTP 200"

    # --- 6b. v2.1 features: UI markers, health, thumbnail, password reset ---
    Check ($list.Content -match 'id="q"') "v2.1: Search box mojood"
    Check ($list.Content -match "Sab Shuru Karein") "v2.1: Sab Start/Stop toolbar mojood"
    Check ($list.Content -match "Photo Taaza") "v2.1: Photo Taaza button mojood"
    $health = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=health" -WebSession $sess -UseBasicParsing -TimeoutSec 30
    Check ($health.StatusCode -eq 200) "v2.1: Health page HTTP 200"
    Check ($health.Content -match "PHP version") "v2.1: Health page par PHP maloomat"
    $thumbOk = $false
    foreach ($i in 1..12) {
        if (Get-ChildItem "$app\sites\cismoke\.webdevhub-thumb.*" -ErrorAction SilentlyContinue) { $thumbOk = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $thumbOk "v2.1: Thumbnail file ban gayi"
    # Password reset: naya password set karo, phir wp-login se check
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "resetpass"; site = "cismoke"; new_pass = "NewCiPass!456" } -WebSession $sess -UseBasicParsing -TimeoutSec 180 | Out-Null
    Invoke-WebRequest "$siteUrl/wp-login.php" -Method Post -Body @{ log = "admin"; pwd = "NewCiPass!456"; "wp-submit" = "Log In" } -UseBasicParsing -TimeoutSec 60 -SessionVariable wplogin | Out-Null
    $cookieNames = $wplogin.Cookies.GetCookies($siteUrl) | ForEach-Object { $_.Name }
    Check ($cookieNames -match "wordpress_logged_in") "v2.1: Reset ke baad naya password chalta hai"

    # --- 6c. v2.2: Clone (copy engine ka Windows par asal test) ---
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "clone"; site = "cismoke"; new_name = "ciclone" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    $cloneMeta = "$app\sites\ciclone\.webdevhub.json"
    $ok = $false
    foreach ($i in 1..72) {
        if (Test-Path $cloneMeta) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.2: Clone ban gaya (.webdevhub.json)"
    $cloneUrl = (Get-Content $cloneMeta | ConvertFrom-Json).url
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "ciclone" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $ok = $false
    foreach ($i in 1..24) {
        try {
            $fr = Invoke-WebRequest "$cloneUrl/" -UseBasicParsing -TimeoutSec 30
            if ($fr.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "v2.2: Clone front page HTTP 200 (nayi port par)"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "ciclone" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "ciclone" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\ciclone")) "v2.2: Clone delete ho gaya"

    # --- 6d. v2.3: Plugin manager + mail catcher (cismoke site par) ---
    $pv = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=plugins&site=cismoke" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($pv.Content -match "Elementor") "v2.3: Plugins view mein list hai"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "plugin_toggle"; site = "cismoke"; plugin = "code-snippets/code-snippets.php"; mode = "on" } -WebSession $sess -UseBasicParsing -TimeoutSec 180 | Out-Null
    # Tasdeeq: plugins view par Code Snippets wali ROW mein hi \u25CF Chalu ho
    $pv2 = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=plugins&site=cismoke" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($pv2.Content -match "Code Snippets[\s\S]{0,500}\u25CF Chalu") "v2.3: Plugin toggle chalu hua"
    $mails = Get-ChildItem "$app\sites\cismoke\wp-content\webdevhub-mail\*.json" -ErrorAction SilentlyContinue
    Check ($mails.Count -ge 1) "v2.3: Mail catcher ne mail pakri (welcome)"

    # --- 6e. v2.4: Health naye hisse + card buttons ---
    $hv = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=health" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($hv.Content -match "XAMPP scan") "v2.4: Health page par XAMPP scan hai"
    Check ($hv.Content -match "PHP versions") "v2.4: Health page par PHP versions hain"
    Check ($hv.Content -match "TAMEERO version") "v2.4: Health page par TAMEERO version hai"
    $home2 = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($home2.Content -match "Deploy Package") "v2.4: Card par Deploy Package button hai"
    Check ($home2.Content -match "Preview Link") "v2.4: Card par Preview Link button hai"
    Check ($home2.Content -match "HTTPS Shuru") "v2.4: Card par HTTPS button hai"
    # v2.8: Zuban + Lite toggle ab Home par nahi, Settings page par
    # hain (Mockup A restyle - run #23 isi par gira tha: Home par
    # "English"/"Lite mode" lafz hi nahi raha). Check bhi wahi hota hai.
    $settingsPage = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=settings" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($settingsPage.StatusCode -eq 200) "v2.5: Settings page HTTP 200"
    Check ($settingsPage.Content -match "English") "v2.5: Settings par Zuban toggle (English button) mojood hai"
    Check ($settingsPage.Content -match "Lite Mode") "v2.5: Settings par Lite mode toggle mojood hai"

    # --- 6f. v2.6.2: Store site BORN-COMPLETE (owner faisla 2026-10-31 - FULL E2E shart) ---
    $storeBody = @{
        csrf = $csrf; action = "create"; name = "cistore"; blueprint = "store"; demo_content = "1"
        admin_user = "admin"; admin_email = "admin@example.local"; admin_pass = "cismoke-pass-123"
    }
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body $storeBody -WebSession $sess -UseBasicParsing -TimeoutSec 30 | Out-Null
    $storeMeta = "$app\sites\cistore\.webdevhub.json"
    $storeFail = "$app\sites\cistore\.create-failed.json"
    $ok = $false
    foreach ($i in 1..120) {  # 10 minute tak
        if (Test-Path $storeFail) { throw "FAIL - Store create fail ho gayi: $(Get-Content $storeFail -Raw)" }
        if (Test-Path $storeMeta) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.6.2 E2E: Store site ban gayi (koi Creating latka nahi)"
    $storeJob = Get-Content "$app\sites\cistore.job.log" -Raw
    Check ($storeJob -match "## STEP 6/6") "v2.6.2 E2E: Job log mein saare steps (6/6) hain"
    $vraw = & $php "$app\tools\setup-site.php" "--path=$app\sites\cistore" --verify
    $vtext = ($vraw -join "`n"); $vtext = $vtext.Substring($vtext.IndexOf('{'))
    $v = $vtext | ConvertFrom-Json
    Check ($v.installed -eq $true) "v2.6.2 E2E: WP installed hai"
    Check ($v.active_plugins -match "woocommerce") "v2.6.2 E2E: WooCommerce ACTIVE hai"
    foreach ($pg in @("shop","cart","checkout","my-account")) {
        Check ($v.pages -contains $pg) "v2.6.2 E2E: Woo page mojood: $pg"
    }
    Check ($v.woo_products -eq 8) "v2.6.2 E2E: 8 demo products hain (mila: $($v.woo_products))"
    Check ($v.woo_currency -eq "PKR") "v2.6.2 E2E: Currency PKR hai"
    Check ($v.woo_coming_soon -eq "no") "v2.6.2 E2E: Store LIVE hai (Coming Soon nahi)"
    $storeUrl = (Get-Content $storeMeta | ConvertFrom-Json).url
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $ok = $false
    foreach ($i in 1..24) {
        try {
            $sf = Invoke-WebRequest "$storeUrl/" -UseBasicParsing -TimeoutSec 30
            if ($sf.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "v2.6.2 E2E: Store front page HTTP 200"
    $shop = Invoke-WebRequest "$storeUrl/shop/" -UseBasicParsing -TimeoutSec 60
    Check ($shop.StatusCode -eq 200) "v2.6.2 E2E: /shop/ HTTP 200"
    $pm = [regex]::Match($shop.Content, 'href="(http://127\.0\.0\.1:\d+/product/[^"]+)"')
    Check $pm.Success "v2.6.2 E2E: Shop par product link hai"
    if ($pm.Success) {
        $prod = Invoke-WebRequest $pm.Groups[1].Value -UseBasicParsing -TimeoutSec 60
        Check ($prod.StatusCode -eq 200) "v2.6.2 E2E: Product page HTTP 200"
    }
    # 6m ke liye export zip bacha lo (cistore delete hone se pehle)
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "export"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 300 -OutFile "$env:TEMP\ci-export.zip" | Out-Null
    Check (Test-Path "$env:TEMP\ci-export.zip") "v2.8: Export zip bana (6m import ke liye)"
    # v2.8.9: export ka size job log mein - run #31 mein body phase ka
    # shak tha lekin size ka fact kabhi log nahi hua tha.
    $ciExportSize = (Get-Item "$env:TEMP\ci-export.zip").Length
    Write-Host "CI-EXPORT-SIZE bytes:$ciExportSize MB:$([math]::Round($ciExportSize / 1MB, 1))"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cistore")) "v2.6.2 E2E: Store delete ho gaya"

    # --- 6g. v2.6.2: NEGATIVE hang test - timeout + Failed state + dashboard recovery ---
    # Doosra dashboard (alag port) stall env ke sath: worker ko env
    # dashboard process se inherit hota hai (wscript -> .cmd chain).
    $env:WDH_TEST_STALL = "tool:demo-content"
    # 60s: stall step ke liye kaafi hai, aur aam steps ko bhi saans
    # milti hai - 20s par Windows runner par blueprint step ka
    # thanda WP+Woo bootstrap ek dafa 20s se nikal gaya tha
    # (run #19, cihang job log saboot) aur test ghalat step par
    # fail ho gaya tha, halanke product bilkul theek tha.
    $env:WDH_STEP_TIMEOUT = "60"
    $dash2Port = 7778
    $dash2 = Start-Process $php -ArgumentList "-S","127.0.0.1:$dash2Port","-t","$app\panel\public","$app\panel\server.php" -PassThru -WindowStyle Hidden
    try {
        $ok = $false
        foreach ($i in 1..30) {
            try {
                $r2 = Invoke-WebRequest "http://127.0.0.1:$dash2Port/" -UseBasicParsing -TimeoutSec 10 -SessionVariable sess2
                if ($r2.StatusCode -eq 200) { $ok = $true; break }
            } catch { Start-Sleep -Seconds 1 }
        }
        Check $ok "v2.6.2 NEG: Doosra dashboard (stall env) HTTP 200"
        $csrf2 = [regex]::Match($r2.Content, 'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
        $hangBody = @{
            csrf = $csrf2; action = "create"; name = "cihang"; blueprint = "store"; demo_content = "1"
            admin_user = "admin"; admin_email = "admin@example.local"; admin_pass = "cismoke-pass-123"
        }
        Invoke-WebRequest "http://127.0.0.1:$dash2Port/" -Method Post -Body $hangBody -WebSession $sess2 -UseBasicParsing -TimeoutSec 30 | Out-Null
        $hangFail = "$app\sites\cihang\.create-failed.json"
        $ok = $false
        foreach ($i in 1..108) {  # 9 minute tak (steps 1-4 + 20s stall timeout)
            if (Test-Path $hangFail) { $ok = $true; break }
            if (Test-Path "$app\sites\cihang\.webdevhub.json") { throw "FAIL - v2.6.2 NEG: stall ke bawjood site ban gayi (hook kaam nahi kiya?)" }
            Start-Sleep -Seconds 5
        }
        Check $ok "v2.6.2 NEG: Atki hui creation Failed state mein gayi (marker bana)"
        $fj = Get-Content $hangFail -Raw
        Check ($fj -match '"step":"demo"') "v2.6.2 NEG: Failure marker mein step=demo hai"
        $hangJob = Get-Content "$app\sites\cihang.job.log" -Raw
        Check ($hangJob -match "TIMEOUT") "v2.6.2 NEG: Job log mein TIMEOUT line hai"
        $homeHang = Invoke-WebRequest "http://127.0.0.1:$dash2Port/" -WebSession $sess2 -UseBasicParsing -TimeoutSec 30
        Check ($homeHang.Content -match "cihang") "v2.6.2 NEG: Dashboard par cihang card hai"
        Check ($homeHang.Content -match "Fail") "v2.6.2 NEG: Card Failed state dikhata hai (Creating nahi)"
        Invoke-WebRequest "http://127.0.0.1:$dash2Port/" -Method Post -Body @{ csrf = $csrf2; action = "delete"; site = "cihang" } -WebSession $sess2 -UseBasicParsing -TimeoutSec 60 | Out-Null
        Check (!(Test-Path "$app\sites\cihang")) "v2.6.2 NEG: Failed site dashboard se delete (recovery) ho gaya"
    }
    finally {
        if ($dash2 -and !$dash2.HasExited) { Stop-Process -Id $dash2.Id -Force -ErrorAction SilentlyContinue }
        Remove-Item env:WDH_TEST_STALL -ErrorAction SilentlyContinue
        Remove-Item env:WDH_STEP_TIMEOUT -ErrorAction SilentlyContinue
    }

    # --- 6k. v2.8: Dukaan theme CHOICE se store (create-time choice) ---
    $dukaanBody = @{
        csrf = $csrf; action = "create"; name = "cidukaan"; blueprint = "store"; theme = "dukaan-fse"; demo_content = "1"
        admin_user = "admin"; admin_email = "admin@example.local"; admin_pass = "cismoke-pass-123"
    }
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body $dukaanBody -WebSession $sess -UseBasicParsing -TimeoutSec 30 | Out-Null
    $dukaanMeta = "$app\sites\cidukaan\.webdevhub.json"
    $dukaanFail = "$app\sites\cidukaan\.create-failed.json"
    $ok = $false
    foreach ($i in 1..120) {  # 10 minute tak
        if (Test-Path $dukaanFail) { throw "FAIL - Dukaan create fail ho gayi: $(Get-Content $dukaanFail -Raw)" }
        if (Test-Path $dukaanMeta) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.8: Dukaan store ban gaya"
    $vraw = & $php "$app\tools\setup-site.php" "--path=$app\sites\cidukaan" --verify
    $vtext = ($vraw -join "`n"); $vtext = $vtext.Substring($vtext.IndexOf('{'))
    $v = $vtext | ConvertFrom-Json
    Check ($v.theme -eq "dukaan-fse") "v2.8: Dukaan theme active hai (mila: $($v.theme))"
    Check ($v.woo_products -eq 8) "v2.8: Dukaan store mein 8 demo products hain"
    Check ($v.woo_currency -eq "PKR") "v2.8: Dukaan store currency PKR hai"
    Check ($v.woo_coming_soon -eq "no") "v2.8: Dukaan store LIVE hai"
    $dukaanUrl = (Get-Content $dukaanMeta | ConvertFrom-Json).url
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "cidukaan" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $ok = $false
    foreach ($i in 1..24) {
        try {
            $df = Invoke-WebRequest "$dukaanUrl/" -UseBasicParsing -TimeoutSec 30
            if ($df.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "v2.8: Dukaan store front page HTTP 200"
    Check ($df.Content -match "dukaan-fse") "v2.8: Dukaan front page par theme ki file ka nishan hai"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "cidukaan" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cidukaan" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cidukaan")) "v2.8: Dukaan store delete ho gaya"

    # --- 6l. v2.8: MCP toggle asal mein server chalata/band karta hai ---
    # (v2.7.1 field bug #4: toggle sirf flag tha, port par kuch nahi sunta tha)
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "mcp_toggle"; want = "1" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $mcpUp = $false
    foreach ($i in 1..24) {
        try {
            $tc = New-Object System.Net.Sockets.TcpClient
            $tc.Connect("127.0.0.1", 8971)
            if ($tc.Connected) { $mcpUp = $true; $tc.Close(); break }
            $tc.Close()
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $mcpUp "v2.8: MCP toggle ON par port 8971 sun raha hai"
    $mcpCode = 0
    try {
        # POST lazmi hai: server GET par 405 deta hai (method check
        # auth se pehle ata hai) - bina token ke POST par 401 chahiye
        Invoke-WebRequest "http://127.0.0.1:8971/" -Method Post -Body '{}' -ContentType "application/json" -UseBasicParsing -TimeoutSec 15 | Out-Null
    } catch {
        if ($_.Exception.Response) { $mcpCode = [int]$_.Exception.Response.StatusCode }
    }
    Check ($mcpCode -eq 401) "v2.8: MCP bina token ke 401 deta hai (auth gate zinda hai)"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "mcp_toggle" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $mcpDown = $false
    foreach ($i in 1..24) {
        try {
            $tc = New-Object System.Net.Sockets.TcpClient
            $tc.Connect("127.0.0.1", 8971)
            $tc.Close()
            Start-Sleep -Seconds 5
        } catch { $mcpDown = $true; break }
    }
    Check $mcpDown "v2.8: MCP toggle OFF par port 8971 band hai"

    # --- 6m. v2.8.14: Import CHUNKED raaste se + main dashboard responsive ---
    # Field bug: 451MB import par dashboard 15+ min murda lagta tha
    # (single-thread server par bari upload + session lock). Ab zip
    # 8 MiB tukron mein jati hai (raw octet-stream, sequential,
    # per-chunk SHA-256), is liye koi aik request kabhi bari nahi
    # hoti. Sathi server par import ka poora raasta azmaya jata hai.
    $upHome = $null
    foreach ($i in 1..24) {
        try {
            $upHome = Invoke-WebRequest "http://127.0.0.1:$upPort/" -WebSession $sess -UseBasicParsing -TimeoutSec 30
            if ($upHome.StatusCode -eq 200) { break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check (($null -ne $upHome) -and ($upHome.StatusCode -eq 200)) "v2.8: Sathi server HTTP 200 deta hai"
    Check ($upHome.Content -match "data-upload-base=""http://127.0.0.1:$upPort""") "v2.8: Import form sathi origin par hai"
    # v2.8.12: curl tayyari aik hi jagah (A/B + chunked client dono
    # ke liye) - exe path (.Source, PowerShell curl alias se bachne
    # ke liye) aur session cookie $sess se.
    $curlExe = Get-Command curl.exe -ErrorAction SilentlyContinue
    $phpSessId = $null
    $sess.Cookies.GetCookies("http://127.0.0.1/") | Where-Object { $_.Name -eq "PHPSESSID" } | ForEach-Object { $phpSessId = $_.Value }
    # v2.8.10: tmp-watcher - upload ke doran TEMP ki growth sample karo.
    # Pattern par nahi (v2.8.9 ka php*.tmp kabhi match nahi karta tha -
    # asal PHP tmp naam php<random> hota hai, koi extension nahi), is
    # liye baseline-diff: baseline ke baad nayi/badli files log karo.
    # Growth = body aa rahi hai (susti); freeze = filter stall ki
    # nishani; kuch nahi = bytes tmp write tak pahunche hi nahi.
    # Watcher kabhi gate ko nahi torega: Start-Job fail ho to bhi
    # POST baghair watcher chalega (saboot v2.8.9 level par rahega).
    $watchFile = "$env:TEMP\TAMEERO-upload-watch.log"
    if (Test-Path $watchFile) { Remove-Item $watchFile -Force }
    $watchJob = $null
    try {
        $watchJob = Start-Job -ScriptBlock {
            param($tempDir, $outFile)
            $base = @{}
            # -File zaroori: directory ki Length $null hoti hai, warna
            # har poll mein har directory ke liye jhooti NEW line aati.
            # v2.8.11: apni files ko leaf NAME se bahar rakho (TAMEERO-*
            # - watch log + curl body/err). FullName comparison run #33
            # mein fail hua tha (path-form ka farq); naam ka muqabla
            # path ki shakal se azaad hai.
            # $base pehli sight par update karo: NEW sirf aik dafa, phir
            # size badle to GROW (run #33 mein NEW har poll mein aati thi).
            Get-ChildItem $tempDir -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -notlike 'TAMEERO-*' } | ForEach-Object { $base[$_.Name] = $_.Length }
            "$(Get-Date -Format 'HH:mm:ss') WATCH-START baseline:$($base.Count)" | Out-File -FilePath $outFile -Append
            while ($true) {
                $now = Get-Date -Format "HH:mm:ss"
                Get-ChildItem $tempDir -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -notlike 'TAMEERO-*' } | ForEach-Object {
                    $old = $base[$_.Name]
                    if ($null -eq $old) { "$now NEW $($_.Name) $($_.Length)" | Out-File -FilePath $outFile -Append; $base[$_.Name] = $_.Length }
                    elseif ($old -ne $_.Length) { "$now GROW $($_.Name) $($_.Length)" | Out-File -FilePath $outFile -Append; $base[$_.Name] = $_.Length }
                }
                Start-Sleep -Seconds 5
            }
        } -ArgumentList $env:TEMP, $watchFile
    } catch {
        Write-Host "UPLOAD-WATCH: watcher start nahi hua ($($_.Exception.Message)) - POST baghair watcher chalega"
    }
    # v2.8.14: A/B control - Python raw receiver vs PHP receive path.
    # Evidence-only: gate ka faisla in par NAHI. Wahi file (420.3MB),
    # wahi curl, alternating tartib (Python -> PHP -> PHP -> Python)
    # taake runner-load spike gumrah na kare. Python receiver
    # (tools/raw-receiver.py) body ko recv + SHA-256 + DISCARD karta
    # hai (disk par nahi likhta). PHP side raw POST companion root
    # par jata hai (koi action nahi - sirf receive time mapna hai;
    # flash mein "token ghalat" likhe ga, be-zarar). Falsification:
    # Python bhi sust ho to PHP-specific diagnosis wapas.
    $pyExe = Get-Command python.exe -ErrorAction SilentlyContinue
    if (-not $pyExe) { $pyExe = Get-Command python -ErrorAction SilentlyContinue }
    if (-not $pyExe) { $pyExe = Get-Command py -ErrorAction SilentlyContinue }
    $receiver = Join-Path $PSScriptRoot "..\tools\raw-receiver.py"
    if ((-not $pyExe) -or (-not $curlExe) -or (-not (Test-Path $receiver))) {
        Write-Host "AB-PROBE: python/curl/receiver na mila - A/B skip, gate par asar nahi"
    } else {
        $abPort = 7830
        $pyProc = $null
        $statJob = $null
        foreach ($side in @("python", "php", "php", "python")) {
            try {
                if ($side -eq "python") {
                    $pyLog = "$env:TEMP\TAMEERO-ab-py.log"
                    if (Test-Path $pyLog) { Remove-Item $pyLog -Force }
                    $pyArgs = @($receiver, "--port", "$abPort", "--max-seconds", "300")
                    if ($pyExe.Name -eq "py.exe") { $pyArgs = @("-3") + $pyArgs }
                    $pyProc = Start-Process $pyExe.Source -ArgumentList $pyArgs -RedirectStandardOutput $pyLog -PassThru -WindowStyle Hidden
                    $pyReady = $false
                    foreach ($w in 1..30) {
                        Start-Sleep -Seconds 1
                        if ((Test-Path $pyLog) -and ((Get-Content $pyLog -Raw -ErrorAction SilentlyContinue) -match "RECEIVER-START")) { $pyReady = $true; break }
                        if ($pyProc.HasExited) { break }
                    }
                    if (-not $pyReady) {
                        Write-Host "AB-PROBE side:python receiver start nahi hua - attempt skip"
                        if (-not $pyProc.HasExited) { Stop-Process -Id $pyProc.Id -Force -ErrorAction SilentlyContinue }
                        continue
                    }
                } else {
                    # PHP CPU/mem sampler: companion php ki memory growth
                    # = body buffering ka saboot ya rad.
                    $statLog = "$env:TEMP\TAMEERO-ab-phpstat.log"
                    if (Test-Path $statLog) { Remove-Item $statLog -Force }
                    $statJob = Start-Job -ScriptBlock {
                        param($outFile)
                        while ($true) {
                            Get-Process php -ErrorAction SilentlyContinue | ForEach-Object { "$(Get-Date -Format 'HH:mm:ss') php Id=$($_.Id) CPU=$($_.CPU) WS=$($_.WorkingSet64)" | Out-File -FilePath $outFile -Append }
                            Start-Sleep -Seconds 2
                        }
                    } -ArgumentList $statLog
                }
                $abBody = "$env:TEMP\TAMEERO-ab-body.txt"
                $abErr = "$env:TEMP\TAMEERO-ab-err.txt"
                $abUrl = if ($side -eq "python") { "http://127.0.0.1:$abPort/" } else { "http://127.0.0.1:$upPort/" }
                $abArgs = @("-sS", "--max-time", "300", "-w", "AB-WRITE-OUT side:$side size_upload:%{size_upload} time_total:%{time_total}", "-H", "Expect:", "-H", "Content-Type: application/octet-stream", "--data-binary", "@$env:TEMP\ci-export.zip", "-o", $abBody, $abUrl)
                if (($side -eq "php") -and $phpSessId) { $abArgs = @("-sS", "--max-time", "300", "-b", "PHPSESSID=$phpSessId", "-w", "AB-WRITE-OUT side:$side size_upload:%{size_upload} time_total:%{time_total}", "-H", "Expect:", "-H", "Content-Type: application/octet-stream", "--data-binary", "@$env:TEMP\ci-export.zip", "-o", $abBody, $abUrl) }
                & $curlExe.Source @abArgs 2> $abErr
                Write-Host "AB-PROBE side:$side exit:$LASTEXITCODE"
                if ($side -eq "python") {
                    if (-not $pyProc.WaitForExit(10000)) { Stop-Process -Id $pyProc.Id -Force -ErrorAction SilentlyContinue }
                    Get-Content $pyLog -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "AB-PROBE python: $_" }
                } else {
                    if ($statJob) { Stop-Job $statJob -ErrorAction SilentlyContinue; Remove-Job $statJob -Force -ErrorAction SilentlyContinue }
                    Get-Content $statLog -Tail 6 -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "AB-PHPSTAT: $_" }
                }
            } catch {
                Write-Host "AB-PROBE side:$side ERROR $($_.Exception.Message) - attempt skip, gate par asar nahi"
                if ($pyProc -and (-not $pyProc.HasExited)) { Stop-Process -Id $pyProc.Id -Force -ErrorAction SilentlyContinue }
                if ($statJob) { Stop-Job $statJob -ErrorAction SilentlyContinue; Remove-Job $statJob -Force -ErrorAction SilentlyContinue }
            }
        }
    }
    # v2.8.14: chunked import helper - zip ko 8 MiB tukron mein bhejta
    # hai (raw octet-stream, har chunk ka SHA-256, har chunk par 3
    # koshish), phir finalize karta hai. Wapas: finalize ka JSON
    # (redirect + assembled sha256). Koi chunk na jaye to FAIL throw.
    function Invoke-ChunkedImport {
        param($ZipPath, $SiteName, $UploadId)
        if (-not $curlExe) { throw "FAIL - v2.8.14: curl.exe nahi mila (site $SiteName)" }
        $zipLen = (Get-Item $ZipPath).Length
        $chunkSize = 8MB
        $total = [math]::Ceiling($zipLen / $chunkSize)
        $fs = [System.IO.File]::OpenRead($ZipPath)
        try {
            $buf = New-Object byte[] $chunkSize
            for ($i = 0; $i -lt $total; $i++) {
                $read = $fs.Read($buf, 0, $chunkSize)
                $chunkFile = "$env:TEMP\TAMEERO-chunk-$i.bin"
                $cf = [System.IO.File]::Create($chunkFile)
                $cf.Write($buf, 0, $read)
                $cf.Close(); $cf.Dispose()
                $sha = (Get-FileHash $chunkFile -Algorithm SHA256).Hash.ToLower()
                $cBody = "$env:TEMP\TAMEERO-chunk-body.txt"
                $cErr = "$env:TEMP\TAMEERO-chunk-err.txt"
                $okChunk = $false
                foreach ($try in 1..3) {
                    $cArgs = @("-sS", "--max-time", "120", "-H", "X-WDH-Action: import_chunk", "-H", "X-CSRF-Token: $csrf", "-H", "X-Upload-Id: $UploadId", "-H", "X-Chunk-Index: $i", "-H", "X-Chunk-Total: $total", "-H", "X-Chunk-Sha256: $sha", "-H", "Expect:", "-H", "Content-Type: application/octet-stream", "--data-binary", "@$chunkFile", "-o", $cBody, "http://127.0.0.1:$upPort/")
                    if ($phpSessId) { $cArgs = @("-sS", "--max-time", "120", "-b", "PHPSESSID=$phpSessId", "-H", "X-WDH-Action: import_chunk", "-H", "X-CSRF-Token: $csrf", "-H", "X-Upload-Id: $UploadId", "-H", "X-Chunk-Index: $i", "-H", "X-Chunk-Total: $total", "-H", "X-Chunk-Sha256: $sha", "-H", "Expect:", "-H", "Content-Type: application/octet-stream", "--data-binary", "@$chunkFile", "-o", $cBody, "http://127.0.0.1:$upPort/") }
                    & $curlExe.Source @cArgs 2> $cErr
                    $cExit = $LASTEXITCODE
                    $cResp = Get-Content $cBody -Raw -ErrorAction SilentlyContinue
                    if (($cExit -eq 0) -and ($cResp -match '"ok":true')) { $okChunk = $true; break }
                    Write-Host "CHUNK-UPLOAD site:$SiteName chunk:$i/$total try:$try exit:$cExit - retry"
                }
                Remove-Item $chunkFile -Force -ErrorAction SilentlyContinue
                if (-not $okChunk) { throw "FAIL - v2.8.14: chunk $i/$total nahi gaya (site $SiteName)" }
                Write-Host "CHUNK-UPLOAD site:$SiteName chunk:$i/$total ok"
            }
        } finally {
            $fs.Close(); $fs.Dispose()
        }
        $finJson = (@{ upload_id = $UploadId; name = $SiteName; total_chunks = $total; total_size = $zipLen } | ConvertTo-Json -Compress)
        $finBody = "$env:TEMP\TAMEERO-finalize-body.txt"
        $finErr = "$env:TEMP\TAMEERO-finalize-err.txt"
        $fArgs = @("-sS", "--max-time", "300", "-H", "X-WDH-Action: import_finalize", "-H", "X-CSRF-Token: $csrf", "-H", "Expect:", "-H", "Content-Type: application/json", "-d", $finJson, "-o", $finBody, "http://127.0.0.1:$upPort/")
        if ($phpSessId) { $fArgs = @("-sS", "--max-time", "300", "-b", "PHPSESSID=$phpSessId", "-H", "X-WDH-Action: import_finalize", "-H", "X-CSRF-Token: $csrf", "-H", "Expect:", "-H", "Content-Type: application/json", "-d", $finJson, "-o", $finBody, "http://127.0.0.1:$upPort/") }
        & $curlExe.Source @fArgs 2> $finErr
        if ($LASTEXITCODE -ne 0) { throw "FAIL - v2.8.14: finalize nahi gaya (site $SiteName)" }
        Write-Host "CHUNK-FINALIZE site:$SiteName total:$total size:$zipLen"
        return (Get-Content $finBody -Raw)
    }
    # v2.8.14: tiny-import probe - chunked raaste se (1 chunk).
    # Probe pass + bari import fail = masla chunked raaste ka nahi,
    # size/duration ka hai. Tiny zip ka worker fail hoga (TAMEERO
    # export nahi) - be-zarar: ciimport ki files ko haath nahi lagta.
    New-Item -ItemType Directory -Force "$env:TEMP\ci-probe-src" | Out-Null
    Set-Content "$env:TEMP\ci-probe-src\probe.txt" "tameero probe" -NoNewline
    Compress-Archive -Path "$env:TEMP\ci-probe-src\probe.txt" -DestinationPath "$env:TEMP\ci-probe.zip" -Force
    $probeContent = Invoke-ChunkedImport -ZipPath "$env:TEMP\ci-probe.zip" -SiteName "ciprobe" -UploadId "ciprobe-test"
    Check ($probeContent -match "redirect") "v2.8.14: Tiny import probe ne redirect JSON diya (chunked handler+spawn companion par zinda)"
    # v2.8.14: bari import chunked raaste se (ciimport). Fail ho to
    # evidence dump (companion tail + export size + Defender reads)
    # phir rethrow - failure mode wahi, saboot ke sath.
    $finContent = $null
    try {
        $finContent = Invoke-ChunkedImport -ZipPath "$env:TEMP\ci-export.zip" -SiteName "ciimport" -UploadId "ciimport-test"
    } catch {
        Write-Host "IMP-TIMEOUT-EVIDENCE companion-log-tail:"
        Get-Content "$app\panel\.dashboard-companion.log" -Tail 10 -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "IMP-TIMEOUT-EVIDENCE companion-log: $_" }
        Write-Host "IMP-TIMEOUT-EVIDENCE export-size: $((Get-Item "$env:TEMP\ci-export.zip" -ErrorAction SilentlyContinue).Length)"
        # v2.8.10: Defender read-only reads (koi setting change nahi -
        # na Set-* cmdlet, na exclusion, na disable; cmdlets na hon to
        # SilentlyContinue se kuch nahi chhapega).
        Get-MpComputerStatus -ErrorAction SilentlyContinue | Select-Object RealTimeProtectionEnabled, AntivirusEnabled | ForEach-Object { Write-Host "IMP-TIMEOUT-EVIDENCE defender: $_" }
        Get-MpPreference -ErrorAction SilentlyContinue | Select-Object -ExpandProperty ExclusionPath -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "IMP-TIMEOUT-EVIDENCE defender-exclusion: $_" }
        if ($watchJob) { Stop-Job $watchJob -ErrorAction SilentlyContinue; Remove-Job $watchJob -Force -ErrorAction SilentlyContinue }
        Get-Content $watchFile -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "UPLOAD-WATCH: $_" }
        throw
    }
    if ($watchJob) { Stop-Job $watchJob -ErrorAction SilentlyContinue; Remove-Job $watchJob -Force -ErrorAction SilentlyContinue }
    Get-Content $watchFile -ErrorAction SilentlyContinue | ForEach-Object { Write-Host "UPLOAD-WATCH: $_" }
    Check ($finContent -match "redirect") "v2.8.14: Chunked import ne redirect JSON diya"
    $finJson = $finContent | ConvertFrom-Json
    $expHash = (Get-FileHash "$env:TEMP\ci-export.zip" -Algorithm SHA256).Hash.ToLower()
    Check ($finJson.sha256 -eq $expHash) "v2.8.14: Finalize ka SHA-256 export zip se match karta hai"
    # Import chalte hue main dashboard foran jawab de (yehi asal bug tha)
    $mainDuring = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -WebSession $sess -UseBasicParsing -TimeoutSec 30
    Check ($mainDuring.StatusCode -eq 200) "v2.8.14: Import ke dauraan main dashboard jawab deta hai"
    $impMeta = "$app\sites\ciimport\.webdevhub.json"
    $impFail = "$app\sites\ciimport\.create-failed.json"
    $ok = $false
    foreach ($i in 1..120) {  # 10 minute tak
        if (Test-Path $impFail) { throw "FAIL - Import fail ho gayi: $(Get-Content $impFail -Raw)" }
        if (Test-Path $impMeta) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.8: Sathi server se import mukammal hua"
    $impUrl = (Get-Content $impMeta | ConvertFrom-Json).url
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "ciimport" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $ok = $false
    foreach ($i in 1..24) {
        try {
            $imf = Invoke-WebRequest "$impUrl/" -UseBasicParsing -TimeoutSec 30
            if ($imf.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "v2.8: Import ki hui site ka front page HTTP 200"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "ciimport" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "ciimport" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\ciimport")) "v2.8: Import ki hui site delete ho gayi"

    # --- 6h. v2.6.3 UPGRADE-PATH: stale zip + mojooda site -> in-place upgrade -> dobara create ---
    # Asal bug isi raste par tha: upgraded PC par dist mein purani
    # wordpress-6.8.3.zip pari rehti thi aur creation usi se banti
    # thi. Yahan wahi halat jaan boojh kar banayi jati hai.
    $expectedWp = (Get-Content (Join-Path $PSScriptRoot "..\bundle\manifest.json") | ConvertFrom-Json).wordpress.version
    $wpDist = "$app\bundle\wordpress\dist"
    Invoke-WebRequest "https://wordpress.org/wordpress-6.8.3.zip" -OutFile "$wpDist\wordpress-6.8.3.zip"
    Copy-Item "$wpDist\wordpress-6.8.3.zip" "$env:TEMP\wp-6.8.3.zip" -Force
    Check (Test-Path "$wpDist\wordpress-6.8.3.zip") "v2.6.3 UPG: Stale 6.8.3 zip plant ho gayi (purani halat)"
    # Stale zip ke bawajood creation - selection highest-wins hai
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "create"; name = "ciupgrade"; blueprint = "store"; admin_user = "admin"; admin_email = "a@b.c"; admin_pass = "testpass123"; demo_content = "1" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    $metaUp = "$app\sites\ciupgrade\.webdevhub.json"
    $failUp = "$app\sites\ciupgrade\.create-failed.json"
    $to = (Get-Date).AddMinutes(10)
    while ((Get-Date) -lt $to -and !(Test-Path $metaUp)) {
        if (Test-Path $failUp) { throw "ciupgrade creation fail (upgrade-path): $(Get-Content $failUp -Raw)" }
        Start-Sleep -Seconds 5
    }
    Check (Test-Path $metaUp) "v2.6.3 UPG: Stale zip ke bawajood Store site ban gayi"
    $verUp = [regex]::Match((Get-Content "$app\sites\ciupgrade\wp-includes\version.php" -Raw), "wp_version = '([^']+)'").Groups[1].Value
    Check ($verUp -eq $expectedWp) "v2.6.3 UPG: Site ka WP $expectedWp hai (purani zip NAHI uthi)"
    # In-place upgrade: pehle dashboard + sites band (runtime lock na ho)
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "cismoke" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Stop-Dashboard
    Start-Sleep -Seconds 3
    Start-Process $exe.FullName -ArgumentList "/VERYSILENT","/SUPPRESSMSGBOXES","/NORESTART" -Wait
    Start-Sleep -Seconds 2
    Check (!(Test-Path "$wpDist\wordpress-6.8.3.zip")) "v2.6.3 UPG: Upgrade par stale 6.8.3 zip saaf ho gayi"
    Check (Test-Path "$wpDist\wordpress-$expectedWp.zip") "v2.6.3 UPG: Mojooda WP zip ($expectedWp) dist mein hai"
    Check (Test-Path $metaUp) "v2.6.3 UPG: Upgrade se pehle bani site salamat hai"
    # Dashboard dobara chalao (session + csrf taaza)
    $dash = Start-Process $php -ArgumentList "-S","127.0.0.1:$dashPort","-t","$app\panel\public","$app\panel\server.php" -PassThru -WindowStyle Hidden
    $ok = $false
    foreach ($i in 1..30) {
        try {
            $r = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -UseBasicParsing -TimeoutSec 10 -SessionVariable sess
            if ($r.StatusCode -eq 200) { $ok = $true; break }
        } catch { Start-Sleep -Seconds 1 }
    }
    Check $ok "v2.6.3 UPG: Upgrade ke baad dashboard HTTP 200"
    $csrf = [regex]::Match($r.Content, 'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
    # Purani site upgrade ke baad bhi chalti hai
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "start"; site = "ciupgrade" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    $metaUpObj = Get-Content $metaUp | ConvertFrom-Json
    $ok = $false
    foreach ($i in 1..24) {
        try { $fr = Invoke-WebRequest "$($metaUpObj.url)/" -UseBasicParsing -TimeoutSec 30; if ($fr.StatusCode -eq 200) { $ok = $true; break } } catch { Start-Sleep -Seconds 5 }
    }
    Check $ok "v2.6.3 UPG: Purani site upgrade ke baad front page 200"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "ciupgrade" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "ciupgrade" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
    Check (!(Test-Path "$app\sites\ciupgrade")) "v2.6.3 UPG: Test site saaf ho gayi"

    # --- 6i. v2.6.3 FAILURE-PATH: incompatible jora (sirf WP 6.8.3) -> Failed state ---
    # Owner wali asal ghalti ka regression test: dist mein sirf
    # purana WP rakho - creation ko saaf Failed hona chahiye,
    # "Creating..." par latakna NAHI chahiye.
    Move-Item "$wpDist\wordpress-$expectedWp.zip" "$env:TEMP\wp-current.zip" -Force
    Copy-Item "$env:TEMP\wp-6.8.3.zip" "$wpDist\wordpress-6.8.3.zip" -Force
    try {
        Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "create"; name = "cifail"; blueprint = "store"; admin_user = "admin"; admin_email = "a@b.c"; admin_pass = "testpass123"; demo_content = "1" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
        $failMark = "$app\sites\cifail\.create-failed.json"
        $to = (Get-Date).AddMinutes(10)
        while ((Get-Date) -lt $to -and !(Test-Path $failMark)) {
            if (Test-Path "$app\sites\cifail\.webdevhub.json") { throw "cifail ban gayi - incompatible jore par fail hona chahiye tha" }
            Start-Sleep -Seconds 5
        }
        Check (Test-Path $failMark) "v2.6.3 FAIL: Incompatible jore par failure marker bana"
        $fj = Get-Content $failMark -Raw
        Check ($fj -match '"step":"activate"') "v2.6.3 FAIL: Marker mein step=activate hai"
        $homeI = (Invoke-WebRequest "http://127.0.0.1:$dashPort/" -WebSession $sess -UseBasicParsing -TimeoutSec 30).Content
        Check ($homeI -match "cifail") "v2.6.3 FAIL: Dashboard par cifail ka card hai"
        Check ($homeI -match "Fail") "v2.6.3 FAIL: Card Failed state dikhata hai"
        Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cifail" } -WebSession $sess -UseBasicParsing -TimeoutSec 120 | Out-Null
        Check (!(Test-Path "$app\sites\cifail")) "v2.6.3 FAIL: Failed site delete (recovery) ho gaya"
    } finally {
        Remove-Item "$wpDist\wordpress-6.8.3.zip" -Force -ErrorAction SilentlyContinue
        Move-Item "$env:TEMP\wp-current.zip" "$wpDist\wordpress-$expectedWp.zip" -Force -ErrorAction SilentlyContinue
    }
    Check (Test-Path "$wpDist\wordpress-$expectedWp.zip") "v2.6.3 FAIL: Dist wapas asal halat mein hai"

    # --- 7. Stop + delete ---
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "cismoke" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Start-Sleep -Seconds 2
    $still = $true
    try { Invoke-WebRequest "$siteUrl/" -UseBasicParsing -TimeoutSec 5 | Out-Null } catch { $still = $false }
    Check (!$still) "Stop ke baad site band hai"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cismoke" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cismoke")) "Delete: project folder gaya"
}
finally {
    # Diagnostics ke liye state + log PEHLE mehfooz karo:
    # stop_hub.vbs state file delete kar deta hai, is liye
    # workflow diagnostics step ko ye files warna kabhi nahi
    # milengi (v2.8.2 run #25 ka artifact isi liye khaali tha).
    New-Item -ItemType Directory -Force "$env:TEMP\TAMEERO-diagnostics" | Out-Null
    New-Item -ItemType Directory -Force "$PWD\TAMEERO-diagnostics" | Out-Null
    Copy-Item "$app\panel\.dashboard-state.json" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.dashboard.log" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.dashboard-companion.log" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.launcher-trace.log" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.import-trace.log" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$env:TEMP\TAMEERO-upload-watch.log" "$env:TEMP\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.dashboard-state.json" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.dashboard.log" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.dashboard-companion.log" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.launcher-trace.log" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$app\panel\.import-trace.log" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    Copy-Item "$env:TEMP\TAMEERO-upload-watch.log" "$PWD\TAMEERO-diagnostics" -ErrorAction SilentlyContinue
    foreach ($f in @(".dashboard-state.json", ".dashboard.log", ".dashboard-companion.log", ".launcher-trace.log", ".import-trace.log", "TAMEERO-upload-watch.log")) {
        $tLen = "MISSING"
        $wLen = "MISSING"
        if (Test-Path "$env:TEMP\TAMEERO-diagnostics\$f") { $tLen = (Get-Item "$env:TEMP\TAMEERO-diagnostics\$f").Length }
        if (Test-Path "$PWD\TAMEERO-diagnostics\$f") { $wLen = (Get-Item "$PWD\TAMEERO-diagnostics\$f").Length }
        Write-Host "DIAG-PRESERVE $f TEMP:$tLen workspace:$wLen"
    }
    if ($watchJob) { Stop-Job $watchJob -ErrorAction SilentlyContinue; Remove-Job $watchJob -Force -ErrorAction SilentlyContinue }
    Stop-Dashboard
}

# --- 6j. MCP state + uninstall (v2.7.1 negative test) ---
# v2.7.0 ka fail yahi tha: dashboard ka MCP badge count sirf
# PARHNE par .mcp-actions folder bana deta tha, jo uninstall
# ke baad {app}\panel ko zinda rakhta tha. Pehle sirf READ:
& $php -r "require '$app\panel\lib\hub.php'; hub_mcp_pending_count();" 2>$null | Out-Null
Check (!(Test-Path "$app\panel\.mcp-actions")) "6j MCP read: actions folder NAHI bana"
# Phir MCP asal istemal ki state bana kar dekho - [UninstallDelete]
# use bhi saaf kare, warna aakhri check phir fail hoga.
New-Item -ItemType Directory -Force "$app\panel\.mcp-actions" | Out-Null
Set-Content "$app\panel\.mcp-actions\act_smoketest.json" "{}"
Set-Content "$app\panel\.mcp-audit.log" "{}"
Check (Test-Path "$app\panel\.mcp-actions\act_smoketest.json") "6j MCP state banaya (uninstall isay saaf kare ga)"
# v2.8 ki nayi runtime state bhi (MCP server pid/log + chat) -
# [UninstallDelete] inhein bhi saaf kare, warna panel reh jaye ga.
Set-Content "$app\panel\.mcp-server.pid" "999999"
Set-Content "$app\panel\.mcp-server.log" "test"
New-Item -ItemType Directory -Force "$app\panel\.chat" | Out-Null
Set-Content "$app\panel\.chat\test.json" "{}"
Check (Test-Path "$app\panel\.chat\test.json") "6j v2.8 state banaya (uninstall isay saaf kare ga)"

# --- 8. Uninstall ---
$unins = "$app\unins000.exe"
Check (Test-Path $unins) "Uninstaller mojood hai"
Start-Process $unins -ArgumentList "/VERYSILENT","/SUPPRESSMSGBOXES","/NORESTART" -Wait
Start-Sleep -Seconds 2
Check (!(Test-Path "$app\panel")) "Uninstall: dashboard gaya"
Write-Host ""
Write-Host "WINDOWS SMOKE SAB PASS"

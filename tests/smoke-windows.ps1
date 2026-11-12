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
    $list = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=projects" -WebSession $sess -UseBasicParsing -TimeoutSec 30
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
    $home2 = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=projects" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($home2.Content -match "Deploy Package") "v2.4: Card par Deploy Package button hai"
    Check ($home2.Content -match "Preview Link") "v2.4: Card par Preview Link button hai"
    Check ($home2.Content -match "HTTPS Shuru") "v2.4: Card par HTTPS button hai"
    # v2.10 Phase 3: filters + More menu + Details tabs + Themes gallery
    Check ($home2.Content -match 'data-f="updates"') "v2.10: Projects par filters hain"
    Check ($home2.Content -match "menupanel") "v2.10: Card par More menu hai"
    $det = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=site&site=cismoke&tab=settings" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($det.Content -match "PHP version") "v2.10: Details Settings tab par PHP version section hai"
    $thm = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=themes" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($thm.Content -match "theme-dukaan.jpg") "v2.10: Themes gallery mein Dukaan preview hai"
    # v2.10 Phase 4: AI shortcuts + slim tab + frozen bubble rang
    Check ($home2.Content -match "scbtn") "v2.10: AI panel mein quick shortcuts hain"
    Check ($home2.Content -match "railTab") "v2.10: Panel slim tab maujood hai"
    $panelCss = Get-Content (Join-Path $app "panel\public\assets\panel.css") -Raw
    Check ($panelCss -match "1E293B") "v2.10: Chat bubbles ka rang frozen hai"
    Check ($home2.Content -match 'name="lang"') "v2.10 Phase 5: Topbar language dropdown asal hai"
    # v2.8: Zuban + Lite toggle ab Home par nahi, Settings page par
    # hain (Mockup A restyle - run #23 isi par gira tha: Home par
    # "English"/"Lite mode" lafz hi nahi raha). Check bhi wahi hota hai.
    $settingsPage = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=settings" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($settingsPage.StatusCode -eq 200) "v2.5: Settings page HTTP 200"
    Check ($settingsPage.Content -match "navToggle") "v2.10 Phase 6: Hamburger button hai"
    Check ($settingsPage.Content -match "setgroup") "v2.10 Phase 6: Settings group cards"
    Check ($settingsPage.Content -match 'setgh">.{0,3}License') "v2.10 Phase 6: License section reserved"
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
    $expResp = Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "export"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 300 -OutFile "$env:TEMP\ci-export.zip" -PassThru -SkipHttpErrorCheck
    Check (Test-Path "$env:TEMP\ci-export.zip") "v2.8: Export zip bana (6m import ke liye)"
    # v2.10.1 Fix Round 3: asal-zip check - gate runs #1-#3 mein
    # export fail hone par HTML error page zip samajh kar download
    # ho gaya tha aur import ER_NOZIP par gira tha. Ye teen check
    # pass hon tab hi import aage barhta hai: Content-Type HTML
    # nahi, size server wali file (Content-Length) ke barabar,
    # aur magic bytes asal zip wale (50 4B 03 04).
    $expCT = "$($expResp.Headers['Content-Type'])" | Select-Object -First 1
    Check ($expCT -notlike "text/html*") "v2.10.1: Export response HTML nahi hai (status $($expResp.StatusCode), Content-Type: $expCT)"
    $expCL = 0
    [void][long]::TryParse((("$($expResp.Headers['Content-Length'])" | Select-Object -First 1)), [ref]$expCL)
    Check ($expCL -gt 0 -and (Get-Item "$env:TEMP\ci-export.zip").Length -eq $expCL) "v2.10.1: Download size server file ke barabar hai ($expCL bytes)"
    $expBytes = [System.IO.File]::ReadAllBytes("$env:TEMP\ci-export.zip")
    $magicHex = if ($expBytes.Length -ge 4) { (($expBytes[0..3]) | ForEach-Object { $_.ToString("X2") }) -join " " } else { "TOO-SMALL:$($expBytes.Length)" }
    Check ($magicHex -eq "50 4B 03 04") "v2.10.1: Export download asal zip hai (magic: $magicHex)"
    # v2.8.9: export ka size job log mein - run #31 mein body phase ka
    # shak tha lekin size ka fact kabhi log nahi hua tha.
    $ciExportSize = (Get-Item "$env:TEMP\ci-export.zip").Length
    Write-Host "CI-EXPORT-SIZE bytes:$ciExportSize MB:$([math]::Round($ciExportSize / 1MB, 1))"
    # v2.10.1 byte-level diagnosis (gate run #2: SHA match ke
    # bawjood worker ko ER_NOZIP mila): download hui zip ke
    # pehle 8 aur aakhri 48 bytes hex mein, aur server par bani
    # asal export file ke size se muqabla - taake pata chale
    # bytes kis hop par badle.
    $dlAll = [System.IO.File]::ReadAllBytes("$env:TEMP\ci-export.zip")
    $dlHead = (($dlAll[0..7]) | ForEach-Object { $_.ToString("X2") }) -join " "
    $dlTail = (($dlAll[($dlAll.Length - 48)..($dlAll.Length - 1)]) | ForEach-Object { $_.ToString("X2") }) -join " "
    Write-Host "BYTE-DIAG download-size:$($dlAll.Length) head:$dlHead"
    Write-Host "BYTE-DIAG tail:$dlTail"
    $srvZipItem = Get-ChildItem "$app\sites\.exports\*.zip" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($srvZipItem) { Write-Host "BYTE-DIAG server-size:$($srvZipItem.Length) server-name:$($srvZipItem.Name) size-match:$($srvZipItem.Length -eq $dlAll.Length)" }
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
    Check ($v.active_plugins -match "tameero-edit-mode") "v2.13: Dukaan store par Edit Mode plugin ACTIVE hai"
    Check (Test-Path "$app\sites\cidukaan\wp-content\themes\dukaan-fse\tameero-edit-config.json") "v2.13: Dukaan theme mein edit config mojood hai"
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

    # --- 6k2. v2.13: News site FORM ke raste se (theme select NAHI bhejna) ---
    # pk-news bug wala gate check: blueprint ki theme khud lagni chahiye aur
    # Edit Mode plugin site mein ja kar ACTIVE hona chahiye.
    $newsBody = @{
        csrf = $csrf; action = "create"; name = "cinews"; blueprint = "news"
        admin_user = "admin"; admin_email = "admin@example.local"; admin_pass = "cismoke-pass-123"
    }
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body $newsBody -WebSession $sess -UseBasicParsing -TimeoutSec 30 | Out-Null
    $newsMeta = "$app\sites\cinews\.webdevhub.json"
    $newsFail = "$app\sites\cinews\.create-failed.json"
    $ok = $false
    foreach ($i in 1..120) {  # 10 minute tak
        if (Test-Path $newsFail) { throw "FAIL - News create fail ho gayi: $(Get-Content $newsFail -Raw)" }
        if (Test-Path $newsMeta) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.13: News site ban gayi (baghair theme select kiye)"
    $newsJob = Get-Content "$app\sites\cinews.job.log" -Raw
    Check ($newsJob -match "OK activated: tameero-edit-mode") "v2.13: News job log mein plugin activation ka saboot hai"
    Check (Test-Path "$app\sites\cinews\wp-content\plugins\tameero-edit-mode\tameero-edit-mode.php") "v2.13: Edit Mode plugin site mein mojood hai"
    Check (Test-Path "$app\sites\cinews\wp-content\themes\urdu-news\tameero-edit-config.json") "v2.13: Urdu News theme + edit config mojood hai"
    $vraw = & $php "$app\tools\setup-site.php" "--path=$app\sites\cinews" --verify
    $vtext = ($vraw -join "`n"); $vtext = $vtext.Substring($vtext.IndexOf('{'))
    $v = $vtext | ConvertFrom-Json
    Check ($v.theme -eq "urdu-news") "v2.13: Urdu News theme ACTIVE hai (mila: $($v.theme))"
    Check ($v.active_plugins -match "tameero-edit-mode") "v2.13: Edit Mode plugin ACTIVE hai"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cinews" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cinews")) "v2.13: News site delete ho gayi"

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
            $upHome = Invoke-WebRequest "http://127.0.0.1:$upPort/?view=projects" -WebSession $sess -UseBasicParsing -TimeoutSec 30
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

    # v2.10.1 NEG: kharab zip ka import FORAN Failed marker de -
    # 10-minute wala intezaar dobara na ho (test-repo gate run #1
    # ka sabak: marker banta hi nahi tha, fail import dashboard
    # mein nazar hi nahi ata tha).
    Set-Content "$env:TEMP\ci-bad.zip" "this is not a zip" -NoNewline
    $badFin = Invoke-ChunkedImport -ZipPath "$env:TEMP\ci-bad.zip" -SiteName "cibad" -UploadId "cibad-test"
    Check ($badFin -match "redirect") "v2.10.1 NEG: Kharab zip ka finalize redirect JSON deta hai"
    $badFail = "$app\sites\cibad\.create-failed.json"
    $badMeta = "$app\sites\cibad\.webdevhub.json"
    $ok = $false
    foreach ($i in 1..24) {  # 2 minute tak - asal mein chand second
        if (Test-Path $badMeta) { throw "FAIL - v2.10.1 NEG: Kharab zip se site ban gayi" }
        if (Test-Path $badFail) { $ok = $true; break }
        Start-Sleep -Seconds 5
    }
    Check $ok "v2.10.1 NEG: Kharab zip ka import foran Failed marker deta hai"
    $badJob = Get-Content "$app\sites\cibad.job.log" -Raw
    Check ($badJob -match "Zip nahi khuli") "v2.10.1 NEG: Job log mein Zip nahi khuli hai"
    Check ($badJob -match "code=\d+") "v2.10.1 NEG: Job log mein asal error code hai"
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cibad" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cibad")) "v2.10.1 NEG: Failed import dashboard se delete ho gaya"

    # --- 6n. v2.8.15: chat gateway key (Authorization header) ---
    # Stub gateway (bundled PHP) request capture kar ke sabit karta
    # hai: key set ho to "Authorization: Bearer <key>" jata hai; key
    # khaali ho to header NAHI jata (purana bartao). Asal installed
    # chat.php hi chalti hai (driver usay include kar ke round chalata
    # hai); settings file waqti badal kar wapas rakh di jati hai.
    $capDir = "$env:TEMP\TAMEERO-chatcap"
    New-Item -ItemType Directory -Force -Path $capDir | Out-Null
    $capLog = Join-Path $capDir "capture.jsonl"
    if (Test-Path $capLog) { Remove-Item $capLog -Force }
    $capRouter = Join-Path $capDir "router.php"
    @'
<?php
$a = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
$h = function_exists('getallheaders') ? getallheaders() : null;
file_put_contents(__DIR__ . '/capture.jsonl', json_encode(array('a' => $a, 'h' => $h, 'b' => file_get_contents('php://input'))) . "\n", FILE_APPEND);
header('Content-Type: application/json');
echo '{"choices":[{"message":{"role":"assistant","content":"stub-ok"}}]}';
'@ | Set-Content -Path $capRouter -Encoding ASCII
    $capDriver = Join-Path $capDir "drive.php"
    @'
<?php
require $argv[1];
try {
    $m = hub_chat_gateway_round( array( array( 'role' => 'user', 'content' => 'hy' ) ), array() );
    echo 'ROUND-OK: ' . ( isset( $m['content'] ) ? $m['content'] : '' ) . "\n";
} catch ( Exception $e ) {
    echo 'ROUND-ERR: ' . $e->getMessage() . "\n";
}
'@ | Set-Content -Path $capDriver -Encoding ASCII
    $setFile = "$app\panel\.settings.json"
    $setBak = Join-Path $capDir "settings.bak"
    $setExisted = Test-Path $setFile
    if ($setExisted) { Copy-Item $setFile $setBak -Force }
    $capProc = Start-Process -FilePath $php -ArgumentList @('-S', '127.0.0.1:20199', '-t', $capDir, $capRouter) -PassThru -WindowStyle Hidden
    try {
        $capUp = $false
        foreach ($i in 1..30) {
            try { Invoke-WebRequest "http://127.0.0.1:20199/" -UseBasicParsing -TimeoutSec 3 | Out-Null; $capUp = $true; break } catch { Start-Sleep -Milliseconds 500 }
        }
        Check $capUp "v2.8.15: Stub gateway (header capture) uth gaya"
        if (Test-Path $capLog) { Remove-Item $capLog -Force }
        if ($setExisted) { $setObj = Get-Content $setFile -Raw | ConvertFrom-Json } else { $setObj = [pscustomobject]@{} }
        $setObj | Add-Member -NotePropertyName chat_gateway_url -NotePropertyValue "http://127.0.0.1:20199/v1" -Force
        $setObj | Add-Member -NotePropertyName chat_model -NotePropertyValue "" -Force
        $setObj | Add-Member -NotePropertyName chat_gateway_key -NotePropertyValue "ci-test-key-123" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $capErr = Join-Path $capDir "driver-err.txt"
        $outKey = & $php $capDriver "$app\panel\lib\chat.php" 2> $capErr | Out-String
        if (Test-Path $capErr) { Write-Host "CHATCAP-DRIVER-ERR(key): $(Get-Content $capErr -Raw)" }
        Write-Host "CHATCAP-OUT(key): $outKey"
        Check ($outKey -match 'ROUND-OK: stub-ok') "v2.8.15: Chat round stub gateway se ho gaya (key ke sath)"
        $capKey = Get-Content $capLog | Select-Object -Last 1
        Check ($capKey -match 'Bearer ci-test-key-123') "v2.8.15: Authorization Bearer header gaya (key set)"
        $setObj | Add-Member -NotePropertyName chat_gateway_key -NotePropertyValue "" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $outNo = & $php $capDriver "$app\panel\lib\chat.php" 2> $capErr | Out-String
        if (Test-Path $capErr) { Write-Host "CHATCAP-DRIVER-ERR(nokey): $(Get-Content $capErr -Raw)" }
        Write-Host "CHATCAP-OUT(nokey): $outNo"
        Check ($outNo -match 'ROUND-OK: stub-ok') "v2.8.15: Chat round stub gateway se ho gaya (baghair key)"
        $capNo = Get-Content $capLog | Select-Object -Last 1
        Check ($capNo -notmatch 'Authorization') "v2.8.15: Key khaali par Authorization header NAHI gaya"
    } finally {
        if ($setExisted) { Copy-Item $setBak $setFile -Force } elseif (Test-Path $setFile) { Remove-Item $setFile -Force }
        if ($capProc -and !$capProc.HasExited) { Stop-Process -Id $capProc.Id -Force -ErrorAction SilentlyContinue }
    }

    # --- 6o. v2.9.0: AI Connection (models + test) aur Brain Folder ---
    # Stub gateway par asal installed chat.php se: models load (key
    # ke sath / baghair), connection test (pass / partial / fail),
    # aur system prompt mein brain marker + projects block (is waqt
    # cismoke site maujood hai). Settings ki file ko haath NAHI
    # lagta - driver ko url/key seedha diye jate hain. Settings
    # page + chat_ping bhi HTTP se azmaye jate hain.
    $connDir = "$env:TEMP\TAMEERO-chatconn"
    New-Item -ItemType Directory -Force -Path $connDir | Out-Null
    $connLog = Join-Path $connDir "capture.jsonl"
    if (Test-Path $connLog) { Remove-Item $connLog -Force }
    $connRouter = Join-Path $connDir "router.php"
    @'
<?php
if (strpos($_SERVER['REQUEST_URI'], '/models') !== false) {
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || $_SERVER['HTTP_AUTHORIZATION'] !== 'Bearer ci-test-key-123') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo '{"error":{"message":"Missing API key"}}';
        exit;
    }
    header('Content-Type: application/json');
    echo '{"data":[{"id":"ci-model-a"},{"id":"ci-model-b"}]}';
    exit;
}
$b = file_get_contents('php://input');
file_put_contents(__DIR__ . '/capture.jsonl', json_encode(array('a' => isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null, 'b' => $b)) . "\n", FILE_APPEND);
header('Content-Type: application/json');
echo '{"choices":[{"message":{"role":"assistant","content":"stub-ok"}}]}';
'@ | Set-Content -Path $connRouter -Encoding ASCII
    $connDriver = Join-Path $connDir "drive.php"
    @'
<?php
require $argv[1];
$url = 'http://127.0.0.1:20200/v1';
try {
    $m = hub_chat_models($url, 'ci-test-key-123');
    echo 'MODELS-OK: ' . implode(',', $m) . "\n";
} catch ( Exception $e ) { echo 'MODELS-ERR: ' . $e->getMessage() . "\n"; }
try {
    hub_chat_models($url, '');
    echo "MODELS-NOKEY: NO-EXC\n";
} catch ( Exception $e ) { echo 'MODELS-NOKEY: ' . (strpos($e->getMessage(), 'Missing API key') !== false ? '1' : '0') . "\n"; }
$t = hub_chat_test_connection($url, 'ci-test-key-123', 'ci-model-a');
echo 'TEST-OK: ' . ($t['ok'] ? '1' : '0') . ' PARTIAL: ' . ($t['partial'] ? '1' : '0') . ' MSG: ' . $t['message'] . "\n";
$t2 = hub_chat_test_connection($url, 'ci-test-key-123', '');
echo 'TEST-NOMODEL: ' . ($t2['ok'] ? '1' : '0') . ' PARTIAL: ' . ($t2['partial'] ? '1' : '0') . "\n";
$t3 = hub_chat_test_connection($url, 'bad-key', 'ci-model-a');
echo 'TEST-BADKEY: ' . ($t3['ok'] ? '1' : '0') . ' MSG: ' . $t3['message'] . "\n";
$t4 = hub_chat_test_connection('http://127.0.0.1:29999/v1', '', 'x');
echo 'TEST-DEAD: ' . ($t4['ok'] ? '1' : '0') . "\n";
$sys = hub_chat_system_prompt();
echo 'SYS-MARKER: ' . (strpos($sys, 'one-click local WordPress environment') !== false ? '1' : '0') . "\n";
echo 'SYS-PROJECTS: ' . (strpos($sys, 'cismoke') !== false ? '1' : '0') . "\n";
echo 'SYS-CAP: ' . (strlen($sys) <= 16000 ? '1' : '0') . "\n";
echo 'BRAIN-OK: ' . (hub_chat_brain_ok() ? '1' : '0') . "\n";
'@ | Set-Content -Path $connDriver -Encoding ASCII
    $connProc = Start-Process -FilePath $php -ArgumentList @('-S', '127.0.0.1:20200', '-t', $connDir, $connRouter) -PassThru -WindowStyle Hidden
    try {
        $connUp = $false
        foreach ($i in 1..30) {
            try { Invoke-WebRequest "http://127.0.0.1:20200/" -UseBasicParsing -TimeoutSec 3 | Out-Null; $connUp = $true; break } catch { Start-Sleep -Milliseconds 500 }
        }
        Check $connUp "v2.9.0: Stub gateway (models/test) uth gaya"
        $connErr = Join-Path $connDir "driver-err.txt"
        $connOut = & $php $connDriver "$app\panel\lib\chat.php" 2> $connErr | Out-String
        if (Test-Path $connErr) { Write-Host "CHATCONN-DRIVER-ERR: $(Get-Content $connErr -Raw)" }
        Write-Host "CHATCONN-OUT: $connOut"
        Check ($connOut -match 'MODELS-OK: ci-model-a,ci-model-b') "v2.9.0: Models load stub se (key ke sath)"
        Check ($connOut -match 'MODELS-NOKEY: 1') "v2.9.0: Models load baghair key fail (Missing API key hint)"
        Check ($connOut -match 'TEST-OK: 1 PARTIAL: 0') "v2.9.0: Connection test pass (model ke sath)"
        Check ($connOut -match 'TEST-NOMODEL: 1 PARTIAL: 1') "v2.9.0: Model khali par partial - jhoota pass nahi"
        Check ($connOut -match 'TEST-BADKEY: 0') "v2.9.0: Ghalat key par test fail"
        Check ($connOut -match 'TEST-DEAD: 0') "v2.9.0: Band gateway par test fail"
        Check ($connOut -match 'SYS-MARKER: 1') "v2.9.0: System prompt mein brain marker"
        Check ($connOut -match 'SYS-PROJECTS: 1') "v2.9.0: System prompt mein projects block (cismoke)"
        Check ($connOut -match 'SYS-CAP: 1') "v2.9.0: System prompt 16000 cap ke andar"
        Check ($connOut -match 'BRAIN-OK: 1') "v2.9.0: Brain folder maujood (installed)"
        $connCap = Get-Content $connLog | Select-Object -Last 1
        Check ($connCap -match 'Bearer ci-test-key-123') "v2.9.0: Test round mein Bearer header gaya"
        Check ($connCap -match 'ping') "v2.9.0: Test round mein ping message gaya"
        $setHtml = (Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=settings" -WebSession $sess -UseBasicParsing -TimeoutSec 30).Content
        Check ($setHtml -match 'id="chatProvider"') "v2.9.0: Settings page par AI Provider dropdown"
        Check ($setHtml -match 'value="openrouter"') "v2.9.0: Presets mein OpenRouter maujood"
        Check ($setHtml -match 'id="chatModelsBtn"') "v2.9.0: Settings page par Models Load button"
        Check ($setHtml -match 'id="chatTestBtn"') "v2.9.0: Settings page par Connection Test button"
        $pingJson = (Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=chat_ping" -WebSession $sess -UseBasicParsing -TimeoutSec 15).Content
        Check ($pingJson -match '"brain":true') "v2.9.0: chat_ping brain:true"
    } finally {
        if ($connProc -and !$connProc.HasExited) { Stop-Process -Id $connProc.Id -Force -ErrorAction SilentlyContinue }
    }

    # --- 6p. v2.9.2: SSE-tolerant chat parser ---
    # Stub gateway SSE jawab deta hai (stream mange baghair bhi):
    # round SSE text + tool_calls, test SSE ok, 401 par saaf auth fail.
    # Asal installed chat.php chalti hai; settings waqti badal kar wapas.
    $sseDir = "$env:TEMP\TAMEERO-chatsse"
    New-Item -ItemType Directory -Force -Path $sseDir | Out-Null
    $sseRouter = Join-Path $sseDir "router.php"
    @'
<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', $path)));
$mode = $parts[0] ?? 'json';
$endpoint = implode('/', array_slice($parts, 1));
if ($endpoint === 'models') {
    header('Content-Type: application/json');
    echo '{"data":[{"id":"ci-sse-model"}]}';
    exit;
}
if ($mode === 'sse-text') {
    header('Content-Type: text/event-stream');
    echo "\xEF\xBB\xBF";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"content\":null},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hello! \"},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"content\":\"CI SSE ok\"},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\r\n";
    echo "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":10,\"completion_tokens\":5}}\r\n";
    echo "data: [DONE]\r\n";
    exit;
}
if ($mode === 'sse-tools') {
    header('Content-Type: text/event-stream');
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call_ci\",\"type\":\"function\",\"function\":{\"name\":\"site_list\",\"arguments\":\"\"}}]},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"{\\\"limit\\\":\"}}]},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"5}\"}}]},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\r\n";
    echo "data: [DONE]\r\n";
    exit;
}
if ($mode === 'sse-trunc') {
    header('Content-Type: text/event-stream');
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call_trunc\",\"type\":\"function\",\"function\":{\"name\":\"site_list\",\"arguments\":\"\"}}]},\"finish_reason\":null}]}\r\n";
    echo "data: {\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"{\\\"limit\\\":\"}}]},\"finish_reason\":null}]}\r\n";
    exit;
}
if ($mode === '401') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"error":{"message":"Missing API key"}}';
    exit;
}
header('Content-Type: application/json');
echo '{"choices":[{"message":{"role":"assistant","content":"stub-ok"}}]}';
'@ | Set-Content -Path $sseRouter -Encoding ASCII
    $sseDriver = Join-Path $sseDir "drive.php"
    @'
<?php
require $argv[1];
$mode = $argv[2];
$base = 'http://127.0.0.1:20201';
if ($mode === 'round-text') {
    try {
        $m = hub_chat_gateway_round(array(array('role' => 'user', 'content' => 'hi')), array());
        echo 'ROUND-SSE-TEXT: ' . (isset($m['content']) ? $m['content'] : '') . "\n";
    } catch (Exception $e) { echo 'ROUND-SSE-TEXT-ERR: ' . $e->getMessage() . "\n"; }
} elseif ($mode === 'round-tools') {
    try {
        $tools = array(array('type' => 'function', 'function' => array('name' => 'site_list', 'description' => 'x', 'parameters' => array('type' => 'object', 'properties' => new stdClass))));
        $m = hub_chat_gateway_round(array(array('role' => 'user', 'content' => 'list')), $tools);
        $tc = isset($m['tool_calls'][0]) ? $m['tool_calls'][0] : null;
        echo 'ROUND-SSE-TOOLS: ' . ($tc ? $tc['id'] . '|' . $tc['function']['name'] : 'NONE') . "\n";
        echo 'ROUND-SSE-TOOLS-ARGS: ' . ($tc ? $tc['function']['arguments'] : 'NONE') . "\n";
    } catch (Exception $e) { echo 'ROUND-SSE-TOOLS-ERR: ' . $e->getMessage() . "\n"; }
} elseif ($mode === 'round-trunc') {
    try {
        $tools = array(array('type' => 'function', 'function' => array('name' => 'site_list', 'description' => 'x', 'parameters' => array('type' => 'object', 'properties' => new stdClass))));
        $m = hub_chat_gateway_round(array(array('role' => 'user', 'content' => 'list')), $tools);
        echo "ROUND-TRUNC: NO-EXC\n";
    } catch (Exception $e) { echo 'ROUND-TRUNC: ' . $e->getMessage() . "\n"; }
} elseif ($mode === 'round-401') {
    try {
        $m = hub_chat_gateway_round(array(array('role' => 'user', 'content' => 'hi')), array());
        echo "ROUND-401: NO-EXC\n";
    } catch (Exception $e) { echo 'ROUND-401: ' . $e->getMessage() . "\n"; }
} elseif ($mode === 'test') {
    $t = hub_chat_test_connection($base . '/sse-text', 'ci-test-key-123', 'ci-sse-model');
    echo 'TEST-SSE: ' . ($t['ok'] ? '1' : '0') . "\n";
    $t2 = hub_chat_test_connection($base . '/401', 'ci-test-key-123', 'ci-sse-model');
    echo 'TEST-401: ' . ($t2['ok'] ? '1' : '0') . ' MSG: ' . $t2['message'] . "\n";
}
'@ | Set-Content -Path $sseDriver -Encoding ASCII
    $setFile = "$app\panel\.settings.json"
    $setBak = Join-Path $sseDir "settings.bak"
    $setExisted = Test-Path $setFile
    if ($setExisted) { Copy-Item $setFile $setBak -Force }
    $sseProc = Start-Process -FilePath $php -ArgumentList @('-S', '127.0.0.1:20201', '-t', $sseDir, $sseRouter) -PassThru -WindowStyle Hidden
    try {
        $sseUp = $false
        foreach ($i in 1..30) {
            try { Invoke-WebRequest "http://127.0.0.1:20201/" -UseBasicParsing -TimeoutSec 3 | Out-Null; $sseUp = $true; break } catch { Start-Sleep -Milliseconds 500 }
        }
        Check $sseUp "v2.9.2: Stub gateway (SSE) uth gaya"
        if ($setExisted) { $setObj = Get-Content $setFile -Raw | ConvertFrom-Json } else { $setObj = [pscustomobject]@{} }
        $sseErr = Join-Path $sseDir "driver-err.txt"
        $setObj | Add-Member -NotePropertyName chat_gateway_url -NotePropertyValue "http://127.0.0.1:20201/sse-text" -Force
        $setObj | Add-Member -NotePropertyName chat_model -NotePropertyValue "ci-sse-model" -Force
        $setObj | Add-Member -NotePropertyName chat_gateway_key -NotePropertyValue "ci-test-key-123" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $outText = & $php $sseDriver "$app\panel\lib\chat.php" round-text 2> $sseErr | Out-String
        if (Test-Path $sseErr) { Write-Host "CHATSSE-DRIVER-ERR(text): $(Get-Content $sseErr -Raw)" }
        Write-Host "CHATSSE-OUT(text): $outText"
        Check ($outText -match 'ROUND-SSE-TEXT: Hello! CI SSE ok') "v2.9.2: SSE text round - content jura"
        $setObj | Add-Member -NotePropertyName chat_gateway_url -NotePropertyValue "http://127.0.0.1:20201/sse-tools" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $outTools = & $php $sseDriver "$app\panel\lib\chat.php" round-tools 2> $sseErr | Out-String
        if (Test-Path $sseErr) { Write-Host "CHATSSE-DRIVER-ERR(tools): $(Get-Content $sseErr -Raw)" }
        Write-Host "CHATSSE-OUT(tools): $outTools"
        Check ($outTools -match 'ROUND-SSE-TOOLS: call_ci\|site_list') "v2.9.2: SSE tool_calls - id aur name durust"
        Check ($outTools -match '"limit":5') "v2.9.2: SSE tool_calls - arguments jure"
        $setObj | Add-Member -NotePropertyName chat_gateway_url -NotePropertyValue "http://127.0.0.1:20201/sse-trunc" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $outTrunc = & $php $sseDriver "$app\panel\lib\chat.php" round-trunc 2> $sseErr | Out-String
        if (Test-Path $sseErr) { Write-Host "CHATSSE-DRIVER-ERR(trunc): $(Get-Content $sseErr -Raw)" }
        Write-Host "CHATSSE-OUT(trunc): $outTrunc"
        Check ($outTrunc -match 'adhoora tool call') "v2.9.2: Adhoora tool call execute nahi hota"
        $setObj | Add-Member -NotePropertyName chat_gateway_url -NotePropertyValue "http://127.0.0.1:20201/401" -Force
        $setObj | ConvertTo-Json -Compress | Set-Content -Path $setFile -Encoding ASCII
        $out401 = & $php $sseDriver "$app\panel\lib\chat.php" round-401 2> $sseErr | Out-String
        if (Test-Path $sseErr) { Write-Host "CHATSSE-DRIVER-ERR(401): $(Get-Content $sseErr -Raw)" }
        Write-Host "CHATSSE-OUT(401): $out401"
        Check ($out401 -match 'key qabool nahi ki') "v2.9.2: Asal 401 par saaf auth error"
        Check ($out401 -match 'Missing API key') "v2.9.2: 401 mein gateway ka asal paigham"
        $outTest = & $php $sseDriver "$app\panel\lib\chat.php" test 2> $sseErr | Out-String
        if (Test-Path $sseErr) { Write-Host "CHATSSE-DRIVER-ERR(test): $(Get-Content $sseErr -Raw)" }
        Write-Host "CHATSSE-OUT(test): $outTest"
        Check ($outTest -match 'TEST-SSE: 1') "v2.9.2: Connection test SSE jawab par PASS"
        Check ($outTest -match 'TEST-401: 0') "v2.9.2: Connection test 401 par fail"
        Check ($outTest -match 'Missing API key') "v2.9.2: Test 401 mein asal paigham"
    } finally {
        if ($setExisted) { Copy-Item $setBak $setFile -Force } elseif (Test-Path $setFile) { Remove-Item $setFile -Force }
        if ($sseProc -and !$sseProc.HasExited) { Stop-Process -Id $sseProc.Id -Force -ErrorAction SilentlyContinue }
    }

    # --- 6h. v2.6.3 UPGRADE-PATH: stale zip + mojooda site -> in-place upgrade -> dobara create ---
    # Asal bug isi raste par tha: upgraded PC par dist mein purani
    # wordpress-6.8.3.zip pari rehti thi aur creation usi se banti
    # thi. Yahan wahi halat jaan boojh kar banayi jati hai.
    # v2.9.0: Brain owner-edit hifazat - upgrade se PEHLE installed
    # brain file mein marker line dalo (owner ki edit ki naqal);
    # upgrade ke baad wahi line maujood honi chahiye. Ye asal
    # upgrade test hai - sirf .iss parhna nahi chalega.
    $brainRule = "$app\panel\ai-brain\03-rules.md"
    Check (Test-Path $brainRule) "v2.9.0 UPG: Installed brain file maujood hai"
    Add-Content -Path $brainRule -Value "`n# UPG-MARKER-v290: owner ki edit - upgrade ke baad bhi rehni chahiye"
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
    $brainRuleAfter = Get-Content $brainRule -Raw -ErrorAction SilentlyContinue
    Check ($brainRuleAfter -match 'UPG-MARKER-v290') "v2.9.0 UPG: Owner-edit brain line upgrade ke baad bhi maujood (onlyifdoesntexist)"
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

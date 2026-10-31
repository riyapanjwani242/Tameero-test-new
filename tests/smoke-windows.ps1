# WebDevHub — Windows install smoke (CI, asal imtihan)
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

# --- 3. Dashboard start (runtime PHP se, VBS ke baghair taake CI mein browser na khule) ---
$env:WDH_NO_WIZARD = "1" # wizard ka pehla-run redirect smoke mein nahi (Linux par alag azmaya jata hai)
$dashPort = 7777
$dash = Start-Process $php -ArgumentList "-S","127.0.0.1:$dashPort","-t","$app\panel\public","$app\panel\server.php" -PassThru -WindowStyle Hidden
try {
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
    # Tasdeeq: plugins view par Code Snippets wali ROW mein hi ● Chalu ho
    $pv2 = Invoke-WebRequest "http://127.0.0.1:$dashPort/?view=plugins&site=cismoke" -WebSession $sess -UseBasicParsing -TimeoutSec 60
    Check ($pv2.Content -match "Code Snippets[\s\S]{0,500}● Chalu") "v2.3: Plugin toggle chalu hua"
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
    Check ($home2.Content -match "English") "v2.5: Zuban toggle (English button) mojood hai"
    Check ($home2.Content -match "Lite mode") "v2.5: Lite mode toggle mojood hai"

    # --- 6f. v2.6.2: Store site BORN-COMPLETE (owner faisla 2026-10-31 — FULL E2E shart) ---
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
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "stop"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Invoke-WebRequest "http://127.0.0.1:$dashPort/" -Method Post -Body @{ csrf = $csrf; action = "delete"; site = "cistore" } -WebSession $sess -UseBasicParsing -TimeoutSec 60 | Out-Null
    Check (!(Test-Path "$app\sites\cistore")) "v2.6.2 E2E: Store delete ho gaya"

    # --- 6g. v2.6.2: NEGATIVE hang test — timeout + Failed state + dashboard recovery ---
    # Doosra dashboard (alag port) stall env ke sath: worker ko env
    # dashboard process se inherit hota hai (wscript -> .cmd chain).
    $env:WDH_TEST_STALL = "tool:demo-content"
    $env:WDH_STEP_TIMEOUT = "20"
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
    if ($dash -and !$dash.HasExited) { Stop-Process -Id $dash.Id -Force -ErrorAction SilentlyContinue }
}

# --- 8. Uninstall ---
$unins = "$app\unins000.exe"
Check (Test-Path $unins) "Uninstaller mojood hai"
Start-Process $unins -ArgumentList "/VERYSILENT","/SUPPRESSMSGBOXES","/NORESTART" -Wait
Start-Sleep -Seconds 2
Check (!(Test-Path "$app\panel")) "Uninstall: dashboard gaya"
Write-Host ""
Write-Host "WINDOWS SMOKE SAB PASS"

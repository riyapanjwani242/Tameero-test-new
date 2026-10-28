# WebDevHub — Windows PHP runtime fetch (packaging ke liye)
# PHP 8.4 (NTS, VS17, x64) windows.php.net se + VC++ redistributable
# aka.ms se download karke repo ke runtime\ folder mein tayyar karta hai.
# Ye binaries repo mein commit NAHI hote (.gitignore) — CI aur local
# packaging dono is script ko chala kar runtime tayyar karte hain.
#
# Version pin: 8.4.27-1 (rebuild suffix "-1" lazmi hai — baghair is ke
# file 404 deti hai; pehle CI run mein yahi fail pakra gaya tha).
# Saath mein official SHA256 (releases.json se) verify hota hai.
# Agar kabhi pin purana ho jaye, script releases.json se mojooda
# 8.4 NTS VS17 x64 build khud dhoondh leti hai (file + hash dono).
#
# Usage:  pwsh installer-windows/fetch-runtime.ps1
#         (repo root se chalao; script khud paths nikalti hai)

$ErrorActionPreference = "Stop"

$PhpVersion = "8.4.27-1"
$PhpZipName = "php-$PhpVersion-nts-Win32-vs17-x64.zip"
$PhpSha256  = "dbae154a0a89791f36c3d48e68bae797878333e7d6cb5721e74617de59b63ab7"
$PhpUrl     = "https://windows.php.net/downloads/releases/$PhpZipName"
$RedistUrl  = "https://aka.ms/vs/17/release/vc_redist.x64.exe"

$RepoRoot   = Split-Path -Parent $PSScriptRoot
$RuntimeDir = Join-Path $RepoRoot "runtime"
$PhpDir     = Join-Path $RuntimeDir "php"
$CacheDir   = Join-Path $RuntimeDir ".cache"

New-Item -ItemType Directory -Force $RuntimeDir, $CacheDir | Out-Null

# --- PHP ---
$zipPath = Join-Path $CacheDir $PhpZipName
if (!(Test-Path $zipPath)) {
    Write-Host "PHP $PhpVersion download ho raha hai..."
    try {
        Invoke-WebRequest $PhpUrl -OutFile $zipPath
    } catch {
        # Pin purana ho gaya hoga — releases.json se mojooda 8.4 build lo
        Write-Host "Pinned file nahi mili — releases.json se taza 8.4 build dhoondh raha hoon..."
        $rel = Invoke-RestMethod "https://windows.php.net/downloads/releases/releases.json"
        $entry = $rel.'8.4'.'nts-vs17-x64'.zip
        $PhpZipName = $entry.path
        $PhpSha256  = $entry.sha256
        $zipPath    = Join-Path $CacheDir $PhpZipName
        Invoke-WebRequest "https://windows.php.net/downloads/releases/$PhpZipName" -OutFile $zipPath
    }
}
$actual = (Get-FileHash $zipPath -Algorithm SHA256).Hash.ToLower()
if ($actual -ne $PhpSha256) { throw "PHP zip ka SHA256 match nahi hua — download kharab hai." }
Write-Host "SHA256 verify ho gaya."
if (Test-Path $PhpDir) { Remove-Item -Recurse -Force $PhpDir }
New-Item -ItemType Directory -Force $PhpDir | Out-Null
Expand-Archive $zipPath -DestinationPath $PhpDir -Force

# Hamari php.ini lagao (extensions waghera isi se chalte hain)
Copy-Item (Join-Path $PSScriptRoot "php.ini") (Join-Path $PhpDir "php.ini") -Force

# --- VC++ redistributable (PHP VS17 build ke liye lazmi — class note #4) ---
$redistPath = Join-Path $RuntimeDir "vc_redist.x64.exe"
if (!(Test-Path $redistPath)) {
    Write-Host "VC++ redistributable download ho raha hai..."
    Invoke-WebRequest $RedistUrl -OutFile $redistPath
}

# --- Verify: php.exe asal mein chale (redist ke baghair yehi crash hota hai) ---
$ver = & (Join-Path $PhpDir "php.exe") -v
Write-Host "Runtime tayyar hai: $($ver[0])"
$mods = & (Join-Path $PhpDir "php.exe") -m
foreach ($need in @("curl","gd","intl","mbstring","openssl","pdo_sqlite","sqlite3","zip","fileinfo","exif")) {
    if ($mods -notcontains $need) { throw "Extension missing hai: $need" }
}
Write-Host "Saari zaroori extensions mojood hain."

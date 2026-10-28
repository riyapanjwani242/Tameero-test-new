; WebDev Hub Studio v2 — Inno Setup script (improved)
; v1 se behtari: fixed AppId, per-user install, saaf uninstaller,
; version metadata mukammal, signing-ready (neeche SignTool note dekho).
; Build: GitHub Actions windows runner par ISCC se compile hota hai.
; Bundle: ..\bundle ka shared package — Windows/Linux EK hi bundle.

#define MyAppName "WebDev Hub Studio"
#define MyAppVersion "2.0.0"
#define MyAppPublisher "WebDev Hub Studio"
#define MyAppExeName "WebDevHub.exe"

[Setup]
AppId={{8F3A2C41-9D4E-4B7A-A1C2-5E6F7D8B9A01}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
VersionInfoVersion={#MyAppVersion}
VersionInfoCompany={#MyAppPublisher}
VersionInfoDescription={#MyAppName} — one-click local WordPress
DefaultDirName={localappdata}\Programs\WebDevHub
DefaultGroupName={#MyAppName}
PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=dialog
OutputDir=..\build\windows
OutputBaseFilename=WebDevHub_Setup_v{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
; Code-signing: certificate milne par neeche wali do lines enable karna
; (docs/code-signing.md dekho — certificate owner ka faisla hai).
; SignTool=webdevhub
; SignedUninstaller=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Desktop shortcut banao"; GroupDescription: "Shortcuts:"

[Files]
; Shared bundle — poora package (WP core dist packaging ke waqt
; fetch steps se tayyar hota hai — CI mein windows-build.yml dekho)
Source: "..\bundle\*"; DestDir: "{app}\bundle"; Flags: recursesubdirs createallsubdirs; Excludes: "plugins\dist\*.tmp"
; Site setup tools (setup-site.php khud bundle ka rasta apne parent
; folder se nikalta hai — tools ka parent {app} hai, bundle wahi hai)
Source: "..\installer-linux\setup-site.php"; DestDir: "{app}\tools"; Flags: ignoreversion
Source: "..\installer-linux\router.php"; DestDir: "{app}\tools"; Flags: ignoreversion
Source: "..\installer-linux\reset-admin.php"; DestDir: "{app}\tools"; Flags: ignoreversion
; Dashboard (product ka dil) — bin\worker sameet
Source: "..\panel\*"; DestDir: "{app}\panel"; Flags: recursesubdirs createallsubdirs; Excludes: ".dashboard.*"
; PHP runtime (packaging pass): fetch-runtime.ps1 se tayyar hota hai
Source: "..\runtime\php\*"; DestDir: "{app}\runtime\php"; Flags: recursesubdirs createallsubdirs
; VC++ redistributable — PHP (VS17) ke liye lazmi (class note #4).
; Sirf tab install hota hai jab pehle se mojood na ho (NeedsVCRedist).
Source: "..\runtime\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall; Check: NeedsVCRedist
; Launchers: start (port khud dhoondhta hai) + stop (ek click band)
Source: "launch_hub.vbs"; DestDir: "{app}"; Flags: ignoreversion
Source: "stop_hub.vbs"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\launch_hub.vbs"""
Name: "{group}\Stop {#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\stop_hub.vbs"""
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\launch_hub.vbs"""; Tasks: desktopicon
Name: "{autodesktop}\Stop {#MyAppName}"; Filename: "wscript.exe"; Parameters: """{app}\stop_hub.vbs"""; Tasks: desktopicon

[Run]
; Pehle VC++ runtime (khamoshi se) — warna php.exe crash hota hai
Filename: "{tmp}\vc_redist.x64.exe"; Parameters: "/install /quiet /norestart"; StatusMsg: "Microsoft runtime lag raha hai..."; Check: NeedsVCRedist; Flags: waituntilterminated
; Post-install: dashboard pehli dafa khud khulta hai (silent launcher)
Filename: "wscript.exe"; Parameters: """{app}\launch_hub.vbs"""; Description: "{#MyAppName} Dashboard chalao"; Flags: nowait postinstall skipifsilent

; Uninstall par sirf panel ke APNE banaye hue runtime files saaf hote
; hain — ye Inno ke manifest mein nahi hote, in ke baghair {app}\panel
; uninstall ke baad reh jata tha (CI smoke Run #3 mein pakra gaya).
; User ke projects ({app}\sites) jaan boojh kar is list mein NAHI hain —
; uninstall par projects delete nahi hote. Data pehle.
[UninstallDelete]
Type: filesandordirs; Name: "{app}\panel\.run"
Type: files; Name: "{app}\panel\.dashboard-state.json"
Type: files; Name: "{app}\panel\.dashboard.log"

[Code]
function NeedsVCRedist(): Boolean;
var
  Installed: Cardinal;
begin
  // VC++ 2015-2022 x64 runtime ki registry pehchan
  Result := True;
  if RegQueryDWordValue(HKLM, 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Installed', Installed) then
    if Installed = 1 then
      Result := False;
end;

function InitializeSetup(): Boolean;
begin
  Result := True;
  // v2 design: admin sirf tab chahiye jab hosts/service ki zaroorat ho.
  // Default install per-user hai (PrivilegesRequired=lowest) taake
  // pehli screen par hi UAC ki rukawat na aye.
end;

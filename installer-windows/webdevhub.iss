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
; Shared bundle — poora package (WP core dist installer ke waqt fetch-bundle se tayyar hota hai)
Source: "..\bundle\*"; DestDir: "{app}\bundle"; Flags: recursesubdirs createallsubdirs; Excludes: "plugins\dist\*.tmp"
Source: "..\installer-linux\setup-site.php"; DestDir: "{app}\tools"; Flags: ignoreversion
; Panel/launcher baad ke phase mein yahan add hoga.

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{#MyAppName} chalao"; Flags: nowait postinstall skipifsilent

[UninstallDelete]
Type: filesandordirs; Name: "{app}\sites"

[Code]
function InitializeSetup(): Boolean;
begin
  Result := True;
  // v2 design: admin sirf tab chahiye jab hosts/service ki zaroorat ho.
  // Default install per-user hai (PrivilegesRequired=lowest) taake
  // pehli screen par hi UAC ki rukawat na aye.
end;

' WebDev Hub — Silent Launcher (Windows)
' Class wale pattern par: koi console window nahi ati, dashboard
' peeche chalta hai aur browser mein khul jata hai.
' Install layout (Inno): {app}\panel, {app}\runtime\php\php.exe
' NOTE: runtime\php ki asal packaging (PHP binaries) Windows CI step
' mein finalize hoti hai — dekho installer-windows\README.md.

Dim shell, fso, appDir, phpExe, panelDir, port, url
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

appDir = fso.GetParentFolderName(WScript.ScriptFullName)
' Agar ye script installer-windows folder se chal rahi hai (dev mode),
' to ek level upar hi app dir hai; install ke baad ye {app} mein hoti hai.
phpExe = appDir & "\runtime\php\php.exe"
panelDir = appDir & "\panel"
If Not fso.FileExists(phpExe) Then
  phpExe = appDir & "\..\runtime\php\php.exe"
  panelDir = appDir & "\..\panel"
End If

port = "7777"
url = "http://127.0.0.1:" & port & "/"

' Dashboard peeche chalao (window bilkul hidden = 0)
Dim cmd
cmd = """" & phpExe & """ -S 127.0.0.1:" & port & " -t """ & panelDir & "\public"" """ & panelDir & "\server.php"""
shell.Run cmd, 0, False

' Server ko waqt do, phir browser kholo
WScript.Sleep 2500
shell.Run url, 1, False

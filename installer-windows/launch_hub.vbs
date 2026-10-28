' WebDev Hub — Silent Launcher (Windows) v2
' Class checklist ke mutabiq:
'   - Koi hardcoded path nahi — script apni jagah se app dir nikalta hai
'   - Dashboard pehle se chal raha ho to sirf browser khulta hai
'   - Port khud dhoondhta hai (7777 se 7780) — jo pehle jawab de
'   - Server khamoshi se (hidden), state file mein port mehfooz —
'     stop_hub.vbs usi se dashboard band karta hai
' Install layout (Inno): {app}\panel, {app}\runtime\php\php.exe

Dim shell, fso, appDir, phpExe, panelDir, stateFile, runDir
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

appDir = fso.GetParentFolderName(WScript.ScriptFullName)
phpExe = appDir & "\runtime\php\php.exe"
panelDir = appDir & "\panel"
If Not fso.FileExists(phpExe) Then
  ' Dev mode: script installer-windows folder se chal rahi ho
  phpExe = appDir & "\..\runtime\php\php.exe"
  panelDir = appDir & "\..\panel"
End If
stateFile = panelDir & "\.dashboard-state.json"
runDir = panelDir & "\.run"

Function ProbeDashboard(p)
  ' Is port par HAMARA dashboard jawab de raha hai?
  Dim http
  ProbeDashboard = False
  On Error Resume Next
  Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
  http.setTimeouts 400, 400, 400, 400
  http.Open "GET", "http://127.0.0.1:" & p & "/", False
  http.Send
  If Err.Number = 0 Then
    If http.Status = 200 And InStr(http.responseText, "WebDev Hub") > 0 Then
      ProbeDashboard = True
    End If
  End If
  Err.Clear
  On Error GoTo 0
End Function

Function WaitDashboard(p, seconds)
  Dim i
  WaitDashboard = False
  For i = 1 To seconds * 2
    If ProbeDashboard(p) Then
      WaitDashboard = True
      Exit Function
    End If
    WScript.Sleep 500
  Next
End Function

Sub SaveState(p)
  Dim f
  Set f = fso.CreateTextFile(stateFile, True)
  f.Write "{""port"":" & p & "}"
  f.Close
End Sub

Sub OpenBrowser(p)
  shell.Run "http://127.0.0.1:" & p & "/", 1, False
End Sub

Dim port, started
started = False

' 1) Pehle se chal raha hai? Sirf browser kholo.
For port = 7777 To 7780
  If ProbeDashboard(port) Then
    SaveState port
    OpenBrowser port
    WScript.Quit 0
  End If
Next

' 2) Naya server: pehla port jo asal mein jawab dene lage.
If Not fso.FolderExists(runDir) Then fso.CreateFolder(runDir)
For port = 7777 To 7780
  Dim cmdFile, tf, cmdLine
  cmdFile = runDir & "\dashboard.cmd"
  Set tf = fso.CreateTextFile(cmdFile, True)
  tf.WriteLine "@echo off"
  cmdLine = """" & phpExe & """ -S 127.0.0.1:" & port & _
            " -t """ & panelDir & "\public"" """ & panelDir & "\server.php""" & _
            " >> """ & panelDir & "\.dashboard.log"" 2>&1"
  tf.WriteLine cmdLine
  tf.Close
  shell.Run "cmd /C """ & cmdFile & """", 0, False
  If WaitDashboard(port, 10) Then
    SaveState port
    OpenBrowser port
    started = True
    Exit For
  End If
Next

If Not started Then
  MsgBox "WebDev Hub dashboard start nahi ho saka. Log dekho: " & panelDir & "\.dashboard.log", vbExclamation, "WebDev Hub"
End If

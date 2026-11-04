' TAMEERO — Silent Launcher (Windows)
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
traceFile = panelDir & "\.launcher-trace.log"

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
    If http.Status = 200 And InStr(http.responseText, "TAMEERO") > 0 Then
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

Sub SaveState2(p, up)
  Dim f
  Set f = fso.CreateTextFile(stateFile, True)
  If up > 0 Then
    f.Write "{""port"":" & p & ",""upload_port"":" & up & "}"
  Else
    f.Write "{""port"":" & p & "}"
  End If
  f.Close
End Sub

' Trace: launcher ka har faisla waqt ke sath likho (v2.8.4).
' File har line ke baad band hoti hai, is liye chalte server
' ke bawajood CI diagnostics ise copy kar sakta hai. Agar
' launcher state likhe baghair mar jaye to isi file se pata
' chalega ke woh kahan ruka tha (run #26 ka sabak).
Sub Trace(msg)
  Dim tf
  On Error Resume Next
  Set tf = fso.OpenTextFile(traceFile, 8, True)
  tf.WriteLine Now & " " & msg
  tf.Close
  On Error GoTo 0
End Sub

' Ek dashboard server process chalao (port p par) — khamoshi se.
Sub StartServer(p)
  Dim cmdFile, tf, cmdLine
  cmdFile = runDir & "\dashboard-" & p & ".cmd"
  Set tf = fso.CreateTextFile(cmdFile, True)
  tf.WriteLine "@echo off"
  cmdLine = """" & phpExe & """ -S 127.0.0.1:" & p & _
            " -d upload_max_filesize=1024M -d post_max_size=1024M -d memory_limit=1024M" & _
            " -t """ & panelDir & "\public"" """ & panelDir & "\server.php""" & _
            " >> """ & panelDir & "\.dashboard.log"" 2>&1"
  tf.WriteLine cmdLine
  tf.Close
  shell.Run "cmd /C """ & cmdFile & """", 0, False
End Sub

Sub OpenBrowser(p)
  shell.Run "http://127.0.0.1:" & p & "/", 1, False
End Sub

Dim port, started
started = False
Trace "=== LAUNCHER START appDir=" & appDir & " ==="

' 1) Pehle se chal raha hai? Sirf browser kholo (state file ko
' haath nahi lagate — us mein upload_port bhi hota hai).
For port = 7777 To 7780
  If ProbeDashboard(port) Then
    Trace "ALREADY-RUNNING port=" & port
    OpenBrowser port
    WScript.Quit 0
  End If
Next

' 2) Naya server: pehla port jo asal mein jawab dene lage. Sath
' ek dusra server (port+50) sirf bari uploads ke liye — Windows
' par php -S single-thread hota hai; 451MB wali import ke
' dauraan dashboard murda na lage (v2.8 import-hang fix).
If Not fso.FolderExists(runDir) Then fso.CreateFolder(runDir)
For port = 7777 To 7780
  Trace "STARTSERVER main port=" & port
  StartServer port
  If WaitDashboard(port, 10) Then
    Trace "WAIT main port=" & port & " OK"
    Trace "STARTSERVER companion port=" & (port + 50)
    StartServer port + 50
    If WaitDashboard(port + 50, 30) Then
      Trace "WAIT companion port=" & (port + 50) & " OK"
      SaveState2 port, port + 50
      Trace "SAVESTATE port=" & port & " upload_port=" & (port + 50)
    Else
      ' Companion nahi utha: state mein upload_port ka jhoota
      ' dawa nahi likhna - dashboard phir bhi chalega, import
      ' main server se hogi (sathi ke baghair).
      Trace "WAIT companion port=" & (port + 50) & " TIMEOUT 30s"
      SaveState2 port, 0
      Trace "SAVESTATE port=" & port & " upload_port=NONE"
    End If
    OpenBrowser port
    started = True
    Exit For
  Else
    Trace "WAIT main port=" & port & " TIMEOUT 10s"
  End If
Next

If Not started Then
  Trace "START FAILED: koi port 10s mein jawab nahi de saka"
  MsgBox "TAMEERO dashboard start nahi ho saka. Log dekho: " & panelDir & "\.dashboard.log", vbExclamation, "TAMEERO"
End If

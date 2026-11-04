' TAMEERO — Stop (Windows)
' Dashboard ko ek click mein band karta hai. Port pehle state file
' se, warna 7777..7780 scan karke netstat se listener ki pid nikaal
' kar sirf USI process ko band karta hai (process isolation — class
' checklist #6: kisi aur server/XAMPP ko haath nahi lagta).

Dim shell, fso, appDir, panelDir, stateFile, port
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

appDir = fso.GetParentFolderName(WScript.ScriptFullName)
panelDir = appDir & "\panel"
If Not fso.FolderExists(panelDir) Then panelDir = appDir & "\..\panel"
stateFile = panelDir & "\.dashboard-state.json"

port = 0
If fso.FileExists(stateFile) Then
  Dim txt, m
  txt = fso.OpenTextFile(stateFile).ReadAll
  With New RegExp
    .Pattern = """port""\s*:\s*(\d+)"
    Set m = .Execute(txt)
    If m.Count > 0 Then port = CLng(m(0).SubMatches(0))
  End With
End If

Sub KillPort(p)
  Dim exec, out, line, pid
  Set exec = shell.Exec("netstat -ano -p TCP")
  out = exec.StdOut.ReadAll
  Dim re, matches
  Set re = New RegExp
  re.Pattern = "127\.0\.0\.1:" & p & "\s+\S+\s+LISTENING\s+(\d+)"
  re.Global = True
  Set matches = re.Execute(out)
  Dim mm
  For Each mm In matches
    pid = mm.SubMatches(0)
    shell.Run "taskkill /PID " & pid & " /F", 0, True
  Next
End Sub

' Sathi upload server (port+50) bhi band karo — state file se
' upload_port parh kar (v2.8).
Dim uport
uport = 0
If fso.FileExists(stateFile) Then
  Dim txt2, m2
  txt2 = fso.OpenTextFile(stateFile).ReadAll
  With New RegExp
    .Pattern = """upload_port""\s*:\s*(\d+)"
    Set m2 = .Execute(txt2)
    If m2.Count > 0 Then uport = CLng(m2(0).SubMatches(0))
  End With
End If

If port > 0 Then
  KillPort port
Else
  Dim p
  For p = 7777 To 7780
    KillPort p
  Next
End If
If uport > 0 Then KillPort uport

' MCP server bhi band karo — PID file wali asal process hi mare
' gi (port par koi aur ho to use haath nahi lage ga).
Dim pidFile, mpid
pidFile = panelDir & "\.mcp-server.pid"
If fso.FileExists(pidFile) Then
  mpid = Trim(fso.OpenTextFile(pidFile).ReadAll)
  If IsNumeric(mpid) And CLng(mpid) > 0 Then
    shell.Run "taskkill /PID " & CLng(mpid) & " /F", 0, True
  End If
  fso.DeleteFile pidFile
End If

If fso.FileExists(stateFile) Then fso.DeleteFile stateFile

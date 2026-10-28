' WebDevHub — hidden runner (Windows)
' Ek .cmd file ko bilkul khamoshi se (koi window nahi) chalata hai.
' Istemal: dashboard/worker isay background kaamon ke liye bulata hai
' (site banana, site server chalana) — cmd file ke andar asal command
' aur us ki log redirection hoti hai.
' Usage: wscript.exe run-hidden.vbs "C:\path\to\job.cmd"

Dim shell, target
If WScript.Arguments.Count < 1 Then WScript.Quit 1
target = WScript.Arguments(0)
Set shell = CreateObject("WScript.Shell")
shell.Run "cmd /C """ & target & """", 0, False

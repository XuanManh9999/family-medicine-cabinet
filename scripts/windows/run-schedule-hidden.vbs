' MedCabinet - goi "php artisan schedule:run" ma khong hien cua so (dung boi Task Scheduler)
' Tham so: <duong dan php.exe> <duong dan file artisan>
If WScript.Arguments.Count < 2 Then WScript.Quit 1

Dim phpBin, artisan, workDir
phpBin = WScript.Arguments(0)
artisan = WScript.Arguments(1)
workDir = CreateObject("Scripting.FileSystemObject").GetParentFolderName(artisan)

Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = workDir
shell.Run """" & phpBin & """ """ & artisan & """ schedule:run", 0, False

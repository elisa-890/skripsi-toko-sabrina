' =====================================================================
'  Buka_Toko_Sabrina.vbs
'  ---------------------------------------------------------------
'  Peluncur "senyap" -- menjalankan Jalankan_Toko_Sabrina.bat tanpa
'  memunculkan jendela hitam Command Prompt. Buat SHORTCUT ke file
'  INI (bukan ke file .bat) untuk ditaruh di Desktop, supaya
'  pengalamannya seperti membuka aplikasi biasa.
' =====================================================================
Set objShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
strFolder = fso.GetParentFolderName(WScript.ScriptFullName)
objShell.Run """" & strFolder & "\Jalankan_Toko_Sabrina.bat""", 0, False

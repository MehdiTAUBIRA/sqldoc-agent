!macro customUnInstallBegin
    DetailPrint "Stopping processes..."
    nsExec::Exec 'taskkill /F /IM "SQLINFO-Agent.exe" /T'
    nsExec::Exec 'taskkill /F /IM "electron.exe" /T'
    nsExec::Exec 'taskkill /F /IM "php.exe" /T'
    Sleep 3000
    DetailPrint "Removing app data..."
    RMDir /r "$APPDATA\sqlinfo-agent"
    RMDir /r "$APPDATA\sqldoc-electron"
    RMDir /r "$LOCALAPPDATA\sqlinfo-agent"
    RMDir /r "$LOCALAPPDATA\sqldoc-electron"
!macroend

!macro customUnInstall
    DetailPrint "Stopping processes..."
    nsExec::Exec 'taskkill /F /IM "SQLINFO-Agent.exe" /T'
    nsExec::Exec 'taskkill /F /IM "electron.exe" /T'
    nsExec::Exec 'taskkill /F /IM "php.exe" /T'
    Sleep 3000
    RMDir /r "$APPDATA\sqlinfo-agent"
    RMDir /r "$APPDATA\sqldoc-electron"
    RMDir /r "$LOCALAPPDATA\sqlinfo-agent"
    RMDir /r "$LOCALAPPDATA\sqldoc-electron"
!macroend

!macro customInstall
    SetOutPath "$INSTDIR"
    CreateShortCut "$DESKTOP\SQLINFO-Agent.lnk" "$INSTDIR\SQLINFO-Agent.exe" "" "$INSTDIR\SQLINFO-Agent.exe" 0
!macroend
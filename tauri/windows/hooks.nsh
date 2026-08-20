!macro NSIS_HOOK_POSTINSTALL
  ${If} ${FileExists} "$INSTDIR\app\artisan"
    DetailPrint "Limpiando cache..."
    ExecWait '$INSTDIR\php.exe $INSTDIR\app\artisan optimize:clear' $0

    ; Check wether installation process exited successfully (code 0) or not
    ${If} $0 == 0
      DetailPrint "Cache limpiado con exito"
    ${Else}
      MessageBox MB_ICONEXCLAMATION "Error limpiando cache. Debe limpiarse manualmente"
    ${EndIf}
    ${IfNot} ${FileExists} "$INSTDIR\app\config\settings.php"
      ExecWait '$INSTDIR\php.exe $INSTDIR\app\artisan db:restore' $0
       ${If} $0 == 0
         DetailPrint "App migrada con exito"
       ${Else}
         MessageBox MB_ICONEXCLAMATION "No se pudo migrar la DB"
      ${EndIf}
    ${EndIf}
  ${EndIf}
!macroend
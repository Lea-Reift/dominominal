!macro NSIS_HOOK_POSTINSTALL
  ${If} ${FileExists} "$INSTDIR\resources\app\artisan"
    DetailPrint "Limpiando cache..."
    ExecWait '$INSTDIR\php.exe $INSTDIR\resources\app\artisan optimize:clear' $0

    ; Check wether installation process exited successfully (code 0) or not
    ${If} $0 == 0
      DetailPrint "Cache limpiado con exito"
    ${Else}
      MessageBox MB_ICONEXCLAMATION "Error limpiando cache. Debe limpiarse manualmente"
    ${EndIf}
  ${EndIf}
!macroend
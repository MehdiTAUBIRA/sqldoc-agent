@echo off
echo ====================================
echo Test PHP SQLDoc
echo ====================================
echo.

set INSTALL_PATH=%LOCALAPPDATA%\Programs\SQLDoc\resources
set PHP_PATH=%INSTALL_PATH%\php\php.exe
set LARAVEL_PATH=%INSTALL_PATH%\laravel

echo 1. Verification PHP...
if exist "%PHP_PATH%" (
    echo ✅ PHP trouve: %PHP_PATH%
    "%PHP_PATH%" -v
) else (
    echo ❌ PHP introuvable: %PHP_PATH%
    pause
    exit /b 1
)

echo.
echo 2. Verification Laravel...
if exist "%LARAVEL_PATH%\public\index.php" (
    echo ✅ Laravel trouve
) else (
    echo ❌ Laravel introuvable
    pause
    exit /b 1
)

echo.
echo 3. Test direct PHP...
cd /d "%LARAVEL_PATH%"
echo Lancement sur http://127.0.0.1:8000
echo Appuyez sur Ctrl+C pour arreter
echo.
start http://127.0.0.1:8000
"%PHP_PATH%" -S 127.0.0.1:8000 -t public

pause
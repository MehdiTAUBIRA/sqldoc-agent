@echo off
echo ====================================
echo Preparation de Laravel pour Electron
echo ====================================
echo.

if not exist "sqldoc-simple" (
    echo ❌ Erreur: Le dossier sqldoc-simple n'existe pas!
    pause
    exit /b 1
)

cd sqldoc-simple

echo 1. NETTOYAGE COMPLET DES CACHES...
if exist "public\build" rmdir /s /q public\build
if exist "public\hot" del /q public\hot

REM ✅ SUPPRIMER TOUS LES FICHIERS DE CACHE
if exist "bootstrap\cache\*.php" del /q bootstrap\cache\*.php
if exist "storage\framework\cache\data\*" del /q storage\framework\cache\data\*
if exist "storage\framework\sessions\*" del /q storage\framework\sessions\*
if exist "storage\framework\views\*.php" del /q storage\framework\views\*.php

REM ✅ NETTOYER AVEC ARTISAN
php artisan cache:clear 2>nul
php artisan config:clear 2>nul
php artisan route:clear 2>nul
php artisan view:clear 2>nul
php artisan clear-compiled 2>nul

echo.
echo 2. Creation de la base SQLite...
if not exist "database" mkdir database
if not exist "database\database.sqlite" (
    type nul > database\database.sqlite
    echo ✅ Base de donnees creee
) else (
    echo ✅ Base de donnees existe deja
)

echo.
echo 3. Migrations...
php artisan migrate --force

echo.
echo 4. Creation des dossiers storage...
if not exist "storage\logs" mkdir storage\logs
if not exist "storage\framework\sessions" mkdir storage\framework\sessions
if not exist "storage\framework\views" mkdir storage\framework\views
if not exist "storage\framework\cache\data" mkdir storage\framework\cache\data
if not exist "bootstrap\cache" mkdir bootstrap\cache

echo.
echo 5. Permissions (PowerShell)...
powershell -Command "$acl = Get-Acl storage; $rule = New-Object System.Security.AccessControl.FileSystemAccessRule('Everyone','FullControl','ContainerInherit,ObjectInherit','None','Allow'); $acl.SetAccessRule($rule); Set-Acl storage $acl"
powershell -Command "$acl = Get-Acl bootstrap\cache; $rule = New-Object System.Security.AccessControl.FileSystemAccessRule('Everyone','FullControl','ContainerInherit,ObjectInherit','None','Allow'); $acl.SetAccessRule($rule); Set-Acl bootstrap\cache $acl"

echo.
echo 6. Installation des dependances NPM...
call npm install --legacy-peer-deps

echo.
echo 7. BUILD DES ASSETS VUE (PRODUCTION)...
set NODE_ENV=production
call npm run build

echo.
echo 8. Copie du manifest.json...
if exist "public\build\.vite\manifest.json" (
    copy /Y "public\build\.vite\manifest.json" "public\build\manifest.json"
    echo ✅ manifest.json copie
)

echo.
echo 9. Verification du build...
if exist "public\build\manifest.json" (
    echo ✅ Assets Vue buildes avec succes
) else (
    echo ❌ ERREUR: manifest.json introuvable!
    cd ..
    pause
    exit /b 1
)

echo.
echo 10. ✅ NE PAS CACHER LA CONFIG MAINTENANT
REM Ne pas faire: php artisan config:cache
REM Ne pas faire: php artisan route:cache
REM Ne pas faire: php artisan view:cache
echo La config sera mise en cache au premier lancement

echo.
echo 11. Verification des fichiers critiques...
echo Fichiers critiques:
if exist ".env" (echo ✅ .env) else (echo ❌ .env MANQUANT!)
if exist ".env.example" (echo ✅ .env.example) else (echo ❌ .env.example MANQUANT!)
if exist "vendor\autoload.php" (echo ✅ vendor) else (echo ❌ vendor MANQUANT!)
if exist "public\index.php" (echo ✅ public\index.php) else (echo ❌ public\index.php MANQUANT!)
if exist "public\build\manifest.json" (echo ✅ manifest.json) else (echo ❌ manifest.json MANQUANT!)
if exist "bootstrap\app.php" (echo ✅ bootstrap\app.php) else (echo ❌ bootstrap\app.php MANQUANT!)

cd ..

echo.
echo ✅ Laravel est pret pour Electron!
echo ⚠️  Ne pas lancer php artisan serve maintenant
echo    (cela recreerait des caches avec des chemins absolus)
echo.
pause
@echo off
echo ====================================
echo Diagnostic SQLDoc
echo ====================================
echo.

cd sqldoc-simple

echo 1. Verification de la structure...
if exist "public\index.php" (echo ✅ public\index.php) else (echo ❌ public\index.php MANQUANT)
if exist ".env" (echo ✅ .env) else (echo ❌ .env MANQUANT)
if exist "vendor" (echo ✅ vendor) else (echo ❌ vendor MANQUANT - Lancer: composer install)
if exist "public\build\manifest.json" (echo ✅ Assets Vue buildes) else (echo ❌ Assets Vue MANQUANTS - Lancer: npm run build)

echo.
echo 2. Verification des dossiers storage...
if exist "storage\logs" (echo ✅ storage\logs) else (mkdir storage\logs && echo ✅ storage\logs CREE)
if exist "storage\framework\sessions" (echo ✅ storage\framework\sessions) else (mkdir storage\framework\sessions && echo ✅ CREE)
if exist "storage\framework\views" (echo ✅ storage\framework\views) else (mkdir storage\framework\views && echo ✅ CREE)
if exist "storage\framework\cache\data" (echo ✅ storage\framework\cache\data) else (mkdir storage\framework\cache\data && echo ✅ CREE)
if exist "bootstrap\cache" (echo ✅ bootstrap\cache) else (mkdir bootstrap\cache && echo ✅ CREE)

echo.
echo 3. Nettoyage des caches...
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo.
echo 4. Verification de la configuration...
php artisan config:show app.key
php artisan config:show database.default

echo.
echo 5. Derniere erreur dans les logs...
if exist "storage\logs\laravel.log" (
    echo --- DERNIERES LIGNES DU LOG ---
    powershell -Command "Get-Content storage\logs\laravel.log -Tail 30"
) else (
    echo Aucun log trouve
)

echo.
echo 6. Test du serveur PHP...
echo Lancement sur http://127.0.0.1:8000
echo Appuyez sur Ctrl+C pour arreter
start http://127.0.0.1:8000
php artisan serve

cd ..
pause
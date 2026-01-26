@echo off
echo 🔨 Building SQLDoc Electron App...

cd sqldoc-simple

echo 📦 Installing Laravel dependencies...
call composer install --no-dev --optimize-autoloader

echo 🔑 Generating app key...
php artisan key:generate --force

echo 🗃️ Running migrations...
php artisan migrate --force

echo 🧹 Optimizing Laravel...
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo 📦 Building Vue assets for PRODUCTION...
call npm install
set NODE_ENV=production
call npm run build

echo 🧼 Cleaning up...
rmdir /s /q node_modules
del /q composer.lock
del /q package-lock.json

cd ..

echo 🚀 Building Electron app...
call npm run build

echo ✅ Build complete! Check dist/ folder
pause
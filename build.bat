@echo off
echo 🔨 Building SQLDoc Electron App...

cd sqldoc-simple

echo 📦 Installing Laravel dependencies...
call composer install --no-dev --optimize-autoloader

echo 🔑 Generating app key...
php artisan key:generate

echo 🗃️ Running migrations...
php artisan migrate --force

echo 🧹 Optimizing Laravel...
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo 📦 Building Vue assets...
call npm install
call npm run build

cd ..

echo 🚀 Building Electron app...
npm run build

echo ✅ Build complete!
pause
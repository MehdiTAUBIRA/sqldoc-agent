// prebuild.js
const fs = require('fs-extra');
const path = require('path');
const { execSync } = require('child_process');

const laravelPath = path.join(__dirname, 'sqldoc-simple');

console.log('🧹 Cleaning Laravel for production...');

// 1. Install production dependencies only
console.log('📦 Installing production dependencies...');
execSync('composer install --no-dev --optimize-autoloader --no-interaction', {
  cwd: laravelPath,
  stdio: 'inherit'
});

// 2. Cache Laravel
console.log('⚡ Caching Laravel configuration...');
try {
  execSync('php artisan config:cache', { cwd: laravelPath, stdio: 'inherit' });
  execSync('php artisan route:cache', { cwd: laravelPath, stdio: 'inherit' });
  execSync('php artisan view:cache', { cwd: laravelPath, stdio: 'inherit' });
} catch (e) {
  console.log('⚠️  Cache failed (might be ok)');
}

// 3. Remove unnecessary files
console.log('🗑️  Removing unnecessary files...');

const vendorPath = path.join(laravelPath, 'vendor');
const dirsToRemove = [
  'tests',
  'test',
  'Tests',
  'docs',
  '.git',
  'examples',
  'Examples',
  'demo',
  'Demo'
];

function cleanDirectory(dir) {
  if (!fs.existsSync(dir)) return;
  
  const items = fs.readdirSync(dir);
  
  items.forEach(item => {
    const fullPath = path.join(dir, item);
    const stat = fs.statSync(fullPath);
    
    if (stat.isDirectory()) {
      if (dirsToRemove.includes(item)) {
        console.log(`   Removing: ${fullPath.replace(laravelPath, '')}`);
        fs.removeSync(fullPath);
      } else {
        cleanDirectory(fullPath);
      }
    }
  });
}

cleanDirectory(vendorPath);

// 4. Remove specific large unnecessary packages
const unnecessaryDirs = [
  'vendor/symfony/*/Tests',
  'vendor/laravel/framework/src/Illuminate/Foundation/Testing',
  'vendor/phpunit',
  'vendor/mockery',
  'vendor/fakerphp',
  'vendor/filp/whoops',
];

unnecessaryDirs.forEach(pattern => {
  const fullPath = path.join(laravelPath, pattern);
  if (fs.existsSync(fullPath)) {
    console.log(`   Removing: ${pattern}`);
    fs.removeSync(fullPath);
  }
});

console.log('✅ Laravel cleaned for production');

// 5. Show final size
const { execSync: exec } = require('child_process');
try {
  const size = exec(`du -sh "${vendorPath}"`, { encoding: 'utf8' });
  console.log(`📊 Final vendor/ size: ${size}`);
} catch (e) {
  // Windows fallback
  console.log('📊 Vendor cleaned (size check unavailable on Windows)');
}
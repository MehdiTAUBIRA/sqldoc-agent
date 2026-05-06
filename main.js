const { app, BrowserWindow, dialog } = require('electron');
const path = require('path');
const { spawn, execSync } = require('child_process');
const http = require('http');
const fs = require('fs');
const axios = require('axios');

//  Système de logging
const logFile = path.join(require('os').tmpdir(), 'sqlinfo-startup.log');

function log(message) {
  const timestamp = new Date().toISOString();
  const logMessage = `[${timestamp}] ${message}\n`;
  console.log(message);
  try {
    fs.appendFileSync(logFile, logMessage);
  } catch (e) {
    // Ignore
  }
}

//  Capturer toutes les erreurs
process.on('uncaughtException', (error) => {
  log(' UNCAUGHT EXCEPTION: ' + error.message);
  log('Stack: ' + error.stack);
  dialog.showErrorBox('Erreur Critique', 
    'Une erreur inattendue s\'est produite.\n\n' +
    error.message + '\n\nLog: ' + logFile
  );
});

process.on('unhandledRejection', (reason) => {
  log(' UNHANDLED REJECTION: ' + reason);
});

log(" main.js started");
log("  Platform: " + process.platform);
log(" Log file: " + logFile);

app.commandLine.appendSwitch('disable-http-cache');
app.commandLine.appendSwitch('disk-cache-size', '0');

let mainWindow;
let phpProcess;
let syncInterval;

// ===== FONCTION POUR OBTENIR LE CHEMIN PHP =====
function getPhpPath() {
  const isPackaged = app.isPackaged;
  
  if (isPackaged) {
    if (process.platform === 'win32') {
      return path.join(process.resourcesPath, 'php', 'php.exe');
    } else if (process.platform === 'darwin') {
      const embeddedPhp = path.join(process.resourcesPath, 'php', 'php');
      if (fs.existsSync(embeddedPhp)) {
        return embeddedPhp;
      }
      return '/usr/bin/php';
    } else {
      return 'php';
    }
  } else {
    if (process.platform === 'win32') {
      return path.join(__dirname, 'build', 'php', 'php.exe');
    } else if (process.platform === 'darwin') {
      const embeddedPhp = path.join(__dirname, 'build', 'php-mac', 'php');
      if (fs.existsSync(embeddedPhp)) {
        return embeddedPhp;
      }
      return '/usr/bin/php';
    } else {
      return 'php';
    }
  }
}

// ===== FONCTION POUR OBTENIR LE CHEMIN LARAVEL =====
function getLaravelPath() {
  return app.isPackaged
    ? path.join(process.resourcesPath, 'laravel')
    : path.join(__dirname, 'sqldoc-simple');
}

// ===== FONCTION POUR OBTENIR LE CHEMIN DES CERTIFICATS SSL =====
function getCertsPath() {
  let certsPath;
  
  if (app.isPackaged) {
    // Essayer d'abord dans extraResources
    certsPath = path.join(process.resourcesPath, 'certs', 'cacert.pem');
    
    // Si pas trouvé, essayer dans app.asar.unpacked (au cas où)
    if (!fs.existsSync(certsPath)) {
      certsPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'certs', 'cacert.pem');
    }
  } else {
    // En développement
    certsPath = path.join(__dirname, 'certs', 'cacert.pem');
  }
  
  log(' Certificate path: ' + certsPath);
  log(' Certificate exists: ' + fs.existsSync(certsPath));
  
  return certsPath;
}

// ===== FONCTION POUR OBTENIR LE CHEMIN php.ini =====
function getPhpIniPath() {
  return app.isPackaged 
    ? path.join(process.resourcesPath, 'php', 'php.ini')
    : path.join(__dirname, 'build', 'php', 'php.ini');
}

// ===== INITIALISATION DE LARAVEL =====
function initializeLaravel(laravelPath) {
  log(' Initializing Laravel...');

  // 1️⃣ Supprimer les caches Laravel
  const cachesToClear = [
    path.join(laravelPath, 'bootstrap', 'cache', 'config.php'),
    path.join(laravelPath, 'bootstrap', 'cache', 'routes-v7.php'),
    path.join(laravelPath, 'bootstrap', 'cache', 'services.php'),
  ];
  
  cachesToClear.forEach(cache => {
    if (fs.existsSync(cache)) {
      fs.unlinkSync(cache);
      log('     Removed cached file: ' + path.basename(cache));
    }
  });
  
  // 2️⃣ Créer les dossiers nécessaires dans AppData
  const userDataPath = app.getPath('userData');
  const storagePath = path.join(userDataPath, 'storage');
  const databasePath = path.join(userDataPath, 'database');
  
  log(' User data path: ' + userDataPath);
  log(' Storage path: ' + storagePath);
  log(' Database path: ' + databasePath);
  
  const requiredDirs = [
    path.join(storagePath, 'logs'),
    path.join(storagePath, 'framework', 'sessions'),
    path.join(storagePath, 'framework', 'views'),
    path.join(storagePath, 'framework', 'cache', 'data'),
    path.join(storagePath, 'app', 'public'),
    path.join(userDataPath, 'bootstrap', 'cache'),
    databasePath
  ];
  
  requiredDirs.forEach(dir => {
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
      log(`    Created: ${dir}`);
    }
  });
  
  // 3️⃣ Créer la base de données SQLite
  const dbPath = path.join(databasePath, 'database.sqlite');
  if (!fs.existsSync(dbPath)) {
    fs.writeFileSync(dbPath, '');
    log('    Created database.sqlite');
  }
  
  // 4️⃣ Créer un .env minimal dans AppData
  const envPathAppData = path.join(userDataPath, '.env');
  
  const crypto = require('crypto');
  let appKey = `base64:${crypto.randomBytes(32).toString('base64')}`;
  
  // Réutiliser l'APP_KEY existante si elle existe
  if (fs.existsSync(envPathAppData)) {
    const existingEnv = fs.readFileSync(envPathAppData, 'utf8');
    const keyMatch = existingEnv.match(/APP_KEY=(.+)/);
    if (keyMatch) {
      appKey = keyMatch[1].trim();
      log('   ♻️  Reusing existing APP_KEY');
    }
  }
  
  // .env SANS les chemins (ils seront passés en variables d'environnement)
  const envContent = `APP_NAME=SQLINFO
APP_ENV=production
APP_KEY=${appKey}
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_MODE=agent

LOG_CHANNEL=single
LOG_LEVEL=error

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
`;
  
  // Écrire dans AppData
  fs.writeFileSync(envPathAppData, envContent);
  log('    Created minimal .env in AppData');
  
  // Essayer de copier dans Laravel (si possible)
  const envPathLaravel = path.join(laravelPath, '.env');
  try {
    fs.writeFileSync(envPathLaravel, envContent);
    log('    Copied .env to Laravel folder');
  } catch (error) {
    log('     Could not write to Laravel folder (will use env vars)');
  }
  
  log('   DB path: ' + dbPath.replace(/\\/g, '/'));
  
  // 5️⃣ Définir les variables d'environnement globales
  process.env.SQLINFO_STORAGE_PATH = storagePath;
  process.env.SQLINFO_DATABASE_PATH = dbPath;
  
  log(' Laravel initialized');
}

// ===== FONCTION POUR CRÉER UN php.ini DYNAMIQUE =====
function createDynamicPhpIni(baseIniPath, certsPath) {
  let content = fs.readFileSync(baseIniPath, 'utf8');
  const cert = certsPath.replace(/\\/g, '/');

  // Supprimer toute config SSL existante
  content = content.replace(/^curl\.cainfo\s*=.*$/gm, '');
  content = content.replace(/^openssl\.cafile\s*=.*$/gm, '');

  // Injecter les bonnes valeurs
  content += `
curl.cainfo="${cert}"
openssl.cafile="${cert}"
`;

  const dynamicIni = path.join(app.getPath('userData'), 'php.ini');
  fs.writeFileSync(dynamicIni, content);

  log(' php.ini generated: ' + dynamicIni);
  log(' CA path: ' + cert);

  return dynamicIni;
}

// ===== FONCTION POUR OBTENIR L'APP_KEY =====
function getAppKey() {
  const userDataPath = app.getPath('userData');
  const envPathAppData = path.join(userDataPath, '.env');
  let appKey = '';
  
  if (fs.existsSync(envPathAppData)) {
    const envContent = fs.readFileSync(envPathAppData, 'utf8');
    const keyMatch = envContent.match(/APP_KEY=(.+)/);
    if (keyMatch) {
      appKey = keyMatch[1].trim();
    }
  }
  
  // Si pas de clé, en générer une
  if (!appKey) {
    const crypto = require('crypto');
    appKey = `base64:${crypto.randomBytes(32).toString('base64')}`;
    log('Generated new APP_KEY');
  }
  
  return appKey;
}

// ===== FONCTION POUR CRÉER L'ENVIRONNEMENT PHP =====
function createPhpEnvironment() {
  const userDataPath = app.getPath('userData');
  const storagePath = path.join(userDataPath, 'storage');
  const dbPath = path.join(userDataPath, 'database', 'database.sqlite');
  const certsPath = getCertsPath();
  const appKey = getAppKey();
  
  return {
    ...process.env,
    CURL_CA_BUNDLE: certsPath,
    SSL_CERT_FILE: certsPath,
    APP_NAME: 'SQLINFO',
    APP_ENV: 'production',
    APP_KEY: appKey,
    APP_DEBUG: 'false',
    APP_URL: 'http://127.0.0.1:8000',
    APP_MODE: 'agent',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'error',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath.replace(/\\/g, '/'),
    SESSION_DRIVER: 'file',
    SESSION_LIFETIME: '120',
    CACHE_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    VIEW_COMPILED_PATH: path.join(storagePath, 'framework', 'views').replace(/\\/g, '/'),
    SQLINFO_STORAGE_PATH: storagePath,
    SQLINFO_DATABASE_PATH: dbPath,
  };
}

// ===== FONCTION POUR DÉCLENCHER LA SYNCHRONISATION =====
async function triggerSync() {
  try {
    log(' Triggering sync...');
    const response = await axios.post('http://127.0.0.1:8000/api/sync-trigger', {
      timeout: 30000,
    });
    log(' Sync completed');
  } catch (error) {
    if (error.code === 'ECONNREFUSED') {
      log('  Server not ready for sync');
    } else if (error.response && error.response.status === 401) {
      log('  Agent not connected, skipping sync');
    } else {
      log(' Sync failed: ' + error.message);
    }
  }
}

// ===== FONCTION POUR DÉMARRER L'INTERVALLE DE SYNC =====
function startSyncInterval() {
  log(' Starting sync interval (every 5 minutes)...');
  setTimeout(() => { triggerSync(); }, 60000);
  syncInterval = setInterval(() => { triggerSync(); }, 5 * 60 * 1000);
  log(' Sync interval configured');
}

// ===== FONCTION POUR VÉRIFIER SI LE SERVEUR EST PRÊT =====
function checkServerReady(url, maxRetries = 30, callback) {
  let retries = 0;
  
  const check = () => {
    http.get(url, (res) => {
      log(`Server status: ${res.statusCode}`);
      
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        if (res.statusCode === 500) {
          log(' Server Error 500');
          log('Error response: ' + data.substring(0, 500));
          
          const errorFile = path.join(app.getPath('desktop'), 'sqldoc-error.html');
          fs.writeFileSync(errorFile, data);
          
          dialog.showErrorBox(
            'Erreur Serveur (500)',
            'Une erreur s\'est produite.\n\nLog: ' + logFile
          );
          
          callback(false);
        } else {
          callback(res.statusCode === 200 || res.statusCode === 302);
        }
      });
    }).on('error', (err) => {
      retries++;
      log(` Waiting for server... (${retries}/${maxRetries})`);
      
      if (retries < maxRetries) {
        setTimeout(check, 1000);
      } else {
        log(' Server failed to start');
        dialog.showErrorBox(
          'Erreur de démarrage',
          'Le serveur PHP n\'a pas pu démarrer.\n\nLog: ' + logFile
        );
        callback(false);
      }
    });
  };
  
  check();
}

// ===== FONCTION POUR CRÉER LA FENÊTRE =====
function createWindow() {
  log(' Creating window...');
  
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    show: false,
    title: 'SQLINFO',
    backgroundColor: '#1a1a2e',
    icon: path.join(__dirname, 'build', 'icon.png'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      enableRemoteModule: false,
      preload: path.join(__dirname, 'preload.js')
    }
  });

  const loadingHTML = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>SQLINFO - Chargement...</title>
      <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
          margin: 0; display: flex; align-items: center; justify-content: center; 
          height: 100vh; 
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white; overflow: hidden;
        }
        .loader-container { text-align: center; animation: fadeIn 0.5s ease-in; }
        .logo { font-size: 48px; font-weight: 700; margin-bottom: 30px; letter-spacing: 2px; }
        .spinner { 
          border: 4px solid rgba(255,255,255,0.2);
          border-top: 4px solid white; border-radius: 50%;
          width: 50px; height: 50px;
          animation: spin 1s linear infinite; margin: 0 auto 30px;
        }
        .loading-text { font-size: 18px; font-weight: 500; opacity: 0.9; margin-bottom: 10px; }
        .loading-subtext { font-size: 14px; opacity: 0.7; animation: pulse 2s ease-in-out infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; } }
        .progress-bar-container {
          width: 300px; height: 4px; background: rgba(255,255,255,0.2);
          border-radius: 2px; margin: 20px auto 0; overflow: hidden;
        }
        .progress-bar {
          height: 100%; background: white; border-radius: 2px;
          width: 0%; animation: progress 3s ease-in-out infinite;
        }
        @keyframes progress { 0% { width: 0%; } 50% { width: 70%; } 100% { width: 100%; } }
      </style>
    </head>
    <body>
      <div class="loader-container">
        <div class="logo">SQLINFO</div>
        <div class="spinner"></div>
        <div class="loading-text">Démarrage de l'application</div>
        <div class="loading-subtext">Initialisation du serveur...</div>
        <div class="progress-bar-container"><div class="progress-bar"></div></div>
      </div>
    </body>
    </html>
  `;
  
  mainWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(loadingHTML)}`);
  mainWindow.show();
  log(' Window shown');

  checkServerReady('http://127.0.0.1:8000', 30, (isReady) => {
    if (isReady) {
      log(' Server ready, loading application...');
      mainWindow.loadURL('http://127.0.0.1:8000');
      startSyncInterval();
    } else {
      log(' Server not ready, quitting...');
      app.quit();
    }
  });

  mainWindow.on('closed', () => {
    log(' Window closed');
    mainWindow = null;
    if (syncInterval) clearInterval(syncInterval);
    if (phpProcess) phpProcess.kill();
    app.quit();
  });
  
  mainWindow.on('page-title-updated', (event) => {
    event.preventDefault();
});
}



// ===== ÉVÉNEMENT: APPLICATION PRÊTE =====
app.on('ready', () => {
  log('🎬 App ready event');
  
  const isPackaged = app.isPackaged;
  const phpPath = getPhpPath();
  const laravelPath = getLaravelPath();
  const certsPath = getCertsPath();

  log(' PHP path: ' + phpPath);
  log(' Laravel path: ' + laravelPath);
  log(' Certs path: ' + certsPath);
  log(' Is packaged: ' + isPackaged);

  // ===== VÉRIFICATION 1: PHP existe =====
  if (!fs.existsSync(phpPath)) {
    log(' PHP not found');
    dialog.showErrorBox('Erreur', 'PHP introuvable: ' + phpPath + '\n\nLog: ' + logFile);
    app.quit();
    return;
  }
  log(' PHP found');

  // ===== VÉRIFICATION 2: Laravel existe =====
  if (!fs.existsSync(laravelPath)) {
    log(' Laravel not found');
    dialog.showErrorBox('Erreur', 'Laravel introuvable: ' + laravelPath + '\n\nLog: ' + logFile);
    app.quit();
    return;
  }
  log(' Laravel found');

  // ===== VÉRIFICATION 3: Certificat SSL existe =====
  if (!fs.existsSync(certsPath)) {
    log(' Certificate not found');
    dialog.showErrorBox('Erreur', 'Certificat SSL introuvable: ' + certsPath + '\n\nLog: ' + logFile);
    app.quit();
    return;
  }
  log(' Certificate found');

  // ===== INITIALISATION DE LARAVEL =====
  try {
    const userDataPath = app.getPath('userData');
    
    // ===== 1. VÉRIFIER LA VERSION EN PREMIER =====
    const migrationMarker = path.join(userDataPath, '.migrations-done');
    const versionMarker = path.join(userDataPath, '.app-version');
    const currentVersion = app.getVersion();
    
    let previousVersion = null;
    if (fs.existsSync(versionMarker)) {
        previousVersion = fs.readFileSync(versionMarker, 'utf8').trim();
    }

    const versionChanged = previousVersion !== currentVersion;

    if (versionChanged) {
        log(` Version changed: ${previousVersion} → ${currentVersion}`);
        log(' Cleaning old data...');
        
        const itemsToDelete = [
            path.join(userDataPath, 'database'),
            path.join(userDataPath, 'storage'),
            path.join(userDataPath, 'bootstrap'),
            path.join(userDataPath, 'php.ini'),
            path.join(userDataPath, '.migrations-done'),
        ];
        
        itemsToDelete.forEach(item => {
            if (fs.existsSync(item)) {
                fs.rmSync(item, { recursive: true, force: true });
                log(' Deleted: ' + item);
            }
        });
        
        fs.writeFileSync(versionMarker, currentVersion);
        log(' Version marker updated to ' + currentVersion);
    }

    // ===== 2. INITIALISER LARAVEL APRÈS LE NETTOYAGE =====
    initializeLaravel(laravelPath);
    
    const dbPath = path.join(userDataPath, 'database', 'database.sqlite');
    
    log(' Checking database...');
    log('   Path: ' + dbPath);
    log('   Exists: ' + fs.existsSync(dbPath));

    // ===== 3. MIGRATIONS =====
    if (!fs.existsSync(migrationMarker)) {
        log(' Running migrations (first run)...');
        
        const phpIniPath = getPhpIniPath();
        
        try {
            const migrateCmd = `"${phpPath}" -c "${phpIniPath}" artisan migrate --force`;
            const phpEnv = createPhpEnvironment();
            
            const result = execSync(migrateCmd, {
                cwd: laravelPath,
                encoding: 'utf8',
                windowsHide: true,
                env: phpEnv
            });
            
            log('   Migration output: ' + result);
            fs.writeFileSync(migrationMarker, new Date().toISOString());
            log(' Migrations done');
        } catch (error) {
            log(' Migration failed: ' + error.message);
            log(' Continuing despite migration error...');
        }
    } else {
        log(' Migrations already done');
    }
    
} catch (error) {
    log(' Failed to initialize: ' + error.message);
    log('Stack: ' + error.stack);
    dialog.showErrorBox('Erreur initialisation', error.message + '\n\nLog: ' + logFile);
    app.quit();
    return;
}

  // ===== DÉMARRAGE DU SERVEUR PHP =====
  log(' Starting PHP server...');

  const phpIniPath = getPhpIniPath();
  const basePhpIniPath = phpIniPath;
  const dynamicPhpIniPath = createDynamicPhpIni(basePhpIniPath, certsPath);

  log(' Using php.ini: ' + dynamicPhpIniPath);
  log(' Cert path: ' + certsPath);
  log(' Cert exists: ' + fs.existsSync(certsPath));

  const phpEnv = createPhpEnvironment();

  log('Command: "' + phpPath + '" -c "' + dynamicPhpIniPath + '" -S 127.0.0.1:8000 -t "' + path.join(laravelPath, 'public') + '"');

  phpProcess = spawn(phpPath, [
    '-c', dynamicPhpIniPath,
    '-S', '127.0.0.1:8000',
    '-t', path.join(laravelPath, 'public')
  ], {
    cwd: laravelPath,
    env: phpEnv,
    windowsHide: process.platform === 'win32'
  });

  phpProcess.stdout.on('data', (data) => {
    log(`[PHP] ${data.toString().trim()}`);
  });

  phpProcess.stderr.on('data', (data) => {
    log(`[PHP] ${data.toString().trim()}`);
  });

  phpProcess.on('error', (error) => {
    log(' PHP error: ' + error.message);
    dialog.showErrorBox('Erreur PHP', error.message);
  });

  phpProcess.on('close', (code) => {
    log(`[PHP] Process exited with code ${code}`);
  });

  createWindow();
});

// ===== ÉVÉNEMENT: FERMETURE DE TOUTES LES FENÊTRES =====
app.on('window-all-closed', () => {
  log(' All windows closed');
  if (syncInterval) clearInterval(syncInterval);
  if (phpProcess) phpProcess.kill();
  if (process.platform !== 'darwin') app.quit();
});

// ===== ÉVÉNEMENT: ACTIVATION (macOS) =====
app.on('activate', () => {
  if (mainWindow === null) createWindow();
});







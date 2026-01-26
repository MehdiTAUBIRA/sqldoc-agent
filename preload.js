const { contextBridge, ipcRenderer } = require('electron');

// Expose uniquement les APIs nécessaires à ton app Laravel
contextBridge.exposeInMainWorld('electronAPI', {
  // Exemple : si tu as besoin de fonctionnalités Electron dans Vue
  platform: process.platform,
  
  // Ajoute ici d'autres APIs si nécessaire
  // onUpdateAvailable: (callback) => ipcRenderer.on('update-available', callback),
  // etc.
});

console.log('✅ Preload script loaded');
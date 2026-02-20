const { app, BrowserWindow } = require('electron');
const { spawn, exec } = require('child_process');
const path = require('path');
const net = require('net');
const os = require('os');

app.disableHardwareAcceleration();

if (process.platform === 'win32') {
  app.setPath('userData', path.join(os.tmpdir(), 'hb-pos-cache'));
}

let phpProcess;

/**
 * Wait until Laravel PHP server is ready
 */
function waitForServer(port, callback) {
  const interval = setInterval(() => {
    const socket = net.createConnection(port, '127.0.0.1');

    socket.on('connect', () => {
      clearInterval(interval);
      socket.end();
      callback();
    });

    socket.on('error', () => {
      socket.destroy();
    });
  }, 300);
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1280,
    height: 800,
    show: false, // 🔥 important
    webPreferences: {
      devTools: true,
      contextIsolation: false,
      nodeIntegration: false
    }
  });

  win.loadURL('http://127.0.0.1:8000');

  // Show only when ready
  win.once('ready-to-show', () => {
    win.show();
  });

  // Debug load errors
  win.webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
    console.error('Load failed:', errorCode, errorDescription);
  });

  // Open DevTools for debugging
  win.webContents.openDevTools();
}

app.whenReady().then(() => {
  let phpPath;

  /* =========================
     macOS → MAMP
  ========================== */
  if (process.platform === 'darwin') {
    phpPath = '/Applications/MAMP/bin/php/php8.4.15/bin/php';

    exec('/Applications/MAMP/bin/startMysql.sh', (err) => {
      if (err) console.log('MySQL may already be running.');
    });

  /* =========================
     Windows → XAMPP
  ========================== */
  }  else if (process.platform === 'win32') {
  phpPath = 'C:\\xampp\\php\\php.exe';

  exec('"C:\\xampp\\xampp_start.exe"', (err) => {
    if (err) console.log('XAMPP may already be running.');
  });
} else {
    throw new Error('Unsupported OS');
  }

  const publicPath = path.join(__dirname, '..', 'public');

  phpProcess = spawn(phpPath, [
    '-S',
    '127.0.0.1:8000',
    '-t',
    publicPath
  ], {
    windowsHide: true
  });

  phpProcess.stdout.on('data', d => console.log(`PHP: ${d}`));
  phpProcess.stderr.on('data', d => console.log(`PHP: ${d}`));
  phpProcess.on('error', err => console.error('PHP failed:', err));

  // Catch crashes
  process.on('uncaughtException', (err) => {
    console.error('Uncaught Exception:', err);
  });

  waitForServer(8000, createWindow);
});

app.on('window-all-closed', () => {
  if (phpProcess) phpProcess.kill();
  app.quit();
});
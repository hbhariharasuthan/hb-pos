const { app, BrowserWindow } = require('electron');
const { spawn, exec } = require('child_process');
const path = require('path');
const net = require('net');

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
    webPreferences: {
      devTools: false
    }
  });

  win.loadURL('http://127.0.0.1:8000');
}

app.whenReady().then(() => {
  let phpPath;

  /* =========================
     macOS → MAMP
  ========================== */
  if (process.platform === 'darwin') {
    phpPath = '/Applications/MAMP/bin/php/php8.4.15/bin/php';

    // Ensure MySQL is running (MAMP)
    exec('/Applications/MAMP/bin/startMysql.sh');

  /* =========================
     Windows → XAMPP
  ========================== */
  } else if (process.platform === 'win32') {
    phpPath = 'C:\\xampp\\php\\php.exe';

    // Start Apache & MySQL as services
    exec('net start Apache2.4', () => {});
    exec('net start mysql', () => {});
  } else {
    throw new Error('Unsupported OS');
  }

  /* =========================
     Start Laravel PHP Server
  ========================== */
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
  phpProcess.stderr.on('data', d => console.error(`PHP ERROR: ${d}`));
  phpProcess.on('error', err => console.error('PHP failed:', err));

  waitForServer(8000, createWindow);
});

app.on('window-all-closed', () => {
  if (phpProcess) phpProcess.kill();
  app.quit();
});

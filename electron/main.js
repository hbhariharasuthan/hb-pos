const { app, BrowserWindow } = require('electron');
const { spawn } = require('child_process');
const path = require('path');

let phpProcess;

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
  // ✅ SET PHP PATH BASED ON OS
  const phpPath =
    process.platform === 'darwin'
      ? '/Applications/MAMP/bin/php/php8.4.15/bin/php'
      : path.join(process.resourcesPath, 'php', 'php.exe'); // for Windows build

  // ✅ LARAVEL PUBLIC DIRECTORY
  const publicPath = path.join(__dirname, '..', 'public');

  phpProcess = spawn(phpPath, [
    '-S',
    '127.0.0.1:8000',
    '-t',
    publicPath
  ]);

  phpProcess.stdout.on('data', (data) => {
    console.log(`PHP: ${data}`);
  });

  phpProcess.stderr.on('data', (data) => {
    console.error(`PHP ERROR: ${data}`);
  });

  phpProcess.on('error', (err) => {
    console.error('Failed to start PHP:', err);
  });

  // ⏳ WAIT FOR PHP SERVER
  setTimeout(createWindow, 1500);
});

app.on('window-all-closed', () => {
  if (phpProcess) phpProcess.kill();
  app.quit();
});

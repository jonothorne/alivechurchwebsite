/**
 * SongSelect Cookie Sync - Popup Script
 */

const siteUrlInput = document.getElementById('siteUrl');
const apiKeyInput = document.getElementById('apiKey');
const saveBtn = document.getElementById('saveBtn');
const syncBtn = document.getElementById('syncBtn');
const statusBar = document.getElementById('statusBar');
const statusText = document.getElementById('statusText');
const syncInfo = document.getElementById('syncInfo');

// Load saved config and status
chrome.storage.local.get(['site_url', 'api_key', 'last_sync', 'last_status', 'last_message', 'last_user'], (data) => {
  if (data.site_url) siteUrlInput.value = data.site_url;
  if (data.api_key) apiKeyInput.value = data.api_key;

  if (data.site_url && data.api_key) {
    syncBtn.disabled = false;
  }

  updateStatus(data);
});

// Save configuration
saveBtn.addEventListener('click', () => {
  const siteUrl = siteUrlInput.value.trim();
  const apiKey = apiKeyInput.value.trim();

  if (!siteUrl || !apiKey) {
    showTemporary(saveBtn, 'Fill both fields', 'Save');
    return;
  }

  chrome.storage.local.set({ site_url: siteUrl, api_key: apiKey }, () => {
    syncBtn.disabled = false;
    showTemporary(saveBtn, 'Saved!', 'Save');
  });
});

// Manual sync
syncBtn.addEventListener('click', () => {
  syncBtn.disabled = true;
  syncBtn.innerHTML = '<span class="spinner"></span>Syncing...';

  chrome.runtime.sendMessage({ action: 'sync_now' }, (result) => {
    syncBtn.disabled = false;
    syncBtn.textContent = 'Sync Now';

    if (result) {
      // Refresh status display
      chrome.storage.local.get(['last_sync', 'last_status', 'last_message', 'last_user'], updateStatus);
    }
  });
});

function updateStatus(data) {
  if (!data.last_sync) {
    if (data.site_url && data.api_key) {
      statusBar.className = 'status-bar unconfigured';
      statusText.textContent = 'Ready — visit SongSelect to auto-sync';
    } else {
      statusBar.className = 'status-bar unconfigured';
      statusText.textContent = 'Enter your Site URL and API Key below';
    }
    syncInfo.style.display = 'none';
    return;
  }

  if (data.last_status === 'ok') {
    statusBar.className = 'status-bar ok';
    statusText.textContent = data.last_user
      ? `Connected as ${data.last_user}`
      : 'Cookies synced successfully';
  } else {
    statusBar.className = 'status-bar error';
    statusText.textContent = data.last_message || 'Sync failed';
  }

  // Show last sync time
  const syncTime = new Date(data.last_sync);
  const timeAgo = getTimeAgo(syncTime);
  syncInfo.innerHTML = `<strong>Last sync:</strong> ${timeAgo}`;
  syncInfo.style.display = 'block';
}

function getTimeAgo(date) {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
  if (seconds < 60) return 'just now';
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function showTemporary(btn, msg, original) {
  btn.textContent = msg;
  setTimeout(() => { btn.textContent = original; }, 1500);
}

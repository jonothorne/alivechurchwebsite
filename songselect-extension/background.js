/**
 * SongSelect Cookie Sync - Background Service Worker
 *
 * Monitors for CCLI_JWT_AUTH cookie changes and syncs to the church app API.
 */

const CCLI_DOMAIN = '.ccli.com';
const AUTH_COOKIE = 'CCLI_JWT_AUTH';
const ALARM_NAME = 'periodic-sync';
const SYNC_INTERVAL_MINUTES = 720; // 12 hours

// Listen for cookie changes on ccli.com
chrome.cookies.onChanged.addListener((changeInfo) => {
  const cookie = changeInfo.cookie;

  // Only care about the auth cookie being set (not removed)
  if (cookie.name === AUTH_COOKIE && !changeInfo.removed) {
    console.log('[SongSelect Sync] Auth cookie updated, syncing...');
    syncCookies('cookie_change');
  }
});

// Periodic sync alarm
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === ALARM_NAME) {
    console.log('[SongSelect Sync] Periodic sync triggered');
    syncCookies('periodic');
  }
});

// Set up periodic alarm on install/startup
chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create(ALARM_NAME, { periodInMinutes: SYNC_INTERVAL_MINUTES });
});

chrome.runtime.onStartup.addListener(() => {
  chrome.alarms.create(ALARM_NAME, { periodInMinutes: SYNC_INTERVAL_MINUTES });
});

// Listen for manual sync requests from popup
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'sync_now') {
    syncCookies('manual').then(sendResponse);
    return true; // Keep channel open for async response
  }
  if (message.action === 'get_status') {
    chrome.storage.local.get(['last_sync', 'last_status', 'last_user'], (data) => {
      sendResponse(data);
    });
    return true;
  }
});

/**
 * Collect all ccli.com cookies and send to the church app API
 */
async function syncCookies(trigger) {
  try {
    // Get config
    const config = await chrome.storage.local.get(['site_url', 'api_key']);
    if (!config.site_url || !config.api_key) {
      const result = { success: false, message: 'Extension not configured — set Site URL and API Key' };
      await saveStatus(result);
      return result;
    }

    // Get all cookies for ccli.com
    const cookies = await chrome.cookies.getAll({ domain: CCLI_DOMAIN });
    if (!cookies.length) {
      const result = { success: false, message: 'No CCLI cookies found — visit songselect.ccli.com first' };
      await saveStatus(result);
      return result;
    }

    // Check for auth cookie specifically
    const hasAuth = cookies.some(c => c.name === AUTH_COOKIE);
    if (!hasAuth) {
      const result = { success: false, message: 'Not logged in to SongSelect — log in first' };
      await saveStatus(result);
      return result;
    }

    // Format as cookie header string: name1=value1; name2=value2
    const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

    // Send to API
    const apiUrl = config.site_url.replace(/\/+$/, '') + '/adminnew/services/api/songselect-cookie-sync.php';

    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        api_key: config.api_key,
        cookies: cookieString,
      }),
    });

    const data = await response.json();

    const result = {
      success: data.success || false,
      message: data.message || 'Unknown response',
      user: data.user || null,
      organization: data.organization || null,
    };

    await saveStatus(result);
    console.log(`[SongSelect Sync] ${trigger}: ${result.message}`);
    return result;

  } catch (err) {
    const result = { success: false, message: `Sync failed: ${err.message}` };
    await saveStatus(result);
    console.error('[SongSelect Sync] Error:', err);
    return result;
  }
}

/**
 * Save sync status to storage
 */
async function saveStatus(result) {
  await chrome.storage.local.set({
    last_sync: new Date().toISOString(),
    last_status: result.success ? 'ok' : 'error',
    last_message: result.message,
    last_user: result.user || null,
  });
}

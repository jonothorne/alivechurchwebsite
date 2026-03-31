#!/usr/bin/env node
/**
 * SongSelect Scraper using Puppeteer
 *
 * Usage:
 *   node scraper.js search "song title" --username=email --password=pass
 *   node scraper.js get-song <songId> --username=email --password=pass --key=C
 *   node scraper.js test-login --username=email --password=pass
 */

const { connect } = require('puppeteer-real-browser');
const fs = require('fs');
const path = require('path');

// Helper function for waiting (replaces deprecated waitForTimeout)
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// Cookie storage path
const COOKIE_PATH = path.join(__dirname, '.cookies.json');
const BASE_URL = 'https://songselect.ccli.com';

/**
 * Parse command line arguments
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const command = args[0];
    const params = {};
    const positional = [];

    for (let i = 1; i < args.length; i++) {
        if (args[i].startsWith('--')) {
            const [key, value] = args[i].slice(2).split('=');
            params[key] = value || true;
        } else {
            positional.push(args[i]);
        }
    }

    return { command, params, positional };
}

/**
 * Save cookies to file
 */
async function saveCookies(page) {
    const cookies = await page.cookies();
    fs.writeFileSync(COOKIE_PATH, JSON.stringify(cookies, null, 2));
}

/**
 * Load cookies from file
 */
async function loadCookies(page) {
    if (fs.existsSync(COOKIE_PATH)) {
        const cookies = JSON.parse(fs.readFileSync(COOKIE_PATH, 'utf8'));
        if (cookies.length > 0) {
            await page.setCookie(...cookies);
            return true;
        }
    }
    return false;
}

/**
 * Check if we're logged in
 */
async function isLoggedIn(page) {
    try {
        await page.goto(BASE_URL, { waitUntil: 'networkidle2', timeout: 30000 });
        await sleep(2000);

        // Check the URL - if redirected to signin, we're not logged in
        const currentUrl = page.url();
        if (currentUrl.includes('signin') || currentUrl.includes('login')) {
            return false;
        }

        // Check if user menu exists (logged in state shows user icon, not "Sign In" text)
        const isLoggedInState = await page.evaluate(() => {
            // Look for "Sign In" button text - if present, not logged in
            const signInBtn = Array.from(document.querySelectorAll('button, a')).find(el =>
                el.textContent?.trim() === 'Sign In'
            );
            if (signInBtn) return false;

            // Look for user avatar or account menu which indicates logged in
            const userAvatar = document.querySelector('[class*="avatar"], [class*="user-menu"], [class*="account"]');
            if (userAvatar) return true;

            // Check for "Sign Out" link which means logged in
            const signOutLink = Array.from(document.querySelectorAll('a, button')).find(el =>
                el.textContent?.toLowerCase().includes('sign out')
            );
            if (signOutLink) return true;

            return false;
        });

        return isLoggedInState;
    } catch (e) {
        console.error('Error checking login status:', e.message);
        return false;
    }
}

/**
 * Perform login to SongSelect
 */
async function login(page, username, password) {
    console.error('Navigating to sign-in page...');

    // SongSelect is a SPA - we need to click the sign-in button which will redirect to profile.ccli.com
    await page.goto(BASE_URL, { waitUntil: 'networkidle2', timeout: 60000 });
    await sleep(3000);

    let currentUrl = page.url();
    console.error(`Initial URL: ${currentUrl}`);

    // Look for sign-in link/button on the SPA
    const signInLink = await page.evaluate(() => {
        // Try various selectors
        let el = document.querySelector('a[href*="signin"]');
        if (el) return true;

        // Look for buttons with sign in text
        const buttons = document.querySelectorAll('button, a');
        for (const btn of buttons) {
            if (btn.textContent.toLowerCase().includes('sign in')) {
                btn.click();
                return true;
            }
        }
        return false;
    });

    if (signInLink) {
        console.error('Found and clicked sign-in button...');
        await sleep(3000);
    } else {
        // Navigate directly to profile.ccli.com
        console.error('No sign-in button found, going directly to profile.ccli.com...');
        await page.goto('https://profile.ccli.com/account/signin?appContext=SongSelect', {
            waitUntil: 'networkidle2',
            timeout: 60000
        });
        await sleep(2000);
    }

    currentUrl = page.url();
    console.error(`Current URL: ${currentUrl}`);

    // Check if we're on the profile.ccli.com login page
    if (currentUrl.includes('profile.ccli.com') || currentUrl.includes('signin')) {
        console.error('On CCLI Profile login page');

        // Wait for the page to fully load
        await sleep(2000);

        // Accept cookie consent if present
        console.error('Looking for cookie consent banner...');
        const acceptedCookies = await page.evaluate(() => {
            // Look for "Allow all" or similar buttons
            const buttons = document.querySelectorAll('button, a');
            for (const btn of buttons) {
                const text = btn.textContent?.trim()?.toLowerCase() || '';
                if (text.includes('allow all') || text.includes('accept all') || text.includes('agree')) {
                    btn.click();
                    return true;
                }
            }
            return false;
        });

        if (acceptedCookies) {
            console.error('Accepted cookie consent');
            await sleep(1000);
        }

        // Wait for email field
        try {
            await page.waitForSelector('#EmailAddress', { timeout: 15000 });
        } catch (e) {
            console.error('Email field not found, checking page content...');
            const pageContent = await page.content();
            console.error('Page contains EmailAddress:', pageContent.includes('EmailAddress'));
            throw new Error('Could not find email field');
        }

        // Clear and set email using evaluate to avoid keyboard issues
        console.error('Entering email...');
        await page.evaluate((email) => {
            const input = document.querySelector('#EmailAddress');
            if (input) {
                input.value = email;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, username);
        await sleep(500);

        // Verify email was entered
        const emailValue = await page.$eval('#EmailAddress', el => el.value);
        console.error(`Email entered: ${emailValue}`);

        // Wait for password field (it's rendered by Vue)
        console.error('Waiting for password field...');
        await sleep(1000);

        try {
            await page.waitForSelector('input[type="password"]', { timeout: 15000 });
        } catch (e) {
            console.error('Password field not found');
            throw new Error('Could not find password field');
        }

        // Set password using evaluate to avoid keyboard issues
        console.error('Entering password...');
        await page.evaluate((pwd) => {
            const input = document.querySelector('input[type="password"]');
            if (input) {
                input.value = pwd;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, password);
        await sleep(500);

        // Verify password was entered (check length only for security)
        const passwordValue = await page.$eval('input[type="password"]', el => el.value);
        console.error(`Password entered: ${passwordValue.length} characters`);

        // Check "Keep me signed in" checkbox
        const keepSignedIn = await page.$('#RememberMe');
        if (keepSignedIn) {
            await keepSignedIn.click();
            console.error('Checked "Keep me signed in"');
        }

        // Wait for Turnstile to complete (it should auto-solve for real browsers)
        console.error('Waiting for Turnstile captcha to complete...');

        // Wait for the submit button to become enabled (Turnstile completion)
        let isButtonEnabled = false;
        for (let i = 0; i < 20; i++) {  // Wait up to 20 seconds
            isButtonEnabled = await page.evaluate(() => {
                const btn = document.querySelector('#sign-in');
                return btn && !btn.disabled;
            });

            if (isButtonEnabled) {
                console.error(`Submit button enabled after ${i + 1} seconds`);
                break;
            }

            await sleep(1000);
        }

        if (!isButtonEnabled) {
            console.error('Warning: Submit button still disabled after 20 seconds');
            // Take a screenshot for debugging
            await page.screenshot({ path: path.join(__dirname, 'debug-turnstile-failed.png') });
        }

        // Try clicking submit anyway
        console.error('Clicking submit button...');

        try {
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => {}),
                page.click('#sign-in')
            ]);
        } catch (e) {
            console.error('Navigation after click:', e.message);
        }

        // Wait for redirect
        await sleep(5000);

        // Wait for navigation after form submission
        await sleep(5000);

        // Check if login was successful
        const afterLoginUrl = page.url();
        console.error(`After login URL: ${afterLoginUrl}`);

        // Take a screenshot for debugging
        await page.screenshot({ path: path.join(__dirname, 'debug-after-login.png') });
        console.error('Screenshot saved to debug-after-login.png');

        // Check for error messages first
        const errorText = await page.evaluate(() => {
            // Look for error alert box
            const errorEl = document.querySelector('.alert-danger, .form-error:not(.hidden), .error-message, .field-validation-error');
            if (errorEl) return errorEl.textContent.trim();

            // Look for text containing "not found" or similar
            const pageText = document.body.innerText || '';
            if (pageText.includes('Email or password not found')) {
                return 'Email or password not found';
            }
            if (pageText.includes('Invalid email') || pageText.includes('Invalid password')) {
                return 'Invalid credentials';
            }

            return null;
        });

        if (errorText) {
            console.error(`Login error: ${errorText}`);
            return false;
        }

        // If we're still on the signin page, login failed
        if (afterLoginUrl.includes('Account/Signin')) {
            console.error('Still on signin page - login may have failed');
            // Check if form is still visible
            const formStillVisible = await page.$('#EmailAddress');
            if (formStillVisible) {
                console.error('Login form still visible - Turnstile may not have completed');
                return false;
            }
        }

        // Navigate to SongSelect to verify login
        console.error('Navigating to SongSelect to verify login...');
        await page.goto(BASE_URL, { waitUntil: 'networkidle2', timeout: 30000 });
        await sleep(3000);

        const finalUrl = page.url();
        console.error(`Final URL: ${finalUrl}`);

        // Check if we're logged in by looking for user-specific elements
        const loggedInCheck = await page.evaluate(() => {
            // Look for "Sign Out" or user account elements
            const signOutLink = Array.from(document.querySelectorAll('a, button')).find(el =>
                el.textContent?.toLowerCase().includes('sign out')
            );
            if (signOutLink) return true;

            // Check if "Sign In" button is visible (means NOT logged in)
            const signInBtn = Array.from(document.querySelectorAll('button, a')).find(el =>
                el.textContent?.trim() === 'Sign In'
            );
            if (signInBtn) return false;

            // If we can access search without redirect, we're logged in
            return !window.location.href.includes('signin');
        });

        if (loggedInCheck) {
            console.error('Login verified successfully!');
            await saveCookies(page);
            return true;
        }

        console.error('Login verification failed');
        return false;
    }

    // If we're already on SongSelect and not on signin page, we might be logged in
    if (currentUrl.includes('songselect.ccli.com') && !currentUrl.includes('signin')) {
        console.error('Already logged in!');
        await saveCookies(page);
        return true;
    }

    return false;
}

/**
 * Search for songs
 */
async function searchSongs(page, query, limit = 20) {
    console.error(`Searching for: ${query}`);

    // Navigate to search page
    await page.goto(`${BASE_URL}/search`, {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    await sleep(2000);

    // Check if we got redirected to login
    if (page.url().includes('signin') || page.url().includes('profile.ccli.com')) {
        throw new Error('Session expired - need to re-login');
    }

    // Use URL-based search directly (more reliable than form submission)
    // SongSelect uses 'search' parameter not 'SearchText'
    console.error('Using URL-based search...');
    const searchUrl = `${BASE_URL}/search/results?search=${encodeURIComponent(query)}&cat=all`;
    console.error(`Search URL: ${searchUrl}`);

    await page.goto(searchUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
    });
    await sleep(3000);

    // Take a screenshot for debugging
    await page.screenshot({ path: path.join(__dirname, 'debug-search.png') });
    console.error('Search screenshot saved to debug-search.png');

    // Log current URL
    console.error(`Search results URL: ${page.url()}`);

    // Wait for results container
    await sleep(2000);

    // Get page HTML for debugging
    const pageContent = await page.content();
    const hasSongs = pageContent.includes('/songs/');
    console.error(`Page contains song links: ${hasSongs}`);

    // Extract song results - using SongSelect's Vue SPA structure
    const songs = await page.evaluate((maxResults) => {
        const results = [];
        const seen = new Set();

        // Find all song links first
        const allLinks = document.querySelectorAll('a[href*="/songs/"]');

        allLinks.forEach((link) => {
            if (results.length >= maxResults) return;

            const href = link.href || link.getAttribute('href') || '';
            const songIdMatch = href.match(/\/songs\/(\d+)/);
            if (!songIdMatch) return;

            const songId = songIdMatch[1];
            if (seen.has(songId)) return;
            seen.add(songId);

            // Get the link text and clean it up
            let linkText = link.innerText || link.textContent || '';

            // Remove "Add to Favourites" and similar
            linkText = linkText.replace(/\nAdd to Favourites?/gi, '');
            linkText = linkText.replace(/Add to Favourites?/gi, '');

            // Split into lines
            const lines = linkText.split('\n').map(l => l.trim()).filter(l => l);

            // First line is usually the title
            let title = lines[0] || '';

            // Second line might be album or authors
            let secondLine = lines[1] || '';

            // Parse artist - look for line with commas (authors)
            let artist = '';
            for (const line of lines) {
                if (line.includes(',') && line !== title) {
                    artist = line;
                    break;
                }
            }

            // If title has " / " it might include artist info
            if (title.includes(' / ')) {
                const parts = title.split(' / ');
                title = parts[0].trim();
                if (!artist && parts.length > 1) {
                    artist = parts.slice(1).join(', ').trim();
                }
            }

            // Clean up artist - remove duplicates and "Add to Favourites"
            artist = artist.replace(/\nAdd to Favourites?/gi, '').trim();

            // Song ID is the CCLI number
            const ccliNumber = songId;

            if (title) {
                results.push({
                    songselect_id: songId,
                    ccli_number: ccliNumber,
                    title: title,
                    artist: artist,
                    url: `https://songselect.ccli.com/songs/${songId}`
                });
            }
        });

        return results;
    }, limit);

    console.error(`Found ${songs.length} songs`);
    return songs;
}

/**
 * Get song details including ChordPro
 */
async function getSongDetails(page, songId, key = null) {
    console.error(`Getting details for song: ${songId}`);

    // Navigate directly to the chord chart page
    const chartKey = key || null; // Will use default if not specified
    let chordChartUrl = `${BASE_URL}/songs/${songId}/viewchordchart`;
    if (chartKey) {
        chordChartUrl += `?key=${chartKey}`;
    }

    console.error(`Navigating to: ${chordChartUrl}`);
    await page.goto(chordChartUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    await sleep(3000);

    // Check if redirected to login page
    const currentUrl = page.url();
    if (currentUrl.includes('signin') || currentUrl.includes('login')) {
        console.error('Redirected to login page, session expired');
        throw new Error('Session expired - need to re-login');
    }

    // Click on the "Chords" tab
    console.error('Clicking on Chords tab...');
    const clickedChords = await page.evaluate(() => {
        const tabs = document.querySelectorAll('button, a, [role="tab"]');
        for (const tab of tabs) {
            if (tab.textContent?.trim() === 'Chords') {
                tab.click();
                return true;
            }
        }
        return false;
    });
    console.error(`Clicked Chords tab: ${clickedChords}`);
    await sleep(2000);

    // If a key was requested, select it
    if (chartKey) {
        console.error(`Selecting key: ${chartKey}`);
        await page.evaluate((keyToSelect) => {
            // Look for key selector dropdown or buttons
            const elements = document.querySelectorAll('button, select option, [class*="key"]');
            for (const el of elements) {
                if (el.textContent?.trim() === keyToSelect) {
                    el.click();
                    return true;
                }
            }
            return false;
        }, chartKey);
        await sleep(1000);
    }

    // Take a debug screenshot
    await page.screenshot({ path: path.join(__dirname, 'debug-chord-page.png') });
    console.error('Chord page screenshot saved');

    // Close any cookie settings popup
    await page.evaluate(() => {
        const closeBtn = document.querySelector('[class*="cookie"] button[class*="close"], [class*="Cookie"] [aria-label="close"]');
        if (closeBtn) closeBtn.click();
        // Also look for X button
        const xBtn = Array.from(document.querySelectorAll('button')).find(b =>
            b.closest('[class*="cookie"], [class*="Cookie"]') &&
            (b.textContent === '×' || b.textContent === 'X' || b.querySelector('svg'))
        );
        if (xBtn) xBtn.click();
    });
    await sleep(500);

    let chordProContent = '';

    // First, MUST close the cookie modal - it blocks clicks
    console.error('Closing cookie modal first...');

    // Press Escape multiple times
    await page.keyboard.press('Escape');
    await sleep(300);
    await page.keyboard.press('Escape');
    await sleep(300);

    // Remove cookie modal from DOM
    await page.evaluate(() => {
        document.querySelectorAll('[class*="cookie"], [id*="cookie"], [id*="Cookie"], [class*="cookiebot"], [id*="CybotCookiebot"], [class*="Cookiebot"]').forEach(el => {
            el.remove();
        });
    });
    await sleep(500);

    // Take screenshot to verify modal is closed
    await page.screenshot({ path: path.join(__dirname, 'debug-modal-closed.png') });
    console.error('Cookie modal removed');

    // Scroll down to ensure chord chart is visible and loaded
    console.error('Scrolling to load chord chart...');
    await page.evaluate(() => {
        window.scrollTo(0, 400);
    });
    await sleep(2000);

    // Take screenshot to see current state
    await page.screenshot({ path: path.join(__dirname, 'debug-after-scroll.png') });
    console.error('Scrolled page');

    // Now look for the download button in Sheet Music Actions area
    // The download button has class 'fa-download' icon and title='Download'
    console.error('Looking for download button...');

    // Find and click the download button with dropdown
    const downloadBtnInfo = await page.evaluate(() => {
        // Find button with title="Download"
        let downloadBtn = document.querySelector('button[title="Download"]');

        // Or look for button containing fa-download icon
        if (!downloadBtn) {
            const icons = document.querySelectorAll('.fa-download, [class*="fa-download"]');
            for (const icon of icons) {
                const btn = icon.closest('button');
                if (btn) {
                    downloadBtn = btn;
                    break;
                }
            }
        }

        if (downloadBtn) {
            const rect = downloadBtn.getBoundingClientRect();
            // Check if there's a dropdown caret/chevron icon
            const caret = downloadBtn.querySelector('.fa-caret-down, .fa-chevron-down, [class*="caret"], [class*="chevron"]');
            let caretX = rect.x + rect.width - 10; // Default to right side where dropdown arrow usually is
            if (caret) {
                const caretRect = caret.getBoundingClientRect();
                caretX = caretRect.x + caretRect.width / 2;
            }
            return {
                found: true,
                x: rect.x + rect.width / 2,
                y: rect.y + rect.height / 2,
                caretX: caretX,
                width: rect.width,
                height: rect.height,
                title: downloadBtn.getAttribute('title'),
                hasDropdown: !!caret,
                html: downloadBtn.outerHTML.substring(0, 200)
            };
        }
        return { found: false };
    });

    console.error(`Download button info: ${JSON.stringify(downloadBtnInfo)}`);

    if (downloadBtnInfo.found) {
        // Try multiple click strategies to open the dropdown
        console.error('Attempting to open download dropdown...');

        // First, ensure we're focused on the main page content
        await page.evaluate(() => {
            document.body.click();
        });
        await sleep(500);

        // Strategy 1: Click the caret/dropdown arrow multiple times with retries
        let dropdownVisible = { visible: false };

        for (let attempt = 0; attempt < 3 && !dropdownVisible.visible; attempt++) {
            console.error(`Dropdown click attempt ${attempt + 1}...`);

            // Click on the download button area
            await page.mouse.click(downloadBtnInfo.caretX, downloadBtnInfo.y);
            await sleep(1500);

            dropdownVisible = await page.evaluate(() => {
                // Look for any visible dropdown/popover/menu
                const menus = document.querySelectorAll('[class*="dropdown"], [class*="popover"], [class*="menu"]');
                for (const menu of menus) {
                    if (menu.offsetHeight > 50 && menu.innerText.length > 10) {
                        return { visible: true, text: menu.innerText.substring(0, 200) };
                    }
                }
                // Also check for any element containing ChordPro that's visible
                const chordProEl = Array.from(document.querySelectorAll('*')).find(el =>
                    el.innerText?.includes('ChordPro') && el.offsetHeight > 0
                );
                if (chordProEl) {
                    return { visible: true, text: chordProEl.innerText.substring(0, 200) };
                }
                return { visible: false };
            });

            if (!dropdownVisible.visible) {
                // Try clicking on the main button area
                await page.mouse.click(downloadBtnInfo.x, downloadBtnInfo.y);
                await sleep(1000);
            }
        }

        console.error(`After dropdown attempts: ${JSON.stringify(dropdownVisible)}`);

        // If dropdown is visible and contains ChordPro, click it!
        if (dropdownVisible.visible && dropdownVisible.text && dropdownVisible.text.includes('ChordPro')) {
            console.error('Dropdown visible with ChordPro option! Clicking it...');

            // Set up download interception
            const client = await page.target().createCDPSession();
            await client.send('Page.setDownloadBehavior', {
                behavior: 'allow',
                downloadPath: __dirname
            });

            // Also intercept fetch/XHR responses that might contain the ChordPro data
            let downloadedContent = '';
            page.on('response', async (response) => {
                const url = response.url();
                if (url.includes('chordpro') || url.includes('ChordPro') || url.includes('download')) {
                    try {
                        const contentType = response.headers()['content-type'] || '';
                        if (!contentType.includes('html') && !contentType.includes('javascript')) {
                            const text = await response.text();
                            if (text && (text.includes('{title') || text.includes('[') || text.includes('Verse'))) {
                                downloadedContent = text;
                                console.error(`Intercepted download: ${text.length} chars`);
                            }
                        }
                    } catch (e) {}
                }
            });

            // Find and click the ChordPro button/link
            const chordProClicked = await page.evaluate(() => {
                // Look for any element containing "ChordPro" text
                const elements = document.querySelectorAll('a, button, [role="menuitem"], li, span, div');
                for (const el of elements) {
                    const text = el.textContent || '';
                    if (text.includes('ChordPro') && el.offsetHeight > 0) {
                        console.log('Found ChordPro element:', el.tagName, text);
                        el.click();
                        return { clicked: true, text: text.trim(), tag: el.tagName };
                    }
                }
                return { clicked: false };
            });

            console.error(`ChordPro click result: ${JSON.stringify(chordProClicked)}`);

            if (chordProClicked.clicked) {
                // Wait for download to complete
                await sleep(5000);

                // Check if we intercepted the content
                if (downloadedContent) {
                    chordProContent = downloadedContent;
                    console.error('Got ChordPro from intercepted download!');
                }

                // Also check for downloaded file
                const downloadedFiles = fs.readdirSync(__dirname).filter(f => f.endsWith('.txt') || f.endsWith('.cho') || f.endsWith('.chordpro'));
                if (downloadedFiles.length > 0) {
                    const latestFile = downloadedFiles[downloadedFiles.length - 1];
                    const content = fs.readFileSync(path.join(__dirname, latestFile), 'utf8');
                    if (content.includes('{title') || content.includes('Verse')) {
                        chordProContent = content;
                        console.error(`Got ChordPro from downloaded file: ${latestFile}`);
                    }
                }

                // Take screenshot after clicking
                await page.screenshot({ path: path.join(__dirname, 'debug-after-chordpro-click.png') });
            }
        }

        // Strategy 2: Focus and use keyboard
        if (!dropdownVisible.visible) {
            console.error('Trying keyboard approach...');
            await page.focus('button[title="Download"]');
            await page.keyboard.press('Enter');
            await sleep(1000);

            dropdownVisible = await page.evaluate(() => {
                const menus = document.querySelectorAll('[class*="dropdown"], [class*="popover"], [class*="menu"]');
                for (const menu of menus) {
                    if (menu.offsetHeight > 50 && menu.innerText.length > 10) {
                        return { visible: true, text: menu.innerText.substring(0, 200) };
                    }
                }
                return { visible: false };
            });
            console.error(`After keyboard: ${JSON.stringify(dropdownVisible)}`);
        }

        // Strategy 3: Double-click or right-click
        if (!dropdownVisible.visible) {
            console.error('Trying double-click...');
            await page.mouse.click(downloadBtnInfo.x, downloadBtnInfo.y, { clickCount: 2 });
            await sleep(1000);
        }

        // Take screenshot to see current state
        await page.screenshot({ path: path.join(__dirname, 'debug-after-dropdown-attempts.png') });
    }

    // Take screenshot to see the dropdown
    await page.screenshot({ path: path.join(__dirname, 'debug-download-dropdown.png') });
    console.error('Download dropdown screenshot saved');

    // Look for ChordPro option in the dropdown
    // User said it has title='Download ChordPro®'
    const chordProBtnInfo = await page.evaluate(() => {
        // Look for element with title containing 'ChordPro'
        const allElements = document.querySelectorAll('a, button, [role="menuitem"], li');
        for (const el of allElements) {
            const title = el.getAttribute('title') || '';
            const text = el.textContent || '';
            if (title.toLowerCase().includes('chordpro') || text.toLowerCase().includes('chordpro')) {
                const rect = el.getBoundingClientRect();
                // Make sure it's visible (not zero size)
                if (rect.width > 0 && rect.height > 0) {
                    return {
                        found: true,
                        x: rect.x + rect.width / 2,
                        y: rect.y + rect.height / 2,
                        title: title,
                        text: text.substring(0, 50),
                        tag: el.tagName,
                        href: el.href || null
                    };
                }
            }
        }
        return { found: false };
    });

    console.error(`ChordPro button info: ${JSON.stringify(chordProBtnInfo)}`);

    // Take screenshot before clicking ChordPro
    await page.screenshot({ path: path.join(__dirname, 'debug-before-chordpro.png') });

    if (chordProBtnInfo.found) {
        // Set up download interception BEFORE clicking
        // This will catch the download request and get the content
        let downloadUrl = null;

        // Listen for download requests
        page.on('response', async (response) => {
            const url = response.url();
            const contentType = response.headers()['content-type'] || '';
            if (url.includes('chordpro') || url.includes('ChordPro') ||
                contentType.includes('text/plain') || contentType.includes('application/octet-stream')) {
                try {
                    const buffer = await response.buffer();
                    const text = buffer.toString('utf8');
                    if (text.includes('{title') || text.includes('[') || text.includes('Verse')) {
                        chordProContent = text;
                        console.error(`Intercepted ChordPro content: ${text.length} chars`);
                    }
                } catch (e) {}
            }
        });

        // If it has an href, fetch it directly
        if (chordProBtnInfo.href) {
            console.error(`ChordPro has direct link: ${chordProBtnInfo.href}`);
            const response = await page.evaluate(async (url) => {
                const resp = await fetch(url, { credentials: 'include' });
                if (resp.ok) {
                    return await resp.text();
                }
                return null;
            }, chordProBtnInfo.href);

            if (response) {
                chordProContent = response;
                console.error(`Downloaded ChordPro: ${chordProContent.length} chars`);
            }
        } else {
            // Click the ChordPro button
            await page.mouse.click(chordProBtnInfo.x, chordProBtnInfo.y);
            console.error('Clicked ChordPro button');
            await sleep(2000);

            // Take screenshot after click
            await page.screenshot({ path: path.join(__dirname, 'debug-after-chordpro-click.png') });
        }
    }

    // List all visible elements that might be dropdown options (for debugging)
    const dropdownItems = await page.evaluate(() => {
        const items = [];
        // Look specifically for dropdown/popover content
        document.querySelectorAll('[class*="dropdown"], [class*="popover"], [class*="menu"], [role="menu"], [role="listbox"]').forEach(container => {
            container.querySelectorAll('a, button, li').forEach(el => {
                const text = el.textContent?.trim();
                const title = el.getAttribute('title');
                if (text || title) {
                    items.push({ text: text?.substring(0, 30), title, tag: el.tagName });
                }
            });
        });
        return items.slice(0, 15);
    });
    console.error(`Dropdown items: ${JSON.stringify(dropdownItems)}`);

    // Try multiple approaches to get ChordPro
    console.error('Attempting to get ChordPro file...');

    // Approach 1: Try the API endpoint pattern for ChordPro download
    // Common patterns: /api/songs/{id}/chordpro, /songs/{id}/download/chordpro, etc.
    const selectedKey = chartKey || 'A';
    const possibleUrls = [
        `${BASE_URL}/api/songs/${songId}/chordpro?key=${selectedKey}`,
        `${BASE_URL}/api/songs/${songId}/download?format=chordpro&key=${selectedKey}`,
        `${BASE_URL}/songs/${songId}/chordpro?key=${selectedKey}`,
        `${BASE_URL}/api/GetChordPro?songId=${songId}&key=${selectedKey}`
    ];

    for (const url of possibleUrls) {
        console.error(`Trying URL: ${url}`);
        try {
            const response = await page.evaluate(async (fetchUrl) => {
                try {
                    const resp = await fetch(fetchUrl, { credentials: 'include' });
                    if (resp.ok) {
                        const contentType = resp.headers.get('content-type') || '';
                        const text = await resp.text();
                        // Check if it looks like ChordPro (starts with { or contains chord markers)
                        if (!contentType.includes('html') && (text.startsWith('{') || text.includes('[') || text.includes('Verse'))) {
                            return text;
                        }
                    }
                } catch (e) {
                    return null;
                }
                return null;
            }, url);

            if (response && response.length > 100) {
                chordProContent = response;
                console.error(`ChordPro downloaded from ${url}: ${chordProContent.length} characters`);
                break;
            }
        } catch (e) {
            console.error(`URL ${url} failed: ${e.message}`);
        }
    }

    // Approach 2: If API didn't work, try clicking through the UI
    if (!chordProContent) {
        console.error('API approach failed, trying UI interaction...');

        // Look for and click the Export button which may have ChordPro
        await page.evaluate(() => {
            const exportBtn = document.querySelector('button[title="Export"]');
            if (exportBtn) {
                exportBtn.click();
                return true;
            }
            return false;
        });
        await sleep(1000);

        // Now look for ChordPro in the dropdown
        const chordProLink = await page.evaluate(() => {
            const elements = document.querySelectorAll('a, button, [role="menuitem"]');
            for (const el of elements) {
                const title = el.getAttribute('title') || '';
                const text = el.textContent || '';
                if (title.toLowerCase().includes('chordpro') || text.toLowerCase().includes('chordpro')) {
                    if (el.href) return { type: 'link', url: el.href };
                    el.click();
                    return { type: 'clicked' };
                }
            }
            return null;
        });

        if (chordProLink?.url) {
            console.error(`Found ChordPro link: ${chordProLink.url}`);
            const response = await page.evaluate(async (url) => {
                const resp = await fetch(url, { credentials: 'include' });
                if (resp.ok) {
                    const contentType = resp.headers.get('content-type') || '';
                    if (!contentType.includes('html')) {
                        return await resp.text();
                    }
                }
                return null;
            }, chordProLink.url);

            if (response) {
                chordProContent = response;
                console.error(`ChordPro downloaded: ${chordProContent.length} characters`);
            }
        }
    }

    // Take final screenshot
    await page.screenshot({ path: path.join(__dirname, 'debug-download-menu.png') });

    // Close cookie popup aggressively BEFORE extracting content
    console.error('Final cookie popup cleanup...');
    for (let i = 0; i < 5; i++) {
        await page.keyboard.press('Escape');
        await page.evaluate(() => {
            // Remove cookie modals from DOM entirely
            document.querySelectorAll('[class*="cookie"], [id*="cookie"], [id*="Cookie"], [class*="cookiebot"], [id*="CybotCookiebot"]').forEach(el => {
                el.remove();
            });
            // Click body to unfocus anything
            document.body.click();
        });
        await sleep(200);
    }

    // Scroll to make sure chord chart content is loaded - scroll the main content area
    await page.evaluate(() => {
        // Find the chord chart container and scroll to it
        const chartContainer = document.querySelector('[class*="chord-chart"], [class*="ChordChart"], [class*="sheet-music"], [class*="song-content"]');
        if (chartContainer) {
            chartContainer.scrollIntoView();
        } else {
            // Fallback - scroll the page
            window.scrollTo(0, 500);
        }
    });
    await sleep(2000);  // Wait longer for content to render

    // Take a screenshot after dismissing the popup
    await page.screenshot({ path: path.join(__dirname, 'debug-after-cookie-dismiss.png') });
    console.error('Screenshot saved after cookie dismiss');

    // NOW extract all song data from the chord chart page (after modal is gone)
    console.error('Extracting song data...');

    // Extract chord chart content from the visible page
    // The chord chart is rendered inside an iframe - need to access iframe content
    let chordChartContent = '';

    // Get all frames on the page
    const frames = page.frames();
    console.error(`Found ${frames.length} frames`);

    // Find the chord sheet frame
    let chordSheetFrame = null;
    for (const frame of frames) {
        const frameUrl = frame.url();
        console.error(`Checking frame: ${frameUrl.substring(0, 100)}`);
        if (frameUrl.includes('viewchordsheet') || frameUrl.includes('chordchart')) {
            chordSheetFrame = frame;
            console.error('Found chord sheet frame!');
        }
    }

    // Try to get content from the chord sheet frame
    if (chordSheetFrame) {
        try {
            // The chord chart content is in an iframe. Navigate to its URL directly
            // with cookies maintained, and wait for Vue to render
            const frameUrl = chordSheetFrame.url();
            console.error(`Frame URL: ${frameUrl}`);

            // Navigate to the frame URL directly
            console.error('Navigating to chord sheet frame URL directly...');
            await page.goto(frameUrl, { waitUntil: 'networkidle2', timeout: 30000 });

            // Wait for Vue to render the chord chart
            console.error('Waiting for chord chart to render...');
            await sleep(3000);

            // Scroll down to trigger lazy loading
            await page.evaluate(() => window.scrollTo(0, 500));
            await sleep(2000);

            // Take screenshot
            await page.screenshot({ path: path.join(__dirname, 'debug-frame-direct.png') });

            // Wait for INTRO or VERSE to appear (Vue rendering)
            try {
                await page.waitForFunction(() => {
                    const text = document.body?.innerText || '';
                    return text.includes('INTRO') || text.includes('VERSE') || text.includes('CHORUS');
                }, { timeout: 15000 });
                console.error('Chord content detected!');
            } catch (e) {
                console.error('Timeout waiting for chord content to render');
            }

            // Extra scroll and much longer wait for Vue to render
            await page.evaluate(() => window.scrollTo(0, 800));
            await sleep(5000);  // Wait 5 seconds for rendering

            // Debug: Check for nested iframes and shadow DOM
            const debugStructure = await page.evaluate(() => {
                const iframes = document.querySelectorAll('iframe');
                const iframeInfo = [];
                iframes.forEach((iframe, i) => {
                    try {
                        const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;
                        const iframeText = iframeDoc?.body?.innerText || '';
                        iframeInfo.push({
                            index: i,
                            src: iframe.src?.substring(0, 100),
                            hasContent: iframeText.length,
                            hasIntro: iframeText.includes('INTRO'),
                            hasVerse: iframeText.includes('VERSE'),
                            sample: iframeText.substring(0, 200)
                        });
                    } catch (e) {
                        iframeInfo.push({ index: i, src: iframe.src?.substring(0, 100), error: e.message });
                    }
                });

                // Check for shadow DOM roots - specifically look for sheet-music-chords
                const sheetMusicDiv = document.querySelector('.sheet-music-chords');
                let shadowContent = '';
                let shadowInfo = null;

                if (sheetMusicDiv && sheetMusicDiv.shadowRoot) {
                    // Get innerText from shadow root (not textContent which includes CSS)
                    const shadowInner = sheetMusicDiv.shadowRoot.innerHTML || '';
                    // Strip HTML to get text
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = shadowInner;
                    shadowContent = tempDiv.innerText || tempDiv.textContent || '';

                    // Check for section markers (case-insensitive)
                    const lowerContent = shadowContent.toLowerCase();
                    shadowInfo = {
                        found: true,
                        contentLength: shadowContent.length,
                        hasIntro: lowerContent.includes('intro'),
                        hasVerse: lowerContent.includes('verse'),
                        hasChorus: lowerContent.includes('chorus'),
                        hasBridge: lowerContent.includes('bridge'),
                        sample: shadowContent.substring(0, 500),
                        fullContent: shadowContent
                    };
                } else {
                    // Check all elements for shadow roots
                    const allElements = document.querySelectorAll('*');
                    allElements.forEach(el => {
                        if (el.shadowRoot && !shadowContent) {
                            const shadowInner = el.shadowRoot.innerHTML || '';
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = shadowInner;
                            const text = tempDiv.innerText || '';
                            if (text.includes('INTRO') || text.includes('VERSE') || text.includes('CHORUS')) {
                                shadowContent = text;
                                shadowInfo = {
                                    found: true,
                                    tag: el.tagName,
                                    class: el.className?.substring?.(0, 50),
                                    contentLength: text.length,
                                    hasIntro: text.includes('INTRO'),
                                    hasVerse: text.includes('VERSE'),
                                    sample: text.substring(0, 500),
                                    fullContent: text
                                };
                            }
                        }
                    });
                }

                return {
                    iframeCount: iframes.length,
                    iframeInfo,
                    shadowInfo: shadowInfo || { found: false },
                    shadowContent: shadowContent
                };
            });

            console.error(`Debug structure: shadowInfo=${JSON.stringify(debugStructure.shadowInfo)}`);

            // If we found shadow DOM content with chord data, use it!
            if (debugStructure.shadowInfo?.found && (debugStructure.shadowInfo.hasIntro || debugStructure.shadowInfo.hasVerse || debugStructure.shadowInfo.hasChorus || debugStructure.shadowInfo.hasBridge)) {
                chordChartContent = debugStructure.shadowContent;
                console.error(`Got chord content from Shadow DOM! Length: ${chordChartContent.length}`);
                console.error(`Preview: ${chordChartContent.substring(0, 500)}`);
            }

            // If we find chord content in a nested iframe, extract it
            if (!chordChartContent) {
                for (const iframeData of debugStructure.iframeInfo || []) {
                    if (iframeData.hasIntro || iframeData.hasVerse) {
                        console.error(`Found chord content in nested iframe ${iframeData.index}!`);
                        const nestedContent = await page.evaluate((iframeIndex) => {
                            const iframe = document.querySelectorAll('iframe')[iframeIndex];
                            if (iframe) {
                                const iframeDoc = iframe.contentDocument || iframe.contentWindow?.document;
                                return iframeDoc?.body?.innerText || '';
                            }
                            return '';
                        }, iframeData.index);

                        if (nestedContent.includes('INTRO') || nestedContent.includes('VERSE')) {
                            chordChartContent = nestedContent;
                            console.error('Extracted from nested iframe!');
                            break;
                        }
                    }
                }
            }

            // Keep trying to get content with retries
            let directFrameContent = { hasIntro: false, hasVerse: false, hasChorus: false, text: '', textLength: 0 };

            for (let attempt = 0; attempt < 3; attempt++) {
                directFrameContent = await page.evaluate(() => {
                    const text = document.body?.innerText || '';
                    return {
                        textLength: text.length,
                        hasIntro: text.includes('INTRO'),
                        hasVerse: text.includes('VERSE'),
                        hasChorus: text.includes('CHORUS'),
                        hasBridge: text.includes('BRIDGE'),
                        text: text,
                        sample: text.substring(0, 1000)
                    };
                });

                console.error(`Attempt ${attempt + 1}: len=${directFrameContent.textLength}, hasIntro=${directFrameContent.hasIntro}, hasVerse=${directFrameContent.hasVerse}, hasChorus=${directFrameContent.hasChorus}`);

                if (directFrameContent.hasIntro || directFrameContent.hasVerse || directFrameContent.hasChorus || directFrameContent.hasBridge) {
                    console.error('Found chord content!');
                    break;
                }

                // Scroll more and wait
                await page.evaluate((offset) => window.scrollTo(0, offset), 400 + (attempt * 200));
                await sleep(2000);
            }

            // Take screenshot after all attempts
            await page.screenshot({ path: path.join(__dirname, 'debug-frame-scrolled.png') });

            console.error(`Final frame content: len=${directFrameContent.textLength}, hasIntro=${directFrameContent.hasIntro}, hasVerse=${directFrameContent.hasVerse}`);

            if (directFrameContent.hasIntro || directFrameContent.hasVerse || directFrameContent.hasChorus || directFrameContent.hasBridge) {
                chordChartContent = directFrameContent.text;
                console.error('Got chord content from direct frame navigation!');
            } else {
                console.error(`Sample: ${directFrameContent.sample}`);
            }
        } catch (e) {
            console.error(`Frame content error: ${e.message}`);
        }
    }

    // The chord chart is visible in screenshots but not extractable via innerText
    // This suggests it might be rendered via canvas, SVG, or special Vue rendering
    // Let's try to find all text nodes in the document - only if we don't have good content yet
    const lowerChordContent = chordChartContent.toLowerCase();
    if (!chordChartContent || chordChartContent.length < 500 || (!lowerChordContent.includes('verse') && !lowerChordContent.includes('intro') && !lowerChordContent.includes('chorus'))) {
        console.error('Attempting deep text extraction...');

        // Try getting text from all possible sources
        const deepExtract = await page.evaluate(() => {
            // Get all text nodes
            const getAllTextNodes = (node) => {
                let text = '';
                if (node.nodeType === Node.TEXT_NODE) {
                    text += node.textContent;
                } else if (node.childNodes) {
                    for (const child of node.childNodes) {
                        text += getAllTextNodes(child);
                    }
                }
                return text;
            };

            const allText = getAllTextNodes(document.body);

            // Also try getting text from specific areas
            const mainContent = document.querySelector('main, [class*="content"], [class*="viewer"]');
            const mainText = mainContent ? mainContent.innerText : '';

            // Check for canvas elements (might be rendering as image)
            const canvases = document.querySelectorAll('canvas');

            // Check for SVG elements
            const svgs = document.querySelectorAll('svg');

            // Look for any element with chord-related classes
            const chordElements = document.querySelectorAll('[class*="chord"], [class*="section"], [class*="intro"], [class*="verse"]');
            let chordText = '';
            chordElements.forEach(el => {
                chordText += el.innerText + '\n';
            });

            return {
                allTextLength: allText.length,
                allTextSample: allText.substring(0, 500),
                mainTextLength: mainText.length,
                hasIntroInAll: allText.includes('INTRO'),
                hasVerseInAll: allText.includes('VERSE'),
                canvasCount: canvases.length,
                svgCount: svgs.length,
                chordElementCount: chordElements.length,
                chordText: chordText,
                fullAllText: allText
            };
        });

        console.error(`Deep extract: allText=${deepExtract.allTextLength}, hasIntro=${deepExtract.hasIntroInAll}, hasVerse=${deepExtract.hasVerseInAll}, canvases=${deepExtract.canvasCount}, svgs=${deepExtract.svgCount}`);
        console.error(`All text sample: ${deepExtract.allTextSample}`);

        if (deepExtract.hasIntroInAll || deepExtract.hasVerseInAll) {
            chordChartContent = deepExtract.fullAllText;
            console.error('Got chord content from deep text extraction!');
        } else if (deepExtract.chordText && deepExtract.chordText.length > 50) {
            chordChartContent = deepExtract.chordText;
            console.error('Got chord content from chord elements!');
        }
    }

    // Take final screenshot showing the state
    await page.screenshot({ path: path.join(__dirname, 'debug-final-state.png') });

    console.error(`Final chord chart content length: ${chordChartContent.length}`);

    // If still not found, try scrolling into view and waiting for Vue to render
    if (!chordChartContent) {
        console.error('Chord content not in frames, trying to scroll to chord area...');

        // Scroll to bottom of the visible content area
        await page.evaluate(() => {
            // Find the chord chart section and scroll to it
            const tabs = document.querySelector('[role="tabpanel"], [class*="tab-content"]');
            if (tabs) {
                tabs.scrollIntoView({ behavior: 'instant', block: 'start' });
            }
            window.scrollTo(0, 600);
        });
        await sleep(2000);

        // Take another screenshot
        await page.screenshot({ path: path.join(__dirname, 'debug-after-extra-scroll.png') });

        // Try again
        for (const frame of page.frames()) {
            try {
                const frameContent = await frame.evaluate(() => {
                    const text = document.body?.innerText || '';
                    if (text.includes('INTRO') || text.includes('VERSE') || text.includes('CHORUS')) {
                        return text;
                    }
                    return null;
                }).catch(() => null);

                if (frameContent) {
                    console.error('Found chord content after scroll');
                    chordChartContent = frameContent;
                    break;
                }
            } catch (e) {}
        }
    }

    console.error(`Chord chart content length: ${chordChartContent.length}`);

    const songData = await page.evaluate((songIdArg, requestedKey, extractedChordChart) => {
        const data = {
            title: '',
            artist: '',
            authors: '',
            ccli_number: songIdArg,
            copyright: '',
            themes: '',
            tempo: null,
            time_signature: '',
            default_key: '',
            lyrics: '',
            chord_chart: ''
        };

        // Get title from h1
        const h1 = document.querySelector('h1');
        if (h1) {
            data.title = h1.innerText?.trim().replace(/\s*[♡❤️]$/, '').replace(/\s+$/, '') || '';
        }

        // Get authors - look for text near "Authors" label or author links
        const pageText = document.body.innerText || '';

        // Try to find authors from the visible content (format: "David Leonard | Jason Ingram | Leslie Jordan")
        const authorsMatch = pageText.match(/(?:Authors?\s*[:\|]?\s*)?([A-Z][a-z]+\s+[A-Z][a-z]+(?:\s*\|\s*[A-Z][a-z]+\s+[A-Z][a-z]+)+)/);
        if (authorsMatch) {
            data.authors = authorsMatch[1].replace(/\s*\|\s*/g, ', ');
            data.artist = data.authors.split(',')[0].trim();
        } else {
            // Fallback to link-based extraction
            const authorLinks = document.querySelectorAll('a[href*="/search"]');
            const authors = [];
            authorLinks.forEach(link => {
                const text = link.innerText?.trim();
                if (text && !text.includes('Search') && text.length < 50 && text.match(/^[A-Z]/)) {
                    authors.push(text);
                }
            });
            if (authors.length > 0) {
                data.authors = authors.slice(0, 5).join(', ');
                data.artist = authors[0];
            }
        }

        // Get tempo and time signature (format: "Key - A | Tempo - 144 | Time - 6/8")
        const tempoMatch = pageText.match(/Tempo\s*[-:]\s*(\d+)/i);
        if (tempoMatch) {
            data.tempo = parseInt(tempoMatch[1]);
        }

        const timeMatch = pageText.match(/Time\s*[-:]\s*(\d+\/\d+)/i);
        if (timeMatch) {
            data.time_signature = timeMatch[1];
        }

        // Get default key
        const keyMatch = pageText.match(/Key\s*[-:]\s*([A-G][#b]?m?)/i);
        if (keyMatch) {
            data.default_key = keyMatch[1];
        }

        // Get CCLI number from page
        const ccliMatch = pageText.match(/Song Number\s*(\d+)/i);
        if (ccliMatch) {
            data.ccli_number = ccliMatch[1];
        }

        // Use the pre-extracted chord chart content
        if (extractedChordChart) {
            // Parse the chord chart - extract just the song content
            const lines = extractedChordChart.split('\n');
            let chartLines = [];
            let inChordChart = false;
            const sectionKeywords = ['INTRO', 'VERSE', 'CHORUS', 'PRE-CHORUS', 'BRIDGE', 'TAG', 'OUTRO', 'INTERLUDE', 'INSTRUMENTAL', 'ENDING', 'TURNAROUND'];
            const skipKeywords = ['TRANSPOSE KEY', 'CHART TYPE', 'SHEET MUSIC', 'COLUMNS', 'SCALE', 'FONT', 'RESET',
                'GENERAL', 'LYRICS', 'CHORDS', 'LEAD', 'VOCAL', 'MULTITRACKS', 'REHEARSE', 'PREFERENCES',
                'LEARN MORE', 'TOP MULTITRACKS', 'FREE WORSHIP', 'COOKIE', 'NECESSARY', 'STATISTICS', 'MARKETING',
                'DOWNLOAD', 'PRINT', 'SHARE', 'EMAIL', 'UPGRADE', 'YOUR LIBRARY', 'ACTIVITY', 'THEMES',
                'CCLI TOP', 'LYRIC VIDEOS', 'LITURGY', 'SONGS BY', 'PUBLIC DOMAIN', 'SEE WHAT', 'SIGN IN', 'SIGN OUT',
                'GREAT ARE YOU LORD', 'SONGSELECT'];

            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed) {
                    if (inChordChart) chartLines.push('');
                    continue;
                }

                const upperLine = trimmed.toUpperCase();

                // Skip UI/navigation elements
                if (skipKeywords.some(s => upperLine.includes(s) && !sectionKeywords.some(k => upperLine === k))) {
                    continue;
                }

                // Check for section headers
                const isSection = sectionKeywords.some(s => upperLine === s || upperLine.match(new RegExp(`^${s}\\s*\\d*$`)));
                if (isSection) {
                    inChordChart = true;
                    chartLines.push('');
                    chartLines.push(trimmed);
                    continue;
                }

                // Stop at copyright
                if (trimmed.includes('©') || upperLine.includes('CCLI SONG') || upperLine.includes('CCLI LICENSE')) {
                    if (trimmed.includes('©')) {
                        data.copyright = trimmed;
                    }
                    break;
                }

                // Capture chord/lyric lines
                if (inChordChart) {
                    if (trimmed.length > 120) continue;
                    if (trimmed.length < 2) continue;
                    chartLines.push(trimmed);
                }
            }

            data.chord_chart = chartLines.join('\n').trim();
        }

        return data;
    }, songId, chartKey, chordChartContent);

    // Use the downloaded ChordPro content if available
    if (chordProContent) {
        songData.chord_chart = chordProContent;
        console.error('Using downloaded ChordPro content');
        return songData;
    }

    // Use scraped chord chart content from Shadow DOM if available
    if (chordChartContent && chordChartContent.length > 500) {
        console.error(`Using scraped chord chart content: ${chordChartContent.length} chars`);

        // Clean up the content - remove CSS styling that gets included
        let cleanedContent = chordChartContent;

        // Find where the actual song content starts (after CSS)
        // Look for the song title pattern or section headers
        const titleMatch = cleanedContent.match(/Great Are You Lord|([A-Z][a-z]+\s+[A-Z][a-z]+.*\n\s*David Leonard|Jason Ingram)/);
        if (titleMatch) {
            const titleIndex = cleanedContent.indexOf(titleMatch[0]);
            if (titleIndex > 0) {
                cleanedContent = cleanedContent.substring(titleIndex);
            }
        }

        // Also try to find where Intro/Verse starts if no title found
        if (cleanedContent.startsWith('.cpro') || cleanedContent.startsWith('\n')) {
            const sectionMatch = cleanedContent.match(/\n(Intro|INTRO|Verse|VERSE)/);
            if (sectionMatch) {
                const sectionIndex = cleanedContent.indexOf(sectionMatch[0]);
                // Also get the title/author info before that (look for song title)
                const lines = cleanedContent.substring(0, sectionIndex).split('\n');
                let songStartIndex = sectionIndex;
                for (let i = lines.length - 1; i >= 0; i--) {
                    const line = lines[i].trim();
                    if (line.length > 0 && !line.startsWith('.') && !line.startsWith('{') && !line.startsWith('/*') && !line.startsWith('*') && !line.startsWith('@')) {
                        // Found potential song info
                        songStartIndex = cleanedContent.indexOf(line);
                        break;
                    }
                }
                cleanedContent = cleanedContent.substring(songStartIndex);
            }
        }

        // Strip any remaining CSS at the end
        const cssEndMatch = cleanedContent.match(/\n:root\s*\{[\s\S]*$/);
        if (cssEndMatch) {
            cleanedContent = cleanedContent.substring(0, cleanedContent.indexOf(cssEndMatch[0]));
        }

        // Extract metadata from the cleaned chord chart BEFORE adding ChordPro headers
        // Add leading newline for regex matching
        const contentWithNewline = '\n' + cleanedContent;

        // Authors (format: "David Leonard | Jason Ingram | Leslie Jordan")
        // First try the pipe-separated format
        let authorsMatch = contentWithNewline.match(/\n([A-Z][a-z]+\s+[A-Z][a-z]+\s*\|\s*[^\n]+)\n/);

        // Also try matching from first few lines more loosely
        if (!authorsMatch) {
            const lines = cleanedContent.split('\n');
            for (let i = 0; i < Math.min(5, lines.length); i++) {
                if (lines[i].includes('|') && lines[i].match(/[A-Z][a-z]+/)) {
                    authorsMatch = [null, lines[i].trim()];
                    console.error(`Found authors on line ${i}: ${lines[i]}`);
                    break;
                }
            }
        }

        if (authorsMatch) {
            const extractedAuthors = authorsMatch[1].replace(/\s*\|\s*/g, ', ').trim();
            console.error(`Extracted authors from chord chart: ${extractedAuthors}`);
            songData.authors = extractedAuthors;
            songData.artist = extractedAuthors.split(',')[0].trim();
        } else {
            console.error('Authors not found. First 5 lines: ' + cleanedContent.split('\n').slice(0,5).join(' | '));
        }

        // Key, Tempo, Time (format: "Key - A | Tempo - 144 | Time - 6/8")
        const keyMatch = cleanedContent.match(/Key\s*[-:]\s*([A-G][#b]?m?)/i);
        if (keyMatch) {
            songData.default_key = keyMatch[1];
        }

        const tempoMatch = cleanedContent.match(/Tempo\s*[-:]\s*(\d+)/i);
        if (tempoMatch) {
            songData.tempo = parseInt(tempoMatch[1]);
        }

        const timeMatch = cleanedContent.match(/Time\s*[-:]\s*(\d+\/\d+)/i);
        if (timeMatch) {
            songData.time_signature = timeMatch[1];
        }

        // Copyright
        const copyrightMatch = cleanedContent.match(/©\s*(\d+[^©\n]+)/);
        if (copyrightMatch) {
            songData.copyright = '© ' + copyrightMatch[1].trim();
        }

        // Now set the chord chart (after extracting metadata)
        songData.chord_chart = cleanedContent.trim();
    }

    // Format as ChordPro if we have content from scraping
    if (songData.chord_chart) {
        const lines = songData.chord_chart.split('\n');

        // Extract proper authors from the chord chart content (before formatting)
        // Look for "Name Name | Name Name | Name Name" pattern
        for (let i = 0; i < Math.min(10, lines.length); i++) {
            const line = lines[i].trim();
            // Look for author line: multiple names separated by |
            if (line.match(/^[A-Z][a-z]+\s+[A-Z][a-z]+\s*\|/) && !line.includes('Key -')) {
                const extractedAuthors = line.replace(/\s*\|\s*/g, ', ').trim();
                console.error(`Found authors in chord chart line ${i}: ${extractedAuthors}`);
                songData.authors = extractedAuthors;
                songData.artist = extractedAuthors.split(',')[0].trim();
                break;
            }
        }

        let chordProLines = [];

        // Add metadata
        chordProLines.push(`{title: ${songData.title || 'Unknown'}}`);
        if (songData.authors) {
            chordProLines.push(`{artist: ${songData.authors}}`);
        }
        chordProLines.push(`{key: ${chartKey || songData.default_key || 'Unknown'}}`);
        if (songData.ccli_number) {
            chordProLines.push(`{ccli: ${songData.ccli_number}}`);
        }
        chordProLines.push('');

        // Process each line
        const sectionKeywords = ['Verse', 'Chorus', 'Pre-Chorus', 'Bridge', 'Tag', 'Intro', 'Outro', 'Interlude', 'Instrumental'];

        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) continue;

            // Check if section header
            const sectionMatch = sectionKeywords.find(s =>
                trimmed.toLowerCase().startsWith(s.toLowerCase())
            );

            if (sectionMatch) {
                chordProLines.push('');
                // Extract section number if present (e.g., "Verse 1" -> "verse 1")
                const sectionText = trimmed.toLowerCase();
                chordProLines.push(`{${sectionText}}`);
            } else {
                chordProLines.push(trimmed);
            }
        }

        songData.chord_chart = chordProLines.join('\n');
    }

    return songData;
}

/**
 * Main entry point
 */
async function main() {
    const { command, params, positional } = parseArgs();

    if (!command) {
        console.error('Usage: node scraper.js <command> [options]');
        console.error('Commands: search, get-song, test-login');
        process.exit(1);
    }

    const username = params.username;
    const password = params.password;

    if (!username || !password) {
        console.error('Error: --username and --password are required');
        process.exit(1);
    }

    // Launch browser using puppeteer-real-browser to bypass Turnstile
    const headless = params.headless !== 'false'; // Allow --headless=false for debugging

    const { page, browser } = await connect({
        headless: headless ? 'auto' : false,
        turnstile: true,  // Auto-handle Cloudflare Turnstile
        fingerprint: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });

    try {
        // Set viewport
        await page.setViewport({ width: 1280, height: 800 });

        // Load existing cookies
        await loadCookies(page);

        // Check if we need to login
        let loggedIn = await isLoggedIn(page);

        if (!loggedIn) {
            console.error('Not logged in, attempting login...');
            loggedIn = await login(page, username, password);

            if (!loggedIn) {
                console.error('Login failed');
                console.log(JSON.stringify({ error: 'Login failed' }));
                process.exit(1);
            }
        }

        // Execute command
        let result;

        switch (command) {
            case 'test-login':
                result = { success: true, message: 'Login successful' };
                break;

            case 'search':
                const query = positional[0];
                if (!query) {
                    console.error('Error: Search query required');
                    process.exit(1);
                }
                const limit = parseInt(params.limit) || 20;
                result = await searchSongs(page, query, limit);
                break;

            case 'get-song':
                const songId = positional[0];
                if (!songId) {
                    console.error('Error: Song ID required');
                    process.exit(1);
                }
                const key = params.key || null;
                result = await getSongDetails(page, songId, key);
                break;

            default:
                console.error(`Unknown command: ${command}`);
                process.exit(1);
        }

        // Output result as JSON
        console.log(JSON.stringify(result, null, 2));

    } catch (error) {
        console.error(`Error: ${error.message}`);
        console.log(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        await browser.close();
    }
}

main();

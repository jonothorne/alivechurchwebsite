/**
 * Search WorshipTogether by CCLI number and return the song URL
 *
 * Usage: node search-by-ccli.js <ccli_number>
 * Returns: The WorshipTogether song URL if found, or empty string if not
 */

const puppeteer = require('puppeteer');

async function searchByCCLI(ccliNumber) {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();

        // Set a reasonable viewport
        await page.setViewport({ width: 1280, height: 800 });

        // Go to search results page with CCLI number
        const searchUrl = `https://www.worshiptogether.com/search-results/#?cludoquery=${ccliNumber}&cludopage=1`;
        await page.goto(searchUrl, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for search results to load (Cludo renders them)
        await page.waitForSelector('.search-results, .cludo-r, .no-results', { timeout: 10000 })
            .catch(() => null);

        // Small delay for results to fully render
        await new Promise(r => setTimeout(r, 2000));

        // Try to find a song link in the results
        const songUrl = await page.evaluate(() => {
            // Look for song links in search results
            const songLinks = document.querySelectorAll('a[href*="/songs/"]');
            for (const link of songLinks) {
                const href = link.getAttribute('href');
                // Skip navigation links, only get actual song pages
                if (href && href.match(/\/songs\/[a-z0-9-]+-[a-z0-9-]+\/?$/)) {
                    return href.startsWith('http') ? href : 'https://www.worshiptogether.com' + href;
                }
            }
            return '';
        });

        return songUrl;

    } catch (error) {
        console.error('Error:', error.message);
        return '';
    } finally {
        await browser.close();
    }
}

// Main execution
const ccliNumber = process.argv[2];

if (!ccliNumber) {
    console.error('Usage: node search-by-ccli.js <ccli_number>');
    process.exit(1);
}

searchByCCLI(ccliNumber)
    .then(url => {
        console.log(url);
        process.exit(url ? 0 : 1);
    })
    .catch(err => {
        console.error(err.message);
        process.exit(1);
    });

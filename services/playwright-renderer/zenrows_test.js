const { chromium } = require('playwright');
const key = '13afb9566971d8c324655065df79b4ec932ba8b0';

// Enable premium proxy + stealth mode + JS rendering
const cdpUrl = `wss://browser.zenrows.com?apikey=${key}&premium_proxy=true&proxy_country=us`;
const url = 'https://www.amazon.com/dp/B09MZTSSR2';

(async () => {
  console.log('Connecting to ZenRows (premium_proxy + us)...');
  const browser = await chromium.connectOverCDP(cdpUrl);
  console.log('Connected.');
  const context = await browser.newContext({
    locale: 'en-US',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
  });
  const page = await context.newPage();
  console.log('Navigating...');
  await page.goto(url, { waitUntil: 'load', timeout: 90000 });
  const html = await page.content();
  const title = await page.title();
  console.log('title:', title.substring(0, 100));
  console.log('html length:', html.length);
  console.log('has captcha:', html.toLowerCase().includes('captcha'));
  console.log('has cannot-be-shipped:', html.includes('cannot be shipped'));
  console.log('a-offscreen count:', (html.match(/a-offscreen/g)||[]).length);
  console.log('has Instant Pot:', html.includes('Instant Pot'));
  await browser.close();
})().catch(e => { console.error('Error:', e.message); process.exit(1); });

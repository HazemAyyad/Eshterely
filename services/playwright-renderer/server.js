'use strict';

const express = require('express');
const { chromium } = require('playwright');

const app = express();
app.use(express.json());

const PORT = parseInt(process.env.PORT || '3001', 10);
const DEFAULT_TIMEOUT_MS = parseInt(process.env.DEFAULT_TIMEOUT_SECONDS || '30', 10) * 1000;
const MAX_TIMEOUT_MS = 120_000;

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
  '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

// JavaScript injected before page scripts — hides headless/webdriver indicators.
const STEALTH_SCRIPT = `
  Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  Object.defineProperty(navigator, 'plugins', {
    get: () => {
      const arr = [
        { name: 'Chrome PDF Plugin', filename: 'internal-pdf-viewer', description: 'Portable Document Format' },
        { name: 'Chrome PDF Viewer', filename: 'mhjfbmdgcfjbbpaeojofohoefgiehjai', description: '' },
        { name: 'Native Client', filename: 'internal-nacl-plugin', description: '' },
      ];
      arr.item = i => arr[i];
      arr.namedItem = n => arr.find(p => p.name === n) || null;
      arr.refresh = () => {};
      return arr;
    },
  });
  Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
  if (!window.chrome) {
    window.chrome = { runtime: {}, loadTimes: () => ({}), csi: () => ({}) };
  }
`;

// ─── Health check ─────────────────────────────────────────────────────────────

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'playwright-renderer', pid: process.pid });
});

// ─── Render endpoint ──────────────────────────────────────────────────────────

/**
 * POST /render
 * Body: { url: string, timeoutSeconds?: number, zenrowsKey?: string }
 *
 * When zenrowsKey is provided the page is rendered via ZenRows cloud browser
 * (remote CDP) which uses US residential IPs and built-in anti-bot bypass.
 * Otherwise a local headless Chromium instance is used.
 *
 * Response:
 *   { success: true,  html: string, finalUrl: string, title: string, provider: string }
 *   { success: false, error: string }
 */
app.post('/render', async (req, res) => {
  const { url, timeoutSeconds, zenrowsKey } = req.body || {};

  if (!url || typeof url !== 'string') {
    return res.status(400).json({ success: false, error: 'url is required and must be a string' });
  }

  const timeoutMs = Math.min(
    (Number.isFinite(timeoutSeconds) ? timeoutSeconds * 1000 : DEFAULT_TIMEOUT_MS),
    MAX_TIMEOUT_MS,
  );

  // Prefer key from request body; fall back to environment variable.
  const resolvedZenrowsKey = zenrowsKey || process.env.ZENROWS_API_KEY || '';
  const useZenrows = resolvedZenrowsKey !== '';

  let browser = null;

  try {
    if (useZenrows) {
      // ── ZenRows cloud browser via CDP ────────────────────────────────────────
      const cdpUrl = `wss://browser.zenrows.com?apikey=${resolvedZenrowsKey}`;
      browser = await chromium.connectOverCDP(cdpUrl);

      const context = browser.contexts()[0] || await browser.newContext();
      const page = await context.newPage();

      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });

      try {
        await page.waitForLoadState('networkidle', { timeout: Math.min(timeoutMs, 8_000) });
      } catch {
        // networkidle timed out — content is still usable.
      }

      const html     = await page.content();
      const finalUrl = page.url();
      const title    = await page.title().catch(() => '');
      await page.close().catch(() => {});

      return res.json({ success: true, html, finalUrl, title, provider: 'zenrows' });

    } else {
      // ── Local headless Chromium ───────────────────────────────────────────────
      const executablePath = (() => {
        const downloaded = chromium.executablePath();
        const fs = require('fs');
        if (fs.existsSync(downloaded)) return downloaded;
        const systemPaths = [
          'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
          'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
          '/usr/bin/google-chrome',
          '/usr/bin/chromium-browser',
          '/usr/bin/chromium',
        ];
        const found = systemPaths.find(p => fs.existsSync(p));
        if (found) return found;
        throw new Error('No Chromium/Chrome binary found. Run: npx playwright install chromium');
      })();

      browser = await chromium.launch({
        headless: true,
        executablePath,
        args: [
          '--disable-blink-features=AutomationControlled',
          '--disable-infobars',
          '--no-sandbox',
          '--disable-setuid-sandbox',
        ],
      });

      const context = await browser.newContext({
        userAgent: USER_AGENT,
        viewport: { width: 1280, height: 900 },
        locale: 'en-US',
        timezoneId: 'America/New_York',
        extraHTTPHeaders: {
          'Accept-Language': 'en-US,en;q=0.9',
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
          'sec-ch-ua': '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
          'sec-ch-ua-mobile': '?0',
          'sec-ch-ua-platform': '"Windows"',
          'sec-fetch-dest': 'document',
          'sec-fetch-mode': 'navigate',
          'sec-fetch-site': 'none',
          'sec-fetch-user': '?1',
          'upgrade-insecure-requests': '1',
        },
      });

      await context.addInitScript(STEALTH_SCRIPT);

      const page = await context.newPage();
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });

      try {
        await page.waitForLoadState('networkidle', { timeout: Math.min(timeoutMs, 8_000) });
      } catch { /* timed out — usable */ }

      const html     = await page.content();
      const finalUrl = page.url();
      const title    = await page.title().catch(() => '');

      return res.json({ success: true, html, finalUrl, title, provider: 'local' });
    }

  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    console.error('[playwright-renderer] render failed:', message, { url, provider: useZenrows ? 'zenrows' : 'local' });
    return res.status(500).json({ success: false, error: message });

  } finally {
    if (browser) {
      await browser.close().catch(() => {});
    }
  }
});

// ─── Start ────────────────────────────────────────────────────────────────────

app.listen(PORT, () => {
  const zenrowsKey = process.env.ZENROWS_API_KEY || '';
  const mode = zenrowsKey ? 'ZenRows CDP (cloud browser)' : 'local Chromium';
  console.log(`[playwright-renderer] listening on http://localhost:${PORT} — default mode: ${mode}`);
});

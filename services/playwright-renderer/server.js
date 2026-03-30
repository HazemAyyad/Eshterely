'use strict';

const express = require('express');
const { chromium } = require('playwright');

const app = express();
app.use(express.json());

const PORT = parseInt(process.env.PORT || '3001', 10);
const DEFAULT_TIMEOUT_MS = parseInt(process.env.DEFAULT_TIMEOUT_SECONDS || '30', 10) * 1000;
const MAX_TIMEOUT_MS = 120_000;

// Realistic desktop user-agent — helps bypass basic bot detection.
const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
  '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

// ─── Health check ────────────────────────────────────────────────────────────

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'playwright-renderer', pid: process.pid });
});

// ─── Render endpoint ──────────────────────────────────────────────────────────

/**
 * POST /render
 * Body: { url: string, timeoutSeconds?: number }
 * Response:
 *   { success: true,  html: string, finalUrl: string, title: string }
 *   { success: false, error: string }
 */
app.post('/render', async (req, res) => {
  const { url, timeoutSeconds } = req.body || {};

  if (!url || typeof url !== 'string') {
    return res.status(400).json({ success: false, error: 'url is required and must be a string' });
  }

  const timeoutMs = Math.min(
    (Number.isFinite(timeoutSeconds) ? timeoutSeconds * 1000 : DEFAULT_TIMEOUT_MS),
    MAX_TIMEOUT_MS,
  );

  let browser = null;

  try {
    // Use system Chrome if the downloaded Chromium binary is not available.
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

    browser = await chromium.launch({ headless: true, executablePath });

    const context = await browser.newContext({
      userAgent: USER_AGENT,
      viewport: { width: 1280, height: 900 },
      locale: 'en-US',
      extraHTTPHeaders: {
        'Accept-Language': 'en-US,en;q=0.9',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
      },
    });

    const page = await context.newPage();

    // Navigate — wait for DOM to be ready; networkidle can be too slow for some stores.
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });

    // Give JS a moment to populate the page without waiting for full network idle.
    try {
      await page.waitForLoadState('networkidle', { timeout: Math.min(timeoutMs, 8_000) });
    } catch {
      // networkidle timed out — page content is still usable.
    }

    const html     = await page.content();
    const finalUrl = page.url();
    const title    = await page.title().catch(() => '');

    return res.json({ success: true, html, finalUrl, title });

  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    console.error('[playwright-renderer] render failed:', message, { url });
    return res.status(500).json({ success: false, error: message });

  } finally {
    if (browser) {
      await browser.close().catch(() => {});
    }
  }
});

// ─── Start ────────────────────────────────────────────────────────────────────

app.listen(PORT, () => {
  console.log(`[playwright-renderer] listening on http://localhost:${PORT}`);
});

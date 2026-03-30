# Playwright Renderer

Headless Chromium microservice used by the Laravel backend as a free rendered-HTML fallback before paid scrapers.

## Setup

```bash
cd services/playwright-renderer
npm install
npx playwright install chromium   # downloads Chromium binary (~150 MB)
cp .env.example .env
node server.js
```

## Endpoints

### GET /health
Returns `{ ok: true }`.

### POST /render
```json
{ "url": "https://www.amazon.com/dp/B08N5WRWNW", "timeoutSeconds": 30 }
```
Returns:
```json
{ "success": true, "html": "<!DOCTYPE html>...", "finalUrl": "https://...", "title": "Product title" }
```
On failure:
```json
{ "success": false, "error": "Navigation timeout" }
```

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `PORT` | `3001` | Port to listen on |
| `DEFAULT_TIMEOUT_SECONDS` | `30` | Per-request timeout |

## Laravel integration

Set in `.env`:
```
PLAYWRIGHT_SERVICE_URL=http://localhost:3001
PLAYWRIGHT_TIMEOUT_SECONDS=30
```

Playwright runs **after** direct HTTP (if blocked) and **before** ScraperAPI (paid).

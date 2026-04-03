@extends('layouts.admin')

@section('title', 'Product Import Tester')

@section('content')
<div class="row g-3">

    {{-- ── Request Form ──────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0">Product Import Tester</h5>
                    <small class="text-muted">Uses the exact same pipeline as the mobile app. Paste any product URL to debug the full extraction flow.</small>
                </div>
            </div>
            <div class="card-body">
                <form id="tester-form">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold mb-1">Product URL <span class="text-danger">*</span></label>
                            <input type="url" id="input-url" class="form-control font-monospace" placeholder="https://www.amazon.com/dp/..." required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold mb-1">Strategy</label>
                            <select id="input-strategy" class="form-select">
                                <option value="auto" selected>auto</option>
                                <option value="jsonld">jsonld</option>
                                <option value="meta">meta</option>
                                <option value="dom">dom</option>
                                <option value="openai">openai</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small fw-semibold mb-1">Qty</label>
                            <input type="number" id="input-qty" class="form-control" value="1" min="1" max="100">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold mb-1">Destination</label>
                            <input type="text" id="input-country" class="form-control" placeholder="SA, AE, US …">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small fw-semibold mb-1">Carrier</label>
                            <select id="input-carrier" class="form-select">
                                <option value="auto" selected>auto</option>
                                <option value="dhl">DHL</option>
                                <option value="ups">UPS</option>
                                <option value="fedex">FedEx</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-1">
                            <button type="submit" class="btn btn-primary w-100" id="btn-test">
                                <span id="btn-label">Test</span>
                                <span id="btn-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Summary cards ─────────────────────────────────────────────── --}}
    <div id="summary-row" class="col-12 d-none">
        <div class="row g-2">
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Store</div>
                        <div class="fw-bold" id="s-store">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Provider</div>
                        <div class="fw-bold font-monospace small" id="s-source">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Price</div>
                        <div class="fw-bold text-primary" id="s-price">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Measurements</div>
                        <div class="fw-bold" id="s-meas">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Shipping</div>
                        <div class="fw-bold" id="s-shipping">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Total time</div>
                        <div class="fw-bold" id="s-time">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Error alert ────────────────────────────────────────────────── --}}
    <div id="error-alert" class="col-12 d-none">
        <div class="alert alert-danger mb-0">
            <strong>Error:</strong> <span id="error-msg"></span>
            <pre id="error-trace" class="mt-2 mb-0 small d-none"></pre>
        </div>
    </div>

    {{-- ── Warnings ───────────────────────────────────────────────────── --}}
    <div id="warnings-box" class="col-12 d-none">
        <div class="alert alert-warning mb-0" id="warnings-inner"></div>
    </div>

    {{-- ── Main result tabs ───────────────────────────────────────────── --}}
    <div id="result-tabs" class="col-12 d-none">
        <ul class="nav nav-tabs flex-wrap" id="result-nav">
            <li class="nav-item"><button class="nav-link active" data-tab="store">Store Resolution</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="providers">Provider Timeline</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="product">Product Data</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="measurements">Measurements</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="shipping">Shipping</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="pricing">Pricing</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="variations">Variants</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="debug">Debug ▾</button></li>
        </ul>

        <div class="card border-0 border-top-0 shadow-sm rounded-0 rounded-bottom">
            <div class="card-body">

                {{-- Store Resolution --}}
                <div id="tab-store" class="tab-pane">
                    <h6 class="text-muted small fw-semibold text-uppercase mb-3">Import Request</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="store-table"></tbody>
                    </table>
                </div>

                {{-- Provider Timeline --}}
                <div id="tab-providers" class="tab-pane d-none">
                    <h6 class="text-muted small fw-semibold text-uppercase mb-3">Provider Attempts (in order)</h6>
                    <div id="provider-timeline"></div>
                    <h6 class="text-muted small fw-semibold text-uppercase mt-4 mb-2">Fetch Info</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="fetch-table"></tbody>
                    </table>
                    <h6 class="text-muted small fw-semibold text-uppercase mt-3 mb-2">Timing</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="timing-table"></tbody>
                    </table>
                </div>

                {{-- Product Data --}}
                <div id="tab-product" class="tab-pane d-none">
                    <div class="row g-3 align-items-start">
                        <div class="col-auto">
                            <img id="p-image" src="" alt="" class="rounded border" style="width:100px;height:100px;object-fit:cover;display:none;">
                            <div id="p-image-placeholder" class="rounded border bg-light d-flex align-items-center justify-content-center" style="width:100px;height:100px;">
                                <span class="text-muted small">No image</span>
                            </div>
                        </div>
                        <div class="col">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="product-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Measurements --}}
                <div id="tab-measurements" class="tab-pane d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted small fw-semibold text-uppercase mb-2">Weight &amp; Dimensions</h6>
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="meas-table"></tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small fw-semibold text-uppercase mb-2">Shipping Estimate Source</h6>
                            <div id="meas-source-badge" class="mb-3"></div>
                            <div id="meas-fallback-note" class="d-none">
                                <div class="alert alert-warning py-2 mb-0 small">
                                    <strong>⚠ Fallback dimensions used.</strong><br>
                                    Real measurements were not found for this product. The shipping quote is based on default package dimensions from <code>config/shipping.php</code>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Shipping --}}
                <div id="tab-shipping" class="tab-pane d-none">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted small fw-semibold text-uppercase mb-2">Shipping Quote</h6>
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="shipping-table"></tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted small fw-semibold text-uppercase mb-2">Review Status</h6>
                            <div id="shipping-review-badge"></div>
                        </div>
                    </div>
                </div>

                {{-- Pricing --}}
                <div id="tab-pricing" class="tab-pane d-none">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="pricing-table"></tbody>
                    </table>
                    <h6 class="text-muted small fw-semibold text-uppercase mt-3 mb-2">Breakdown</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Key</th><th>Label</th><th>Amount</th><th>Estimated</th></tr></thead>
                        <tbody id="breakdown-table"></tbody>
                    </table>
                </div>

                {{-- Variations --}}
                <div id="tab-variations" class="tab-pane d-none">
                    <div id="variations-body"></div>
                </div>

                {{-- Debug (collapsible sections) --}}
                <div id="tab-debug" class="tab-pane d-none">

                    {{-- ScraperAPI raw --}}
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center"
                            type="button" data-bs-toggle="collapse" data-bs-target="#debug-raw">
                            <span>ScraperAPI Raw Response</span><span id="raw-key-count" class="badge bg-secondary">0 keys</span>
                        </button>
                        <div id="debug-raw" class="collapse mt-2">
                            <pre id="raw-pre" class="bg-dark text-light p-3 rounded small mb-0" style="max-height:450px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
                        </div>
                    </div>

                    {{-- AI parsed JSON --}}
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center"
                            type="button" data-bs-toggle="collapse" data-bs-target="#debug-ai">
                            <span>AI Parsed JSON</span><span id="ai-json-badge" class="badge bg-secondary">n/a</span>
                        </button>
                        <div id="debug-ai" class="collapse mt-2">
                            <pre id="ai-json-pre" class="bg-dark text-light p-3 rounded small mb-0" style="max-height:450px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
                        </div>
                    </div>

                    {{-- Full JSON --}}
                    <div>
                        <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center"
                            type="button" data-bs-toggle="collapse" data-bs-target="#debug-full">
                            <span>Full Response JSON</span>
                            <button class="btn btn-xs btn-outline-light btn-sm py-0" onclick="copyFull(event)">Copy</button>
                        </button>
                        <div id="debug-full" class="collapse mt-2">
                            <pre id="full-json" class="bg-dark text-light p-3 rounded small mb-0" style="max-height:550px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
                        </div>
                    </div>
                </div>

            </div>{{-- /card-body --}}
        </div>{{-- /card --}}
    </div>{{-- /result-tabs --}}

</div>{{-- /row --}}

<script>
let lastResponse = null;

// ── Tab switching ──────────────────────────────────────────────────────────
document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('d-none'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.remove('d-none');
    });
});

// ── Submit ─────────────────────────────────────────────────────────────────
document.getElementById('tester-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    setLoading(true);
    hideAll();

    const payload = {
        url:                  document.getElementById('input-url').value.trim(),
        extraction_strategy:  document.getElementById('input-strategy').value,
        quantity:             parseInt(document.getElementById('input-qty').value) || 1,
        carrier:              document.getElementById('input-carrier').value,
        destination_country:  document.getElementById('input-country').value.trim() || undefined,
        _token:               '{{ csrf_token() }}',
    };

    try {
        const res  = await fetch('{{ route('admin.config.product-import.tester.test') }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();
        lastResponse = data;

        if (!data.ok) {
            renderStoreResolution(data.store_resolution);
            renderProviderTimeline(data.provider_attempts);
            showError(data.error, data.trace);
            return;
        }
        renderResponse(data);
    } catch (err) {
        showError(err.message, null);
    } finally {
        setLoading(false);
    }
});

// ── Main render ────────────────────────────────────────────────────────────
function renderResponse(data) {
    const p      = data.product || {};
    const timing = data.timing  || {};
    const sr     = data.store_resolution || {};

    // ── Summary cards
    document.getElementById('s-store').textContent    = sr.store_key || '—';
    document.getElementById('s-source').textContent   = p.extraction_source || '—';
    document.getElementById('s-price').textContent    = p.price != null
        ? `${p.currency || 'USD'} ${parseFloat(p.price).toFixed(2)}` : '—';
    document.getElementById('s-time').textContent     = timing.total_ms != null ? `${timing.total_ms} ms` : '—';

    // Measurements badge
    const measFound = p.measurements_found === true;
    document.getElementById('s-meas').innerHTML = measFound
        ? '<span class="badge bg-success">✓ Found</span>'
        : '<span class="badge bg-warning text-dark">Fallback</span>';

    // Shipping badge
    const sq = p.shipping_quote;
    const shippingAmt = sq?.amount != null ? `${sq.currency || 'USD'} ${parseFloat(sq.amount).toFixed(2)}` : null;
    const shippingEst = p.shipping_estimate_source;
    document.getElementById('s-shipping').innerHTML = shippingAmt
        ? `${escHtml(shippingAmt)} <span class="badge ${shippingEst === 'exact' ? 'bg-success' : 'bg-warning text-dark'} ms-1">${shippingEst || '—'}</span>`
        : '<span class="text-muted">—</span>';

    document.getElementById('summary-row').classList.remove('d-none');

    // ── Warnings
    const warnings = [];
    if (p.blocked_or_captcha) warnings.push('⛔ Page appears to be blocked or CAPTCHA-protected.');
    if (!measFound) warnings.push('⚠ Measurements not found — shipping quote uses fallback dimensions from config.');
    if ((p.name ?? '') === '' || (p.name ?? '') === 'Product') warnings.push('⚠ Product name could not be extracted.');
    if (!(p.price > 0)) warnings.push('⚠ Price is 0 — extraction may have failed.');
    if (!p.image_url) warnings.push('⚠ No product image found.');
    if (sq?.missing_fields?.length) warnings.push('⚠ Missing shipping fields: ' + escHtml(sq.missing_fields.join(', ')));
    renderWarnings(warnings);

    // ── Store Resolution tab
    renderStoreResolution(sr);

    // ── Provider Timeline tab
    renderProviderTimeline(data.provider_attempts);

    // ── Product Data tab
    tableRows('product-table', [
        ['Name',              p.name || '—'],
        ['Price',             p.price != null ? `${p.currency || 'USD'} ${parseFloat(p.price).toFixed(2)}` : '—'],
        ['Currency',          p.currency || '—'],
        ['Store Key',         p.store_key || '—'],
        ['Store Name',        p.store_name || '—'],
        ['Country',           p.country || '—'],
        ['Canonical URL',     p.canonical_url ? `<a href="${escHtml(p.canonical_url)}" target="_blank" class="font-monospace small">${escHtml(p.canonical_url)}</a>` : '—'],
        ['Image URL',         p.image_url    ? `<a href="${escHtml(p.image_url)}" target="_blank" class="font-monospace small">${escHtml(p.image_url)}</a>` : '—'],
        ['Extraction Source', p.extraction_source ? `<span class="badge bg-secondary">${escHtml(p.extraction_source)}</span>` : '—'],
    ]);
    if (p.image_url) {
        const img = document.getElementById('p-image');
        img.src = p.image_url;
        img.style.display = '';
        document.getElementById('p-image-placeholder').style.display = 'none';
    }

    // ── Measurements tab
    const dims = p.dimensions;
    const dimsStr = dims && typeof dims === 'object'
        ? `L=${dims.length ?? '?'} × W=${dims.width ?? '?'} × H=${dims.height ?? '?'} ${dims.unit || ''}`
        : (dims ? String(dims) : '—');

    // Normalized measurement output (required contract)
    const wVal  = p.weight_value ?? p.weight ?? null;
    const wUnit = p.weight_unit ?? null;
    const dl = p.dimensions_length ?? (dims?.length ?? null);
    const dw = p.dimensions_width  ?? (dims?.width  ?? null);
    const dh = p.dimensions_height ?? (dims?.height ?? null);
    const dUnit = p.dimensions_unit ?? (dims?.unit ?? null);
    const hasExact = p.has_exact_measurements === true;
    const src = p.measurements_source || null;
    const srcFields = p.measurements_source_fields || null;

    let rawWeightField = '—';
    let rawDimsField   = '—';
    if (srcFields && typeof srcFields === 'object') {
        const w = srcFields.weight || {};
        const d = srcFields.dimensions || {};
        rawWeightField = w.key ? `<span class="badge bg-dark font-monospace">${escHtml(w.key)}</span> <span class="font-monospace small">${escHtml(w.raw || '')}</span>` : '—';
        rawDimsField   = d.key ? `<span class="badge bg-dark font-monospace">${escHtml(d.key)}</span> <span class="font-monospace small">${escHtml(d.raw || '')}</span>` : '—';
    }

    tableRows('meas-table', [
        ['Raw weight field (Amazon)', rawWeightField],
        ['Raw dimensions field (Amazon)', rawDimsField],
        ['Parsed weight',     wVal != null ? `${parseFloat(wVal)} ${escHtml(wUnit || '')}` : '<span class="text-warning">not found</span>'],
        ['Parsed dimensions', (dl != null && dw != null && dh != null && dUnit)
            ? `L=${parseFloat(dl)} × W=${parseFloat(dw)} × H=${parseFloat(dh)} ${escHtml(dUnit)}`
            : (p.dimensions ? dimsStr : '<span class="text-warning">not found</span>')],
        ['Has exact measurements', hasExact ? '<span class="badge bg-success">true</span>' : '<span class="badge bg-secondary">false</span>'],
        ['Measurements source', src ? `<span class="badge bg-info text-dark">${escHtml(src)}</span>` : '—'],
        ['Measurements Found', measFound ? '<span class="badge bg-success">Yes — real data</span>' : '<span class="badge bg-warning text-dark">No — fallback used</span>'],
        ['Shipping Source',  p.shipping_estimate_source
            ? `<span class="badge ${p.shipping_estimate_source === 'exact' ? 'bg-success' : 'bg-warning text-dark'}">${escHtml(p.shipping_estimate_source)}</span>`
            : '—'],
    ]);
    document.getElementById('meas-source-badge').innerHTML = measFound
        ? '<span class="badge bg-success fs-6 px-3 py-2">✓ Exact measurements</span><p class="text-muted small mt-2">Shipping quote is based on real product weight and dimensions.</p>'
        : '<span class="badge bg-warning text-dark fs-6 px-3 py-2">⚠ Fallback dimensions</span><p class="text-muted small mt-2">No measurements found — default config values were used.</p>';
    if (!measFound) {
        document.getElementById('meas-fallback-note').classList.remove('d-none');
    }

    // ── Shipping tab
    if (sq && typeof sq === 'object') {
        tableRows('shipping-table', [
            ['Amount',         sq.amount != null ? `${sq.currency || ''} ${parseFloat(sq.amount).toFixed(2)}` : '—'],
            ['Currency',       sq.currency || '—'],
            ['Carrier',        sq.carrier  || '—'],
            ['Zone',           sq.zone     || '—'],
            ['Estimated',      boolBadge(sq.estimated)],
            ['Source',         p.shipping_estimate_source
                ? `<span class="badge ${p.shipping_estimate_source === 'exact' ? 'bg-success' : 'bg-warning text-dark'}">${escHtml(p.shipping_estimate_source)}</span>`
                : '—'],
            ['Missing Fields', sq.missing_fields?.length
                ? `<span class="text-warning">${escHtml(sq.missing_fields.join(', '))}</span>`
                : '<span class="text-success">none</span>'],
            ['Note',           sq.note || '—'],
        ]);
    } else {
        document.getElementById('shipping-table').innerHTML = '<tr><td class="text-muted">No shipping quote returned.</td></tr>';
    }
    document.getElementById('shipping-review-badge').innerHTML = p.shipping_review_required
        ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2">⚠ Review Required</span><p class="text-muted small mt-2">Weight or dimensions missing — quote is estimated.</p>'
        : '<span class="badge bg-success fs-6 px-3 py-2">✓ No Review Needed</span>';

    // ── Pricing tab
    const fp = p.final_pricing;
    if (fp && typeof fp === 'object' && !fp.error) {
        tableRows('pricing-table', Object.entries(fp).filter(([k]) => k !== 'breakdown').map(([k, v]) => [k, v != null ? String(v) : '—']));
    } else {
        document.getElementById('pricing-table').innerHTML = fp?.error
            ? `<tr><td class="text-danger">${escHtml(fp.error)}</td></tr>`
            : '<tr><td class="text-muted">No final pricing data.</td></tr>';
    }
    const breakdown = p.pricing?.breakdown || [];
    const bBody = document.getElementById('breakdown-table');
    bBody.innerHTML = breakdown.length
        ? breakdown.map(row =>
            `<tr><td>${escHtml(row.key)}</td><td>${escHtml(row.label)}</td><td>${row.amount != null ? parseFloat(row.amount).toFixed(2) : '—'}</td><td>${row.estimated != null ? boolBadge(row.estimated) : '—'}</td></tr>`
          ).join('')
        : '<tr><td colspan="4" class="text-muted">No breakdown.</td></tr>';

    // ── Variations tab
    const vars    = p.variations || [];
    const varBody = document.getElementById('variations-body');
    varBody.innerHTML = vars.length
        ? vars.map(v => `
            <div class="mb-3">
                <span class="badge bg-primary me-2">${escHtml(v.type)}</span>
                ${(v.options || []).map((opt, i) => {
                    const price = v.prices?.[i];
                    return `<span class="badge bg-light text-dark border me-1">${escHtml(opt)}${price != null ? ` — $${parseFloat(price).toFixed(2)}` : ''}</span>`;
                }).join('')}
            </div>`).join('')
        : '<p class="text-muted">No variations returned.</p>';

    // ── Debug tab
    const rawData  = data.scraperapi_raw;
    const rawKeys  = Object.keys(rawData || {}).length;
    document.getElementById('raw-key-count').textContent = rawKeys + ' keys';
    document.getElementById('raw-pre').textContent = rawData
        ? JSON.stringify(rawData, null, 2)
        : 'No ScraperAPI raw data (HTML pipeline was used or key not set).';

    const aiJson = data.ai_parsed_json || p.ai_parsed_json || null;
    document.getElementById('ai-json-badge').textContent = aiJson ? 'available' : 'n/a';
    document.getElementById('ai-json-pre').textContent = aiJson
        ? JSON.stringify(aiJson, null, 2)
        : 'AI parser was not used or did not return valid JSON.';
    document.getElementById('full-json').textContent = JSON.stringify(data, null, 2);

    // ── Show tabs, activate store tab
    document.getElementById('result-tabs').classList.remove('d-none');
    document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('d-none'));
    document.querySelector('[data-tab="store"]').classList.add('active');
    document.getElementById('tab-store').classList.remove('d-none');
}

// ── Store Resolution tab ───────────────────────────────────────────────────
function renderStoreResolution(sr) {
    if (!sr) return;
    const hasKey = sr.scraperapi_key_configured;
    tableRows('store-table', [
        ['Original URL',         sr.original_url ? `<span class="font-monospace small">${escHtml(sr.original_url)}</span>` : '—'],
        ['Detected Store',       sr.store_key ? `<strong>${escHtml(sr.store_key)}</strong>` : '<span class="text-warning">generic</span>'],
        ['ASIN (Amazon)',        sr.asin ? `<span class="badge bg-dark font-monospace">${escHtml(sr.asin)}</span>` : '<span class="text-muted">n/a</span>'],
        ['Amazon TLD',           sr.amazon_tld ? escHtml(sr.amazon_tld) : '<span class="text-muted">n/a</span>'],
        ['Primary Provider',     sr.primary_provider ? `<span class="badge bg-info text-dark">${escHtml(sr.primary_provider)}</span>` : '—'],
        ['ScraperAPI Key',       hasKey ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-danger">Not configured</span>'],
    ]);
    document.getElementById('result-tabs').classList.remove('d-none');
}

// ── Provider Timeline ──────────────────────────────────────────────────────
function renderProviderTimeline(attempts) {
    const el = document.getElementById('provider-timeline');
    if (!attempts || !attempts.length) {
        el.innerHTML = '<p class="text-muted small">No provider attempt data.</p>';
        return;
    }
    el.innerHTML = attempts.map((a, i) => `
        <div class="d-flex align-items-start mb-3">
            <div class="me-3 text-center" style="min-width:32px">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                     style="width:28px;height:28px;background:${a.success ? '#198754' : '#dc3545'};">
                    ${i + 1}
                </div>
                ${i < attempts.length - 1 ? '<div style="width:2px;height:20px;background:#dee2e6;margin:2px auto;"></div>' : ''}
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <strong class="font-monospace small">${escHtml(a.provider)}</strong>
                    <span class="badge ${a.success ? 'bg-success' : 'bg-danger'}">${a.success ? '✓ success' : '✗ failed'}</span>
                    ${a.strategy ? `<span class="badge bg-light text-dark border">${escHtml(a.strategy)}</span>` : ''}
                </div>
                ${a.note ? `<div class="text-muted small">${escHtml(a.note)}</div>` : ''}
            </div>
        </div>
    `).join('');

    // Also fill fetch info table
    const lastFetch = attempts.find(a => a.strategy !== 'extraction') || {};
    tableRows('fetch-table', [
        ['Fetch Source',        lastFetch.provider ? escHtml(lastFetch.provider) : '—'],
        ['HTML Strategy',       lastFetch.strategy ? escHtml(lastFetch.strategy) : '—'],
        ['Blocked / CAPTCHA',   lastFetch.success === false ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>'],
    ]);
}

// ── Warnings ───────────────────────────────────────────────────────────────
function renderWarnings(warnings) {
    const box = document.getElementById('warnings-box');
    const inner = document.getElementById('warnings-inner');
    if (!warnings.length) { box.classList.add('d-none'); return; }
    inner.innerHTML = warnings.map(w => `<div>${escHtml(w)}</div>`).join('');
    box.classList.remove('d-none');
}

// ── Helpers ────────────────────────────────────────────────────────────────
function tableRows(tbodyId, rows) {
    document.getElementById(tbodyId).innerHTML = rows.map(([k, v]) =>
        `<tr><th class="text-muted small" style="width:220px;white-space:nowrap">${escHtml(k)}</th><td>${v}</td></tr>`
    ).join('');
}

function boolBadge(val) {
    if (val === true  || val === 1) return '<span class="badge bg-danger">Yes</span>';
    if (val === false || val === 0) return '<span class="badge bg-success">No</span>';
    return '<span class="badge bg-secondary">—</span>';
}

function escHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showError(msg, trace) {
    document.getElementById('error-msg').textContent = msg || 'Unknown error';
    const traceEl = document.getElementById('error-trace');
    if (trace && trace.length) {
        traceEl.textContent = Array.isArray(trace) ? trace.join('\n') : trace;
        traceEl.classList.remove('d-none');
    }
    document.getElementById('error-alert').classList.remove('d-none');
}

function hideAll() {
    ['error-alert','result-tabs','summary-row','warnings-box'].forEach(id =>
        document.getElementById(id).classList.add('d-none'));
    document.getElementById('error-trace').classList.add('d-none');
    document.getElementById('meas-fallback-note').classList.add('d-none');
    document.getElementById('p-image').style.display = 'none';
    document.getElementById('p-image-placeholder').style.display = '';
}

function setLoading(on) {
    document.getElementById('btn-label').textContent = on ? 'Testing…' : 'Test';
    document.getElementById('btn-spinner').classList.toggle('d-none', !on);
    document.getElementById('btn-test').disabled = on;
}

function copyFull(e) {
    e.stopPropagation();
    if (lastResponse) navigator.clipboard.writeText(JSON.stringify(lastResponse, null, 2));
}
</script>
@endsection

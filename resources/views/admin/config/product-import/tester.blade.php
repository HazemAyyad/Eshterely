@extends('layouts.admin')

@section('title', 'Product Import Tester')

@section('content')
<div class="row g-3">

    {{-- Form --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Product Import Tester</h5>
                <small class="text-muted">Paste any product URL to inspect the full extraction pipeline response.</small>
            </div>
            <div class="card-body">
                <form id="tester-form">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold mb-1">Product URL <span class="text-danger">*</span></label>
                            <input type="url" id="input-url" class="form-control" placeholder="https://www.amazon.com/dp/..." required>
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
                            <input type="text" id="input-country" class="form-control" placeholder="e.g. SA, AE">
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

    {{-- Summary cards (hidden until response) --}}
    <div id="summary-row" class="col-12 d-none">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Store</div>
                        <div class="fw-bold" id="s-store">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Extraction Source</div>
                        <div class="fw-bold font-monospace small" id="s-source">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Price</div>
                        <div class="fw-bold text-primary" id="s-price">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="card-body p-2">
                        <div class="text-muted small">Total Time</div>
                        <div class="fw-bold" id="s-time">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Error alert --}}
    <div id="error-alert" class="col-12 d-none">
        <div class="alert alert-danger mb-0">
            <strong>Error:</strong> <span id="error-msg"></span>
            <pre id="error-trace" class="mt-2 mb-0 small d-none"></pre>
        </div>
    </div>

    {{-- Tabs --}}
    <div id="result-tabs" class="col-12 d-none">
        <ul class="nav nav-tabs" id="result-nav">
            <li class="nav-item"><button class="nav-link active" data-tab="product">Product</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="shipping">Shipping</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="pricing">Pricing</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="variations">Variations</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="fetch">Fetch Info</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="raw">ScraperAPI Raw</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="full">Full JSON</button></li>
        </ul>

        <div class="card border-0 border-top-0 shadow-sm rounded-0 rounded-bottom">
            <div class="card-body">

                {{-- Product tab --}}
                <div id="tab-product" class="tab-pane">
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

                {{-- Shipping tab --}}
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

                {{-- Pricing tab --}}
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

                {{-- Variations tab --}}
                <div id="tab-variations" class="tab-pane d-none">
                    <div id="variations-body"></div>
                </div>

                {{-- Fetch info tab --}}
                <div id="tab-fetch" class="tab-pane d-none">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="fetch-table"></tbody>
                    </table>
                    <h6 class="text-muted small fw-semibold text-uppercase mt-3 mb-2">Timing</h6>
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="timing-table"></tbody>
                    </table>
                </div>

                {{-- ScraperAPI Raw tab --}}
                <div id="tab-raw" class="tab-pane d-none">
                    <div class="mb-2 text-muted small">Top-level keys returned by ScraperAPI structured endpoint.</div>
                    <pre id="raw-pre" class="bg-dark text-light p-3 rounded small" style="max-height:500px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
                </div>

                {{-- Full JSON tab --}}
                <div id="tab-full" class="tab-pane d-none">
                    <div class="d-flex justify-content-end mb-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyFull()">Copy JSON</button>
                    </div>
                    <pre id="full-json" class="bg-dark text-light p-3 rounded small" style="max-height:600px;overflow:auto;white-space:pre-wrap;word-break:break-all;"></pre>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
let lastResponse = null;

// Tab switching
document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('d-none'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.remove('d-none');
    });
});

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
        const res = await fetch('{{ route('admin.config.product-import.tester.test') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        lastResponse = data;

        if (!data.ok) {
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

function renderResponse(data) {
    const p = data.product || {};
    const timing = data.timing || {};

    // Summary cards
    document.getElementById('s-store').textContent   = data.store_key || '—';
    document.getElementById('s-source').textContent  = p.extraction_source || '—';
    document.getElementById('s-price').textContent   = p.price != null ? `${p.currency || 'USD'} ${parseFloat(p.price).toFixed(2)}` : '—';
    document.getElementById('s-time').textContent    = timing.total_ms != null ? `${timing.total_ms} ms` : '—';
    document.getElementById('summary-row').classList.remove('d-none');

    // Product tab
    const productRows = [
        ['Name',       p.name || '—'],
        ['Price',      p.price != null ? `${p.currency || 'USD'} ${parseFloat(p.price).toFixed(2)}` : '—'],
        ['Currency',   p.currency || '—'],
        ['Store Key',  p.store_key || '—'],
        ['Store Name', p.store_name || '—'],
        ['Country',    p.country || '—'],
        ['Product ID', p.product_id || '—'],
        ['Canonical URL', p.canonical_url ? `<a href="${escHtml(p.canonical_url)}" target="_blank" class="text-truncate d-inline-block" style="max-width:300px">${escHtml(p.canonical_url)}</a>` : '—'],
        ['Image URL',  p.image_url ? `<a href="${escHtml(p.image_url)}" target="_blank" class="text-truncate d-inline-block" style="max-width:300px">${escHtml(p.image_url)}</a>` : '—'],
        ['Weight',     p.weight || '—'],
        ['Dimensions', p.dimensions ? JSON.stringify(p.dimensions) : '—'],
        ['Extraction Source', p.extraction_source ? `<span class="badge bg-secondary">${escHtml(p.extraction_source)}</span>` : '—'],
    ];
    tableRows('product-table', productRows);

    if (p.image_url) {
        const img = document.getElementById('p-image');
        img.src = p.image_url;
        img.style.display = '';
        document.getElementById('p-image-placeholder').style.display = 'none';
    }

    // Shipping tab
    const sq = p.shipping_quote;
    if (sq && typeof sq === 'object') {
        tableRows('shipping-table', [
            ['Amount',        sq.amount != null ? `${sq.currency || ''} ${parseFloat(sq.amount).toFixed(2)}` : '—'],
            ['Currency',      sq.currency || '—'],
            ['Carrier',       sq.carrier || '—'],
            ['Zone',          sq.zone || '—'],
            ['Estimated',     boolBadge(sq.estimated)],
            ['Missing Fields',sq.missing_fields?.length ? `<span class="text-warning">${escHtml(sq.missing_fields.join(', '))}</span>` : '<span class="text-success">none</span>'],
            ['Note',          sq.note || '—'],
        ]);
    } else {
        document.getElementById('shipping-table').innerHTML = '<tr><td class="text-muted">No shipping quote returned.</td></tr>';
    }
    const reviewRequired = p.shipping_review_required;
    document.getElementById('shipping-review-badge').innerHTML = reviewRequired
        ? '<span class="badge bg-warning text-dark fs-6 px-3 py-2">⚠ Shipping Review Required</span><p class="text-muted small mt-2">Weight or dimensions missing — quote is estimated.</p>'
        : '<span class="badge bg-success fs-6 px-3 py-2">✓ No Review Needed</span>';

    // Pricing tab
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
    if (breakdown.length) {
        bBody.innerHTML = breakdown.map(row =>
            `<tr><td>${escHtml(row.key)}</td><td>${escHtml(row.label)}</td><td>${row.amount != null ? parseFloat(row.amount).toFixed(2) : '—'}</td><td>${row.estimated != null ? boolBadge(row.estimated) : '—'}</td></tr>`
        ).join('');
    } else {
        bBody.innerHTML = '<tr><td colspan="4" class="text-muted">No breakdown.</td></tr>';
    }

    // Variations tab
    const vars = p.variations || [];
    const varBody = document.getElementById('variations-body');
    if (vars.length) {
        varBody.innerHTML = vars.map(v => `
            <div class="mb-3">
                <span class="badge bg-primary me-2">${escHtml(v.type)}</span>
                ${(v.options || []).map((opt, i) => {
                    const price = v.prices?.[i];
                    return `<span class="badge bg-light text-dark border me-1">${escHtml(opt)}${price != null ? ` — $${parseFloat(price).toFixed(2)}` : ''}</span>`;
                }).join('')}
            </div>
        `).join('');
    } else {
        varBody.innerHTML = '<p class="text-muted">No variations returned.</p>';
    }

    // Fetch info tab
    tableRows('fetch-table', [
        ['Fetch Source',      p.fetch_source || '—'],
        ['HTML Strategy',     p.html_strategy || '—'],
        ['Blocked/CAPTCHA',   boolBadge(p.blocked_or_captcha)],
        ['HTML Length (bytes)', data.html_length != null ? Number(data.html_length).toLocaleString() : '—'],
        ['ScraperAPI raw keys', (p._scraperapi_raw_keys || []).join(', ') || '—'],
    ]);
    tableRows('timing-table', [
        ['Fetch',    timing.fetch_ms    != null ? `${timing.fetch_ms} ms`    : '—'],
        ['Extract',  timing.extract_ms  != null ? `${timing.extract_ms} ms`  : '—'],
        ['Shipping', timing.shipping_ms != null ? `${timing.shipping_ms} ms` : '—'],
        ['Total',    timing.total_ms    != null ? `${timing.total_ms} ms`    : '—'],
    ]);

    // Raw ScraperAPI
    document.getElementById('raw-pre').textContent = data.scraperapi_raw
        ? JSON.stringify(data.scraperapi_raw, null, 2)
        : 'No ScraperAPI raw data (HTML pipeline was used).';

    // Full JSON
    document.getElementById('full-json').textContent = JSON.stringify(data, null, 2);

    document.getElementById('result-tabs').classList.remove('d-none');

    // Reset to product tab
    document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('d-none'));
    document.querySelector('[data-tab="product"]').classList.add('active');
    document.getElementById('tab-product').classList.remove('d-none');
}

function tableRows(tbodyId, rows) {
    document.getElementById(tbodyId).innerHTML = rows.map(([k, v]) =>
        `<tr><th class="text-muted small" style="width:200px;white-space:nowrap">${escHtml(k)}</th><td>${v}</td></tr>`
    ).join('');
}

function boolBadge(val) {
    if (val === true  || val === 1)  return '<span class="badge bg-danger">Yes</span>';
    if (val === false || val === 0)  return '<span class="badge bg-success">No</span>';
    return '<span class="badge bg-secondary">—</span>';
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
    document.getElementById('error-alert').classList.add('d-none');
    document.getElementById('error-trace').classList.add('d-none');
    document.getElementById('result-tabs').classList.add('d-none');
    document.getElementById('summary-row').classList.add('d-none');
}

function setLoading(on) {
    document.getElementById('btn-label').textContent = on ? 'Testing…' : 'Test';
    document.getElementById('btn-spinner').classList.toggle('d-none', !on);
    document.getElementById('btn-test').disabled = on;
}

function copyFull() {
    if (!lastResponse) return;
    navigator.clipboard.writeText(JSON.stringify(lastResponse, null, 2));
}
</script>
@endsection

@extends('layouts.admin')

@section('title', 'Product Import Logs')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Product Import Logs</h5>
        <div class="d-flex gap-2">
            <input type="text" id="filter-store" class="form-control form-control-sm" placeholder="Filter by store…" style="width:160px">
            <select id="filter-success" class="form-select form-select-sm" style="width:140px">
                <option value="">All results</option>
                <option value="1">Success only</option>
                <option value="0">Failures only</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="logs-table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Store</th>
                        <th>Provider</th>
                        <th>Result</th>
                        <th>Paid</th>
                        <th>URL</th>
                        <th>Error</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="logs-body">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div id="logs-pagination" class="p-3"></div>
    </div>
</div>

<script>
let currentPage = 1;
let filterStore = '';
let filterSuccess = '';

function loadLogs(page) {
    const params = new URLSearchParams({ page });
    if (filterStore) params.set('store_key', filterStore);
    if (filterSuccess !== '') params.set('success', filterSuccess);

    fetch('{{ route('admin.config.product-import.logs.data') }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const body = document.getElementById('logs-body');
        if (!data.data || data.data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No logs found.</td></tr>';
            document.getElementById('logs-pagination').innerHTML = '';
            return;
        }
        body.innerHTML = data.data.map(log => `
            <tr>
                <td>${log.id}</td>
                <td><span class="badge bg-secondary">${log.store_key}</span></td>
                <td><small class="text-muted">${log.provider}</small></td>
                <td>${log.success
                    ? '<span class="badge bg-success">OK</span>'
                    : (log.partial_success ? '<span class="badge bg-warning text-dark">Partial</span>' : '<span class="badge bg-danger">Fail</span>')
                }</td>
                <td>${log.used_paid_provider ? '<span class="badge bg-danger">Yes</span>' : '—'}</td>
                <td class="text-truncate" style="max-width:220px" title="${log.url}">${log.url}</td>
                <td class="text-truncate" style="max-width:180px" title="${log.error_message ?? ''}">${log.error_message ?? '—'}</td>
                <td><small>${log.created_at}</small></td>
            </tr>
        `).join('');

        // Pagination links
        const pages = data.last_page;
        let pager = '';
        for (let i = 1; i <= Math.min(pages, 10); i++) {
            pager += `<button class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-secondary'} me-1"
                        onclick="loadLogs(${i})">${i}</button>`;
        }
        document.getElementById('logs-pagination').innerHTML = pager;
    });
}

document.getElementById('filter-store').addEventListener('input', e => {
    filterStore = e.target.value.trim();
    loadLogs(1);
});
document.getElementById('filter-success').addEventListener('change', e => {
    filterSuccess = e.target.value;
    loadLogs(1);
});

loadLogs(1);
</script>
@endsection

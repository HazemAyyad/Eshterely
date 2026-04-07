@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form.ajax-submit-form');
    if (!forms.length) return;
    const redirectUrl = @json($redirect);
    forms.forEach(form => {

    function clearValidationErrors() {
        form.querySelectorAll('.ajax-field-error').forEach(el => el.remove());
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function showValidationErrors(errors) {
        clearValidationErrors();
        for (const [field, messages] of Object.entries(errors)) {
            const el = form.querySelector(`[name="${field}"]`);
            if (!el || !messages.length) continue;
            el.classList.add('is-invalid');
            const errDiv = document.createElement('div');
            errDiv.className = 'text-danger small mt-1 ajax-field-error';
            errDiv.textContent = messages[0];
            el.insertAdjacentElement('afterend', errDiv);
        }
        form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        clearValidationErrors();
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        // Never use form.action / form.method when the form may contain inputs named "action" or "method"
        // — those shadow the properties and break fetch (URL becomes "[object HTMLInputElement]").
        const actionUrl = form.getAttribute('action');
        const httpMethod = (form.getAttribute('method') || 'get').toUpperCase();
        if (!actionUrl) {
            if (btn) btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Missing form action' });
            return;
        }
        Swal.fire({ title: @json(__('admin.loading')), allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch(actionUrl, {
            method: httpMethod,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        }).then(r => r.json().then(data => ({ ok: r.ok, data, status: r.status })))
          .then(({ ok, data, status }) => {
            Swal.close();
            if (btn) btn.disabled = false;
            if (ok && data.success) {
                Swal.fire({ icon: 'success', title: data.message }).then(() => window.location.href = redirectUrl);
            } else if (status === 422 && data.errors) {
                showValidationErrors(data.errors);
            } else {
                Swal.fire({ icon: 'error', title: data.message || @json(__('admin.error')) });
            }
        }).catch(() => { Swal.close(); if (btn) btn.disabled = false; Swal.fire({ icon: 'error', title: @json(__('admin.error')) }); });
    });
    });
});
</script>
@endpush

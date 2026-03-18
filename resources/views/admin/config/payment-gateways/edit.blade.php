@extends('layouts.admin')

@section('title', 'Payment Gateways')

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Payment Settings (Square + Stripe)</h5>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.config.payment-gateways.edit') }}" class="ajax-submit-form">
                @method('PATCH')
                @csrf

                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label">Default Gateway</label>
                        <select name="default_gateway" class="form-select">
                            <option value="square" {{ old('default_gateway', $values['default_gateway'] ?? 'square') === 'square' ? 'selected' : '' }}>
                                Square
                            </option>
                            <option value="stripe" {{ old('default_gateway', $values['default_gateway'] ?? 'square') === 'stripe' ? 'selected' : '' }}>
                                Stripe
                            </option>
                        </select>
                    </div>

                    <hr class="my-3"/>

                    <div class="col-12">
                        <h6 class="text-muted mb-3">Square</h6>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input type="hidden" name="square_enabled" value="0">
                            <input type="checkbox" class="form-check-input" name="square_enabled" id="square_enabled" value="1"
                                   {{ old('square_enabled', $values['square_enabled'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="square_enabled">Enable Square</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Square Environment</label>
                        <select name="square_environment" class="form-select">
                            <option value="sandbox" {{ old('square_environment', $values['square_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>sandbox</option>
                            <option value="production" {{ old('square_environment', $values['square_environment'] ?? 'sandbox') === 'production' ? 'selected' : '' }}>production</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Square Application ID</label>
                        <input type="text" name="square_application_id" class="form-control"
                               value="{{ old('square_application_id', $values['square_application_id'] ?? '') }}" placeholder="SQUARE_APPLICATION_ID">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Square Location ID</label>
                        <input type="text" name="square_location_id" class="form-control"
                               value="{{ old('square_location_id', $values['square_location_id'] ?? '') }}" placeholder="SQUARE_LOCATION_ID">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Square Access Token</label>
                        <input type="password" name="square_access_token" class="form-control"
                               value="{{ old('square_access_token', $values['square_access_token'] ?? '') }}" placeholder="SQUARE_ACCESS_TOKEN">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Square Webhook Signature Key</label>
                        <input type="password" name="square_webhook_signature_key" class="form-control"
                               value="{{ old('square_webhook_signature_key', $values['square_webhook_signature_key'] ?? '') }}" placeholder="SQUARE_WEBHOOK_SIGNATURE_KEY">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Square Webhook Notification URL</label>
                        <input type="url" name="square_webhook_notification_url" class="form-control"
                               value="{{ old('square_webhook_notification_url', $values['square_webhook_notification_url'] ?? '') }}" placeholder="https://your-domain/api/webhooks/square">
                    </div>

                    <hr class="my-4"/>

                    <div class="col-12">
                        <h6 class="text-muted mb-3">Stripe</h6>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input type="hidden" name="stripe_enabled" value="0">
                            <input type="checkbox" class="form-check-input" name="stripe_enabled" id="stripe_enabled" value="1"
                                   {{ old('stripe_enabled', $values['stripe_enabled'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="stripe_enabled">Enable Stripe</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Stripe Environment</label>
                        <select name="stripe_environment" class="form-select">
                            <option value="test" {{ old('stripe_environment', $values['stripe_environment'] ?? 'test') === 'test' ? 'selected' : '' }}>test</option>
                            <option value="live" {{ old('stripe_environment', $values['stripe_environment'] ?? 'test') === 'live' ? 'selected' : '' }}>live</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Default Stripe Currency (optional)</label>
                        <input type="text" name="stripe_currency_default" class="form-control"
                               value="{{ old('stripe_currency_default', $values['stripe_currency_default'] ?? 'USD') }}" placeholder="USD">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Stripe Publishable Key (safe)</label>
                        <input type="text" name="stripe_publishable_key" class="form-control"
                               value="{{ old('stripe_publishable_key', $values['stripe_publishable_key'] ?? '') }}" placeholder="STRIPE_PUBLISHABLE_KEY">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Stripe Secret Key</label>
                        <input type="password" name="stripe_secret_key" class="form-control"
                               value="{{ old('stripe_secret_key', $values['stripe_secret_key'] ?? '') }}" placeholder="STRIPE_SECRET_KEY">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Stripe Webhook Secret</label>
                        <input type="password" name="stripe_webhook_secret" class="form-control"
                               value="{{ old('stripe_webhook_secret', $values['stripe_webhook_secret'] ?? '') }}" placeholder="STRIPE_WEBHOOK_SECRET">
                        <small class="text-muted d-block mt-1">
                            Stripe webhook endpoint: <code>/api/webhooks/stripe</code>
                        </small>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@include('admin.partials.ajax-form-script', ['redirect' => route('admin.config.payment-gateways.edit')])


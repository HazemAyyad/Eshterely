<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Services\Payments\Gateways\SquarePaymentGateway;
use App\Services\Payments\Gateways\StripePaymentGateway;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * Resolve payment gateway based on admin-controlled settings.
     *
     * This manager keeps backward compatibility by falling back to Square when
     * the settings table doesn't exist or has no row.
     */
    public function getDefaultGatewayCode(): string
    {
        $settings = $this->getSettingsRow();
        $default = ($settings !== null && is_string($settings->default_gateway) && $settings->default_gateway !== '')
            ? $settings->default_gateway
            : 'square';

        $enabled = $this->getEnabledGateways();
        if (in_array($default, $enabled, true)) {
            return (string) $default;
        }

        return $enabled[0] ?? 'square';
    }

    /**
     * @return string[]
     */
    public function getEnabledGateways(): array
    {
        $settings = $this->getSettingsRow();
        if ($settings === null) {
            return ['square'];
        }

        $enabled = [];
        if ((bool) ($settings->square_enabled ?? false)) {
            $enabled[] = 'square';
        }
        if ((bool) ($settings->stripe_enabled ?? false)) {
            $enabled[] = 'stripe';
        }

        // Ensure at least one provider for safety.
        if ($enabled === []) {
            return ['square'];
        }

        return $enabled;
    }

    public function resolve(string $code): PaymentGatewayInterface
    {
        $code = strtolower(trim($code));
        $enabled = $this->getEnabledGateways();
        if (! in_array($code, $enabled, true)) {
            throw new InvalidArgumentException('gateway_unavailable');
        }

        return match ($code) {
            'square' => $this->resolveSquareGateway(),
            'stripe' => $this->resolveStripeGateway(),
            default => throw new InvalidArgumentException('gateway_unavailable'),
        };
    }

    public function resolveDefault(): PaymentGatewayInterface
    {
        return $this->resolve($this->getDefaultGatewayCode());
    }

    private function resolveSquareGateway(): PaymentGatewayInterface
    {
        $settings = $this->getSettingsRow();
        if ($settings !== null) {
            Config::set('square.access_token', (string) ($settings->square_access_token ?? Config::get('square.access_token')));
            Config::set('square.location_id', (string) ($settings->square_location_id ?? Config::get('square.location_id')));
            Config::set('square.environment', (string) ($settings->square_environment ?? Config::get('square.environment')));
            Config::set('square.webhook_signature_key', (string) ($settings->square_webhook_signature_key ?? Config::get('square.webhook_signature_key')));
            Config::set('square.webhook_notification_url', (string) ($settings->square_webhook_notification_url ?? Config::get('square.webhook_notification_url')));
        }

        /** @var PaymentGatewayInterface $gateway */
        $gateway = app(SquarePaymentGateway::class);
        return $gateway;
    }

    private function resolveStripeGateway(): PaymentGatewayInterface
    {
        $settings = $this->getSettingsRow();

        $secretKey = $settings?->stripe_secret_key ?: Config::get('stripe.secret_key');
        $environment = $settings?->stripe_environment ?: Config::get('stripe.environment');
        $webhookSecret = $settings?->stripe_webhook_secret ?: Config::get('stripe.webhook_secret');
        $currencyDefault = $settings?->stripe_currency_default ?: Config::get('stripe.currency_default');

        /** @var PaymentGatewayInterface $gateway */
        $gateway = app(StripePaymentGateway::class, [
            'secretKey' => (string) $secretKey,
            'environment' => (string) ($environment ?: 'test'),
            'webhookSecret' => (string) $webhookSecret,
            'currencyDefault' => $currencyDefault ? (string) $currencyDefault : null,
        ]);

        return $gateway;
    }

    private function getSettingsRow(): ?object
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return null;
        }

        return DB::table('payment_gateway_settings')->first();
    }
}


<?php

namespace App\Services\Wallet;

use App\Models\SavedPaymentMethod;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripeSavedCardService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    public function setApiKey(): void
    {
        Stripe::setApiKey($this->gatewayManager->stripeSecretKey());
    }

    /**
     * @return array{client_secret: string, setup_intent_id: string}
     */
    public function createSetupIntent(User $user): array
    {
        $this->setApiKey();
        $customerId = $this->ensureStripeCustomer($user);

        $si = SetupIntent::create([
            'customer' => $customerId,
            'usage' => 'off_session',
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
        ]);

        if (! is_string($si->client_secret) || $si->client_secret === '') {
            throw new RuntimeException('Stripe did not return a setup intent client secret.');
        }

        return [
            'client_secret' => $si->client_secret,
            'setup_intent_id' => (string) $si->id,
        ];
    }

    /**
     * Confirm add-card: attach PM, persist row, run verification charge ($1.00–$5.00).
     *
     * @return array{saved_payment_method: SavedPaymentMethod, client_secret: ?string, requires_action: bool}
     */
    public function completeAddCardFromSetupIntent(User $user, string $setupIntentId): array
    {
        $this->setApiKey();
        $si = SetupIntent::retrieve($setupIntentId);
        if ($si->status !== 'succeeded') {
            throw new RuntimeException('Setup was not completed. Finish card entry in the app first.');
        }
        $pmId = is_string($si->payment_method) ? $si->payment_method : null;
        if ($pmId === null || $pmId === '') {
            throw new RuntimeException('No payment method on setup intent.');
        }

        $customerId = $this->ensureStripeCustomer($user);

        $pm = PaymentMethod::retrieve($pmId);
        if ((string) ($pm->customer ?? '') !== $customerId) {
            $pm->attach(['customer' => $customerId]);
        }

        $card = $pm->card ?? null;
        $brand = $card ? (string) ($card->brand ?? '') : '';
        $last4 = $card ? (string) ($card->last4 ?? '') : '';
        $expMonth = $card ? (int) ($card->exp_month ?? 0) : null;
        $expYear = $card ? (int) ($card->exp_year ?? 0) : null;

        return DB::transaction(function () use ($user, $customerId, $pmId, $brand, $last4, $expMonth, $expYear) {
            $existing = SavedPaymentMethod::where('user_id', $user->id)
                ->where('stripe_payment_method_id', $pmId)
                ->first();
            if ($existing !== null) {
                throw new RuntimeException('This card is already saved on your account.');
            }

            $hasDefault = SavedPaymentMethod::where('user_id', $user->id)
                ->where('verification_status', SavedPaymentMethod::STATUS_VERIFIED)
                ->where('is_default', true)
                ->exists();

            $row = SavedPaymentMethod::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $pmId,
                'brand' => $brand !== '' ? $brand : null,
                'last4' => $last4 !== '' ? $last4 : null,
                'exp_month' => $expMonth ?: null,
                'exp_year' => $expYear ?: null,
                'is_default' => ! $hasDefault,
                'verification_status' => SavedPaymentMethod::STATUS_PENDING,
                'verification_charge_amount' => null,
                'verification_attempts' => 0,
            ]);

            $verificationCharge = $this->randomVerificationAmount();
            $row->verification_charge_amount = $verificationCharge;
            $row->save();

            $pi = PaymentIntent::create([
                'amount' => (int) round($verificationCharge * 100),
                'currency' => strtolower($this->gatewayManager->stripeCurrencyDefault()),
                'customer' => $customerId,
                'payment_method' => $pmId,
                'payment_method_types' => ['card'],
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'metadata' => [
                    'saved_payment_method_id' => (string) $row->id,
                ],
            ]);

            $row->stripe_verification_payment_intent_id = (string) $pi->id;
            $row->save();

            $requiresAction = ($pi->status === 'requires_action');
            $clientSecret = $requiresAction && is_string($pi->client_secret ?? null) ? $pi->client_secret : null;

            if (in_array($pi->status, ['requires_payment_method', 'canceled'], true)) {
                $row->verification_status = SavedPaymentMethod::STATUS_FAILED;
                $row->save();
                throw new RuntimeException('Verification charge could not be completed. Try another card.');
            }

            return [
                'saved_payment_method' => $row->fresh(),
                'client_secret' => $clientSecret,
                'requires_action' => $requiresAction,
            ];
        });
    }

    /**
     * @return array{client_secret: string, payment_intent_id: string}
     */
    public function createTopUpPaymentIntent(User $user, SavedPaymentMethod $card, float $amount, string $reference, int $walletTopUpId): array
    {
        if ($card->user_id !== $user->id || ! $card->isUsableForTopUp()) {
            throw new RuntimeException('Card is not available for top-up.');
        }

        $this->setApiKey();
        $currency = strtolower($this->gatewayManager->stripeCurrencyDefault());
        $minor = (int) round($amount * 100);
        if ($minor < 100) {
            throw new RuntimeException('Minimum top-up amount is 1.00.');
        }

        $pi = PaymentIntent::create([
            'amount' => $minor,
            'currency' => $currency,
            'customer' => $card->stripe_customer_id,
            'payment_method' => $card->stripe_payment_method_id,
            'payment_method_types' => ['card'],
            'confirmation_method' => 'automatic',
            'confirm' => true,
            'metadata' => [
                'wallet_top_up_reference' => $reference,
                'wallet_top_up_id' => (string) $walletTopUpId,
            ],
        ]);

        if (! is_string($pi->client_secret ?? null) || $pi->client_secret === '') {
            throw new RuntimeException('Stripe did not return a payment intent client secret.');
        }

        return [
            'client_secret' => $pi->client_secret,
            'payment_intent_id' => (string) $pi->id,
        ];
    }

    public function ensureStripeCustomer(User $user): string
    {
        $this->setApiKey();
        if (is_string($user->stripe_customer_id) && $user->stripe_customer_id !== '') {
            return $user->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return (string) $customer->id;
    }

    public function detachPaymentMethod(SavedPaymentMethod $card): void
    {
        $this->setApiKey();
        try {
            $pm = PaymentMethod::retrieve($card->stripe_payment_method_id);
            $pm->detach();
        } catch (\Throwable) {
            // Already detached or missing — still allow deleting local row.
        }
    }

    private function randomVerificationAmount(): float
    {
        return round(mt_rand(100, 500) / 100, 2);
    }
}

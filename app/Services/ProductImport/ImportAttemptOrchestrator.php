<?php

namespace App\Services\ProductImport;

use App\Models\ProductImportLog;
use App\Models\ProductImportStoreSetting;
use Illuminate\Support\Facades\Log;

/**
 * Reads per-store settings and logs every import attempt.
 * The actual extraction is still delegated to ProductExtractionService / ProductPageFetcherService
 * so the existing pipeline (JSON-LD → meta → DOM → OpenAI) is fully preserved.
 *
 * Future: this class will coordinate multi-provider pipelines when store settings require it.
 */
class ImportAttemptOrchestrator
{
    /** Transient state for the current request. */
    private ?int $logId = null;
    private float $startedAt = 0.0;

    /**
     * Record the start of an import attempt. Non-blocking — exceptions are swallowed.
     */
    public function beginAttempt(string $url, string $storeKey): void
    {
        $this->startedAt = microtime(true);

        try {
            $setting = ProductImportStoreSetting::forStore($storeKey);

            if ($setting && ! $setting->is_enabled) {
                Log::info('ProductImport: store disabled', ['store_key' => $storeKey, 'url' => $url]);
            }

            $log = ProductImportLog::query()->create([
                'url'            => $url,
                'store_key'      => $storeKey,
                'provider'       => 'pipeline',
                'attempt_index'  => 0,
                'success'        => false,
                'partial_success' => false,
                'confidence'     => null,
                'missing_fields' => [],
                'error_message'  => null,
                'used_paid_provider' => false,
            ]);

            $this->logId = $log->id;
        } catch (\Throwable $e) {
            Log::warning('ImportAttemptOrchestrator::beginAttempt failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the log row on success. Non-blocking.
     *
     * @param  array<string, mixed>  $product
     */
    public function recordSuccess(string $url, string $storeKey, string $provider): void
    {
        try {
            if ($this->logId === null) {
                return;
            }

            $elapsed = round((microtime(true) - $this->startedAt) * 1000);

            ProductImportLog::query()->where('id', $this->logId)->update([
                'success'         => true,
                'provider'        => $provider,
                'response_snapshot' => ['elapsed_ms' => $elapsed],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImportAttemptOrchestrator::recordSuccess failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the log row on failure. Non-blocking.
     */
    public function recordFailure(string $url, string $storeKey, string $errorMessage): void
    {
        try {
            if ($this->logId === null) {
                return;
            }

            ProductImportLog::query()->where('id', $this->logId)->update([
                'success'       => false,
                'error_message' => mb_substr($errorMessage, 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImportAttemptOrchestrator::recordFailure failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Return the per-store settings (or null if not configured).
     */
    public function getStoreSetting(string $storeKey): ?ProductImportStoreSetting
    {
        try {
            return ProductImportStoreSetting::forStore($storeKey);
        } catch (\Throwable) {
            return null;
        }
    }
}

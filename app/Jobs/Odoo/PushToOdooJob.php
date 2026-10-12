<?php

namespace App\Jobs\Odoo;

use App\Exceptions\OdooConnectionException;
use App\Exceptions\OdooValidationException;
use App\Models\OdooSyncJob;
use App\Services\OdooService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class PushToOdooJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $syncJobId) {}

    abstract protected function perform(OdooSyncJob $job, OdooService $odoo): array;

    protected function odooModel(): ?string
    {
        return null;
    }

    public function handle(OdooService $odoo): void
    {
        $job = OdooSyncJob::whereKey($this->syncJobId)->lockForUpdate()->first();
        if (! $job) {
            return;
        }
        if ($job->status === 'success') {
            return;
        }

        $job->update(['status' => 'processing']);

        try {
            if ($job->odoo_res_id && $job->odoo_model) {
                $exists = $this->checkExistsInOdoo($job, $odoo);
                if ($exists) {
                    $job->update(['status' => 'success', 'processed_at' => now()]);

                    return;
                }
            }

            $result = $this->perform($job, $odoo);

            DB::transaction(function () use ($job, $result) {
                $job->update([
                    'status' => 'success',
                    'odoo_res_id' => $result['odoo_res_id'] ?? $job->odoo_res_id,
                    'response_body' => $result,
                    'processed_at' => now(),
                    'last_error' => null,
                ]);
            });
        } catch (OdooConnectionException $e) {
            $this->handleRetryable($job, $e);
        } catch (OdooValidationException $e) {
            $this->handlePermanent($job, $e);
        } catch (\Throwable $e) {
            if ($this->isRetryable($e)) {
                $this->handleRetryable($job, $e);
            } else {
                $this->handlePermanent($job, $e);
            }
        }
    }

    protected function checkExistsInOdoo(OdooSyncJob $job, OdooService $odoo): bool
    {
        try {
            $res = $odoo->searchRead($job->odoo_model, [['id', '=', $job->odoo_res_id]], ['id'], 1);

            return ! empty($res);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function isRetryable(\Throwable $e): bool
    {
        return $e instanceof OdooConnectionException || str_contains(strtolower($e->getMessage()), 'timeout') || str_contains(strtolower($e->getMessage()), 'connection');
    }

    protected function handleRetryable(OdooSyncJob $job, \Throwable $e): void
    {
        $max = (int) config('odoo.sync.max_attempts', 5);
        $attempts = $job->attempts + 1;
        $backoff = min(300, pow(2, $attempts) * 10);

        if ($attempts >= $max) {
            $job->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
                'request_body' => $job->payload,
            ]);
            Log::warning('Odoo sync failed permanently after retries', ['key' => $job->idempotency_key, 'error' => $e->getMessage()]);

            return;
        }

        $job->update([
            'status' => 'pending',
            'attempts' => $attempts,
            'last_error' => $e->getMessage(),
            'next_attempt_at' => now()->addSeconds($backoff),
        ]);

        $this->release($backoff);
    }

    protected function handlePermanent(OdooSyncJob $job, \Throwable $e): void
    {
        $job->update([
            'status' => 'failed',
            'attempts' => $job->attempts + 1,
            'last_error' => $e->getMessage(),
            'request_body' => $job->payload,
        ]);
        Log::error('Odoo sync permanent failure', ['key' => $job->idempotency_key, 'error' => $e->getMessage()]);
    }
}

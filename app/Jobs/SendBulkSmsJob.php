<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\BulkSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $batchId,
        public readonly string $phone,
        public readonly string $message,
        public readonly ?int $sentByAdminId = null
    ) {
    }

    public function handle(BulkSmsService $bulkSmsService): void
    {
        $result = $bulkSmsService->sendSingle($this->phone, $this->message);

        if ($result['ok'] ?? false) {
            SmsLog::query()->updateOrCreate(
                [
                    'batch_id' => $this->batchId,
                    'phone' => $this->phone,
                ],
                [
                    'message' => $this->message,
                    'status' => 'sent',
                    'provider_status_code' => $result['status'] ?? null,
                    'response_body' => is_string($result['response'] ?? null) ? $result['response'] : null,
                    'error_message' => null,
                    'attempts' => $this->attempts(),
                    'sent_by_admin_id' => $this->sentByAdminId,
                ]
            );

            return;
        }

        SmsLog::query()->updateOrCreate(
            [
                'batch_id' => $this->batchId,
                'phone' => $this->phone,
            ],
            [
                'message' => $this->message,
                'status' => 'retrying',
                'provider_status_code' => $result['status'] ?? null,
                'response_body' => is_string($result['response'] ?? null) ? $result['response'] : null,
                'error_message' => (string) ($result['message'] ?? 'Unknown SMS error'),
                'attempts' => $this->attempts(),
                'sent_by_admin_id' => $this->sentByAdminId,
            ]
        );

        throw new \RuntimeException((string) ($result['message'] ?? 'SMS provider request failed.'));
    }

    public function failed(Throwable $exception): void
    {
        SmsLog::query()->updateOrCreate(
            [
                'batch_id' => $this->batchId,
                'phone' => $this->phone,
            ],
            [
                'message' => $this->message,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'sent_by_admin_id' => $this->sentByAdminId,
            ]
        );
    }
}

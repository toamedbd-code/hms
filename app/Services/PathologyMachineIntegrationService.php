<?php

namespace App\Services;

use App\Models\PathologyMachineIntegrationLog;
use App\Models\Pathology;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PathologyMachineIntegrationService
{
    public function handleWebhook(Request $request): array
    {
        $options = $this->resolvePathologyOptions();
        $requestId = (string) str()->uuid();

        if (!(bool) Arr::get($options, 'enabled', false)) {
            $result = [
                'ok' => false,
                'code' => 403,
                'message' => 'Pathology machine integration is disabled.',
            ];

            $this->writeIntegrationLog('warning', 'integration_disabled', [
                'request_id' => $requestId,
                'ip' => $request->ip(),
            ], $options, $request);

            return $this->decorateResponseProfile($result, $options, 'unknown', $requestId);
        }

        $signatureCheck = $this->verifySignature($request, $options);
        if ($signatureCheck !== true) {
            $result = [
                'ok' => false,
                'code' => 403,
                'message' => $signatureCheck,
            ];

            $this->writeIntegrationLog('warning', 'signature_failed', [
                'request_id' => $requestId,
                'message' => $signatureCheck,
                'ip' => $request->ip(),
            ], $options, $request);

            return $this->decorateResponseProfile($result, $options, 'unknown', $requestId);
        }

        $parsed = $this->parseIncomingPayload($request, $options);
        if (empty($parsed['results'])) {
            $result = [
                'ok' => false,
                'code' => 422,
                'message' => 'No machine result found in payload.',
                'meta' => [
                    'format' => $parsed['format'] ?? 'unknown',
                ],
            ];

            $this->writeIntegrationLog('warning', 'empty_payload', [
                'request_id' => $requestId,
                'format' => $parsed['format'] ?? 'unknown',
                'ip' => $request->ip(),
            ], $options, $request);

            return $this->decorateResponseProfile($result, $options, (string) ($parsed['format'] ?? 'unknown'), $requestId);
        }

        $applied = $this->applyResults($parsed['results']);

        $result = [
            'ok' => true,
            'code' => 200,
            'message' => 'Machine payload processed.',
            'meta' => [
                'format' => $parsed['format'] ?? 'unknown',
                'total_results' => count($parsed['results']),
                'updated_pathologies' => $applied['updated_pathologies'],
                'unmatched_results' => $applied['unmatched_results'],
            ],
        ];

        $this->writeIntegrationLog('info', 'payload_processed', [
            'request_id' => $requestId,
            'format' => $parsed['format'] ?? 'unknown',
            'total_results' => count($parsed['results']),
            'updated_pathologies' => $applied['updated_pathologies'],
            'unmatched_results' => $applied['unmatched_results'],
            'ip' => $request->ip(),
        ], $options, $request);

        return $this->decorateResponseProfile($result, $options, (string) ($parsed['format'] ?? 'unknown'), $requestId);
    }

    public function retryFromRawPayload(?string $rawPayload, array $context = []): array
    {
        $options = $this->resolvePathologyOptions();
        $requestId = (string) str()->uuid();

        if (!(bool) Arr::get($options, 'enabled', false)) {
            return [
                'ok' => false,
                'code' => 403,
                'message' => 'Pathology machine integration is disabled.',
                'request_id' => $requestId,
            ];
        }

        $rawPayload = trim((string) $rawPayload);
        if ($rawPayload === '') {
            return [
                'ok' => false,
                'code' => 422,
                'message' => 'No raw payload available for retry simulation.',
                'request_id' => $requestId,
            ];
        }

        $parsed = $this->parseRawPayloadContent($rawPayload, $options);
        if (empty($parsed['results'])) {
            return [
                'ok' => false,
                'code' => 422,
                'message' => 'Retry simulation could not parse any result from raw payload.',
                'meta' => [
                    'format' => $parsed['format'] ?? 'unknown',
                ],
                'request_id' => $requestId,
            ];
        }

        $applied = $this->applyResults($parsed['results']);

        $this->writeIntegrationLog('info', 'retry_simulation_processed', [
            'request_id' => $requestId,
            'format' => $parsed['format'] ?? 'unknown',
            'total_results' => count($parsed['results']),
            'updated_pathologies' => $applied['updated_pathologies'],
            'unmatched_results' => $applied['unmatched_results'],
            'source' => 'manual_retry',
            'log_context' => $context,
        ], $options, null, $rawPayload);

        return [
            'ok' => true,
            'code' => 200,
            'message' => 'Retry simulation processed successfully.',
            'meta' => [
                'format' => $parsed['format'] ?? 'unknown',
                'total_results' => count($parsed['results']),
                'updated_pathologies' => $applied['updated_pathologies'],
                'unmatched_results' => $applied['unmatched_results'],
            ],
            'request_id' => $requestId,
        ];
    }

    private function decorateResponseProfile(array $result, array $options, string $sourceFormat, string $requestId): array
    {
        $ackMode = (string) Arr::get($options, 'ack_response_mode', 'auto');
        $ackSuccessText = (string) Arr::get($options, 'ack_success_text', 'ACK');
        $ackFailureText = (string) Arr::get($options, 'ack_failure_text', 'NACK');

        if ($ackMode === 'auto') {
            $ackMode = in_array($sourceFormat, ['hl7', 'astm'], true) ? 'plain' : 'json';
        }

        $ackMode = in_array($ackMode, ['json', 'plain', 'hl7'], true) ? $ackMode : 'json';

        $result['request_id'] = $requestId;
        $result['ack_mode'] = $ackMode;
        $result['ack_success_text'] = $ackSuccessText;
        $result['ack_failure_text'] = $ackFailureText;

        return $result;
    }

    private function resolvePathologyOptions(): array
    {
        $setting = WebSetting::query()->select('attendance_device_options')->latest('id')->first();
        $options = $setting?->attendance_device_options;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($options)) {
            $options = [];
        }

        $pathology = $options['pathology'] ?? [];
        return is_array($pathology) ? $pathology : [];
    }

    private function verifySignature(Request $request, array $options): bool|string
    {
        $sharedSecret = trim((string) Arr::get($options, 'security.shared_secret', ''));

        if ($sharedSecret === '') {
            return true;
        }

        $header = (string) ($request->header('X-Pathology-Signature') ?? '');
        if ($header === '') {
            return 'Missing signature header.';
        }

        $algo = 'sha256';
        $prefix = $algo . '=';
        if (!str_starts_with($header, $prefix)) {
            return 'Invalid signature format.';
        }

        $rawBody = (string) $request->getContent();
        $expected = $prefix . hash_hmac($algo, $rawBody, $sharedSecret);

        if (!hash_equals($expected, $header)) {
            return 'Invalid signature.';
        }

        return true;
    }

    private function parseIncomingPayload(Request $request, array $options): array
    {
        $rawBody = trim((string) $request->getContent());
        $jsonPayload = $request->all();

        if (is_array($jsonPayload) && !empty($jsonPayload)) {
            $results = $this->extractResultsFromJson($jsonPayload, $options);
            if (!empty($results)) {
                return [
                    'format' => 'json',
                    'results' => $results,
                ];
            }
        }

        return $this->parseRawPayloadContent($rawBody, $options);
    }

    private function parseRawPayloadContent(string $rawBody, array $options): array
    {
        if ($rawBody === '') {
            return [
                'format' => 'unknown',
                'results' => [],
            ];
        }

        $decoded = json_decode($rawBody, true);
        if (is_array($decoded) && !empty($decoded)) {
            $results = $this->extractResultsFromJson($decoded, $options);
            if (!empty($results)) {
                return [
                    'format' => 'json',
                    'results' => $results,
                ];
            }
        }

        if (str_contains($rawBody, 'MSH|')) {
            return [
                'format' => 'hl7',
                'results' => $this->extractResultsFromHl7($rawBody),
            ];
        }

        if (preg_match('/^H\|/m', $rawBody)) {
            return [
                'format' => 'astm',
                'results' => $this->extractResultsFromAstm($rawBody),
            ];
        }

        return [
            'format' => 'unknown',
            'results' => [],
        ];
    }

    private function extractResultsFromJson(array $payload, array $options): array
    {
        $mapping = Arr::get($options, 'mapping', []);

        $sampleKey = (string) ($mapping['sample_id_key'] ?? 'sample_id');
        $patientKey = (string) ($mapping['patient_id_key'] ?? 'patient_id');
        $testCodeKey = (string) ($mapping['test_code_key'] ?? 'test_code');
        $resultValueKey = (string) ($mapping['result_value_key'] ?? 'result_value');
        $resultUnitKey = (string) ($mapping['result_unit_key'] ?? 'unit');
        $referenceRangeKey = (string) ($mapping['reference_range_key'] ?? 'reference_range');
        $resultTimeKey = (string) ($mapping['result_time_key'] ?? 'result_time');

        $rows = [];
        if (isset($payload['results']) && is_array($payload['results'])) {
            $rows = $payload['results'];
        } elseif (isset($payload[0]) && is_array($payload[0])) {
            $rows = $payload;
        } else {
            $rows = [$payload];
        }

        $parsed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $testCode = trim((string) ($row[$testCodeKey] ?? ''));
            $resultValue = $row[$resultValueKey] ?? null;

            if ($testCode === '' && ($resultValue === null || $resultValue === '')) {
                continue;
            }

            $parsed[] = [
                'sample_id' => trim((string) ($row[$sampleKey] ?? $payload[$sampleKey] ?? '')),
                'patient_id' => trim((string) ($row[$patientKey] ?? $payload[$patientKey] ?? '')),
                'bill_no' => trim((string) ($row['bill_no'] ?? $payload['bill_no'] ?? '')),
                'pathology_no' => trim((string) ($row['pathology_no'] ?? $payload['pathology_no'] ?? '')),
                'case_id' => trim((string) ($row['case_id'] ?? $payload['case_id'] ?? '')),
                'test_code' => $testCode,
                'result_value' => $resultValue,
                'result_unit' => trim((string) ($row[$resultUnitKey] ?? '')),
                'reference_range' => trim((string) ($row[$referenceRangeKey] ?? '')),
                'result_time' => trim((string) ($row[$resultTimeKey] ?? $payload[$resultTimeKey] ?? now()->toDateTimeString())),
            ];
        }

        return $parsed;
    }

    private function extractResultsFromHl7(string $raw): array
    {
        $segments = preg_split('/\r\n|\n|\r/', $raw) ?: [];

        $patientId = '';
        $sampleId = '';
        $pathologyNo = '';
        $billNo = '';
        $caseId = '';

        $results = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts = explode('|', $segment);
            $type = $parts[0] ?? '';

            if ($type === 'PID') {
                $patientId = trim((string) ($parts[3] ?? $parts[2] ?? ''));
                continue;
            }

            if ($type === 'OBR') {
                $sampleId = trim((string) ($parts[3] ?? $parts[2] ?? ''));
                $pathologyNo = trim((string) ($parts[18] ?? ''));
                continue;
            }

            if ($type !== 'OBX') {
                continue;
            }

            $codeRaw = trim((string) ($parts[3] ?? ''));
            $codeParts = explode('^', $codeRaw);
            $testCode = trim((string) ($codeParts[0] ?? ''));

            $value = $parts[5] ?? '';
            $unit = trim((string) ($parts[6] ?? ''));
            $range = trim((string) ($parts[7] ?? ''));
            $obsTime = trim((string) ($parts[14] ?? now()->toDateTimeString()));

            if ($testCode === '' && $value === '') {
                continue;
            }

            $results[] = [
                'sample_id' => $sampleId,
                'patient_id' => $patientId,
                'bill_no' => $billNo,
                'pathology_no' => $pathologyNo,
                'case_id' => $caseId,
                'test_code' => $testCode,
                'result_value' => $value,
                'result_unit' => $unit,
                'reference_range' => $range,
                'result_time' => $obsTime,
            ];
        }

        return $results;
    }

    private function extractResultsFromAstm(string $raw): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];

        $sampleId = '';
        $results = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line);
            $recordType = $parts[0] ?? '';

            if ($recordType === 'O') {
                $sampleId = trim((string) ($parts[2] ?? ''));
                continue;
            }

            if ($recordType !== 'R') {
                continue;
            }

            $codeRaw = trim((string) ($parts[2] ?? ''));
            $code = trim((string) (explode('^', $codeRaw)[3] ?? $codeRaw));
            $value = $parts[3] ?? '';
            $unit = trim((string) ($parts[4] ?? ''));
            $range = trim((string) ($parts[5] ?? ''));
            $resultTime = trim((string) ($parts[12] ?? now()->toDateTimeString()));

            if ($code === '' && $value === '') {
                continue;
            }

            $results[] = [
                'sample_id' => $sampleId,
                'patient_id' => '',
                'bill_no' => '',
                'pathology_no' => '',
                'case_id' => '',
                'test_code' => $code,
                'result_value' => $value,
                'result_unit' => $unit,
                'reference_range' => $range,
                'result_time' => $resultTime,
            ];
        }

        return $results;
    }

    private function applyResults(array $results): array
    {
        $updatedPathologyIds = [];
        $unmatchedCount = 0;

        foreach ($results as $result) {
            $pathology = $this->findTargetPathology($result);
            if (!$pathology) {
                $unmatchedCount++;
                continue;
            }

            $tests = $this->decodeTests($pathology->tests);
            if (empty($tests)) {
                $tests = [];
            }

            $matched = false;
            foreach ($tests as &$test) {
                if (!$this->isMatchingTest($test, (string) ($result['test_code'] ?? ''))) {
                    continue;
                }

                $test['machine_result_value'] = (string) ($result['result_value'] ?? '');
                $test['machine_result_unit'] = (string) ($result['result_unit'] ?? '');
                $test['machine_reference_range'] = (string) ($result['reference_range'] ?? '');
                $test['report_date'] = $this->normalizeResultDate($result['result_time'] ?? '');
                $test['result_source'] = 'machine';
                $matched = true;
            }
            unset($test);

            if (!$matched) {
                $tests[] = [
                    'test_name' => (string) ($result['test_code'] ?? 'Machine Result'),
                    'test_short_name' => (string) ($result['test_code'] ?? ''),
                    'machine_result_value' => (string) ($result['result_value'] ?? ''),
                    'machine_result_unit' => (string) ($result['result_unit'] ?? ''),
                    'machine_reference_range' => (string) ($result['reference_range'] ?? ''),
                    'report_date' => $this->normalizeResultDate($result['result_time'] ?? ''),
                    'result_source' => 'machine',
                ];
            }

            $pathology->tests = json_encode($tests, JSON_UNESCAPED_UNICODE);
            $pathology->date = $this->normalizeResultDate($result['result_time'] ?? '') ?: $pathology->date;
            $pathology->save();

            $updatedPathologyIds[$pathology->id] = true;
        }

        return [
            'updated_pathologies' => count($updatedPathologyIds),
            'unmatched_results' => $unmatchedCount,
        ];
    }

    private function findTargetPathology(array $result): ?Pathology
    {
        $billNo = trim((string) ($result['bill_no'] ?? ''));
        $pathologyNo = trim((string) ($result['pathology_no'] ?? ''));
        $caseId = trim((string) ($result['case_id'] ?? ''));

        $query = Pathology::query()->whereNull('deleted_at');

        if ($billNo !== '') {
            $item = (clone $query)->where('bill_no', $billNo)->orderByDesc('id')->first();
            if ($item) {
                return $item;
            }
        }

        if ($pathologyNo !== '') {
            $item = (clone $query)->where('pathology_no', $pathologyNo)->orderByDesc('id')->first();
            if ($item) {
                return $item;
            }
        }

        if ($caseId !== '') {
            $item = (clone $query)->where('case_id', $caseId)->orderByDesc('id')->first();
            if ($item) {
                return $item;
            }
        }

        $sampleId = trim((string) ($result['sample_id'] ?? ''));
        if ($sampleId !== '') {
            $item = (clone $query)->where('bill_no', 'like', '%' . $sampleId . '%')->orderByDesc('id')->first();
            if ($item) {
                return $item;
            }
        }

        return null;
    }

    private function decodeTests(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isMatchingTest(array $test, string $incomingCode): bool
    {
        $incomingCode = strtolower(trim($incomingCode));
        if ($incomingCode === '') {
            return false;
        }

        $candidates = [
            (string) ($test['test_short_name'] ?? ''),
            (string) ($test['test_name'] ?? ''),
            (string) ($test['test_code'] ?? ''),
            (string) ($test['testId'] ?? ''),
            (string) ($test['test_id'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '' && $candidate === $incomingCode) {
                return true;
            }
        }

        return false;
    }

    private function normalizeResultDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function writeIntegrationLog(string $level, string $event, array $context, array $options, ?Request $request = null, ?string $rawPayloadOverride = null): void
    {
        $saveRawPayload = (bool) Arr::get($options, 'save_raw_payload', false);
        $rawPayload = $saveRawPayload
            ? ($rawPayloadOverride ?? (string) ($request?->getContent() ?? ''))
            : null;

        $entry = [
            'event' => $event,
            'at' => now()->toDateTimeString(),
            'context' => $context,
        ];

        if ($saveRawPayload) {
            $entry['raw_payload'] = mb_substr((string) $rawPayload, 0, 8000);
        }

        try {
            PathologyMachineIntegrationLog::create([
                'request_id' => (string) ($context['request_id'] ?? ''),
                'event' => $event,
                'level' => $level,
                'source_format' => (string) ($context['format'] ?? ''),
                'communication_mode' => (string) Arr::get($options, 'communication_mode', ''),
                'ip_address' => (string) ($context['ip'] ?? $request?->ip() ?? ''),
                'message' => (string) ($context['message'] ?? ''),
                'context' => $context,
                'raw_payload' => $saveRawPayload ? mb_substr((string) $rawPayload, 0, 64000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('pathology_machine_integration_db_log_failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        if ($level === 'warning') {
            Log::warning('pathology_machine_integration', $entry);
        } elseif ($level === 'error') {
            Log::error('pathology_machine_integration', $entry);
        } else {
            Log::info('pathology_machine_integration', $entry);
        }
    }
}

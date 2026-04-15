<?php

namespace App\Services;

use App\Models\WebSetting;
use Illuminate\Support\Facades\Http;

class BulkSmsService
{
    public function sendSingle(string $phoneNumber, string $message): array
    {
        $settings = $this->getActiveSettings();

        if (!($settings?->sms_enabled)) {
            return [
                'ok' => false,
                'message' => 'Bulk SMS is disabled in General Setting.',
            ];
        }

        $apiUrl = trim((string) ($settings->sms_api_url ?? ''));
        $apiKey = trim((string) ($settings->sms_api_key ?? ''));

        if ($apiUrl === '' || $apiKey === '') {
            return [
                'ok' => false,
                'message' => 'SMS API URL বা API Key সেট করা নেই।',
            ];
        }

        $cleanNumber = $this->sanitizePhoneNumbers([$phoneNumber]);
        if (empty($cleanNumber)) {
            return [
                'ok' => false,
                'message' => 'Valid phone number পাওয়া যায়নি।',
            ];
        }

        $payload = [
            'api_key' => $apiKey,
            'sender_id' => (string) ($settings->sms_sender_id ?? ''),
            'route' => (string) ($settings->sms_route ?? ''),
            'unicode' => ($settings->sms_is_unicode ? '1' : '0'),
            'to' => $cleanNumber[0],
            'message' => $message,
        ];

        $additionalParams = json_decode((string) ($settings->sms_additional_params ?? ''), true);
        if (is_array($additionalParams)) {
            $payload = array_merge($payload, $additionalParams);
        }

        $response = Http::asForm()->timeout(20)->post($apiUrl, $payload);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => 'SMS provider request failed.',
                'status' => $response->status(),
                'response' => $response->body(),
            ];
        }

        return [
            'ok' => true,
            'message' => 'SMS sent successfully.',
            'status' => $response->status(),
            'response' => $response->body(),
        ];
    }

    public function send(array $phoneNumbers, string $message): array
    {
        $settings = $this->getActiveSettings();

        if (!($settings?->sms_enabled)) {
            return [
                'ok' => false,
                'message' => 'Bulk SMS is disabled in General Setting.',
            ];
        }

        $apiUrl = trim((string) ($settings->sms_api_url ?? ''));
        $apiKey = trim((string) ($settings->sms_api_key ?? ''));

        if ($apiUrl === '' || $apiKey === '') {
            return [
                'ok' => false,
                'message' => 'SMS API URL বা API Key সেট করা নেই।',
            ];
        }

        $cleanNumbers = $this->sanitizePhoneNumbers($phoneNumbers);
        if (empty($cleanNumbers)) {
            return [
                'ok' => false,
                'message' => 'Valid phone number পাওয়া যায়নি।',
            ];
        }

        $payload = [
            'api_key' => $apiKey,
            'sender_id' => (string) ($settings->sms_sender_id ?? ''),
            'route' => (string) ($settings->sms_route ?? ''),
            'unicode' => ($settings->sms_is_unicode ? '1' : '0'),
            'to' => implode(',', $cleanNumbers),
            'message' => $message,
        ];

        $additionalParams = json_decode((string) ($settings->sms_additional_params ?? ''), true);
        if (is_array($additionalParams)) {
            $payload = array_merge($payload, $additionalParams);
        }

        $response = Http::asForm()->timeout(20)->post($apiUrl, $payload);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => 'SMS provider request failed.',
                'status' => $response->status(),
                'response' => $response->body(),
            ];
        }

        return [
            'ok' => true,
            'message' => 'Bulk SMS sent successfully.',
            'total' => count($cleanNumbers),
            'response' => $response->body(),
        ];
    }

    private function getActiveSettings(): ?WebSetting
    {
        return get_cached_web_setting();
    }

    public function sanitizePhoneNumbers(array $numbers): array
    {
        $normalized = [];

        foreach ($numbers as $number) {
            $value = trim((string) $number);
            if ($value === '') {
                continue;
            }

            // Keep only digits and optional leading plus.
            $value = preg_replace('/(?!^\+)\D+/', '', $value) ?? '';
            if ($value === '' || strlen(preg_replace('/\D+/', '', $value) ?? '') < 10) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}

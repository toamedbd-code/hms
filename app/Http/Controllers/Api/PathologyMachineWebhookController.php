<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PathologyMachineIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class PathologyMachineWebhookController extends Controller
{
    public function __construct(private readonly PathologyMachineIntegrationService $service)
    {
    }

    public function __invoke(Request $request): Response|JsonResponse
    {
        $result = $this->service->handleWebhook($request);

        $ackMode = (string) ($result['ack_mode'] ?? 'json');
        $ok = (bool) ($result['ok'] ?? false);
        $code = (int) ($result['code'] ?? 200);
        $requestId = (string) ($result['request_id'] ?? '');
        $ackText = $ok
            ? (string) ($result['ack_success_text'] ?? 'ACK')
            : (string) ($result['ack_failure_text'] ?? 'NACK');

        if ($ackMode === 'plain') {
            return response($ackText, $code, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Request-Id' => $requestId,
            ]);
        }

        if ($ackMode === 'hl7') {
            $hl7Ack = "MSH|^~\\&|HMS|HMS|DEVICE|LAB|" . now()->format('YmdHis') . "||ACK|" . ($requestId !== '' ? $requestId : uniqid('ack_', true)) . "|P|2.3\\r";
            $hl7Ack .= "MSA|" . ($ok ? 'AA' : 'AE') . "|" . ($requestId !== '' ? $requestId : '0') . "|" . ($result['message'] ?? '') . "\\r";

            return response($hl7Ack, $code, [
                'Content-Type' => 'application/hl7-v2; charset=utf-8',
                'X-Request-Id' => $requestId,
            ]);
        }

        return response()->json([
            'ok' => $ok,
            'message' => $result['message'] ?? '',
            'request_id' => $requestId,
            'meta' => $result['meta'] ?? null,
        ], $code, [
            'X-Request-Id' => $requestId,
        ]);
    }
}

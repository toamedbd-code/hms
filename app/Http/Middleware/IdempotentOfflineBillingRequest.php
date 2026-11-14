<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotentOfflineBillingRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->header('X-Offline-Request-Id', ''));
        if ($requestId === '') {
            return $next($request);
        }

        if (!Str::isUuid($requestId)) {
            return response()->json(['message' => 'Invalid offline request ID.'], 422);
        }

        $adminId = (int) auth('admin')->id();
        if ($adminId < 1 || (int) $request->header('X-Offline-Admin-Id', 0) !== $adminId) {
            return response()->json(['message' => 'This offline request belongs to a different user.'], 403);
        }

        $requestHash = hash('sha256', implode("\n", [
            strtoupper($request->method()),
            $request->getPathInfo(),
            $request->getContent(),
        ]));

        try {
            return DB::transaction(function () use ($request, $next, $requestId, $adminId, $requestHash) {
                $stored = DB::table('offline_billing_requests')
                    ->where('request_id', $requestId)
                    ->where('admin_id', $adminId)
                    ->lockForUpdate()
                    ->first();

                if ($stored) {
                    if (!hash_equals((string) $stored->request_hash, $requestHash)) {
                        return response()->json([
                            'message' => 'This offline request ID was already used with different billing data.',
                        ], 409);
                    }

                    if ((int) $stored->response_status < 100) {
                        return response()->json(['message' => 'This billing request is still being processed.'], 409);
                    }

                    return $this->storedResponse($stored);
                }

                $id = DB::table('offline_billing_requests')->insertGetId([
                    'request_id' => $requestId,
                    'admin_id' => $adminId,
                    'request_hash' => $requestHash,
                    'route_name' => $request->route()?->getName(),
                    'response_status' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $response = $next($request);

                DB::table('offline_billing_requests')->where('id', $id)->update([
                    'response_status' => $response->getStatusCode(),
                    'response_content' => $response->getContent(),
                    'response_content_type' => $response->headers->get('Content-Type'),
                    'response_location' => $response->headers->get('Location'),
                    'updated_at' => now(),
                ]);

                return $response;
            });
        } catch (QueryException $exception) {
            if (!in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $stored = DB::table('offline_billing_requests')
                ->where('request_id', $requestId)
                ->where('admin_id', $adminId)
                ->first();

            if (!$stored) {
                throw $exception;
            }
            if (!hash_equals((string) $stored->request_hash, $requestHash)) {
                return response()->json([
                    'message' => 'This offline request ID was already used with different billing data.',
                ], 409);
            }

            return $this->storedResponse($stored);
        }
    }

    private function storedResponse(object $stored): Response
    {
        if ((int) $stored->response_status < 100) {
            return response()->json(['message' => 'This billing request is still being processed.'], 409);
        }

        $headers = [];
        if ($stored->response_content_type) {
            $headers['Content-Type'] = $stored->response_content_type;
        }
        if ($stored->response_location) {
            $headers['Location'] = $stored->response_location;
        }

        return response((string) ($stored->response_content ?? ''), (int) $stored->response_status, $headers);
    }
}
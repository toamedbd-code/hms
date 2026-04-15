<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DebugController extends Controller
{
    public function featuredDoctors(Request $request)
    {
        $webSetting = function_exists('get_cached_web_setting') ? get_cached_web_setting() : null;
        $raw = trim((string) ($webSetting?->website_featured_doctors_json ?? ''));

        $doctors = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $doc) {
                    $img = (string) ($doc['image_url'] ?? '');
                    $normalized = trim(str_replace('\\', '/', $img), '/');
                    $diskExists = false;
                    $publicUrl = null;

                    if ($normalized !== '') {
                        // check public disk
                        if (Storage::disk('public')->exists($normalized)) {
                            $diskExists = true;
                            $publicUrl = asset('storage/' . $normalized);
                        } else {
                            // try publicStorageUrl() helper if available
                            if (function_exists('publicStorageUrl')) {
                                $publicUrl = publicStorageUrl($normalized);
                            } else {
                                $publicUrl = asset($normalized);
                            }
                        }
                    }

                    $doctors[] = [
                        'raw' => $doc,
                        'image_normalized' => $normalized,
                        'disk_exists' => $diskExists,
                        'public_url' => $publicUrl,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $doctors,
        ]);
    }
}

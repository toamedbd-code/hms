<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class PublicStorageController extends Controller
{
    public function show(string $path)
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');

        if ($normalized === '' || str_contains($normalized, '..')) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($normalized)) {
            abort(404);
        }

        return response()->file(storage_path('app/public/' . $normalized));
    }
}

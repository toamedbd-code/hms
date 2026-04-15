<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AssetController extends Controller
{
    public function storage(Request $request, $path)
    {
        $safePath = trim($path, "/\\");
        $storagePath = storage_path('app/public/' . $safePath);

        if (!is_file($storagePath)) {
            abort(404);
        }

        $mime = File::mimeType($storagePath) ?: 'application/octet-stream';
        return response()->file($storagePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}

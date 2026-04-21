<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $admin = Admin::where('email', $data['email'])->first();

        if (! $admin) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($admin->status !== 'Active') {
            return response()->json(['message' => 'Account inactive'], 403);
        }

        if (! Hash::check($data['password'], $admin->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // create a personal access token
        $token = $admin->createToken('admin-api-token');

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'admin' => $admin->only(['id', 'first_name', 'last_name', 'email', 'role_id'])
        ]);
    }

    public function adminLogout(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }
}

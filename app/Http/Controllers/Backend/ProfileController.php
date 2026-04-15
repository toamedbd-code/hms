<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function edit()
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $rawPhotoPath = (string) $admin->getRawOriginal('photo');
        $photoVersion = optional($admin->updated_at)->timestamp ?: time();

        return Inertia::render('Backend/Profile/Edit', [
            'pageTitle' => 'My Profile',
            'adminData' => [
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'photo' => !empty($rawPhotoPath)
                    ? route('backend.profile.photo') . '?v=' . urlencode((string) $photoVersion)
                    : null,
            ],
        ]);
    }

    public function photo()
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $rawPhotoPath = (string) $admin->getRawOriginal('photo');

        if (empty($rawPhotoPath) || !Storage::disk('public')->exists($rawPhotoPath)) {
            abort(404);
        }

        $absolutePhotoPath = storage_path('app/public/' . ltrim($rawPhotoPath, '/'));

        return response()->file(
            $absolutePhotoPath,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    public function update(Request $request)
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $oldPhotoPath = $admin->getRawOriginal('photo');
            if (!empty($oldPhotoPath) && Storage::disk('public')->exists($oldPhotoPath)) {
                Storage::disk('public')->delete($oldPhotoPath);
            }

            $validated['photo'] = $request->file('photo')->store('users', 'public');
        }

        $admin->first_name = $validated['first_name'];
        $admin->last_name = $validated['last_name'] ?? null;
        $admin->email = $validated['email'];
        $admin->phone = $validated['phone'] ?? null;

        if (isset($validated['photo'])) {
            $admin->photo = $validated['photo'];
        }

        $admin->save();

        return redirect()
            ->route('backend.profile.edit')
            ->with('successMessage', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $admin->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $admin->password = $validated['password'];
        $admin->save();

        return redirect()
            ->route('backend.profile.edit')
            ->with('successMessage', 'Password changed successfully.')
            ->with('savedPassword', (string) $validated['password']);
    }
}

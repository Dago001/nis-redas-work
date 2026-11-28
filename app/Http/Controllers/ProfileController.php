<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('user.profile', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(6)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('user.profile')->with('status', 'Password updated successfully.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Please choose an image file to upload.',
            'photo.image' => 'The uploaded file must be a valid image.',
            'photo.mimes' => 'Profile photo must be a JPG, PNG, or WEBP image.',
            'photo.max' => 'Profile photo must not exceed 5 MB.',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $disk = Storage::disk('local');

        if (! empty($user->profile_photo_path) && $disk->exists($user->profile_photo_path)) {
            $disk->delete($user->profile_photo_path);
        }

        $file = $validated['photo'];
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = 'avatar-'.$user->id.'-'.Str::random(12).'.'.$ext;
        $storedPath = $file->storeAs('avatars', $filename, 'local');

        $user->profile_photo_path = $storedPath;
        $user->save();

        return redirect()->route('user.profile')->with('status', 'Profile photo updated successfully.');
    }

    public function destroyPhoto(): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $disk = Storage::disk('local');

        if (! empty($user->profile_photo_path) && $disk->exists($user->profile_photo_path)) {
            $disk->delete($user->profile_photo_path);
        }

        $user->profile_photo_path = null;
        $user->save();

        return redirect()->route('user.profile')->with('status', 'Profile photo removed.');
    }

    public function showPhoto(User $user): BinaryFileResponse
    {
        abort_if(empty($user->profile_photo_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->profile_photo_path), 404);

        $fullPath = $disk->path($user->profile_photo_path);

        return response()->file($fullPath, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

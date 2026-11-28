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

        // Also store a compact DB-backed copy so profile photos survive ephemeral
        // container restarts/redeploys (e.g. Render free tier).
        [$photoBase64, $photoMime] = $this->encodePhotoForDatabase($file->getRealPath(), $file->getMimeType() ?: 'image/jpeg');

        $user->profile_photo_path = $storedPath;
        $user->profile_photo_data = $photoBase64;
        $user->profile_photo_mime = $photoMime;
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
        $user->profile_photo_data = null;
        $user->profile_photo_mime = null;
        $user->save();

        return redirect()->route('user.profile')->with('status', 'Profile photo removed.');
    }

    public function showPhoto(User $user): BinaryFileResponse|\Illuminate\Http\Response
    {
        $disk = Storage::disk('local');
        if (! empty($user->profile_photo_path) && $disk->exists($user->profile_photo_path)) {
            return response()->file($disk->path($user->profile_photo_path), [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        if (! empty($user->profile_photo_data)) {
            $binary = base64_decode((string) $user->profile_photo_data, true);
            if ($binary !== false) {
                return response($binary, 200, [
                    'Content-Type' => $user->profile_photo_mime ?: 'image/jpeg',
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }
        }

        abort(404);
    }

    /**
     * Resize to a compact 256x256 thumbnail when GD is available so DB storage stays tiny (~15-30KB).
     *
     * @return array{0: ?string, 1: string}
     */
    private function encodePhotoForDatabase(string|false $realPath, string $fallbackMime): array
    {
        if (! is_string($realPath) || ! is_file($realPath)) {
            return [null, $fallbackMime];
        }

        $raw = @file_get_contents($realPath);
        if ($raw === false) {
            return [null, $fallbackMime];
        }

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $src = @imagecreatefromstring($raw);
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);
                $max = 256;
                if ($w > $max || $h > $max) {
                    $scale = min($max / max(1, $w), $max / max(1, $h));
                    $nw = max(1, (int) round($w * $scale));
                    $nh = max(1, (int) round($h * $scale));
                    $dst = imagecreatetruecolor($nw, $nh);
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefill($dst, 0, 0, $white);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagedestroy($src);
                    $src = $dst;
                }
                ob_start();
                imagejpeg($src, null, 82);
                $jpeg = ob_get_clean();
                imagedestroy($src);
                if (is_string($jpeg) && $jpeg !== '') {
                    return [base64_encode($jpeg), 'image/jpeg'];
                }
            }
        }

        return [base64_encode($raw), $fallbackMime];
    }
}

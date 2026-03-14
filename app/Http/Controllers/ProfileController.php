<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $avatarFile = $request->file('avatar');

        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);

        unset($data['avatar'], $data['remove_avatar']);

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($removeAvatar) {
            $this->deleteStoredFileFromPublicUrl($user->avatar_url);
            $user->avatar_url = null;
        }

        if ($avatarFile instanceof UploadedFile) {
            $user->avatar_url = $this->storeUserAvatar($user, $avatarFile);
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function storeUserAvatar(User $user, UploadedFile $avatarFile): string
    {
        Storage::disk('public')->deleteDirectory("users/{$user->id}/avatar");

        $avatarPath = $avatarFile->store("users/{$user->id}/avatar", 'public');

        return '/storage/'.$avatarPath;
    }

    private function deleteStoredFileFromPublicUrl(?string $publicUrl): void
    {
        $relativePath = $this->resolvePublicStorageRelativePath($publicUrl);

        if (! $relativePath) {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    private function resolvePublicStorageRelativePath(?string $publicUrl): ?string
    {
        if (! $publicUrl) {
            return null;
        }

        $path = parse_url($publicUrl, PHP_URL_PATH);
        $normalizedPath = is_string($path) && $path !== '' ? $path : $publicUrl;

        $prefix = '/storage/';
        if (! str_starts_with($normalizedPath, $prefix)) {
            return null;
        }

        $relativePath = ltrim(substr($normalizedPath, strlen($prefix)), '/');

        return $relativePath !== '' ? $relativePath : null;
    }
}

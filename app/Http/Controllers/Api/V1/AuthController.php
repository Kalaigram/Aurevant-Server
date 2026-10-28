<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\DTOs\UserDTO;
use App\Application\Auth\UseCases\ChangePassword;
use App\Application\Auth\UseCases\DeleteUserAvatar;
use App\Application\Auth\UseCases\LoginUser;
use App\Application\Auth\UseCases\ManageTwoFactor;
use App\Application\Auth\UseCases\RegisterUser;
use App\Application\Auth\UseCases\RequestPasswordReset;
use App\Application\Auth\UseCases\ResetPassword;
use App\Application\Auth\UseCases\StoreUserAvatar;
use App\Application\Auth\UseCases\UpdateOwnProfile;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request, RegisterUser $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $auth = $useCase(
            $validated['name'],
            $validated['email'],
            $validated['phone'] ?? null,
            $validated['password'],
        );

        return ApiResponse::success($auth, 'Pendaftaran berhasil.', 201);
    }

    public function login(Request $request, LoginUser $useCase): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(
            $useCase(
                $validated['login'],
                $validated['password'],
                $validated['two_factor_code'] ?? null,
                $validated['device_name'] ?? null,
            ),
            'Login berhasil.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Berhasil keluar.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(UserDTO::fromModel($request->user()), 'Profil saya.');
    }

    public function updateProfile(Request $request, UpdateOwnProfile $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('users', 'phone')->ignore($request->user()->id),
            ],
        ]);

        return ApiResponse::success($useCase($request->user(), $validated), 'Profil diperbarui.');
    }

    public function uploadAvatar(Request $request, StoreUserAvatar $useCase): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return ApiResponse::success($useCase($request->user(), $validated['avatar']), 'Foto profil diperbarui.');
    }

    public function deleteAvatar(Request $request, DeleteUserAvatar $useCase): JsonResponse
    {
        return ApiResponse::success($useCase($request->user()), 'Foto profil dihapus.');
    }

    public function forgotPassword(Request $request, RequestPasswordReset $useCase): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $useCase($validated['email']);

        return ApiResponse::success(
            null,
            'Jika email terdaftar, tautan atur ulang password sudah dikirim.'
        );
    }

    public function resetPassword(Request $request, ResetPassword $useCase): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $useCase($validated['email'], $validated['token'], $validated['password']);

        return ApiResponse::success(null, 'Password berhasil diatur ulang. Silakan masuk kembali.');
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'createdAt' => $token->created_at?->toIso8601String(),
                'lastUsedAt' => $token->last_used_at?->toIso8601String(),
                'isCurrent' => $token->id === $currentId,
            ]);

        return ApiResponse::success($sessions, 'Daftar sesi.');
    }

    public function revokeSession(Request $request, int $token): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        if ($token === $currentId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'token' => 'Gunakan logout untuk mengakhiri sesi yang sedang dipakai.',
            ]);
        }

        $found = $request->user()->tokens()->whereKey($token)->first();

        if ($found === null) {
            abort(404);
        }

        $found->delete();

        return ApiResponse::success(null, 'Sesi diakhiri.');
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;
        $query = $request->user()->tokens();

        if ($currentId !== null) {
            $query->whereKeyNot($currentId);
        }

        $deleted = $query->delete();

        return ApiResponse::success(['revoked' => $deleted], 'Sesi lain diakhiri.');
    }

    public function twoFactorStatus(Request $request, ManageTwoFactor $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->status($request->user()), 'Status 2FA.');
    }

    public function twoFactorSetup(Request $request, ManageTwoFactor $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->setup($request->user()), 'Pindai kode QR lalu masukkan kode 6 digit.');
    }

    public function twoFactorEnable(Request $request, ManageTwoFactor $useCase): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $useCase->enable($request->user(), $validated['code']);

        return ApiResponse::success(['enabled' => true], '2FA aktif.');
    }

    public function twoFactorDisable(Request $request, ManageTwoFactor $useCase): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $useCase->disable($request->user(), $validated['password']);

        return ApiResponse::success(['enabled' => false], '2FA nonaktif.');
    }

    public function changePassword(Request $request, ChangePassword $useCase): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $useCase($request->user(), $validated['current_password'], $validated['password']);

        return ApiResponse::success(null, 'Password berhasil diganti.');
    }
}

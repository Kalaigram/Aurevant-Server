<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\DTOs\UserDTO;
use App\Application\Auth\UseCases\ChangePassword;
use App\Application\Auth\UseCases\DeleteUserAvatar;
use App\Application\Auth\UseCases\LoginUser;
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
        ]);

        return ApiResponse::success(
            $useCase($validated['login'], $validated['password']),
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

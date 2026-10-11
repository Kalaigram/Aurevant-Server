<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\DTOs\UserDTO;
use App\Application\Auth\UseCases\LoginUser;
use App\Application\Auth\UseCases\RegisterUser;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}

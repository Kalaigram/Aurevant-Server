<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Notify\UseCases\ManageDeviceTokens;
use App\Application\Notify\UseCases\ManageNotifications;
use App\Application\Notify\UseCases\ManagePreferences;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request, ManageNotifications $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'company_id' => ['sometimes', 'integer'],
            'unread' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                $request->user(),
                (int) ($validated['per_page'] ?? 15),
                isset($validated['company_id']) ? (int) $validated['company_id'] : null,
                (bool) ($validated['unread'] ?? false),
            ),
            'Notifikasi.'
        );
    }

    public function unreadCount(Request $request, ManageNotifications $useCase): JsonResponse
    {
        return ApiResponse::success(
            ['unread' => $useCase->unreadCount($request->user())],
            'Belum dibaca.'
        );
    }

    public function read(Request $request, int $notification, ManageNotifications $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->read($request->user(), $notification), 'Sudah dibaca.');
    }

    public function readAll(Request $request, ManageNotifications $useCase): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::success(
            ['read' => $useCase->readAll(
                $request->user(),
                isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            )],
            'Semua ditandai dibaca.'
        );
    }

    public function preferences(Request $request, ManagePreferences $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->list($request->user()), 'Preferensi notifikasi.');
    }

    public function setPreference(Request $request, ManagePreferences $useCase): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        return ApiResponse::success(
            $useCase->set($request->user(), $validated['type'], (bool) $validated['enabled']),
            'Preferensi disimpan.'
        );
    }

    public function registerToken(Request $request, ManageDeviceTokens $useCase): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
        ]);

        $useCase->register($request->user(), $validated['token'], $validated['platform'] ?? null);

        return ApiResponse::success(null, 'Token perangkat disimpan.');
    }

    public function removeToken(Request $request, ManageDeviceTokens $useCase): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $useCase->remove($request->user(), $validated['token']);

        return ApiResponse::success(null, 'Token perangkat dihapus.');
    }

    public function tokens(Request $request, ManageDeviceTokens $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->tokensFor($request->user()), 'Perangkat terdaftar.');
    }
}

<?php

use App\Domain\Stock\Exceptions\InsufficientStockException;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\RequireBranchScope;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\SetCompanyTeam;
use App\Shared\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'company.team' => SetCompanyTeam::class,
            'scope.cabang' => RequireBranchScope::class,
            'subscription.aktif' => CheckSubscription::class,
            'super.admin' => RequireSuperAdmin::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'Unauthenticated.', null, 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Tidak ditemukan.', null, 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Validasi gagal.', $e->errors(), 422);
            }
        });

        $exceptions->render(function (InsufficientStockException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), null, 422);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*') || $e->getStatusCode() !== 403) {
                return null;
            }

            if ($e instanceof UnauthorizedException) {
                return ApiResponse::error('Akses ditolak.', null, 403);
            }

            return ApiResponse::error($e->getMessage() !== '' ? $e->getMessage() : 'Akses ditolak.', null, 403);
        });
    })->create();

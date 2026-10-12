<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_super_admin !== true) {
            throw new HttpException(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SetCompanyTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if ($company instanceof Company) {
            $user = $request->user();

            if ($user !== null && ! $user->is_super_admin && ! Access::hasActiveAssignment($user, $company)) {
                throw new HttpException(403, 'Akses ditolak.');
            }

            setPermissionsTeamId($company->id);
        }

        return $next($request);
    }
}

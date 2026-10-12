<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if ($company instanceof Company) {
            setPermissionsTeamId($company->id);
        }

        return $next($request);
    }
}

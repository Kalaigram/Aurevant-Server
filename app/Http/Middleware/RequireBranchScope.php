<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Company;
use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $request->route('company');

        if ($user !== null && $company instanceof Company) {
            $ids = $this->branchIds($request);

            if ($ids !== []) {
                Access::requireBranches($user, $company, $ids);
            }
        }

        return $next($request);
    }

    /** @return list<int> */
    private function branchIds(Request $request): array
    {
        $ids = [];
        $routeBranch = $request->route('branch');

        if ($routeBranch instanceof Branch) {
            $ids[] = $routeBranch->id;
        } elseif (is_numeric($routeBranch)) {
            $ids[] = (int) $routeBranch;
        }

        foreach (['branch_id'] as $key) {
            $value = $request->input($key);

            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}

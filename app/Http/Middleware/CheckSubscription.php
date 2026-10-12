<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Tulis (non-GET) butuh langganan aktif. Baca tetap boleh (read-only). */
final class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $company = $request->route('company');
        if (! $company instanceof Company) {
            return $next($request);
        }

        $subscription = Subscription::query()->where('company_id', $company->id)->first();
        if ($subscription === null || ! $subscription->allowsWrites()) {
            throw new HttpException(403, 'Langganan tidak aktif.');
        }

        return $next($request);
    }
}

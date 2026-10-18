<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Audit\Audit;
use App\Application\Billing\Mail\SubscriptionReminderMail;
use App\Application\Notify\Notifier;
use App\Models\Company;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Harian: kabari Pemilik saat langganan/trial tersisa H-7/H-3/H-1/H-0,
 * dan tandai read_only begitu lewat (berlaku trial_ends_at atau ends_at).
 */
final class SendSubscriptionReminders
{
    /** @return array{reminded: int, expired: int} */
    public function __invoke(?string $date = null): array
    {
        $today = ($date !== null && $date !== '' ? Carbon::parse($date) : Carbon::now())->startOfDay();
        $reminded = 0;
        $expired = 0;

        Subscription::query()
            ->whereIn('status', Subscription::ACTIVE)
            ->with(['company', 'plan'])
            ->each(function (Subscription $subscription) use ($today, &$reminded, &$expired): void {
                $end = $subscription->status === 'trial'
                    ? $subscription->trial_ends_at
                    : ($subscription->ends_at ?? $subscription->trial_ends_at);

                if ($end === null || $subscription->company === null) {
                    return;
                }

                $daysLeft = (int) floor(($end->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);
                $company = $subscription->company;

                if ($daysLeft < 0) {
                    $previous = $subscription->status;
                    $subscription->status = 'read_only';
                    $subscription->save();

                    Audit::record($company, null, 'langganan.berakhir', 'subscription', $subscription->id, ['status' => $previous], ['status' => 'read_only']);
                    $this->notifyOwners($company, $subscription, true, 0, $end);
                    $expired++;

                    return;
                }

                if (in_array($daysLeft, [7, 3, 1, 0], true)) {
                    $this->notifyOwners($company, $subscription, false, $daysLeft, $end);
                    $reminded++;
                }
            });

        return ['reminded' => $reminded, 'expired' => $expired];
    }

    private function notifyOwners(Company $company, Subscription $subscription, bool $expired, int $daysLeft, CarbonInterface $endDate): void
    {
        $planName = $subscription->plan?->name ?? 'Langganan';
        $endLabel = $endDate->format('d/m/Y');

        $title = $expired
            ? 'Langganan berakhir'
            : ($daysLeft === 0 ? 'Langganan berakhir hari ini' : 'Langganan berakhir dalam '.$daysLeft.' hari');

        $body = $expired
            ? 'Masa langganan '.$company->name.' sudah berakhir. Akses tulis dibatasi sampai diperpanjang.'
            : 'Paket '.$planName.' untuk '.$company->name.' berakhir pada '.$endLabel.'.';

        Notifier::toRole($company, 'Pemilik', 'langganan.jatuh_tempo', $title, $body, '/subscription');

        foreach (Notifier::usersWithRole($company, 'Pemilik') as $owner) {
            Mail::to($owner->email)->send(new SubscriptionReminderMail(
                companyName: $company->name,
                planName: $planName,
                endDate: $endLabel,
                daysLeft: $daysLeft,
                expired: $expired,
            ));
        }
    }
}

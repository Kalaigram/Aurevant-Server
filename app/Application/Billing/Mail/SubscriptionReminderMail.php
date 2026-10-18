<?php

declare(strict_types=1);

namespace App\Application\Billing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SubscriptionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $companyName,
        public readonly string $planName,
        public readonly string $endDate,
        public readonly int $daysLeft,
        public readonly bool $expired,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->expired
                ? 'Langganan '.$this->companyName.' telah berakhir'
                : 'Langganan '.$this->companyName.' segera berakhir',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-reminder',
            with: [
                'companyName' => $this->companyName,
                'planName' => $this->planName,
                'endDate' => $this->endDate,
                'daysLeft' => $this->daysLeft,
                'expired' => $this->expired,
            ],
        );
    }
}

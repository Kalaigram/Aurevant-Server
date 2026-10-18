<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Notify\Push\PushSender;
use App\Application\Notify\UseCases\ManageDeviceTokens;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, string|int|float|null> $data */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
    ) {}

    public function handle(PushSender $sender, ManageDeviceTokens $tokens): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            return;
        }

        foreach ($tokens->tokensFor($user) as $row) {
            if (! $sender->send($row['token'], $this->title, $this->body, $this->data)) {
                UserDeviceToken::query()->where('token', $row['token'])->delete();
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use App\Application\Notify\Push\PushSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 tanpa dependency tambahan: JWT RS256 via openssl,
 * access token Google di-cache ±55 menit.
 */
final class FcmPushSender implements PushSender
{
    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $projectId,
    ) {}

    public function send(string $deviceToken, string $title, ?string $body, array $data = []): bool
    {
        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return true; // konfigurasi bermasalah: jangan hapus token user
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = (string) $value;
        }

        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => array_filter([
                        'title' => $title,
                        'body' => $body,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'data' => $stringData,
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // 404/410 = token tidak terdaftar lagi → hapus
        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }

        Log::warning('FCM kirim gagal.', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return true;
    }

    private function accessToken(): ?string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(55), function (): ?string {
            $raw = @file_get_contents($this->credentialsPath);
            $credentials = $raw !== false ? json_decode($raw, true) : null;

            if (! is_array($credentials)
                || ! isset($credentials['client_email'], $credentials['private_key'])
            ) {
                Log::warning('Kredensial Firebase tidak bisa dibaca.', ['path' => $this->credentialsPath]);

                return null;
            }

            $now = time();
            $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('Google oauth token gagal.', ['status' => $response->status()]);

                return null;
            }

            return (string) $response->json('access_token');
        });
    }
}

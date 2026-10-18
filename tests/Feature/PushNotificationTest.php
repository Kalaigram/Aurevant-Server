<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Notify\Notifier;
use App\Application\Notify\Push\PushSender;
use App\Application\Notify\UseCases\ManageDeviceTokens;
use App\Infrastructure\Push\FcmPushSender;
use App\Models\Company;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: User, 2: Company} */
    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $gudang = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);

        setPermissionsTeamId($company->id);
        $role = Role::create(['name' => 'Staf Gudang', 'guard_name' => 'web']);
        $role->syncPermissions(['stok.transfer']);
        $gudang->assignRole($role);

        return [$owner, $gudang, $company];
    }

    /** @var string kunci RSA sekali-pakai untuk tes (bukan rahasia) */
    private const TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCh5ZdnYBfSXsv4
G+BUlwVZKg83SDt4cjF0SjCHEXvs93C+AhzfYS6cpiRjAdz3wbbT2yBrnZ1kL8LI
0OKDuS12QleIyoLeFHiAQsYHEv6wUtXmSLu4PoO1sPrwhSAMDkv/uk3qSg5is0vp
tHl+rlwlk3zcgQUClqY8PEqnvDIol0KQIfJ0cB/RawFK1r59B2BjGtYFORdCvW26
ICF7L7gJDZU8XyAdJ5FQ2thHUu2E3GkYzfVVrHYo7vBeqk6TcW1cawQ3PoceMdlW
I00Tp/QyHJsuag2IoQ/grDTehP7TiRPC5UmpFHosAzSshQY1C/2VTEuBDu/EODIo
CeZ9fboLAgMBAAECggEAFmsHax4oTk4oKmQZzTzYVb94Oex7p7MQXA0F6T4njem5
p+X8/4WigbiT403XvksJuQ5nz98Uc1IVw98A7sKOpAiNvHhmZMAq63/Tkhk0QVBH
JEtAC0RE0rz+Ep4skN7N8UxoC6BTm6E+Ss9d34ffZKimqpYHDpOz0zV6FP3/aBVJ
PZU0x5gxMdumE5RK0Ao3eZOfGadpWB4vbJQQkiwKwJuX1+ONov9JK714dtMV5mwu
5dUpBu/aw4k4iecHmovn2SO9ShwM1j32OJlXxMD7f0h4zdtXpJDEV7qayzrChkd6
OsktdUBY8EB8PDoHG1em+pOwDo+Vld2EM8qG1Ra8gQKBgQDjWiC+2eVl9xXyO9XK
/RW6jC/Ixkj3D36ShQgyQaJs0bzovT8VtiX+Yqaf4wm4/GGdqVMSCoRCtwCZLoTX
6gTL+Dzjj05M2sNDO6SfUUnriTPMb0E3KNjiIlxIzs6sIZfi8D1YbPXeIp5z1mWE
F0s9l+Lm+m0eHsNhuEwSTp+JwQKBgQC2TAYLGcRV4KCa+Ht0EZaLlRpeMu9AWAs3
xNzjV4ylqklpScxbWQQ4jARth6UefL4CMHykC6IEdyZyaBkCSCwavyrCL+XgUt5B
EIyakvuMP77qC0rfPAd0c0baJzHqSobMgMC0CWBcNc+1ngHZzwcCq50qzXKe21VU
pSZFQc7+ywKBgQDU5mKBtlSeboqLo0KqauO9JGfe8OgFC+pwVMl/Hn12v9XScN2x
fDgKBuGYGijRpO3YkWasH1MJkoup5qkZ9Bh31UdXpawiRj3SKPkZBCtfuMTb7Vc5
yzOGV7zppVheUrsEFgqsUqTPVsXhHeVEk/eufxxJRjuGmDJIwSfjgOLfQQKBgDE7
7xXoa6JV/IpeOdlrNqGUL37peryCCuWRAadU6eOETjdlew/gxscO6dM7I1lMj3S/
66nSintyQqv7eWWoYGEI6F9x2D/xoZ0o/xBwSgfi34kOa7ZG3CyTRbt9gnmojyeE
TodRUPkWf3apqg5NX7NU7im1OxO0YSNvRxrFX0ZpAoGAYlgeoOh9l8OKLUOHRbEK
4S6xh61cRsHKI2vGpL9Bkg7FwH7pnjrjt57nV+gHNF3qisBnY/0Nr428M8f1mG3X
izXTIHNrr3Pp5+2qiekqCMAYaR0rBH/HohChhyNiAPRfx2dczjqqXTR/ONsqs5Z5
dVt6mBVCfs7hs4TqFVEQtYo=
-----END PRIVATE KEY-----
PEM;

    private function fakeServiceAccount(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode([
            'client_email' => 'svc@proyek.iam.gserviceaccount.com',
            'private_key' => self::TEST_PRIVATE_KEY,
        ]));

        return $path;
    }

    public function test_fcm_sender_mengirim_lewat_http_v1(): void
    {
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.TEST', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/proyek-test/messages/1']),
        ]);

        $path = $this->fakeServiceAccount();
        $sender = new FcmPushSender($path, 'proyek-test');

        $sent = $sender->send('token-hp-1', 'Transfer diajukan', 'TRF-1 menunggu approval', ['type' => 'transfer.diajukan']);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request->hasHeader('Authorization', 'Bearer ya29.TEST')
            && $request['message']['token'] === 'token-hp-1'
            && $request['message']['notification']['title'] === 'Transfer diajukan'
            && $request['message']['data']['type'] === 'transfer.diajukan');

        unlink($path);
    }

    public function test_fcm_sender_menandai_token_mati(): void
    {
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.TEST', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
        ]);

        $path = $this->fakeServiceAccount();
        $sender = new FcmPushSender($path, 'proyek-test');

        $this->assertFalse($sender->send('token-mati', 'Halo', null));

        unlink($path);
    }

    public function test_notifier_mengirim_push_dan_menghapus_token_mati(): void
    {
        [$owner, $gudang, $company] = $this->setupWorld();
        $tokens = app(ManageDeviceTokens::class);
        $tokens->register($gudang, 'token-gudang', 'android');
        $tokens->register($owner, 'token-owner-mati', 'android');

        $spy = new class implements PushSender
        {
            /** @var list<string> */
            public array $sent = [];

            public function send(string $deviceToken, string $title, ?string $body, array $data = []): bool
            {
                $this->sent[] = $deviceToken;

                return $deviceToken !== 'token-owner-mati';
            }
        };
        $this->app->instance(PushSender::class, $spy);

        Notifier::toPermissioned(
            $company,
            ['stok.transfer'],
            'transfer.diajukan',
            'Transfer diajukan',
            'TRF-1 menunggu approval',
        );

        $this->assertEqualsCanonicalizing(['token-gudang', 'token-owner-mati'], $spy->sent);
        // token mati dihapus, token hidup tetap
        $this->assertDatabaseMissing('user_device_tokens', ['token' => 'token-owner-mati']);
        $this->assertDatabaseHas('user_device_tokens', ['token' => 'token-gudang']);

        // preferensi off → user tidak dapat push lagi
        $tokens->register($owner, 'token-owner-2', 'android');
        NotificationPreference::query()->create([
            'user_id' => $owner->id, 'type' => 'transfer.diajukan', 'enabled' => false,
        ]);

        Notifier::toPermissioned($company, ['stok.transfer'], 'transfer.diajukan', 'Transfer diajukan');

        $this->assertEqualsCanonicalizing(['token-gudang', 'token-owner-mati', 'token-gudang'], $spy->sent);
        $this->assertDatabaseHas('user_device_tokens', ['token' => 'token-owner-2']);
    }
}

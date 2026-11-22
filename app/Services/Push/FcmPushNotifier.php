<?php

namespace App\Services\Push;

use App\Contracts\PushNotifier;
use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 (D22). Authenticates with a service
 * account (signed JWT → OAuth token, cached) and prunes dead device tokens.
 */
class FcmPushNotifier implements PushNotifier
{
    /** @var array<string, string> */
    private array $credentials;

    public function __construct(string $credentialsPath, private readonly ?string $projectId = null)
    {
        $json = is_file($credentialsPath) ? file_get_contents($credentialsPath) : $credentialsPath;
        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded) || empty($decoded['private_key']) || empty($decoded['client_email'])) {
            throw new RuntimeException('Invalid FCM service account credentials.');
        }

        $this->credentials = $decoded;
    }

    public function send(Customer $customer, string $title, string $body, array $data = []): int
    {
        $sent = 0;
        $project = $this->projectId ?: $this->credentials['project_id'];

        foreach ($customer->devices()->get() as $device) {
            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                    'message' => [
                        'token' => $device->fcm_token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'high'],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ]);

            if ($response->successful()) {
                $device->update(['last_seen_at' => now()]);
                $sent++;

                continue;
            }

            $code = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

            if (in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                CustomerDevice::query()->whereKey($device->id)->delete();
            } else {
                Log::warning('FCM send failed.', ['customer_id' => $customer->id, 'status' => $response->status(), 'code' => $code]);
            }
        }

        return $sent;
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access_token:'.md5($this->credentials['client_email']), now()->addMinutes(50), function (): string {
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $this->credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            openssl_sign(implode('.', $segments), $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Could not get an FCM access token.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\JwtConfigurationException;
use App\Services\JwtService;
use App\Services\SettingService;
use Illuminate\Console\Command;

class SecurityCheckCommand extends Command
{
    protected $signature = 'redas:security:check';

    protected $description = 'Validate critical runtime security configuration (JWT, APP_KEY, debug flag, geolocation HTTPS, public/.env exposure)';

    public function handle(): int
    {
        $failures = [];

        // 1. APP_KEY check
        $appKey = (string) config('app.key', '');
        if (trim($appKey) === '') {
            $failures[] = 'APP_KEY is not configured.';
        } else {
            $rawKey = str_starts_with($appKey, 'base64:')
                ? base64_decode(substr($appKey, 7), true)
                : $appKey;
            if (! is_string($rawKey) || strlen($rawKey) < 32) {
                $failures[] = 'APP_KEY must decode to at least 32 bytes.';
            }
        }

        // 2. JWT secret check (fail closed)
        try {
            JwtService::assertConfigured();
        } catch (JwtConfigurationException $e) {
            $failures[] = 'JWT check failed: ' . $e->getMessage();
        }

        // 3. APP_DEBUG check in production
        if (app()->environment('production') && config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }

        // 4. Geolocation HTTPS check
        $provider = strtolower((string) config('geolocation.provider', 'ipapi_co'));
        if ($provider === 'ip-api') {
            $provider = 'ip_api_pro';
        }

        $providers = (array) config('geolocation.providers', []);
        if (! empty($providers)) {
            foreach ($providers as $name => $url) {
                if (! is_string($url) || ! str_starts_with(strtolower($url), 'https://')) {
                    $failures[] = "Geolocation provider [{$name}] URL must use HTTPS.";
                }
            }
        }

        // 5. Ensure .env is not placed/linked inside public/
        if (file_exists(public_path('.env')) || is_link(public_path('.env'))) {
            $failures[] = 'public/.env exists and could expose environment secrets over HTTP.';
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('All NIS REDAS runtime security checks passed.');

        return self::SUCCESS;
    }
}

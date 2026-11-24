<?php

namespace App\Services\Auth;

use App\Exceptions\JwtConfigurationException;
use App\Models\AuthChallenge;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\JwtService;
use App\Services\Messaging\ResendMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class ApiLoginService
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    // Static bcrypt hash used when account is missing to equalize password-check timing.
    private const DUMMY_BCRYPT_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function attemptPassword(string $loginInput, string $password, ?string $role, string $ip): JsonResponse
    {
        $trimmedLogin = trim($loginInput);
        $field = filter_var($trimmedLogin, FILTER_VALIDATE_EMAIL) ? 'email' : 'service_number';
        $normalizedLogin = $field === 'email' ? Str::lower($trimmedLogin) : Str::upper($trimmedLogin);
        $throttleKey = 'api-login|'.sha1(Str::lower($trimmedLogin)).'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            AuditLogger::log(
                user: null,
                action: 'api.login.rate_limited',
                status: 'blocked',
                entityType: 'user',
                details: ['login_field' => $field, 'retry_after' => $seconds],
                ip: $ip
            );

            return response()->json([
                'message' => 'TOO_MANY_ATTEMPTS',
                'retry_after' => $seconds,
            ], 429);
        }

        $query = User::query();
        if ($field === 'email') {
            $query->whereRaw('LOWER(email) = ?', [$normalizedLogin]);
        } else {
            $query->where('service_number', $normalizedLogin);
        }

        /** @var User|null $user */
        $user = $query->first();

        $passwordMatches = $user
            ? Hash::check($password, $user->password)
            : Hash::check($password, self::DUMMY_BCRYPT_HASH) && false;

        $roleMatches = true;
        if ($user && $role !== null && $role !== '') {
            $roleMatches = ($user->role === $role || $user->user_category === $role);
        }

        if (! $user || ! $passwordMatches || ! $roleMatches) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            AuditLogger::log(
                user: $user,
                action: 'api.login.password_failed',
                status: 'failure',
                entityType: 'user',
                entityId: $user?->id,
                details: ['login_field' => $field, 'reason' => 'invalid_credentials'],
                ip: $ip
            );

            return response()->json([
                'message' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if ($user->is_enabled === false) {
            AuditLogger::log(
                user: $user,
                action: 'api.login.account_disabled',
                status: 'blocked',
                entityType: 'user',
                entityId: $user->id,
                details: ['reason' => 'account_disabled'],
                ip: $ip
            );

            return response()->json([
                'message' => 'ACCOUNT_DISABLED',
            ], 403);
        }

        if (! $user->email_verified_at) {
            AuditLogger::log(
                user: $user,
                action: 'api.login.email_unverified',
                status: 'blocked',
                entityType: 'user',
                entityId: $user->id,
                details: ['reason' => 'email_not_verified'],
                ip: $ip
            );

            return response()->json([
                'message' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $ttlSeconds = (int) config('otp.ttl', 300);
        $maxAttempts = (int) config('otp.max_attempts', 5);
        $digits = (int) config('otp.digits', 6);

        $rawChallengeToken = bin2hex(random_bytes(32));
        $otp = AuthChallenge::generateOtpCode($digits);

        /** @var AuthChallenge $challenge */
        $challenge = DB::transaction(function () use ($user, $rawChallengeToken, $otp, $ttlSeconds, $maxAttempts, $ip): AuthChallenge {
            $row = AuthChallenge::create([
                'token_hash' => AuthChallenge::hashToken($rawChallengeToken),
                'user_id' => $user->id,
                'purpose' => 'login',
                'otp_hash' => str_repeat('0', 64),
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'sends' => 1,
                'last_sent_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
                'consumed_at' => null,
                'ip_address' => $ip,
            ]);

            $row->otp_hash = AuthChallenge::hashOtp($row->id, $otp);
            $row->save();

            return $row;
        });

        try {
            $mailer = new ResendMailService();
            $mailer->sendFromTemplate(
                'otp_login',
                [
                    'name' => $user->name,
                    'otp' => $otp,
                    'expires_in_minutes' => (int) ceil($ttlSeconds / 60),
                ],
                $user->email,
                $user->name
            );
        } catch (Throwable $e) {
            $challenge->consumed_at = now();
            $challenge->save();

            Log::error('Login OTP email delivery failed', [
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'error' => $e->getMessage(),
            ]);

            AuditLogger::log(
                user: $user,
                action: 'api.otp.delivery_failed',
                status: 'failure',
                entityType: 'auth_challenge',
                entityId: $challenge->id,
                details: ['reason' => 'mail_delivery_error'],
                ip: $ip
            );

            return response()->json([
                'message' => 'We could not send your verification code right now. Please try again shortly.',
            ], 502);
        }

        AuditLogger::log(
            user: $user,
            action: 'api.login.challenge_issued',
            status: 'success',
            entityType: 'auth_challenge',
            entityId: $challenge->id,
            details: ['purpose' => 'login'],
            ip: $ip
        );

        return response()->json([
            'message' => 'OTP_REQUIRED',
            'challenge_token' => $rawChallengeToken,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'otp_digits' => $digits,
            'purpose' => 'login',
        ], 200);
    }

    public function resend(string $challengeToken, string $ip): JsonResponse
    {
        /** @var AuthChallenge|null $challenge */
        $challenge = AuthChallenge::query()
            ->where('token_hash', AuthChallenge::hashToken($challengeToken))
            ->first();

        if (! $challenge || $challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
            return response()->json([
                'message' => 'CHALLENGE_INVALID',
            ], 401);
        }

        $user = User::query()->find($challenge->user_id);
        if (! $user || $user->is_enabled === false || ! $user->email_verified_at) {
            $challenge->consumed_at = now();
            $challenge->save();

            return response()->json([
                'message' => 'CHALLENGE_INVALID',
            ], 401);
        }

        $maxResends = (int) config('otp.max_resends', 3);
        $resendInterval = (int) config('otp.resend_interval', 30);

        if ($challenge->sends >= (1 + $maxResends)) {
            return response()->json([
                'message' => 'OTP_RESEND_LIMIT_EXCEEDED',
            ], 429);
        }

        if ($challenge->last_sent_at !== null && $challenge->last_sent_at->diffInSeconds(now()) < $resendInterval) {
            return response()->json([
                'message' => 'OTP_RESEND_TOO_SOON',
            ], 429);
        }

        $digits = (int) config('otp.digits', 6);
        $ttlSeconds = (int) config('otp.ttl', 300);
        $otp = AuthChallenge::generateOtpCode($digits);

        $challenge->otp_hash = AuthChallenge::hashOtp($challenge->id, $otp);
        $challenge->sends = $challenge->sends + 1;
        $challenge->last_sent_at = now();
        $challenge->save();

        try {
            $mailer = new ResendMailService();
            $mailer->sendFromTemplate(
                'otp_login',
                [
                    'name' => $user->name,
                    'otp' => $otp,
                    'expires_in_minutes' => max(1, (int) ceil(now()->diffInSeconds($challenge->expires_at, false) / 60)),
                ],
                $user->email,
                $user->name
            );
        } catch (Throwable $e) {
            Log::error('OTP resend email delivery failed', [
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'error' => $e->getMessage(),
            ]);

            AuditLogger::log(
                user: $user,
                action: 'api.otp.delivery_failed',
                status: 'failure',
                entityType: 'auth_challenge',
                entityId: $challenge->id,
                details: ['reason' => 'resend_delivery_error'],
                ip: $ip
            );

            return response()->json([
                'message' => 'We could not send your verification code right now. Please try again shortly.',
            ], 502);
        }

        AuditLogger::log(
            user: $user,
            action: 'api.otp.resent',
            status: 'success',
            entityType: 'auth_challenge',
            entityId: $challenge->id,
            details: ['sends' => $challenge->sends],
            ip: $ip
        );

        return response()->json([
            'message' => 'OTP sent successfully.',
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'otp_digits' => $digits,
        ], 200);
    }

    public function verify(string $challengeToken, string $otp, string $ip, string $userAgent): JsonResponse
    {
        return DB::transaction(function () use ($challengeToken, $otp, $ip, $userAgent): JsonResponse {
            /** @var AuthChallenge|null $challenge */
            $challenge = AuthChallenge::query()
                ->where('token_hash', AuthChallenge::hashToken($challengeToken))
                ->first();

            if (! $challenge) {
                return response()->json(['message' => 'CHALLENGE_INVALID'], 401);
            }

            $incremented = AuthChallenge::query()
                ->whereKey($challenge->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->whereColumn('attempts', '<', 'max_attempts')
                ->increment('attempts');

            if ($incremented === 0) {
                $fresh = AuthChallenge::query()->find($challenge->id);

                if ($fresh && $fresh->attempts >= $fresh->max_attempts) {
                    AuthChallenge::query()
                        ->whereKey($fresh->id)
                        ->whereNull('consumed_at')
                        ->update(['consumed_at' => now()]);

                    AuditLogger::log(
                        user: User::query()->find($fresh->user_id),
                        action: 'api.otp.attempts_exceeded',
                        status: 'blocked',
                        entityType: 'auth_challenge',
                        entityId: $fresh->id,
                        details: ['attempts' => $fresh->attempts],
                        ip: $ip
                    );

                    return response()->json(['message' => 'OTP_ATTEMPTS_EXCEEDED'], 429);
                }

                return response()->json(['message' => 'OTP_INVALID'], 401);
            }

            $fresh = AuthChallenge::query()->find($challenge->id);
            if (! $fresh) {
                return response()->json(['message' => 'OTP_INVALID'], 401);
            }

            $expectedHash = AuthChallenge::hashOtp($fresh->id, $otp);
            if (! hash_equals((string) $fresh->otp_hash, $expectedHash)) {
                if ($fresh->attempts >= $fresh->max_attempts) {
                    AuthChallenge::query()
                        ->whereKey($fresh->id)
                        ->whereNull('consumed_at')
                        ->update(['consumed_at' => now()]);
                }

                AuditLogger::log(
                    user: User::query()->find($fresh->user_id),
                    action: 'api.otp.verify_failed',
                    status: 'failure',
                    entityType: 'auth_challenge',
                    entityId: $fresh->id,
                    details: ['attempts' => $fresh->attempts],
                    ip: $ip
                );

                return response()->json(['message' => 'OTP_INVALID'], 401);
            }

            $consumed = AuthChallenge::query()
                ->whereKey($fresh->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($consumed !== 1) {
                return response()->json(['message' => 'OTP_INVALID'], 401);
            }

            /** @var User|null $user */
            $user = User::query()->find($fresh->user_id);

            if (! $user || $user->is_enabled === false) {
                AuditLogger::log(
                    user: $user,
                    action: 'api.login.account_disabled',
                    status: 'blocked',
                    entityType: 'user',
                    entityId: $user?->id,
                    details: ['stage' => 'otp_verify'],
                    ip: $ip
                );

                return response()->json(['message' => 'ACCOUNT_DISABLED'], 403);
            }

            if (! $user->email_verified_at) {
                return response()->json(['message' => 'EMAIL_NOT_VERIFIED'], 403);
            }

            if ($user->must_change_password) {
                return response()->json(['message' => 'PASSWORD_CHANGE_REQUIRED'], 403);
            }

            try {
                $jwt = JwtService::fromConfig();
            } catch (JwtConfigurationException $e) {
                Log::critical('JWT configuration error during OTP verification', ['error' => $e->getMessage()]);

                return response()->json(['message' => 'AUTH_UNAVAILABLE'], 503);
            }

            $familyId = (string) Str::uuid();
            $refreshJti = (string) Str::uuid();
            $accessJti = (string) Str::uuid();

            $refreshJwt = $jwt->issueRefreshToken(
                userId: $user->id,
                role: $user->role,
                jti: $refreshJti,
                familyId: $familyId
            );

            RefreshToken::create([
                'user_id' => $user->id,
                'jti' => $refreshJti,
                'family_id' => $familyId,
                'token_hash' => hash('sha256', $refreshJwt),
                'expires_at' => now()->addSeconds($jwt->getRefreshTtlSeconds()),
                'revoked_at' => null,
                'created_ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            $accessJwt = $jwt->issueAccessToken(
                userId: $user->id,
                role: $user->role,
                jti: $accessJti,
                familyId: $familyId
            );

            AuditLogger::log(
                user: $user,
                action: 'api.login.success',
                status: 'success',
                entityType: 'user',
                entityId: $user->id,
                details: ['family_id' => $familyId],
                ip: $ip
            );

            return response()->json([
                'message' => 'OTP verified successfully.',
                'user_id' => $user->id,
                'access_token' => $accessJwt,
                'refresh_token' => $refreshJwt,
                'token_type' => 'bearer',
                'expires_in' => $jwt->getAccessTtlSeconds(),
            ], 200);
        });
    }

    public function refresh(string $refreshTokenJwt, string $ip, string $userAgent): JsonResponse
    {
        try {
            $jwt = JwtService::fromConfig();
        } catch (JwtConfigurationException $e) {
            Log::critical('JWT configuration error during token refresh', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'AUTH_UNAVAILABLE'], 503);
        }

        try {
            $decoded = $jwt->decodeRefresh($refreshTokenJwt);
        } catch (Throwable) {
            return response()->json(['message' => 'TOKEN_INVALID'], 401);
        }

        $userId = (int) $decoded->sub;
        $jti = (string) $decoded->jti;
        $presentedHash = hash('sha256', $refreshTokenJwt);

        return DB::transaction(function () use ($jwt, $userId, $jti, $presentedHash, $ip, $userAgent): JsonResponse {
            /** @var RefreshToken|null $rt */
            $rt = RefreshToken::query()
                ->where('jti', $jti)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $rt || ! hash_equals((string) $rt->token_hash, $presentedHash)) {
                return response()->json(['message' => 'TOKEN_INVALID'], 401);
            }

            if ($rt->revoked_at !== null) {
                $this->revokeFamily($rt->user_id, $rt->family_id, markReuse: true);

                AuditLogger::log(
                    user: User::query()->find($rt->user_id),
                    action: 'api.refresh.reuse_detected',
                    status: 'blocked',
                    entityType: 'refresh_token',
                    entityId: $rt->id,
                    details: ['family_id' => $rt->family_id, 'jti' => $rt->jti],
                    ip: $ip
                );

                return response()->json(['message' => 'TOKEN_REVOKED'], 401);
            }

            if ($rt->expires_at->isPast()) {
                return response()->json(['message' => 'TOKEN_REVOKED'], 401);
            }

            /** @var User|null $user */
            $user = User::query()->find($userId);
            if (! $user || $user->is_enabled === false) {
                $this->revokeFamily($rt->user_id, $rt->family_id, markReuse: false);

                return response()->json(['message' => 'TOKEN_REVOKED'], 401);
            }

            $familyId = $rt->family_id ?: (string) Str::uuid();
            $newJti = (string) Str::uuid();

            $rt->revoked_at = now();
            $rt->replaced_by_jti = $newJti;
            $rt->family_id = $familyId;
            $rt->save();

            $newRefreshJwt = $jwt->issueRefreshToken(
                userId: $user->id,
                role: $user->role,
                jti: $newJti,
                familyId: $familyId
            );

            RefreshToken::create([
                'user_id' => $user->id,
                'jti' => $newJti,
                'family_id' => $familyId,
                'token_hash' => hash('sha256', $newRefreshJwt),
                'expires_at' => now()->addSeconds($jwt->getRefreshTtlSeconds()),
                'revoked_at' => null,
                'created_ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            $accessJwt = $jwt->issueAccessToken(
                userId: $user->id,
                role: $user->role,
                jti: (string) Str::uuid(),
                familyId: $familyId
            );

            AuditLogger::log(
                user: $user,
                action: 'api.refresh.success',
                status: 'success',
                entityType: 'user',
                entityId: $user->id,
                details: ['family_id' => $familyId],
                ip: $ip
            );

            return response()->json([
                'access_token' => $accessJwt,
                'refresh_token' => $newRefreshJwt,
                'token_type' => 'bearer',
                'expires_in' => $jwt->getAccessTtlSeconds(),
            ], 200);
        });
    }

    public function logout(?string $refreshTokenJwt, ?User $user, ?string $accessFamilyId, string $ip): JsonResponse
    {
        if ($refreshTokenJwt !== null && $refreshTokenJwt !== '') {
            try {
                $decoded = JwtService::fromConfig()->decodeRefresh($refreshTokenJwt);
                $this->revokeFamily((int) $decoded->sub, (string) ($decoded->fam ?? ''), markReuse: false);
            } catch (Throwable) {
                // Ignore malformed refresh token on logout.
            }
        } elseif ($user !== null && $accessFamilyId !== null && $accessFamilyId !== '') {
            $this->revokeFamily($user->id, $accessFamilyId, markReuse: false);
        }

        if ($user !== null) {
            AuditLogger::log(
                user: $user,
                action: 'api.logout',
                status: 'success',
                entityType: 'user',
                entityId: $user->id,
                ip: $ip
            );
        }

        return response()->json(['message' => 'Logged out'], 200);
    }

    private function revokeFamily(int $userId, ?string $familyId, bool $markReuse): void
    {
        $query = RefreshToken::query()->where('user_id', $userId);
        if ($familyId !== null && $familyId !== '') {
            $query->where('family_id', $familyId);
        }

        $now = now();
        $updates = ['revoked_at' => $now];
        if ($markReuse) {
            $updates['reuse_detected_at'] = $now;
        }

        $query->whereNull('revoked_at')->update($updates);
        if ($markReuse) {
            RefreshToken::query()
                ->where('user_id', $userId)
                ->when($familyId !== null && $familyId !== '', fn ($q) => $q->where('family_id', $familyId))
                ->whereNull('reuse_detected_at')
                ->update(['reuse_detected_at' => $now]);
        }
    }
}

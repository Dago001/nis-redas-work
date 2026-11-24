<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthChallenge extends Model
{
    protected $table = 'auth_challenges';

    protected $fillable = [
        'token_hash',
        'user_id',
        'purpose',
        'otp_hash',
        'attempts',
        'max_attempts',
        'sends',
        'last_sent_at',
        'expires_at',
        'consumed_at',
        'ip_address',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'sends' => 'integer',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function hashOtp(int $challengeId, string $otp): string
    {
        return hash_hmac('sha256', $challengeId.'|'.$otp, (string) config('app.key'));
    }

    public static function generateOtpCode(?int $digits = null): string
    {
        $len = max(6, $digits ?? (int) config('otp.digits', 6));
        $max = (10 ** $len) - 1;

        return str_pad((string) random_int(0, $max), $len, '0', STR_PAD_LEFT);
    }
}

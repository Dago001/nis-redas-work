<?php

namespace App\Services;

use App\Exceptions\JwtConfigurationException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use UnexpectedValueException;

class JwtService
{
    public const ALGORITHM = 'HS256';

    private const MIN_SECRET_BYTES = 32;

    private const FORBIDDEN_PLACEHOLDERS = [
        'dev-fallback-jwt-secret-dev-fallback',
        'changeme',
        'secret',
        'your-secret',
        'your-jwt-secret',
        'replace-me',
        'insecure',
    ];

    /**
     * @var array<int, string>
     */
    private array $previousSecrets;

    /**
     * @param  array<int, string>  $previousSecrets
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer = 'nis-redas',
        private readonly int $accessTtlSeconds = 900,
        private readonly int $refreshTtlSeconds = 2592000,
        private readonly string $audience = 'nis-redas-api',
        array $previousSecrets = [],
        private readonly int $leeway = 30
    ) {
        $this->validateRawSecret($this->secret);

        $validatedPrevious = [];
        foreach ($previousSecrets as $prev) {
            $decoded = self::decodeConfiguredSecret($prev);
            $this->validateRawSecret($decoded);
            $validatedPrevious[] = $decoded;
        }
        $this->previousSecrets = $validatedPrevious;
    }

    /**
     * Build a JwtService from Laravel configuration, failing closed on missing or weak secrets.
     */
    public static function fromConfig(): self
    {
        $configured = config('jwt.secret');
        if (! is_string($configured) || trim($configured) === '') {
            throw new JwtConfigurationException('JWT_SECRET is not configured.');
        }

        $rawSecret = self::decodeConfiguredSecret($configured);
        self::assertValidSecretMaterial($rawSecret);

        $previousRaw = [];
        foreach ((array) config('jwt.previous_secrets', []) as $prev) {
            if (! is_string($prev) || trim($prev) === '') {
                continue;
            }
            $decodedPrev = self::decodeConfiguredSecret($prev);
            self::assertValidSecretMaterial($decodedPrev);
            $previousRaw[] = $decodedPrev;
        }

        return new self(
            secret: $rawSecret,
            issuer: (string) config('jwt.issuer', 'nis-redas'),
            accessTtlSeconds: max(60, (int) config('jwt.access_ttl', 900)),
            refreshTtlSeconds: max(300, (int) config('jwt.refresh_ttl', 2592000)),
            audience: (string) config('jwt.audience', 'nis-redas-api'),
            previousSecrets: $previousRaw,
            leeway: max(0, (int) config('jwt.leeway', 30)),
        );
    }

    /**
     * @deprecated Use JwtService::fromConfig() instead. Retained for backward compatibility.
     */
    public static function fromEnv(): self
    {
        return self::fromConfig();
    }

    /**
     * Verify that JWT configuration is valid and fail closed if misconfigured.
     */
    public static function assertConfigured(): void
    {
        self::fromConfig();
    }

    public function issueAccessToken(int $userId, string $role, string $jti, string $familyId = ''): string
    {
        $now = time();
        $fam = $familyId !== '' ? $familyId : $jti;

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $userId,
            'role' => $role,
            'typ' => 'access',
            'type' => 'access',
            'jti' => $jti,
            'fam' => $fam,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtlSeconds,
        ];

        return JWT::encode($payload, $this->secret, self::ALGORITHM, $this->kidFor($this->secret));
    }

    public function issueRefreshToken(int $userId, string $role, string $jti, string $familyId = ''): string
    {
        $now = time();
        $fam = $familyId !== '' ? $familyId : $jti;

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $userId,
            'role' => $role,
            'typ' => 'refresh',
            'type' => 'refresh',
            'jti' => $jti,
            'fam' => $fam,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->refreshTtlSeconds,
        ];

        return JWT::encode($payload, $this->secret, self::ALGORITHM, $this->kidFor($this->secret));
    }

    /**
     * Decode and cryptographically verify a JWT against active and rotated keys.
     *
     * @return object{iss:string,aud:string,sub:string,role:string,typ:string,type:string,jti:string,fam:string,iat:int,nbf:int,exp:int}
     */
    public function decode(string $jwt): object
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->leeway;

        try {
            $keys = [$this->kidFor($this->secret) => new Key($this->secret, self::ALGORITHM)];
            foreach ($this->previousSecrets as $prev) {
                $keys[$this->kidFor($prev)] = new Key($prev, self::ALGORITHM);
            }

            return JWT::decode($jwt, $keys);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    public function decodeAccess(string $jwt): object
    {
        return $this->decodeWithExpectedType($jwt, 'access');
    }

    public function decodeRefresh(string $jwt): object
    {
        return $this->decodeWithExpectedType($jwt, 'refresh');
    }

    private function decodeWithExpectedType(string $jwt, string $expectedType): object
    {
        $decoded = $this->decode($jwt);

        if (($decoded->iss ?? null) !== $this->issuer) {
            throw new UnexpectedValueException('Invalid token issuer.');
        }

        if (($decoded->aud ?? null) !== $this->audience) {
            throw new UnexpectedValueException('Invalid token audience.');
        }

        $typ = $decoded->typ ?? $decoded->type ?? null;
        if ($typ !== $expectedType) {
            throw new UnexpectedValueException('Invalid token type.');
        }

        if (! isset($decoded->sub) || ! ctype_digit((string) $decoded->sub) || (int) $decoded->sub <= 0) {
            throw new UnexpectedValueException('Invalid token subject.');
        }

        if (! isset($decoded->jti) || ! is_string($decoded->jti) || trim($decoded->jti) === '') {
            throw new UnexpectedValueException('Missing token jti.');
        }

        if (! isset($decoded->fam) || ! is_string($decoded->fam) || trim($decoded->fam) === '') {
            throw new UnexpectedValueException('Missing token family.');
        }

        return $decoded;
    }

    public function isExpired(string $jwt): bool
    {
        try {
            $this->decode($jwt);

            return false;
        } catch (ExpiredException) {
            return true;
        }
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getAccessTtlSeconds(): int
    {
        return $this->accessTtlSeconds;
    }

    public function getRefreshTtlSeconds(): int
    {
        return $this->refreshTtlSeconds;
    }

    private function kidFor(string $rawSecret): string
    {
        return substr(hash('sha256', $rawSecret), 0, 16);
    }

    private static function decodeConfiguredSecret(string $value): string
    {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, 'base64:')) {
            $decoded = base64_decode(substr($trimmed, 7), true);
            if ($decoded === false) {
                throw new JwtConfigurationException('JWT_SECRET base64 value is malformed.');
            }

            return $decoded;
        }

        return $trimmed;
    }

    private function validateRawSecret(string $rawSecret): void
    {
        self::assertValidSecretMaterial($rawSecret);
    }

    private static function assertValidSecretMaterial(string $rawSecret): void
    {
        if (strlen($rawSecret) < self::MIN_SECRET_BYTES) {
            throw new JwtConfigurationException('JWT_SECRET must be at least 32 bytes.');
        }

        $lower = strtolower(trim($rawSecret));
        if (in_array($lower, self::FORBIDDEN_PLACEHOLDERS, true)) {
            throw new JwtConfigurationException('JWT_SECRET cannot use a known placeholder value.');
        }
    }
}

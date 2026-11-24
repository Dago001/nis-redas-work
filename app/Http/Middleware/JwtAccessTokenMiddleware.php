<?php

namespace App\Http\Middleware;

use App\Exceptions\JwtConfigurationException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtAccessTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'TOKEN_INVALID'], 401);
        }

        $token = trim(substr($header, 7));

        try {
            $jwt = JwtService::fromConfig();
        } catch (JwtConfigurationException $e) {
            Log::critical('JWT configuration error in API middleware', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'AUTH_UNAVAILABLE'], 503);
        }

        try {
            $decoded = $jwt->decodeAccess($token);
        } catch (Throwable) {
            return response()->json(['message' => 'TOKEN_INVALID'], 401);
        }

        $userId = (int) $decoded->sub;
        $familyId = (string) $decoded->fam;

        $user = User::query()->find($userId);
        if (! $user || $user->is_enabled === false) {
            return response()->json(['message' => 'TOKEN_REVOKED'], 401);
        }

        $hasActiveFamily = RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if (! $hasActiveFamily) {
            return response()->json(['message' => 'TOKEN_REVOKED'], 401);
        }

        $request->attributes->set('jwt_family_id', $familyId);
        Auth::setUser($user);

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Auth\ApiLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ApiOtpController extends Controller
{
    public function __construct(
        private readonly ApiLoginService $loginService
    ) {}

    /**
     * Resend an OTP for an existing, password-verified authentication challenge.
     * Unauthenticated callers cannot initiate an OTP session using only a login identifier.
     */
    public function request(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->loginService->resend(
            challengeToken: (string) $request->input('challenge_token'),
            ip: $request->ip() ?? '0.0.0.0'
        );
    }

    /**
     * Verify an OTP against a password-verified authentication challenge and issue JWTs.
     */
    public function verify(Request $request): Response
    {
        $digits = max(6, (int) config('otp.digits', 6));

        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'otp' => ['required', 'string', 'regex:/^\d{'.$digits.'}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->loginService->verify(
            challengeToken: (string) $request->input('challenge_token'),
            otp: (string) $request->input('otp'),
            ip: $request->ip() ?? '0.0.0.0',
            userAgent: (string) $request->userAgent()
        );
    }
}

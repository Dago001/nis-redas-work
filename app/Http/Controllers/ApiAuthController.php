<?php

namespace App\Http\Controllers;

use App\Services\Auth\ApiLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthController extends Controller
{
    public function __construct(
        private readonly ApiLoginService $loginService
    ) {}

    public function login(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->loginService->attemptPassword(
            loginInput: (string) $request->input('login'),
            password: (string) $request->input('password'),
            role: $request->filled('role') ? (string) $request->input('role') : null,
            ip: $request->ip() ?? '0.0.0.0'
        );
    }

    public function refresh(Request $request): Response
    {
        $refreshTokenJwt = (string) $request->input('refresh_token');

        if ($refreshTokenJwt === '') {
            return response()->json([
                'message' => 'refresh_token is required',
            ], 422);
        }

        return $this->loginService->refresh(
            refreshTokenJwt: $refreshTokenJwt,
            ip: $request->ip() ?? '0.0.0.0',
            userAgent: (string) $request->userAgent()
        );
    }

    public function logout(Request $request): Response
    {
        return $this->loginService->logout(
            refreshTokenJwt: $request->filled('refresh_token') ? (string) $request->input('refresh_token') : null,
            user: $request->user(),
            accessFamilyId: is_string($request->attributes->get('jwt_family_id'))
                ? $request->attributes->get('jwt_family_id')
                : null,
            ip: $request->ip() ?? '0.0.0.0'
        );
    }
}

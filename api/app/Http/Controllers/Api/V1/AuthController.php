<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            (string) $request->validated('email'),
            $request->validated('password'),
            $request->validated('google_sub'),
            $request->validated('apple_sub'),
        );

        return response()->json($result['body'], $result['status']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            (string) $request->validated('email'),
            $request->validated('password'),
        );

        return response()->json($result['body'], $result['status']);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'deconnecte']);
    }
}

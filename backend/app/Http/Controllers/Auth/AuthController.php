<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * AuthController constructor.
     *
     * @param  AuthService  $auth_service  Auth service instance
     */
    public function __construct(
        private AuthService $auth_service
    ) {
    }

    /**
     * Register a new user.
     *
     * @param  RegisterRequest  $request  The registration request
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth_service->register(
            $request->input('name'),
            $request->input('email'),
            $request->input('password')
        );

        return response()->json([
            'message' => 'Registration successful',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'balance' => $result['user']->balance,
            ],
            'token' => $result['token'],
        ], 201);
    }

    /**
     * Login user and create token.
     *
     * @param  LoginRequest  $request  The login request
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth_service->login(
            $request->input('email'),
            $request->input('password')
        );

        if (! $result) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'balance' => $result['user']->balance,
            ],
            'token' => $result['token'],
        ]);
    }

    /**
     * Logout user (revoke token).
     *
     * @param  Request  $request  The logout request
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth_service->logout($request->user());

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get authenticated user.
     *
     * @param  Request  $request  The authenticated user request
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'balance' => $request->user()->balance,
                'balance_dollars' => $request->user()->balance_in_dollars,
            ],
        ]);
    }
}

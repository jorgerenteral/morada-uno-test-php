<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function token(LoginRequest $request, JwtService $jwt): JsonResponse
    {
        $user = User::where('username', $request->validated('username'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid username or password',
            ], 401);
        }

        return response()->json([
            'access_token' => $jwt->issue($user),
            'token_type' => 'bearer',
        ]);
    }
}

<?php

namespace App\Auth;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;

class JwtGuard
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * Resolve the user from the Bearer token of the request, if any.
     */
    public function __invoke(Request $request): ?User
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return null;
        }

        $id = $this->jwt->subject($token);

        return $id === null ? null : User::find($id);
    }
}

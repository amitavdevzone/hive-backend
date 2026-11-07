<?php

namespace App\Http\Controllers\Api;

use App\Data\LoginTokenData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): LoginTokenData
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password) || $user->email_verified_at === null) {
            throw ValidationException::withMessages(['email' => 'These credentials are incorrect.']);
        }

        return new LoginTokenData($user->createToken('api')->plainTextToken);
    }
}

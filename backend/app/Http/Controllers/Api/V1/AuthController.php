<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Staff log in with email or phone; parents with phone. */
    public function login(LoginRequest $request): AuthUserResource
    {
        $login = trim((string) $request->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $login)->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            AuditLogger::log('login_failed', $user, null, ['login' => $login]);

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'Your account is not active. Please contact the center.']);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLogger::log('login', $user, userId: $user->id);

        return new AuthUserResource($user->load('branches'));
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLogger::log('logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user()->load('branches'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ]);
        AuditLogger::log('password_changed', $request->user());

        return response()->json(['message' => 'Password changed.']);
    }
}

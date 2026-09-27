<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * SECURITY FIX: login() now issues a REAL Sanctum token via
 * $user->createToken(), which persists to the personal_access_tokens
 * table and is validated on every request to a route behind the
 * 'auth:sanctum' middleware (see routes/api.php). Previously we
 * returned Str::random(64) -- a string that was never checked again,
 * meaning every endpoint was effectively open to anyone.
 */
class AuthController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 3;
    private const LOCKOUT_MINUTES = 120;

    public function login(Request $request)
    {
        $validated = $request->validate([
            'org_code' => 'required|string',
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $organization = Organization::where('org_code', $validated['org_code'])->first();

        if ($organization === null) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = User::where('org_id', $organization->id)
            ->where(function ($query) use ($validated) {
                $query->where('username', $validated['identifier'])
                    ->orWhere('email', $validated['identifier']);
            })
            ->first();

        if ($user === null) {
            return response()->json(['message' => 'Invalid username/email or password.'], 401);
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $minutesLeft = now()->diffInMinutes($user->locked_until);
            return response()->json([
                'message' => "Account locked due to repeated failed attempts. Try again in {$minutesLeft} min.",
            ], 401);
        }

        if ($user->status !== 1) {
            return response()->json(['message' => 'This account is not active. Contact your administrator.'], 401);
        }

        if (!Hash::check($validated['password'], $user->password_hash)) {
            $user->failed_login_attempts += 1;

            if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $user->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
                $user->failed_login_attempts = 0;
            }

            $user->save();
            return response()->json(['message' => 'Invalid username/email or password.'], 401);
        }

        // success
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->save();

        // Issue a real, persisted Sanctum token -- this is what
        // 'auth:sanctum' middleware validates on every future request.
        $token = $user->createToken('sacco-frontend')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'role_id' => $user->role_id,
            ],
        ]);
    }

    /**
     * Revokes the token used to make THIS request, so a "logged out"
     * token can never be reused -- completes the security story that
     * login() started.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function changePassword(Request $request, int $userId)
    {
        $validated = $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        $user = User::findOrFail($userId);

        $alreadyUsed = PasswordHistory::where('user_id', $userId)
            ->get()
            ->contains(fn ($history) => Hash::check($validated['new_password'], $history->password_hash));

        if ($alreadyUsed) {
            return response()->json(['message' => 'This password has been used before. Choose a different one.'], 422);
        }

        $newHash = Hash::make($validated['new_password']);

        $user->password_hash = $newHash;
        $user->must_change_password = false;
        $user->save();

        PasswordHistory::create([
            'user_id' => $userId,
            'password_hash' => $newHash,
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}

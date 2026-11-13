<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\JudgePinLoginRequest;
use App\Http\Resources\JudgeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function judgePin(JudgePinLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $judges = Judge::where('event_id', $validated['event_id'])
            ->where('is_active', true)
            ->get();

        /** @var Judge|null $matched */
        $matched = $judges->first(
            fn (Judge $judge): bool => Hash::check($validated['pin'], $judge->access_pin_hash)
        );

        if ($matched === null) {
            return ApiResponse::error(
                'Invalid 6-digit Judge PIN for this event.',
                ['pin' => ['The provided Judge PIN is invalid or inactive.']],
                422
            );
        }

        $matched->update([
            'device_fingerprint' => $validated['device_fingerprint'] ?? $matched->device_fingerprint,
            'last_seen_at' => now(),
        ]);

        $token = $matched->createToken(
            $validated['device_fingerprint'] ?? 'judge-tablet',
            ['role:judge']
        )->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'actor_type' => 'judge',
            'judge' => new JudgeResource($matched),
        ], 'Judge session authenticated.');
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if ($user === null || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                'Invalid credentials or inactive account.',
                ['email' => ['These credentials do not match our records.']],
                422
            );
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('tabulator-console', ["role:{$user->role}"])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'actor_type' => 'user',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 'Authenticated successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor instanceof Judge) {
            return ApiResponse::success([
                'actor_type' => 'judge',
                'judge' => new JudgeResource($actor),
            ], 'Authenticated judge profile.');
        }

        /** @var User $actor */
        return ApiResponse::success([
            'actor_type' => 'user',
            'user' => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => $actor->role,
            ],
        ], 'Authenticated user profile.');
    }
}

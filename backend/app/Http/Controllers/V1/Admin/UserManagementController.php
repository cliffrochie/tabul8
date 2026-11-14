<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || $actor->role !== 'super_admin') {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $users = User::orderBy('id')->get()->map(fn (User $u): array => $this->serializeUser($u));

        return ApiResponse::success(['users' => $users], 'Users loaded.');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success(
            ['user' => $this->serializeUser($user)],
            'Staff user created.',
            201
        );
    }

    public function update(StoreUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return ApiResponse::success(
            ['user' => $this->serializeUser($user->fresh())],
            'Staff user updated.'
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->is_active || $actor->role !== 'super_admin') {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if ($actor->id === $user->id) {
            throw new ConflictHttpException('You cannot delete your own active super_admin account.');
        }

        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'Staff user deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}

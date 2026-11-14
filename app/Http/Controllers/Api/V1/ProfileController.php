<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The token owner: who they are, their school, what they may do and, for students and
 * guardians, the student records they can see.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request, PortalScope $portalScope, CurrentSchool $currentSchool): JsonResponse
    {
        $user = $request->user();
        $isPortalUser = $portalScope->isPortalUser($user);
        $token = $user->currentAccessToken();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'school' => $currentSchool->get()?->only(['id', 'name', 'code']),
                'roles' => $user->roles()->pluck('name')->values(),
                'permissions' => $user->permissionNames(),
                'is_portal_user' => $isPortalUser,
                'students' => $isPortalUser
                    ? Student::query()->whereIn('id', $portalScope->studentIds($user))->orderBy('first_name')->get()
                        ->map(fn (Student $student): array => [
                            'id' => $student->id,
                            'name' => $student->full_name,
                            'admission_no' => $student->admission_no,
                        ])->values()
                    : [],
                'token' => $token->exists === true ? [
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'expires_at' => $token->expires_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}

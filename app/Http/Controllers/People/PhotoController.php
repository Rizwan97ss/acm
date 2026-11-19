<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\OrganizationMember;
use App\Models\Student;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Services\People\PhotoStorage;
use App\Services\People\PortalScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Upload, show and remove profile photos. Kinds: students, guardians, staff, members.
 */
class PhotoController extends Controller
{
    public const array KINDS = ['students', 'guardians', 'staff', 'members'];

    public function __construct(
        private PhotoStorage $photos,
        private PortalScope $portal,
        private EmployeePolicy $employees,
    ) {}

    public function show(Request $request, string $kind, int $id): BinaryFileResponse
    {
        $person = $this->resolve($kind, $id);
        abort_unless($this->canView($request->user(), $kind, $person), 404);
        abort_if($person->photo_path === null, 404);

        return $this->photos->respond($person->photo_path);
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $person = $this->resolve($kind, $id);
        abort_unless($this->canEdit($request->user(), $kind, $person), 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=150,min_height=150'],
        ], ['photo.dimensions' => __('Use a photo at least 150 × 150 pixels.')]);

        $path = $this->photos->store($request->file('photo'), (int) $person->school_id, $kind, $person->photo_path);
        $person->forceFill(['photo_path' => $path])->save();

        Log::info('Profile photo updated.', ['kind' => $kind, 'id' => $person->id, 'user_id' => $request->user()->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return back();
    }

    public function destroy(Request $request, string $kind, int $id): RedirectResponse
    {
        $person = $this->resolve($kind, $id);
        abort_unless($this->canEdit($request->user(), $kind, $person), 403);

        $this->photos->delete($person->photo_path);
        $person->forceFill(['photo_path' => null])->save();

        return back();
    }

    private function resolve(string $kind, int $id): Student|Guardian|Employee|OrganizationMember
    {
        return match ($kind) {
            'students' => Student::query()->findOrFail($id),
            'guardians' => Guardian::query()->findOrFail($id),
            'staff' => Employee::query()->findOrFail($id),
            'members' => OrganizationMember::query()->findOrFail($id),
            default => abort(404),
        };
    }

    private function canView(User $user, string $kind, Student|Guardian|Employee|OrganizationMember $person): bool
    {
        if ($this->portal->isPortalUser($user)) {
            return match (true) {
                $person instanceof Student => in_array($person->id, $this->portal->studentIds($user), true),
                $person instanceof Guardian => $person->user_id === $user->id,
                default => false,
            };
        }

        return match (true) {
            $person instanceof Student => $user->hasPermission('students.view'),
            $person instanceof Guardian => $user->hasPermission('guardians.view'),
            $person instanceof Employee => $person->user_id === $user->id || $user->hasPermission('employees.view') || ($person->is_teaching && $user->hasPermission('teachers.view')),
            default => $user->hasPermission('organization.view'),
        };
    }

    private function canEdit(User $user, string $kind, Student|Guardian|Employee|OrganizationMember $person): bool
    {
        return match (true) {
            $person instanceof Student => $user->can('update', $person),
            $person instanceof Guardian => $user->can('update', $person),
            $person instanceof Employee => $this->employees->manageKind($user, $person->is_teaching, 'edit'),
            default => $user->hasPermission('organization.edit'),
        };
    }
}

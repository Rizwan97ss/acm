<?php

namespace App\Http\Controllers\Learning;

use App\Enums\MaterialKind;
use App\Models\AcademicYear;
use App\Models\AssignmentFile;
use App\Models\LessonMaterial;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeachingScope;
use App\Services\Learning\LearningFiles;
use App\Services\Learning\LearningScope;
use App\Services\People\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves learning files behind signed links. Access is checked again here, so a leaked
 * link expires and never works for someone who could not open the file in the portal.
 */
class LearningFileController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningFiles $files,
        private PortalScope $portal,
        private TeachingScope $teaching,
    ) {
        parent::__construct($scope);
    }

    public function material(Request $request, LessonMaterial $material): Response
    {
        abort_if($material->kind === MaterialKind::Link || $material->path === null || $material->disk === null, 404);

        $user = $request->user();
        $lesson = $material->lesson()->with('course')->firstOrFail();
        $download = $request->query('mode') === 'download';

        if ($this->portal->isPortalUser($user)) {
            $year = AcademicYear::query()->current()->first();
            $allowed = $year !== null && Student::query()
                ->whereIn('id', $this->portal->studentIds($user))
                ->get()
                ->contains(fn (Student $student): bool => $this->scope->canViewLesson($student, $lesson, $year));

            abort_unless($allowed, 404);
            abort_if($download && ! $material->is_downloadable, 403);
            abort_if(! $download && ! $material->kind->isPreviewable(), 403);
        } else {
            abort_unless($this->scope->supervises($user, 'courses') || $this->scope->canManage($user, 'courses', $lesson->course), 403);
        }

        Log::info('Learning material opened.', ['material_id' => $material->id, 'user_id' => $user->id, 'mode' => $download ? 'download' : 'inline']);

        return $this->files->respond($material->disk, $material->path, (string) $material->original_name, (string) $material->mime_type, ! $download);
    }

    public function assignment(Request $request, AssignmentFile $file): Response
    {
        $user = $request->user();
        $assignment = $file->assignment()->with('section')->firstOrFail();
        $year = AcademicYear::query()->findOrFail($assignment->academic_year_id);

        if ($this->portal->isPortalUser($user)) {
            $mine = $this->portal->studentIds($user);
            $submissionOwner = $file->submission?->student_id;
            $allowed = $submissionOwner !== null
                ? in_array($submissionOwner, $mine, true)
                : in_array($assignment->section_id, $this->portal->sectionIds($user, $year) ?? [], true);

            abort_unless($allowed, 404);
        } else {
            abort_unless($user->hasPermission('assignments.view') && $this->teaching->canAccessSection($user, $assignment->section, $year, 'assignments'), 403);
        }

        return $this->files->respond($file->disk, $file->path, $file->original_name, $file->mime_type, false);
    }

    /**
     * Students of a class (and section) to choose a restricted audience from.
     */
    public function roster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        abort_unless($this->canBrowseRoster($user), 403);

        $year = $this->currentYear();

        return response()->json($this->scope->rosterFor($year->id, (int) $validated['school_class_id'], isset($validated['section_id']) ? (int) $validated['section_id'] : null));
    }

    private function canBrowseRoster(User $user): bool
    {
        return ! $this->portal->isPortalUser($user)
            && ($user->hasPermission('courses.create') || $user->hasPermission('assignments.create'));
    }
}

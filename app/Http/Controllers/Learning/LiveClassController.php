<?php

namespace App\Http\Controllers\Learning;

use App\Enums\LiveClassStatus;
use App\Enums\MeetingProvider;
use App\Http\Requests\Learning\LiveClassRequest;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scheduling online classes on Zoom, Meet, Teams or any other meeting tool.
 */
class LiveClassController extends LearningController
{
    public function __construct(LearningScope $scope, private LearningNotifier $notifier)
    {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('live_classes.create');

        $user = $request->user();
        $year = $this->currentYear();
        $when = $request->validate(['when' => ['nullable', 'in:upcoming,past']])['when'] ?? 'upcoming';

        $classes = LiveClass::query()
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,color', 'author:id,name'])
            ->where('academic_year_id', $year->id)
            ->when(! $this->scope->supervises($user, 'live_classes'), fn (Builder $query) => $this->limitToTeacher($query, $user, $year))
            ->when($when === 'upcoming', fn (Builder $query) => $query->where('starts_at', '>=', now()->subHours(3))->orderBy('starts_at'), fn (Builder $query) => $query->where('starts_at', '<', now()->subHours(3))->orderByDesc('starts_at'))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('learning/live-classes/index', [
            'liveClasses' => [
                'data' => $classes->getCollection()->map(fn (LiveClass $liveClass): array => [
                    ...$this->present($liveClass),
                    'can_manage' => $this->scope->canManage($user, 'live_classes', $liveClass),
                ])->values(),
                'meta' => $this->meta($classes),
            ],
            'when' => $when,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('live_classes.create');

        return Inertia::render('learning/live-classes/form', [...$this->formOptions($request->user()), 'liveClass' => null]);
    }

    public function store(LiveClassRequest $request): RedirectResponse
    {
        $year = $this->currentYear();
        $this->authorizeAudience($request, $year);

        $liveClass = LiveClass::query()->create([
            ...$request->validated(),
            'academic_year_id' => $year->id,
            'status' => LiveClassStatus::Scheduled,
            'created_by' => $request->user()->id,
        ]);

        $notified = $this->notifier->liveClassScheduled($liveClass);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('Live class scheduled. :count student was notified.|Live class scheduled. :count students were notified.', $notified, ['count' => $notified])]);

        return to_route('learning.live-classes.index');
    }

    public function edit(Request $request, LiveClass $liveClass): Response
    {
        abort_unless($this->scope->canManage($request->user(), 'live_classes', $liveClass), 403);

        return Inertia::render('learning/live-classes/form', [
            ...$this->formOptions($request->user()),
            'liveClass' => [...$this->present($liveClass), 'meeting_url' => $liveClass->meeting_url],
        ]);
    }

    public function update(LiveClassRequest $request, LiveClass $liveClass): RedirectResponse
    {
        abort_unless($this->scope->canManage($request->user(), 'live_classes', $liveClass), 403);
        $this->authorizeAudience($request, $this->currentYear());

        $liveClass->fill($request->validated());
        $timeChanged = $liveClass->isDirty('starts_at');

        if ($timeChanged) {
            $liveClass->reminded_at = null;
        }

        $liveClass->save();

        if ($timeChanged) {
            $this->notifier->liveClassScheduled($liveClass, changed: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Live class updated.')]);

        return to_route('learning.live-classes.index');
    }

    public function cancel(Request $request, LiveClass $liveClass): RedirectResponse
    {
        abort_unless($this->scope->canManage($request->user(), 'live_classes', $liveClass), 403);

        if ($liveClass->status !== LiveClassStatus::Cancelled) {
            $liveClass->update(['status' => LiveClassStatus::Cancelled]);
            $this->notifier->liveClassCancelled($liveClass);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Live class cancelled. Students were told.')]);

        return back();
    }

    public function destroy(Request $request, LiveClass $liveClass): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('live_classes.delete') && $this->scope->canManage($request->user(), 'live_classes', $liveClass), 403);

        $liveClass->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Live class deleted.')]);

        return to_route('learning.live-classes.index');
    }

    /**
     * @param  Builder<LiveClass>  $query
     */
    private function limitToTeacher(Builder $query, User $user, AcademicYear $year): void
    {
        $pairs = collect($this->scope->teachingOptions($user, 'live_classes', $year))
            ->flatMap(fn (array $class) => collect($class['subjects'])->map(fn (array $subject): array => [$class['id'], $subject['id']]));

        $query->where(function (Builder $query) use ($user, $pairs): void {
            $query->where('created_by', $user->id);

            foreach ($pairs as [$classId, $subjectId]) {
                $query->orWhere(fn (Builder $query) => $query->where('school_class_id', $classId)->where('subject_id', $subjectId));
            }
        });
    }

    private function authorizeAudience(LiveClassRequest $request, AcademicYear $year): void
    {
        $allowed = $this->scope->canTeach(
            $request->user(),
            'live_classes',
            $request->integer('school_class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->integer('subject_id'),
            $year,
        );

        if (! $allowed) {
            throw ValidationException::withMessages(['subject_id' => __('You can only schedule classes for classes and subjects you teach.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $year = $this->currentYear();

        return [
            'options' => $this->scope->teachingOptions($user, 'live_classes', $year),
            'providers' => MeetingProvider::options(),
            'courses' => Course::query()->where('academic_year_id', $year->id)->orderBy('title')->get(['id', 'title', 'school_class_id', 'subject_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LiveClass $liveClass): array
    {
        return [
            'id' => $liveClass->id,
            'title' => $liveClass->title,
            'description' => $liveClass->description,
            'provider' => $liveClass->provider->value,
            'provider_label' => $liveClass->provider->label(),
            'starts_at' => $liveClass->starts_at->toIso8601String(),
            'ends_at' => $liveClass->endsAt()->toIso8601String(),
            'duration_minutes' => $liveClass->duration_minutes,
            'status' => $liveClass->status->value,
            'school_class_id' => $liveClass->school_class_id,
            'section_id' => $liveClass->section_id,
            'subject_id' => $liveClass->subject_id,
            'course_id' => $liveClass->course_id,
            'subject' => $liveClass->subject?->name,
            'audience' => $this->audienceLabel($liveClass),
            'author' => $liveClass->author?->name,
        ];
    }
}
